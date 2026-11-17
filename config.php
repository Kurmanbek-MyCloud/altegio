<?php
/**
 * Настройки по умолчанию. Секреты и значения для конкретного сервера —
 * в config.local.php (не хранится в git, шаблон: config.local.example.php).
 */

$config = [
    'app' => [
        'base_url' => 'https://example.com', // адрес сайта, без слеша в конце
        'name' => 'Оплата абонементов',      // заголовок админки и страницы оплаты
        'logo' => '',                        // путь/URL логотипа на странице оплаты (пусто — без логотипа)
    ],

    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=billing;charset=utf8mb4',
        'username' => '',
        'password' => '',
    ],

    'altegio' => [
        'base_url' => 'https://api.alteg.io/api/v1/',
        'partner_token' => '',
        'user_token' => '',
        'company_id' => null, // ID локации
        'account_id' => null, // касса, на которую проводятся оплаты Optima
    ],

    'optima' => [
        'api_url' => 'https://api.optimabusiness.kg/api/v1/generate/qr',
        'api_key' => '',
        'account' => '',           // счёт получателя в Optima
        'callback_username' => '', // Basic Auth, с которым банк присылает колбэк
        'callback_password' => '',
    ],

    // ДЕМО: QR генерируется локально (без Optima) + симулятор банка в админке.
    // На боевом сервере ДОЛЖНО быть false.
    'demo' => [
        'enabled' => false,
    ],

    'admin' => [
        // логин => password_hash(...). Сгенерировать: php -r 'echo password_hash("пароль", PASSWORD_DEFAULT), "\n";'
        'users' => [],
    ],
];

$localConfig = getenv('APP_CONFIG_LOCAL') ?: __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    $config = array_replace_recursive($config, require $localConfig);
}

return $config;
