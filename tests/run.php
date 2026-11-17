<?php
/**
 * Автотесты оплаты абонементов.
 *
 *   php tests/run.php
 *
 * Поднимает два php -S сервера (приложение + заглушка Altegio/Optima),
 * временную SQLite-базу и прогоняет сценарии через HTTP. Настоящие API не вызываются.
 */

declare(strict_types=1);

const APP_PORT = 18082;
const STUB_PORT = 18081;
const APP = 'http://127.0.0.1:' . APP_PORT;

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/billing-tests-' . getmypid();
@mkdir($tmp, 0700, true);

// ---------- Окружение ----------
$db = "$tmp/test.sqlite";
$pdo = new PDO("sqlite:$db");
$pdo->exec((string)file_get_contents("$root/schema.sqlite.sql"));
$pdo->exec("INSERT INTO sqlite_sequence(name, seq) VALUES ('invoices', 10000)");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$stubState = "$tmp/stub.json";
file_put_contents($stubState, '{}');

$adminPass = 'test-pass-123';
file_put_contents("$tmp/config.php", '<?php return ' . var_export([
    'app' => ['base_url' => APP, 'name' => 'Тест'],
    'db' => ['dsn' => "sqlite:$db", 'username' => '', 'password' => ''],
    'altegio' => [
        'base_url' => 'http://127.0.0.1:' . STUB_PORT . '/altegio/',
        'partner_token' => 'partner', 'user_token' => 'user', 'company_id' => 111, 'account_id' => 222,
    ],
    'optima' => [
        'api_url' => 'http://127.0.0.1:' . STUB_PORT . '/optima/generate/qr', 'api_key' => 'test-key', 'account' => '123456',
        'callback_username' => 'optima', 'callback_password' => 'cb-secret',
    ],
    'demo' => ['enabled' => false],
    'admin' => ['users' => ['admin' => password_hash($adminPass, PASSWORD_DEFAULT)]],
], true) . ';');

@mkdir("$tmp/logs");
$env = ['APP_CONFIG_LOCAL' => "$tmp/config.php", 'APP_LOG_DIR' => "$tmp/logs", 'STUB_STATE' => $stubState, 'PATH' => getenv('PATH')];
$servers = [
    proc_open([PHP_BINARY, '-S', '127.0.0.1:' . STUB_PORT, "$root/tests/stub_server.php"], [1 => ['file', "$tmp/stub.log", 'a'], 2 => ['file', "$tmp/stub.log", 'a']], $p1, $root, $env),
    proc_open([PHP_BINARY, '-S', '127.0.0.1:' . APP_PORT], [1 => ['file', "$tmp/app.log", 'a'], 2 => ['file', "$tmp/app.log", 'a']], $p2, $root, $env),
];
register_shutdown_function(function () use ($servers, $tmp) {
    foreach ($servers as $s) {
        proc_terminate($s);
    }
    exec('rm -rf ' . escapeshellarg($tmp));
});
foreach ([STUB_PORT, APP_PORT] as $port) {
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
        usleep(100000);
    }
}

// ---------- Мини-фреймворк ----------
$passed = 0;
$failed = [];
function check(string $name, bool $cond, $debug = null): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  \033[32m✓\033[0m $name\n";
    } else {
        $failed[] = $name;
        echo "  \033[31m✗ $name\033[0m\n";
        if ($debug !== null) {
            echo '      ' . substr(is_string($debug) ? $debug : json_encode($debug, JSON_UNESCAPED_UNICODE), 0, 400) . "\n";
        }
    }
}
function section(string $title): void {
    echo "\n\033[1m$title\033[0m\n";
}

