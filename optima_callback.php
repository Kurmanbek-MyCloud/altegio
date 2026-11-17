<?php
/**
 * Колбэк Optima Bank об оплате QR.
 *
 * POST https://<домен>/optima_callback.php
 * Authorization: Basic base64(callback_username:callback_password)  — config.local.php → optima
 * {
 *   "status": "success",
 *   "sum": 5000,
 *   "note": "10457",                       // номер нашего счёта (invoices.id)
 *   "transactionId": "TXN_123",
 *   "transactionProcessedDateTime": "2026-11-16T12:00:00Z"
 * }
 *
 * Ответ 200 — колбэк принят (в т.ч. если требуется ручной разбор), банк не должен повторять.
 * Ответ 5xx — временная ошибка у нас, банк может повторить. Повтор с тем же transactionId безопасен.
 */

require __DIR__ . '/lib/bootstrap.php';

$cfg = app_config();
$optima = $cfg['optima'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['error' => 'Метод не разрешен']);
}

if ($optima['callback_username'] === '' || $optima['callback_password'] === '') {
    app_log('callback', 'Колбэк отклонён: не настроены callback_username/callback_password');
    json_response(503, ['error' => 'Сервис не настроен']);
}

// --- Basic Auth ---
$user = $_SERVER['PHP_AUTH_USER'] ?? null;
$pass = $_SERVER['PHP_AUTH_PW'] ?? null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($user === null && preg_match('/^Basic\s+(.+)$/i', $authHeader, $m)) {
    [$user, $pass] = array_pad(explode(':', (string)base64_decode($m[1]), 2), 2, '');
}
if (!hash_equals($optima['callback_username'], (string)$user) || !hash_equals($optima['callback_password'], (string)$pass)) {
    app_log('callback', 'Неверная авторизация', ['user' => $user, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    json_response(401, ['error' => 'Ошибка аутентификации']);
}

// --- Данные ---
$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(400, ['error' => 'Неверный формат данных', 'details' => 'Ожидается JSON']);
}
app_log('callback', 'Получен колбэк', $data);

$errors = [];
foreach (['status', 'sum', 'note', 'transactionId', 'transactionProcessedDateTime'] as $f) {
    if (!isset($data[$f]) || !is_scalar($data[$f]) || $data[$f] === '') {
        $errors[] = "Отсутствует обязательное поле: $f";
    }
}
if (isset($data['sum']) && (!is_numeric($data['sum']) || $data['sum'] <= 0)) {
    $errors[] = "Поле 'sum' должно быть положительным числом";
}
if ($errors) {
    app_log('callback', 'Ошибка валидации', $errors);
    json_response(400, ['error' => 'Неверный формат данных', 'details' => implode(', ', $errors)]);
}

$txId = (string)$data['transactionId'];
$sum = round((float)$data['sum'], 2);
$note = trim((string)$data['note']);

try {
    $invoices = new Invoices(app_db());

    // Повтор того же колбэка
    if (!$invoices->registerCallback($txId, $data)) {
        $prev = $invoices->callback($txId);
        $result = (string)($prev['result'] ?? '');
        if ($result !== 'processing') {
            app_log('callback', 'Повторный колбэк', ['transactionId' => $txId, 'result' => $result]);
            json_response(200, ['message' => 'Callback уже обработан', 'transactionId' => $txId, 'result' => $result]);
        }
        // Предыдущая попытка не завершилась. Если она свежая — возможно, ещё идёт: пусть банк повторит позже.
        if (time() - strtotime((string)$prev['received_at']) < 120) {
            app_log('callback', 'Колбэк ещё обрабатывается', ['transactionId' => $txId]);
            json_response(503, ['error' => 'Обрабатывается, повторите позже']);
        }
        app_log('callback', 'Повторная обработка незавершённого колбэка', ['transactionId' => $txId]);
    }

    $finish = function (string $result, ?int $invoiceId, array $extra = []) use ($invoices, $txId) {
        $invoices->finishCallback($txId, $result, $invoiceId);
        app_log('callback', "Результат: $result", ['transactionId' => $txId, 'invoice' => $invoiceId] + $extra);
        json_response(200, ['message' => 'Callback успешно обработан', 'transactionId' => $txId, 'result' => $result]);
    };

    // Счёт по note
    $invoice = ctype_digit($note) ? $invoices->find((int)$note) : null;
    if (!$invoice) {
        $finish('invoice_not_found', null, ['note' => $note, 'sum' => $sum]);
    }
    $invoiceId = (int)$invoice['id'];

    if ($data['status'] !== 'success') {
        $finish('failed', $invoiceId, ['bank_message' => $data['errorMessage'] ?? $data['responseMessage'] ?? null]);
    }

    $paidFields = [
        'paid_amount' => $sum,
        'paid_at' => date('Y-m-d H:i:s'),
        'optima_transaction_id' => $txId,
    ];

    $mismatch = abs($sum - (float)$invoice['amount']) > 0.009;
    $newStatus = $mismatch ? 'amount_mismatch' : 'paid';

    // Перевод счёта из pending — атомарно: второй платёж по тому же счёту сюда не пройдёт
    if (!$invoices->transition($invoiceId, 'pending', $paidFields + ['status' => $newStatus])) {
        $current = $invoices->find($invoiceId);
        if ($current['optima_transaction_id'] !== $txId) {
            $finish($current['status'] === 'cancelled' ? 'invoice_cancelled' : 'duplicate_payment', $invoiceId, ['sum' => $sum]);
        }
        // Счёт уже переведён этой же транзакцией в прошлой (прерванной) попытке — доделываем
        if ($current['status'] === 'paid' && $current['altegio_status'] === 'ok') {
            $finish('paid', $invoiceId);
        }
    }

    // Сумма не совпала — деньги получены, но в Altegio не проводим, разбирается администратор
    if ($mismatch) {
        $finish('amount_mismatch', $invoiceId, ['expected' => $invoice['amount'], 'got' => $sum]);
    }

    $result = $invoices->postToAltegio($invoices->find($invoiceId), app_altegio(), $cfg['altegio']);
    $finish($result['ok'] ? 'paid' : 'paid_altegio_error', $invoiceId, $result);

} catch (Throwable $e) {
    // Колбэк остаётся в статусе processing — повтор от банка обработает его заново
    app_log('callback', 'Ошибка обработки', ['transactionId' => $txId, 'error' => $e->getMessage()]);
    json_response(500, ['error' => 'Временная ошибка, повторите позже']);
}
