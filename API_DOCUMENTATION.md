# API Documentation - Создание платежей в Altegio

## 🎯 Описание

API endpoint для создания платежей в Altegio через наш промежуточный сервис.

**Схема работы:** Клиент → Наш API → Altegio

## 📡 Endpoint

```
POST /api_endpoint.php
```

## 🔧 Параметры запроса

### Обязательные параметры

| Параметр | Тип | Описание | Пример |
|----------|-----|----------|--------|
| `client_id` | integer | ID клиента в Altegio | `172280244` |
| `amount` | float | Сумма платежа | `1500.00` |

### Опциональные параметры

| Параметр | Тип | Описание | По умолчанию |
|----------|-----|----------|--------------|
| `description` | string | Описание платежа | `"Платеж через API"` |
| `payment_method` | string | Способ оплаты | `"cash"` |
| `currency` | string | Валюта | `"RUB"` |
| `master_id` | integer | ID мастера | - |
| `comment` | string | Комментарий | - |

## 📝 Примеры запросов

### cURL

```bash
curl -X POST https://your-domain.com/api_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 172280244,
    "amount": 1500.00,
    "description": "Оплата за стрижку",
    "payment_method": "card",
    "currency": "RUB"
  }'
```

### JavaScript (Fetch)

```javascript
const response = await fetch('https://your-domain.com/api_endpoint.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    client_id: 172280244,
    amount: 1500.00,
    description: 'Оплата за стрижку',
    payment_method: 'card',
    currency: 'RUB'
  })
});

const result = await response.json();
console.log(result);
```

### PHP

```php
$data = [
    'client_id' => 172280244,
    'amount' => 1500.00,
    'description' => 'Оплата за стрижку',
    'payment_method' => 'card',
    'currency' => 'RUB'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://your-domain.com/api_endpoint.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
```

## 📊 Ответы API

### Успешный ответ (200 OK)

```json
{
    "success": true,
    "timestamp": "2025-08-05 11:30:45",
    "data": {
        "payment_id": 525636603,
        "amount": 1500,
        "client_id": 172280244,
        "status": "created",
        "created_at": "2025-08-05 11:30:45",
        "altegio_response": {
            "id": 525636603,
            "amount": -1500,
            "client_id": 172280244,
            "account_id": 2665932,
            "date": "2025-08-05 11:30:45",
            "comment": "Оплата за стрижку"
        }
    },
    "error": null
}
```

### Ошибка валидации (400 Bad Request)

```json
{
    "success": false,
    "timestamp": "2025-08-05 11:30:45",
    "data": null,
    "error": "Amount must be a positive number"
}
```

### Ошибка сервера (500 Internal Server Error)

```json
{
    "success": false,
    "timestamp": "2025-08-05 11:30:45",
    "data": null,
    "error": "Failed to create payment in Altegio"
}
```

## 🔒 Коды ошибок

| HTTP код | Описание |
|----------|----------|
| 200 | Успешное создание платежа |
| 400 | Ошибка валидации данных |
| 405 | Неверный метод запроса (только POST) |
| 500 | Внутренняя ошибка сервера |

## 📋 Валидация

### client_id
- Должен быть положительным целым числом
- Должен существовать в системе Altegio

### amount
- Должен быть положительным числом
- Поддерживает десятичные значения

### payment_method
- Допустимые значения: `cash`, `card`, `online`

### currency
- Допустимые значения: `RUB`, `USD`, `EUR`

## 📊 Логирование

Все запросы логируются в файл `api_endpoint.log`:

```
[2025-08-05 11:30:45] API: Received request: {"client_id":172280244,"amount":1500}
[2025-08-05 11:30:45] API: Creating payment with data: {"client_id":172280244,"amount":1500}
[2025-08-05 11:30:46] API: Payment created successfully: {"id":525636603,"amount":-1500}
```

## 🧪 Тестирование

Откройте `test_api_client.html` в браузере для интерактивного тестирования API.

## 🔒 Безопасность

- Валидация всех входящих данных
- Логирование всех запросов
- Обработка ошибок
- CORS поддержка для веб-приложений

## 📞 Поддержка

При возникновении проблем:

1. Проверьте логи в `api_endpoint.log`
2. Убедитесь в правильности параметров
3. Проверьте доступность Altegio API
4. Убедитесь в правильности ID клиента 