/** @return array{0:int,1:string,2:mixed} [код, тело, JSON] */
function http(string $method, string $path, $body = null, array $headers = [], ?string $cookies = null, ?string $auth = null): array {
    $ch = curl_init(APP . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30]);
    if (is_array($body)) {
        $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($cookies) {
        curl_setopt_array($ch, [CURLOPT_COOKIEFILE => $cookies, CURLOPT_COOKIEJAR => $cookies]);
    }
    if ($auth) {
        curl_setopt($ch, CURLOPT_USERPWD, $auth);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return [$code, $res, json_decode($res, true)];
}

function stub(): array {
    global $stubState;
    return json_decode((string)file_get_contents($stubState), true) ?: [];
}
function stubSet(array $patch): void {
    global $stubState;
    file_put_contents($stubState, json_encode(array_replace(stub(), $patch)));
}
function invoice(int $id): array {
    global $pdo;
    $s = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: [];
}
function callback(array $over = [], string $auth = 'optima:cb-secret'): array {
    static $n = 0;
    $data = array_replace([
        'status' => 'success', 'sum' => 5000, 'note' => '10001',
        'transactionId' => 'TX_' . getmypid() . '_' . (++$n),
        'transactionProcessedDateTime' => gmdate('Y-m-d\TH:i:s\Z'),
    ], $over);
    [$code, , $json] = http('POST', '/optima_callback.php', $data, [], null, $auth);
    return [$code, $json, $data];
}

// ---------- Тесты ----------
section('Нормализация телефонов');
require "$root/lib/bootstrap.php";
foreach (['+996 555 123-456', '0555123456', '555123456', '996555123456'] as $p) {
    check("normalize_phone('$p') = 996555123456", normalize_phone($p) === '996555123456', normalize_phone($p));
}
check('phone_key совпадает для разных форматов', phone_key('+996 (555) 12-34-56') === phone_key('0555123456'));

section('Вход в админку');
$jar = "$tmp/cookies.txt";
[$code, , $json] = http('POST', '/admin/api.php', ['action' => 'invoices'], [], $jar);
check('API без входа → 401', $code === 401, $code);
[$code, $html] = http('POST', '/admin/', 'do=login&user=admin&password=wrong', [], $jar);
check('Неверный пароль отклонён', str_contains($html, 'Неверный логин или пароль'));
http('POST', '/admin/', 'do=login&user=admin&password=' . $adminPass, [], $jar);
[$code, $html] = http('GET', '/admin/', null, [], $jar);
preg_match('/const CSRF = "([a-f0-9]{32})"/', $html, $m);
$csrf = $m[1] ?? '';
check('Вход выполнен, есть CSRF-токен', $csrf !== '');
[$code] = http('POST', '/admin/api.php', ['action' => 'invoices'], [], $jar);
check('Запрос без CSRF → 403', $code === 403, $code);
$api = fn(array $data) => http('POST', '/admin/api.php', $data, ['X-CSRF-Token: ' . $csrf], $jar);
[$code] = http('GET', '/admin/demo_bank.php', null, [], $jar);
check('Симулятор банка недоступен, когда демо выключено', $code === 404, $code);

section('Поиск неоплаченного');
[$code, , $json] = $api(['action' => 'payables', 'phone' => '0555 123 456']);
check('Клиент найден по телефону', $code === 200 && ($json['client']['id'] ?? 0) === 777, $json);
$items = array_column($json['items'] ?? [], null, 'document_id');
check('Запись с услугой «Абонемент» к оплате 5000', ($items[9001]['kind'] ?? '') === 'record' && ($items[9001]['remaining'] ?? 0) == 5000, $items);
check('Абонемент из лояльности к оплате 2500', ($items[9002]['kind'] ?? '') === 'membership' && ($items[9002]['number'] ?? '') === 'M-1', $items);
[$code] = $api(['action' => 'payables', 'phone' => '0700000000']);
check('Неизвестный телефон → 404', $code === 404, $code);

section('Создание счёта');
[$code, , $json] = $api(['action' => 'create_invoice', 'phone' => '0555123456', 'document_id' => 12345, 'amount' => 100]);
check('Чужой документ отклонён', $code === 400, $json);
[$code, , $json] = $api(['action' => 'create_invoice', 'phone' => '0555123456', 'document_id' => 9001, 'amount' => 6000]);
check('Сумма больше остатка отклонена', $code === 400, $json);
[$code, , $json] = $api(['action' => 'create_invoice', 'phone' => '0555123456', 'document_id' => 9001, 'amount' => 5000]);
check('Счёт создан (№10001)', $code === 200 && ($json['invoice_id'] ?? 0) === 10001, $json);
check('Ссылка WhatsApp на номер клиента', str_starts_with($json['whatsapp'] ?? '', 'https://wa.me/996555123456?text='), $json);
$qr = stub()['qr_requests'][0] ?? [];
check('В Optima ушёл note = номер счёта и сумма', ($qr['note'] ?? '') === '10001' && ($qr['sum'] ?? 0) == 5000 && ($qr['account'] ?? 0) === 123456, $qr);
$inv = invoice(10001);
$token = $inv['token'] ?? '';

section('Страница оплаты');
[$code, $html] = http('GET', '/payment_page.php?i=' . $token);
check('Открывается', $code === 200);
check('Показывает сумму и QR', str_contains($html, '5 000') && str_contains($html, 'data:image/png'), substr($html, 0, 300));
[$code, $html] = http('GET', '/payment_page.php?i=' . $token, null, ['User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile']);
preg_match_all('/data-url="([^"]+)"/', $html, $mm);
$links = array_map('base64_decode', $mm[1]);
check('На телефоне 7 банков, ссылки содержат хеш QR', count($links) === 7 && str_ends_with($links[0], '#HASH10001'), $links);
[$code] = http('GET', '/payment_page.php?i=0123456789abcdef0123456789abcdef');
check('Чужой токен → 404', $code === 404, $code);
[$code] = http('GET', '/payment_page.php?amount=1&qr_code=https://evil');
check('Старый режим (сумма в адресе) отключён → 404', $code === 404, $code);
[, , $json] = http('GET', '/payment_status.php?i=' . $token);
check('Статус: pending', ($json['status'] ?? '') === 'pending', $json);

section('Колбэк банка: защита');
[$code] = http('POST', '/optima_callback.php', ['status' => 'success']);
check('Без авторизации → 401', $code === 401, $code);
[$code] = callback([], 'optima:wrong');
check('Неверный пароль → 401', $code === 401, $code);
[$code] = http('POST', '/optima_callback.php', 'not json', [], null, 'optima:cb-secret');
check('Не JSON → 400', $code === 400, $code);
[$code, $json] = callback(['sum' => 'abc', 'note' => '']);
check('Неверные поля → 400', $code === 400, $json);
[$code, $json] = callback(['note' => ['10001'], 'transactionId' => ['x']]);
check('Поля-массивы вместо строк → 400', $code === 400, $json);
[$code] = http('GET', '/optima_callback.php', null, [], null, 'optima:cb-secret');
check('GET → 405', $code === 405, $code);

section('Колбэк банка: оплата');
[$code, $json] = callback(['status' => 'failed', 'note' => '10001']);
check('Отказ банка: счёт остаётся неоплаченным', ($json['result'] ?? '') === 'failed' && invoice(10001)['status'] === 'pending', $json);
[$code, $json, $sent] = callback(['note' => '10001', 'sum' => 5000]);
$inv = invoice(10001);
check('Успешная оплата → paid', $code === 200 && ($json['result'] ?? '') === 'paid', $json);
check('Счёт: оплачен и проведён в Altegio', $inv['status'] === 'paid' && $inv['altegio_status'] === 'ok' && (int)$inv['altegio_transaction_id'] === 880001, $inv);
$pay = stub()['payments'][0] ?? [];
check('В Altegio оплачен документ 9001 на кассу 222 суммой 5000',
    ($pay['document_id'] ?? 0) === 9001 && ($pay['payment']['method'] ?? []) === ['slug' => 'account', 'account_id' => 222] && ($pay['payment']['amount'] ?? 0) == 5000, $pay);
[, , $json] = http('GET', '/payment_status.php?i=' . $token);
check('Статус для страницы: paid', ($json['status'] ?? '') === 'paid' && ($json['sum'] ?? 0) == 5000, $json);
[, $html] = http('GET', '/payment_page.php?i=' . $token);
check('Страница оплаченного счёта сразу показывает «Оплата получена»', preg_match('/class="card done "/', $html) === 1);
[$code, $json] = callback(['note' => '10001', 'transactionId' => $sent['transactionId']]);
check('Повтор того же колбэка: «уже обработан», без второй оплаты', ($json['message'] ?? '') === 'Callback уже обработан' && count(stub()['payments']) === 1, $json);
[$code, $json] = callback(['note' => '10001']);
check('Вторая оплата того же счёта → duplicate_payment, в Altegio не идёт', ($json['result'] ?? '') === 'duplicate_payment' && count(stub()['payments']) === 1, $json);
[$code, $json] = callback(['note' => '99999']);
check('Несуществующий счёт → invoice_not_found', ($json['result'] ?? '') === 'invoice_not_found', $json);
[$code, $json] = callback(['note' => 'abc']);
check('Нечисловой note → invoice_not_found', ($json['result'] ?? '') === 'invoice_not_found', $json);

section('Колбэк банка: нестандартные случаи');
[, , $json] = $api(['action' => 'create_invoice', 'phone' => '0555123456', 'document_id' => 9002, 'amount' => 2500]);
$id2 = (int)($json['invoice_id'] ?? 0);
[$code, $json] = callback(['note' => (string)$id2, 'sum' => 2000]);
check('Сумма не совпала → amount_mismatch, в Altegio не идёт', ($json['result'] ?? '') === 'amount_mismatch' && invoice($id2)['status'] === 'amount_mismatch' && count(stub()['payments']) === 1, $json);
[, , $json] = http('GET', '/payment_status.php?i=' . invoice($id2)['token']);
check('Клиенту при несовпадении суммы показываем «оплачено»', ($json['status'] ?? '') === 'paid', $json);

$pdo->exec("UPDATE invoices SET status = 'cancelled' WHERE id = $id2");
$pdo->exec("INSERT INTO invoices (token, phone, client_id, client_name, document_id, item_type, item_id, item_title, amount, status, qr_url, qr_hash, altegio_status, created_at, updated_at)
            VALUES ('" . str_repeat('c', 32) . "', '996555123456', 777, 'Айбек', 9002, 'membership', 601, 'Безлимит 1 мес', 2500, 'pending', 'https://x#H', 'H', 'none', datetime('now'), datetime('now'))");
$id3 = (int)$pdo->lastInsertId();
[$code, , $json] = $api(['action' => 'cancel', 'invoice_id' => $id3]);
check('Админ отменил счёт', $code === 200 && invoice($id3)['status'] === 'cancelled', $json);
[$code] = http('GET', '/payment_page.php?i=' . str_repeat('c', 32));
check('Страница отменённого счёта → 404', $code === 404, $code);
[$code, $json] = callback(['note' => (string)$id3, 'sum' => 2500]);
check('Оплата отменённого счёта → invoice_cancelled, в Altegio не идёт', ($json['result'] ?? '') === 'invoice_cancelled' && count(stub()['payments']) === 1, $json);

section('Ошибка Altegio и повтор из админки');
$pdo->exec("INSERT INTO invoices (token, phone, client_id, client_name, document_id, item_type, item_id, item_title, amount, status, qr_url, qr_hash, altegio_status, created_at, updated_at)
            VALUES ('" . str_repeat('d', 32) . "', '996555123456', 777, 'Айбек', 9002, 'membership', 601, 'Безлимит 1 мес', 2500, 'pending', 'https://x#H', 'H', 'none', datetime('now'), datetime('now'))");
$id4 = (int)$pdo->lastInsertId();
stubSet(['fail_docs' => ['9002']]);
[$code, $json] = callback(['note' => (string)$id4, 'sum' => 2500]);
$inv = invoice($id4);
check('Altegio не принял → счёт оплачен, ошибка Altegio сохранена', ($json['result'] ?? '') === 'paid_altegio_error' && $inv['status'] === 'paid' && $inv['altegio_status'] === 'error', $json);
check('Банку ответили 200 (деньги приняты)', $code === 200, $code);
[, , $json] = $api(['action' => 'invoices']);
$problems = array_column($json['problems'] ?? [], 'result');
check('Проблемы видны в админке', in_array('paid_altegio_error', $problems, true) && in_array('amount_mismatch', $problems, true) && in_array('duplicate_payment', $problems, true), $problems);
stubSet(['fail_docs' => []]);
[$code, , $json] = $api(['action' => 'retry_altegio', 'invoice_id' => $id4]);
check('Повтор из админки провёл оплату', $code === 200 && invoice($id4)['altegio_status'] === 'ok' && count(stub()['payments']) === 2, $json);
[$code] = $api(['action' => 'retry_altegio', 'invoice_id' => $id4]);
check('Повторно провести уже проведённое нельзя', $code === 400, $code);

section('Восстановление после сбоя');
$pdo->exec("INSERT INTO invoices (token, phone, client_id, client_name, document_id, item_type, item_id, item_title, amount, status, qr_url, qr_hash, altegio_status, created_at, updated_at)
            VALUES ('" . str_repeat('e', 32) . "', '996555123456', 777, 'Айбек', 9001, 'record', 501, 'Абонемент', 100, 'pending', 'https://x#H', 'H', 'none', datetime('now'), datetime('now'))");
$id5 = (int)$pdo->lastInsertId();
stubSet(['remaining' => ['9001' => 100, '9002' => 0]]);
$stuck = ['status' => 'success', 'sum' => 100, 'note' => (string)$id5, 'transactionId' => 'TX_STUCK', 'transactionProcessedDateTime' => gmdate('Y-m-d\TH:i:s\Z')];
$pdo->prepare("INSERT INTO optima_callbacks (transaction_id, bank_status, sum, note, payload, result, received_at) VALUES ('TX_STUCK', 'success', 100, ?, '{}', 'processing', ?)")
    ->execute([(string)$id5, date('Y-m-d H:i:s')]);
[$code] = callback($stuck);
check('Свежий незавершённый колбэк → 503 (банк повторит)', $code === 503, $code);
$pdo->exec("UPDATE optima_callbacks SET received_at = '" . date('Y-m-d H:i:s', time() - 600) . "' WHERE transaction_id = 'TX_STUCK'");
[$code, $json] = callback($stuck);
check('Зависший колбэк обработан при повторе банка', ($json['result'] ?? '') === 'paid' && invoice($id5)['altegio_status'] === 'ok' && count(stub()['payments']) === 3, $json);
[$code, $json] = callback($stuck);
check('Следующий повтор — «уже обработан», без двойной оплаты', ($json['message'] ?? '') === 'Callback уже обработан' && count(stub()['payments']) === 3, $json);

section('Вкладка в окне записи Altegio');
stubSet(['remaining' => ['9001' => 700, '9002' => 0]]);
$tab = ['salon_id' => '111', 'hash' => sha1('111partner'), 'entity_type' => 'visit', 'entity_id' => '501', 'user_id' => '42'];
[$code, $html] = http('GET', '/altegio_tab.php?' . http_build_query(array_replace($tab, ['hash' => 'bad'])));
check('Неверная подпись → ошибка', str_contains($html, 'Неверная подпись Altegio'));
[$code, $html] = http('GET', '/altegio_tab.php?' . http_build_query(array_replace($tab, ['salon_id' => '999', 'hash' => sha1('999partner')])));
check('Чужая локация → ошибка', str_contains($html, 'другой локации'));
[$code, $html] = http('GET', '/altegio_tab.php?' . http_build_query($tab));
check('Вкладка показывает клиента и остаток 700', $code === 200 && str_contains($html, 'Айбек') && str_contains($html, '700 сом'), substr(strip_tags($html), 0, 300));
check('Вкладка шлёт Altegio iframe_ready', str_contains($html, "type: 'iframe_ready'"));
[$code, , $json] = http('POST', '/altegio_tab.php', $tab + ['action' => 'create', 'amount' => 900]);
check('Сумма больше остатка отклонена', $code === 400, $json);
[$code, , $json] = http('POST', '/altegio_tab.php', array_replace($tab, ['hash' => 'bad']) + ['action' => 'create', 'amount' => 700]);
check('POST с неверной подписью → 403', $code === 403, $json);
[$code, , $json] = http('POST', '/altegio_tab.php', $tab + ['action' => 'create', 'amount' => 700]);
$tabInv = invoice((int)($json['invoice_id'] ?? 0));
check('Ссылка создана из вкладки', $code === 200 && ($tabInv['created_by'] ?? '') === 'altegio:42' && (int)$tabInv['document_id'] === 9001, $json);
$tabCreate = $json;
[$code, , $json] = http('POST', '/altegio_tab.php', $tab + ['action' => 'create', 'amount' => 100]);
check('Вторая ссылка на уже выставленную сумму → 409', $code === 409, $json);
$json = $tabCreate;
[, $html] = http('GET', '/altegio_tab.php?' . http_build_query($tab));
check('Вкладка показывает активную ссылку', str_contains($html, 'Ссылка №' . $tabInv['id']) || str_contains($html, '>' . $tabInv['id'] . '<'), substr(strip_tags($html), 0, 300));
check('Вкладка показывает QR активной ссылки', preg_match('/id="aQr"[^>]*src="data:image\/png/', $html) === 1);
check('При создании из вкладки возвращается QR', str_starts_with((string)($json['qr'] ?? ''), 'data:image/png'), array_keys($json ?? []));
[$code, $json] = callback(['note' => (string)$tabInv['id'], 'sum' => 700]);
check('Оплата по ссылке из вкладки проведена в Altegio', ($json['result'] ?? '') === 'paid' && (stub()['remaining']['9001'] ?? -1) == 0, $json);
[, $html] = http('GET', '/altegio_tab.php?' . http_build_query($tab));
check('После оплаты вкладка показывает «Визит оплачен»', str_contains($html, 'Визит оплачен'), substr(strip_tags($html), 0, 300));

section('Защита от двойного проведения в Altegio');
$pdo->exec("INSERT INTO invoices (token, phone, client_id, client_name, document_id, item_type, item_id, item_title, amount, status, qr_url, qr_hash, paid_amount, altegio_status, created_at, updated_at)
            VALUES ('" . str_repeat('f', 32) . "', '996555123456', 777, 'Айбек', 9001, 'record', 501, 'Абонемент', 700, 'paid', 'https://x#H', 'H', 700, 'error', datetime('now'), datetime('now'))");
$id6 = (int)$pdo->lastInsertId();
$before = count(stub()['payments']);
[$code, , $json] = $api(['action' => 'retry_altegio', 'invoice_id' => $id6]);
check('Документ уже оплачен в Altegio → повтор не проводит второй раз', $code === 502 && count(stub()['payments']) === $before && str_contains($json['error'] ?? '', 'Остаток'), $json);

section('Ошибки PHP в логах серверов');
$log = (string)@file_get_contents("$tmp/app.log");
check('Нет Warning/Fatal/Deprecated', !preg_match('/PHP (Warning|Fatal|Deprecated|Notice)/', $log), preg_grep('/PHP (Warning|Fatal|Deprecated|Notice)/', explode("\n", $log)));

// ---------- Итог ----------
echo "\n" . ($failed ? "\033[31m" : "\033[32m") . "Пройдено: $passed, упало: " . count($failed) . "\033[0m\n";
exit($failed ? 1 : 0);
