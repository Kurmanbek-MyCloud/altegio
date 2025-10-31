# 🧪 Тест API Altegio - Получение списка клиентов

Изолированная папка для тестирования API запроса получения списка клиентов из Altegio.

## 📁 Файлы

- `test_clients_api.php` - Основной PHP скрипт для тестирования API
- `curl_example.sh` - Примеры cURL запросов
- `clients_api.log` - Лог файл (создается автоматически)
- `README.md` - Этот файл

## 🚀 Запуск тестов

### PHP тест
```bash
php test_clients_api.php
```

### cURL тесты
```bash
chmod +x curl_example.sh
./curl_example.sh
```

## 📋 API Endpoint

**POST** `/company/{company_id}/clients/search`

### Параметры запроса

| Параметр | Тип | Описание | По умолчанию |
|----------|-----|----------|--------------|
| `page` | number | Номер страницы | `1` |
| `page_size` | number | Размер страницы (макс. 200) | `25` |
| `order_by` | string | Поле для сортировки | `"id"` |
| `order_by_direction` | string | Направление сортировки | `"DESC"` |
| `operation` | string | Логическая операция | `"AND"` |
| `fields` | array | Выбираемые поля | Все поля |
| `filters` | array | Фильтры поиска | - |

### Доступные поля для сортировки

- `id` - ID клиента
- `name` - Имя клиента
- `phone` - Телефон
- `email` - Email
- `discount` - Скидка
- `first_visit_date` - Дата первого визита
- `last_visit_date` - Дата последнего визита
- `sold_amount` - Сумма покупок
- `visit_count` - Количество визитов

### Доступные операции фильтрации

- `=` - Равно
- `!=` - Не равно
- `>` - Больше
- `>=` - Больше или равно
- `<` - Меньше
- `<=` - Меньше или равно
- `LIKE` - Поиск по подстроке
- `IN` - Входит в список
- `NOT_IN` - Не входит в список

## 🔧 Примеры использования

### 1. Простой запрос
```php
$result = getClientsList();
```

### 2. Поиск по имени
```php
$result = getClientsList([
    'filters' => [
        [
            'field' => 'name',
            'operation' => 'LIKE',
            'value' => 'Иван'
        ]
    ]
]);
```

### 3. Сортировка по активности
```php
$result = getClientsList([
    'order_by' => 'last_visit_date',
    'order_by_direction' => 'DESC',
    'page_size' => 10
]);
```

### 4. Пагинация
```php
$result = getClientsList([
    'page' => 2,
    'page_size' => 50
]);
```

### 5. Выборка полей
```php
$result = getClientsList([
    'fields' => ['id', 'name', 'phone', 'email']
]);
```

### 6. Множественные фильтры
```php
$result = getClientsList([
    'filters' => [
        [
            'field' => 'visit_count',
            'operation' => '>=',
            'value' => 1
        ],
        [
            'field' => 'sold_amount',
            'operation' => '>',
            'value' => 0
        ]
    ],
    'operation' => 'AND'
]);
```

## 📊 Структура ответа

```json
{
    "data": [
        {
            "id": 172280244,
            "name": "Иван Иванов",
            "phone": "+79001234567",
            "email": "ivan@example.com",
            "discount": 0,
            "first_visit_date": "2024-01-15",
            "last_visit_date": "2025-08-01",
            "sold_amount": 15000,
            "visit_count": 5
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 10,
        "per_page": 25,
        "total": 250
    }
}
```

## 🔐 Авторизация

Используется комбинированная авторизация:
- **Partner Token**: `gbkp3f4ynkd5jpejjsxp`
- **User Token**: `c1d3041f2185df70f5c341f0926adb44`

Заголовок: `Authorization: Bearer partner_token, User user_token`

## 📝 Логирование

Все запросы и ответы записываются в файл `clients_api_test.log` с временными метками.

## ⚠️ Важные замечания

1. **Метод**: Используется `POST`, а не `GET`
2. **Accept Header**: Обязательно `application/vnd.api.v2+json`
3. **Размер страницы**: Максимум 200 записей
4. **Фильтры**: Поддерживают сложные условия
5. **Сортировка**: По любому полю в любом направлении

## 🧹 Очистка

Для удаления лог файла:
```bash
rm clients_api.log
``` 