<?php
/**
 * Шаблон локальных настроек. Скопируйте в config.local.php и заполните.
 * config.local.php НЕ коммитится в git (см. .gitignore).
 */
return [
    'app' => [
        'base_url' => 'https://ваш-домен',
        'name' => 'Оплата абонементов',
        'logo' => '',  // например 'image_pay_system/logo.png'
    ],
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=billing;charset=utf8mb4',
        'username' => 'billing',
        'password' => '...',
    ],
    'altegio' => [
        'partner_token' => '...',
        'user_token' => '...',
        'company_id' => 0,          // ID боевой компании
        'account_id' => 0,          // ID кассы для оплат Optima
    ],
    'optima' => [
        'api_key' => '...',         // НОВЫЙ ключ (старый засвечен в git — перевыпустить)
        'account' => '...',         // счёт получателя
        'callback_username' => 'optima',
        'callback_password' => '...', // длинный случайный, сообщить банку
    ],
    'admin' => [
        'users' => [
            // 'admin' => '$2y$10$...',  // php -r 'echo password_hash("пароль", PASSWORD_DEFAULT), "\n";'
        ],
    ],
    'demo' => [
        'enabled' => false, // true — демо-QR и симулятор банка (только для теста!)
    ],
];
