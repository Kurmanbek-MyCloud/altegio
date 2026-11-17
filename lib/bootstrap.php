<?php
/**
 * Общая инициализация для оплаты абонементов.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Bishkek');

require_once __DIR__ . '/Altegio.php';
require_once __DIR__ . '/Optima.php';
require_once __DIR__ . '/Invoices.php';
require_once __DIR__ . '/PaymentLinks.php';

function app_config(): array {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

function app_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $db = app_config()['db'];
        $pdo = new PDO($db['dsn'], $db['username'] ?: null, $db['password'] ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function app_altegio(): Altegio {
    $c = app_config()['altegio'];
    return new Altegio($c['base_url'], $c['partner_token'], $c['user_token'], (int)$c['company_id']);
}

function app_demo(): bool {
    return !empty(app_config()['demo']['enabled']);
}

function app_optima(): Optima {
    if (app_demo()) {
        return new DemoOptima();
    }
    $c = app_config()['optima'];
    return new Optima($c['api_url'], $c['api_key'], (string)$c['account']);
}

/**
 * Лог в logs/<channel>.log. Секреты сюда не писать.
 */
function app_log(string $channel, string $message, $context = null): void {
    $dir = getenv('APP_LOG_DIR') ?: __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . "] $message";
    if ($context !== null) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    @file_put_contents("$dir/$channel.log", $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Телефон КР в формате Altegio: 996XXXXXXXXX.
 * Принимает +996 555 123-456, 0555123456, 555123456.
 */
function normalize_phone(string $phone): string {
    $d = preg_replace('/\D+/', '', $phone);
    if (strlen($d) === 10 && $d[0] === '0') {
        $d = '996' . substr($d, 1);
    } elseif (strlen($d) === 9) {
        $d = '996' . $d;
    }
    return $d;
}

/** Последние 9 цифр — для сравнения телефонов в разных форматах */
function phone_key(string $phone): string {
    $d = preg_replace('/\D+/', '', $phone);
    return strlen($d) > 9 ? substr($d, -9) : $d;
}

function json_response(int $code, array $body): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
