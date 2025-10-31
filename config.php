<?php
/**
 * Конфигурация для интеграции с Altegio - Финансовые транзакции
 */

return [
    // Настройки вебхуков
    'webhook' => [
        'secret' => 'YOUR_WEBHOOK_SECRET', // Замените на ваш секретный ключ
        'log_file' => 'webhook_log.txt',
        'debug' => true,
        'allowed_ips' => [], // Массив разрешенных IP-адресов (опционально)
    ],
    
    // Настройки API Altegio
    'api' => [
        'base_url' => 'https://api.alteg.io/api/v1/',
        'user_token' => 'c1d3041f2185df70f5c341f0926adb44', // User token из кабинета
        'partner_token' => 'gbkp3f4ynkd5jpejjsxp', // Токен партнера из кабинета
        'company_id' => 729142, // ID компании
        'account_id' => 2665932, // ID кассы
        'timeout' => 30,
        'retry_attempts' => 3,
    ],
    
    // Настройки базы данных (если используется)
    'database' => [
        'host' => 'localhost',
        'dbname' => 'altegio_payments',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    
    // Настройки уведомлений
    'notifications' => [
        'email' => [
            'enabled' => false,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => 'your-email@gmail.com',
            'smtp_password' => 'your-app-password',
            'from_email' => 'noreply@yourdomain.com',
            'from_name' => 'Altegio Payments',
        ],
        'sms' => [
            'enabled' => false,
            'provider' => 'twilio', // или другой провайдер
            'account_sid' => 'YOUR_ACCOUNT_SID',
            'auth_token' => 'YOUR_AUTH_TOKEN',
            'from_number' => '+1234567890',
        ],
        'telegram' => [
            'enabled' => false,
            'bot_token' => 'YOUR_BOT_TOKEN',
            'chat_id' => 'YOUR_CHAT_ID',
        ],
    ],
    
    // Настройки обработки событий для платежей
    'events' => [
        'transaction.created' => [
            'enabled' => true,
            'actions' => [
                'save_to_database' => true,
                'send_notification' => true,
                'create_invoice' => false,
            ],
        ],
        'transaction.updated' => [
            'enabled' => true,
            'actions' => [
                'update_database' => true,
                'send_update_notification' => true,
            ],
        ],
        'transaction.cancelled' => [
            'enabled' => true,
            'actions' => [
                'mark_as_cancelled' => true,
                'send_cancellation_notification' => true,
                'process_refund' => false,
            ],
        ],
        'payment.received' => [
            'enabled' => true,
            'actions' => [
                'mark_as_paid' => true,
                'send_receipt' => true,
                'update_accounting' => true,
            ],
        ],
        'payment.failed' => [
            'enabled' => true,
            'actions' => [
                'mark_as_failed' => true,
                'send_failure_notification' => true,
                'retry_payment' => false,
            ],
        ],
        'visit.completed' => [
            'enabled' => true,
            'actions' => [
                'create_payment' => true,
                'send_completion_notification' => true,
                'request_feedback' => false,
            ],
        ],
        'appointment.created' => [
            'enabled' => true,
            'actions' => [
                'create_prepayment' => false,
                'send_confirmation' => true,
                'update_calendar' => false,
            ],
        ],
        'appointment.cancelled' => [
            'enabled' => true,
            'actions' => [
                'process_refund' => true,
                'send_cancellation_notification' => true,
                'update_calendar' => false,
            ],
        ],
    ],
    
    // Настройки платежей
    'payments' => [
        'default_currency' => 'RUB',
        'supported_currencies' => ['RUB', 'USD', 'EUR'],
        'payment_methods' => [
            'cash' => 'Наличные',
            'card' => 'Банковская карта',
            'online' => 'Онлайн оплата',
            'transfer' => 'Банковский перевод',
        ],
        'auto_create_payment' => true, // Автоматическое создание платежа при завершении визита
        'require_prepayment' => false, // Требовать предоплату при создании записи
        'refund_policy' => [
            'allow_refunds' => true,
            'refund_period_hours' => 24,
            'refund_fee_percent' => 0,
        ],
    ],
    
    // Настройки уведомлений о платежах
    'payment_notifications' => [
        'success_template' => "✅ Платеж успешно обработан\n\nСумма: {amount} {currency}\nМетод: {payment_method}\nТранзакция: {transaction_id}",
        'failure_template' => "❌ Ошибка платежа\n\nСумма: {amount} {currency}\nОшибка: {error_message}\nТранзакция: {transaction_id}",
        'refund_template' => "💰 Возврат средств\n\nСумма: {amount} {currency}\nПричина: {reason}\nТранзакция: {transaction_id}",
    ],
    
    // Настройки логирования
    'logging' => [
        'level' => 'info', // debug, info, warning, error
        'file' => 'payments.log',
        'max_size' => '10MB',
        'max_files' => 5,
        'log_transactions' => true,
        'log_payments' => true,
        'log_errors' => true,
    ],
    
    // Настройки безопасности
    'security' => [
        'verify_webhook_signature' => true,
        'allowed_payment_methods' => ['cash', 'card', 'online'],
        'max_transaction_amount' => 100000, // Максимальная сумма транзакции
        'min_transaction_amount' => 1, // Минимальная сумма транзакции
        'rate_limit' => [
            'enabled' => true,
            'max_requests_per_minute' => 60,
        ],
    ],
];
?> 