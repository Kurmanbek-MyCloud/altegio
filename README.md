# Altegio Payments Integration

Интеграция для автоматического создания платежей в Altegio через API при получении вебхуков.

## 🎯 Описание

Система автоматически создает платежи в Altegio при создании записей с ненулевой стоимостью услуг. Работает через вебхуки и API Altegio.

## 📁 Структура проекта

```
altegio/
├── main.php              # Обработчик вебхуков Altegio
├── altegio_payments.php  # Класс для работы с API Altegio
├── config.php            # Конфигурация системы
├── test_webhook.html     # Тестовая страница для вебхуков
├── README.md             # Документация
└── PAYMENTS_GUIDE.md     # Руководство по платежам
```

## ⚙️ Настройка

### 1. Конфигурация

Отредактируйте `config.php`:

```php
'api' => [
    'user_token' => 'c1d3041f2185df70f5c341f0926adb44', // User token из кабинета
    'partner_token' => 'gbkp3f4ynkd5jpejjsxp', // Токен партнера из кабинета
    'company_id' => 729142, // ID компании
    'account_id' => 2665932, // ID кассы
],
```

### 2. Настройка вебхуков в Altegio

1. Зайдите в кабинет приложения Altegio
2. Добавьте URL вебхука: `https://your-domain.com/main.php`
3. Выберите события:
   - `record.create` - создание записи
   - `visit.completed` - завершение визита

### 3. Запуск сервера

```bash
php -S localhost:8000
```

### 4. Настройка ngrok (для локальной разработки)

```bash
# Установка ngrok
brew install ngrok

# Регистрация и настройка токена
ngrok config add-authtoken YOUR_TOKEN

# Запуск туннеля
ngrok http 8000
```

## 🚀 Как это работает

1. **Создание записи** в Altegio с услугой стоимостью > 0 RUB
2. **Вебхук отправляется** на `main.php`
3. **Система автоматически создает платеж** через API Altegio
4. **Платеж появляется** в кассе Altegio

## 📋 Поддерживаемые события

- `record.create` - создание записи
- `visit.completed` - завершение визита
- `transaction.created` - создание транзакции
- `transaction.updated` - обновление транзакции
- `payment.received` - получение платежа
- `payment.failed` - неудачный платеж

## 🔧 API Endpoints

- `POST /finance_transactions/{company_id}` - создание финансовой транзакции
- `GET /finance_transactions/{id}` - получение информации о транзакции
- `PUT /finance_transactions/{id}` - обновление транзакции

## 📊 Логирование

Система ведет логи в следующих файлах:
- `webhook_log.txt` - логи вебхуков
- `payments_api.log` - логи API запросов
- `notifications.txt` - уведомления о платежах

## 🧪 Тестирование

1. Откройте `test_webhook.html` в браузере
2. Создайте тестовый вебхук
3. Проверьте логи в `webhook_log.txt`

## 🔒 Безопасность

- Проверка IP-адресов (настраивается в `config.php`)
- Валидация данных вебхуков
- Логирование всех операций
- Обработка ошибок

## 📞 Поддержка

При возникновении проблем:

1. Проверьте логи в `webhook_log.txt`
2. Убедитесь в правильности токенов в `config.php`
3. Проверьте доступность API Altegio
4. Убедитесь в правильности ID кассы и компании

## 📝 Лицензия

MIT License 