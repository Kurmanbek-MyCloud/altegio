<?php
/**
 * JSON API админки. Все запросы — POST с CSRF-токеном в заголовке X-CSRF-Token.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';

if (!admin_user()) {
    json_response(401, ['error' => 'Требуется вход']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    json_response(403, ['error' => 'Сессия устарела, обновите страницу']);
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$action = (string)($in['action'] ?? '');
$cfg = app_config();

try {
    switch ($action) {
        case 'payables':
            $phone = normalize_phone((string)($in['phone'] ?? ''));
            if (strlen($phone) < 9) {
                json_response(400, ['error' => 'Введите телефон клиента']);
            }
            $altegio = app_altegio();
            $client = $altegio->findClientByPhone($phone);
            if (!$client) {
                json_response(404, ['error' => 'Клиент с таким телефоном не найден в Altegio']);
            }
            json_response(200, [
                'client' => $client,
                'items' => $altegio->getPayables($client['id'], $phone, (int)$cfg['altegio']['account_id']),
            ]);

        case 'create_invoice':
            $phone = normalize_phone((string)($in['phone'] ?? ''));
            $documentId = (int)($in['document_id'] ?? 0);
            $amount = round((float)($in['amount'] ?? 0), 2);

            // Позицию и остаток берём из Altegio, а не из браузера
            $altegio = app_altegio();
            $client = $altegio->findClientByPhone($phone);
            $item = null;
            foreach ($client ? $altegio->getPayables($client['id'], $phone, (int)$cfg['altegio']['account_id']) : [] as $p) {
                if ($p['document_id'] === $documentId) {
                    $item = $p;
                }
            }
            if (!$client || !$item) {
                json_response(400, ['error' => 'Эта позиция уже оплачена или не принадлежит клиенту']);
            }
            $result = create_payment_link($client, $item, $amount, (string)admin_user());
            json_response(200, array_diff_key($result, ['token' => 1]));

        case 'invoices':
            $invoices = new Invoices(app_db());
            json_response(200, [
                'invoices' => array_map(fn($i) => array_diff_key($i, ['token' => 1, 'qr_url' => 1, 'qr_hash' => 1]), $invoices->recent(50)),
                'problems' => array_map(fn($c) => array_diff_key($c, ['payload' => 1]), $invoices->problemCallbacks(20)),
            ]);

        case 'retry_altegio':
            $invoices = new Invoices(app_db());
            $invoice = $invoices->find((int)($in['invoice_id'] ?? 0));
            if (!$invoice || $invoice['status'] !== 'paid' || $invoice['altegio_status'] === 'ok') {
                json_response(400, ['error' => 'Повтор возможен только для оплаченного счёта с ошибкой Altegio']);
            }
            $result = $invoices->postToAltegio($invoice, app_altegio(), $cfg['altegio']);
            app_log('admin', 'Повтор проведения в Altegio', ['invoice' => $invoice['id'], 'by' => admin_user(), 'ok' => $result['ok']]);
            json_response($result['ok'] ? 200 : 502, $result['ok'] ? $result : ['error' => $result['error']]);

        case 'cancel':
            $invoices = new Invoices(app_db());
            $id = (int)($in['invoice_id'] ?? 0);
            if (!$invoices->transition($id, 'pending', ['status' => 'cancelled'])) {
                json_response(400, ['error' => 'Отменить можно только неоплаченный счёт']);
            }
            app_log('admin', 'Счёт отменён', ['invoice' => $id, 'by' => admin_user()]);
            json_response(200, ['ok' => true]);

        default:
            json_response(400, ['error' => 'Неизвестное действие']);
    }
} catch (PaymentLinkException $e) {
    json_response($e->httpCode, ['error' => $e->getMessage()]);
} catch (AltegioException $e) {
    json_response(502, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    app_log('admin', 'Ошибка', ['action' => $action, 'error' => $e->getMessage()]);
    json_response(500, ['error' => 'Внутренняя ошибка, подробности в logs/admin.log']);
}
