# Altegio Payments Integration - Краткое описание

## 🎯 Что это

Система для создания платежей в Altegio через API. Поддерживает два режима работы:
1. **Автоматические платежи** через вебхуки
2. **API endpoint** для создания платежей по запросу

## 📁 Файлы проекта

### Основные компоненты
- `main.php` - обработчик вебхуков Altegio (16KB)
- `api_endpoint.php` - API endpoint для клиентов (5KB)
- `altegio_payments.php` - класс для работы с API Altegio (16KB)
- `config.php` - конфигурация с токенами и настройками (6KB)

### Тестирование
- `test_webhook.html` - тестовая страница для вебхуков (18KB)
- `test_api_client.html` - тестовая страница для API (10KB)
- `test_api_simple.php` - простой тест API (2KB)

### Документация
- `README.md` - основная документация (4KB)
- `PAYMENTS_GUIDE.md` - руководство по платежам (6KB)
- `API_DOCUMENTATION.md` - документация API (5KB)
- `PROJECT_SUMMARY.md` - краткое описание (2KB)

## ⚙️ Настройка

1. **Настройте токены** в `config.php`:
   ```php
   'user_token' => 'c1d3041f2185df70f5c341f0926adb44',
   'partner_token' => 'gbkp3f4ynkd5jpejjsxp',
   'company_id' => 729142,
   'account_id' => 2665932,
   ```

2. **Добавьте вебхук** в кабинете Altegio:
   - URL: `https://your-domain.com/main.php`
   - События: `record.create`, `visit.completed`

3. **Запустите сервер**:
   ```bash
   php -S localhost:8000
   ```

## 🚀 Как работает

### Режим 1: Автоматические платежи (вебхуки)
1. **Создание записи** в Altegio с услугой стоимостью > 0 RUB
2. **Вебхук отправляется** на `main.php`
3. **Система автоматически создает платеж** через API Altegio
4. **Платеж появляется** в кассе Altegio

### Режим 2: API endpoint
1. **Клиент отправляет POST запрос** на `api_endpoint.php`
2. **Наш код валидирует данные** и отправляет запрос в Altegio
3. **Altegio создает платеж** в своей системе
4. **Возвращается ответ** с ID платежа

## 📡 API Endpoint

```
POST /api_endpoint.php
```

**Обязательные параметры:**
- `client_id` - ID клиента в Altegio
- `amount` - сумма платежа

**Опциональные параметры:**
- `description` - описание платежа
- `payment_method` - способ оплаты (cash/card/online)
- `currency` - валюта (RUB/USD/EUR)
- `master_id` - ID мастера
- `comment` - комментарий

## ✅ Готово к использованию

Система полностью настроена и протестирована. Создано 4 успешных платежа:
- ID: 525636601 (1500 RUB) - через вебхуки
- ID: 525636602 (2000 RUB) - через вебхуки  
- ID: 525637218 (2000 RUB) - через API endpoint
- ID: 525637247 (2000 RUB) - через API endpoint

## 📊 Логи

Система создает логи в:
- `webhook_log.txt` - логи вебхуков
- `api_endpoint.log` - логи API запросов
- `payments_api.log` - логи API Altegio
- `notifications.txt` - уведомления

## 🔒 Безопасность

- Валидация всех входящих данных
- Проверка IP-адресов (для вебхуков)
- Логирование всех операций
- Обработка ошибок
- CORS поддержка для API

## 📞 Поддержка

При проблемах проверьте:
1. Логи в соответствующих файлах
2. Правильность токенов в `config.php`
3. Доступность API Altegio
4. Правильность ID кассы и компании

## 🧪 Тестирование

- **Вебхуки**: откройте `test_webhook.html`
- **API**: откройте `test_api_client.html` или запустите `test_api_simple.php` 