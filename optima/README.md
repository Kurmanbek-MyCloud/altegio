# Optima Bank Webhook Integration

Интеграция для приема callback'ов от Optima Bank и автоматического создания платежей в системе.

## Структура проекта

```
optima/
├── webhook.php          # Основной endpoint для приема callback'ов
├── createpayment.php    # Обработка платежей и создание записей
├── test_webhook.php     # Тестовый скрипт
├── config.php           # Конфигурация (НЕ коммитить в git!)
├── .gitignore          # Исключения для git
├── webhook.log         # Логи webhook'а
└── createpayment.log   # Логи обработки платежей
```

## Установка и настройка

### 1. Настройка конфигурации

Скопируйте `config.php` и настройте параметры:

```php
$WEBHOOK_CONFIG = [
    'username' => 'your_username',
    'password' => 'your_secure_password',
    'allowed_ips' => [
        'IP_OPTIMA_BANK_1',
        'IP_OPTIMA_BANK_2'
    ],
    // ... другие настройки
];
```

### 2. Настройка прав доступа

```bash
chmod 755 webhook.php
chmod 755 createpayment.php
chmod 644 config.php
```

### 3. Настройка веб-сервера

Убедитесь, что PHP имеет доступ к:
- Файлам проекта
- Существующей системе Vtiger CRM
- Права на запись логов

## Использование

### Endpoint для Optima Bank

**URL:** `https://yourdomain.com/payment_services/optima/webhook.php`

**Метод:** POST

**Аутентификация:** Basic Auth

**Заголовки:**
```
Content-Type: application/json
Authorization: Basic base64(username:password)
```

### Тестирование

Запустите тестовый скрипт:

```bash
php test_webhook.php
```

## Формат данных

### Входящие данные от Optima Bank

```json
{
    "status": "success",
    "sum": 1500.50,
    "note": "373933",
    "transactionId": "TXN_123456789",
    "transactionProcessedDateTime": "2025-01-07T12:00:00Z",
    "merchantId": "MERCH001",
    "orderId": "ORDER_123456",
    "currency": "KZT",
    "paymentMethod": "card",
    "cardMask": "****1234",
    "cardType": "VISA",
    "approvalCode": "APP123456",
    "rrn": "RRN123456789",
    "stan": "123456",
    "terminalId": "TERM001",
    "merchantName": "Test Merchant",
    "responseCode": "00",
    "responseMessage": "Approved",
    "additionalData": {
        "customerEmail": "test@example.com",
        "customerPhone": "+77001234567",
        "accountNumber": "373933"
    }
}
```

### Ответ

**Успешный ответ (200):**
```json
{
    "message": "Callback успешно обработан",
    "transactionId": "TXN_123456789",
    "receivedAt": "2025-01-07T12:00:00Z"
}
```

**Ошибка (400/401/500):**
```json
{
    "error": "Описание ошибки",
    "details": "Детали ошибки"
}
```

## Безопасность

### Рекомендации

1. **Измените credentials** в `config.php`
2. **Настройте IP-фильтрацию** для разрешенных адресов Optima Bank
3. **Используйте HTTPS** в продакшене
4. **Регулярно ротируйте логи**
5. **Мониторьте доступы** к endpoint'у

### Проверка IP-адресов

Добавьте реальные IP-адреса Optima Bank в `config.php`:

```php
'allowed_ips' => [
    '192.168.1.100', // IP Optima Bank 1
    '192.168.1.101', // IP Optima Bank 2
],
```

## Логирование

### Уровни логирования

- **DEBUG** - детальная отладочная информация
- **INFO** - общая информация о работе
- **WARNING** - предупреждения
- **ERROR** - ошибки

### Файлы логов

- `webhook.log` - логи приема callback'ов
- `createpayment.log` - логи обработки платежей

## Обработка ошибок

### Типичные ошибки

1. **401 Unauthorized** - неверные credentials
2. **400 Bad Request** - неверный формат данных
3. **500 Internal Server Error** - ошибка обработки

### Retry механизм

Система автоматически повторяет обработку при ошибках (настраивается в `config.php`).

## Мониторинг

### Ключевые метрики

- Количество успешных/неуспешных платежей
- Время обработки callback'ов
- Ошибки валидации данных
- Ошибки создания платежей

### Алерты

Настройте мониторинг для:
- HTTP ошибок (4xx, 5xx)
- Ошибок в логах
- Превышения времени обработки

## Поддержка

При возникновении проблем:

1. Проверьте логи в `webhook.log` и `createpayment.log`
2. Убедитесь в правильности конфигурации
3. Проверьте доступность системы Vtiger CRM
4. Убедитесь в корректности данных от Optima Bank 