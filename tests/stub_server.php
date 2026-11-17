<?php
/**
 * Заглушка API Altegio и Optima для тестов (роутер для php -S).
 * Состояние — в JSON-файле из переменной окружения STUB_STATE:
 *   remaining   — остаток к оплате по документам продажи
 *   fail_docs   — документы, оплата которых возвращает ошибку
 *   payments    — принятые оплаты документов
 *   qr_requests — запросы генерации QR
 */

$stateFile = getenv('STUB_STATE');
$state = json_decode((string)@file_get_contents($stateFile), true) ?: [];
$state += ['remaining' => ['9001' => 5000, '9002' => 2500], 'fail_docs' => [], 'payments' => [], 'qr_requests' => []];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode((string)file_get_contents('php://input'), true) ?: [];

header('Content-Type: application/json');

$save = function () use (&$state, $stateFile) {
    file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
};
$ok = function ($data, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data, 'meta' => []], JSON_UNESCAPED_UNICODE);
    exit;
};
$fail = function ($message, $code) {
    http_response_code($code);
    echo json_encode(['success' => false, 'data' => null, 'meta' => ['message' => $message]], JSON_UNESCAPED_UNICODE);
    exit;
};

// --- Optima ---
if ($path === '/optima/generate/qr' && $method === 'POST') {
    if (($_SERVER['HTTP_X_API_KEY'] ?? '') !== 'test-key') {
        http_response_code(401);
        exit('{"error":"unauthorized"}');
    }
    $state['qr_requests'][] = $body;
    $save();
    echo json_encode(['qrUrl' => 'https://optimabank.kg/index.php?lang=ru#HASH' . $body['note']]);
    exit;
}

// --- Altegio ---
if (!preg_match('#^/altegio/(.*)$#', $path, $m)) {
    $fail('not found', 404);
}
$ep = $m[1];

if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer partner, User user') {
    $fail('Partner ID not specified', 401);
}

if ($ep === 'company/111/clients/search' && $method === 'POST') {
    $value = $body['filters'][0]['state']['value'] ?? '';
    $ok($value === '996555123456' ? [['id' => 777, 'name' => 'Айбек', 'phone' => '+996555123456']] : []);
}
if ($ep === 'clients/111' && $method === 'GET') {
    $ok([]);
}
if ($ep === 'records/111' && $method === 'GET') {
    $ok(($_GET['client_id'] ?? '') === '777' ? [[
        'id' => 501, 'datetime' => '2026-11-16T15:30:00+06:00', 'deleted' => false, 'paid_full' => 0,
        'services' => [['title' => 'Абонемент', 'cost' => 5000]],
        'documents' => [['id' => 9001, 'type_id' => 7]],
    ]] : []);
}
if ($ep === 'record/111/501' && $method === 'GET') {
    $ok([
        'id' => 501, 'datetime' => '2026-11-16T15:30:00+06:00', 'deleted' => false, 'paid_full' => 0,
        'client' => ['id' => 777, 'display_name' => 'Айбек', 'phone' => '996555123456'],
        'services' => [['title' => 'Абонемент', 'cost' => 5000]],
        'documents' => [['id' => 9001, 'type_id' => 7]],
    ]);
}
if ($ep === 'loyalty/memberships' && $method === 'GET') {
    $ok(($_GET['phone'] ?? '') === '996555123456' && ($_GET['location_id'] ?? '') === '111' ? [[
        'id' => 601, 'number' => 'M-1', 'goods_transaction_id' => 7001, 'created_date' => '2026-11-10T10:00:00+06:00',
        'type' => ['title' => 'Безлимит 1 мес'],
    ]] : []);
}
if ($ep === 'storage_operations/goods_transactions/111/7001' && $method === 'GET') {
    $ok(['id' => 7001, 'document_id' => 9002]);
}
if (preg_match('#^locations/111/sale/(\d+)$#', $ep, $mm) && $method === 'GET') {
    $rem = (float)($state['remaining'][$mm[1]] ?? 0);
    $ok(['state' => ['items' => [], 'payment_transactions' => []], 'payment_methods' => [
        ['slug' => 'account', 'account_id' => 222, 'is_applicable' => $rem > 0, 'applicable_amount' => $rem],
        ['slug' => 'account', 'account_id' => 333, 'is_applicable' => true, 'applicable_amount' => $rem],
    ]]);
}
if (preg_match('#^locations/111/sale/(\d+)/payment$#', $ep, $mm) && $method === 'POST') {
    $doc = $mm[1];
    if (in_array($doc, $state['fail_docs'], true)) {
        $fail('Сбой проведения оплаты', 422);
    }
    $amount = (float)($body['payment']['amount'] ?? 0);
    if ($amount > (float)($state['remaining'][$doc] ?? 0) + 0.001) {
        $fail('Сумма больше остатка', 422);
    }
    $state['remaining'][$doc] = (float)$state['remaining'][$doc] - $amount;
    $state['payments'][] = ['document_id' => (int)$doc] + $body;
    $save();
    $ok(['state' => ['payment_transactions' => [['id' => 880000 + count($state['payments']), 'amount' => $amount]]]]);
}

$fail("unknown endpoint $method $ep", 404);
