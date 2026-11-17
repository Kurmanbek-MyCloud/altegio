<?php
/**
 * Статус счёта для страницы оплаты (опрос раз в несколько секунд).
 * GET /payment_status.php?i=<token счёта>
 */

require __DIR__ . '/lib/bootstrap.php';

try {
    $invoice = (new Invoices(app_db()))->findByToken((string)($_GET['i'] ?? ''));
} catch (Throwable $e) {
    json_response(503, ['status' => 'pending']);
}
if (!$invoice) {
    json_response(404, ['status' => 'not_found']);
}

// Для клиента «сумма не совпала» — тоже «деньги получены», разбирается администратор
$status = in_array($invoice['status'], ['paid', 'amount_mismatch'], true) ? 'paid' : $invoice['status'];

json_response(200, [
    'status' => $status,
    'sum' => $status === 'paid' ? (float)$invoice['paid_amount'] : (float)$invoice['amount'],
]);
