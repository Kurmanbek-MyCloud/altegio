# 💳 Платежная система с диплинками

Система для создания страниц оплаты с диплинками на платежные системы Кыргызстана.

## 🎯 Что это дает

- **Красивая страница оплаты** с информацией о платеже
- **Диплинки на платежные системы**: MBank, ODengi, Balance, Optima Bank
- **Автоматическая обработка** QR-кодов от Optima Bank
- **API endpoint** для интеграции с внешними системами
- **Вшивание данных** в ссылки (сумма, телефон, ID абонемента)

## 📋 Файлы системы

- **`payment_page.php`** - красивая страница оплаты с диплинками
- **`payment_api.php`** - API endpoint для обработки запросов
- **`test_payment_api.html`** - тестовая страница для API

## 🔄 Как это работает

### 1. **Получение данных**
- API получает запрос с данными о платеже
- Обрабатывает QR-коды от Optima Bank
- Извлекает необходимую информацию

### 2. **Генерация ссылок**
- Создает ссылку на страницу оплаты
- Генерирует диплинки для платежных систем
- Вшивает данные в ссылки

### 3. **Страница оплаты**
- Показывает информацию о платеже
- Отображает QR-код (если есть)
- Предоставляет диплинки на платежные системы

## 🚀 Быстрый старт

### 1. **Установка**
```bash
# Скопируйте файлы на сервер
cp payment_page.php /var/www/html/
cp payment_api.php /var/www/html/
cp test_payment_api.html /var/www/html/
```

### 2. **Настройка домена**
Замените `https://your-domain.com` на ваш реальный домен в файлах:
- `payment_api.php` (строка 200)
- `payment_page.php` (если нужно)

### 3. **Тестирование**
Откройте `test_payment_api.html` для тестирования API.

## 📡 API Endpoints

### **POST /payment_api.php**

#### Типы запросов:

##### 1. **Optima QR Request**
```json
{
    "type": "optima_qr",
    "qrUrl": "https://optimabank.kg/index.php?lang=ru#00020101021132650011QR.Optima.20103213101610918209361301831107176085812021113021233360032473798579d3144969cd3f75b299dadf65204999953034175907ZENTARA6304660E",
    "qrBase64": "iVBORw0KGgoAAAANSUhEUgAAASwAAAEsCAIAAAD2HxkiAABYZklEQVR4Xu29C7RnV13n+U89gPBwhBmHqvt+36pUEggEUBFaxMZWQFoQe0SQFkdbGdaawTHSjTi6uic2gt22jroQbKftaaebcogCM8ZlaxAQERISkoK8U5WkHvd9/+/3487e/5M62f/v3vt79u+c8783rnU/XklV/d77cfbj/h+FvUMOOeRAKeA/HHLIIfvL4SQ85JAD5nASHnLIAXM4CQ855IA5nISHHHLAHE7CQw45YIImYafTOXv27Dvf+c7l5eXnP//5BQlKX1kpW+VB+UHXlCeeeOL1r3/9a571LNPh0aNHU/9VuVIOlduQQFxZ1UWkopx9gTg8DY4zSV8aWQIBzri8cQLbyunZp8zZ/9GePAlvu+22xcVFjJYK5Ud5wwB+vvd7vxdd5IFyGx6IK3OpCNsVh6fBIUnarrIEAkhcEXYaxLOtTDiQ0c4mYb/fv+WWW9B3ZpRP5RmDuXjBC16Axnnw7Gc/OzyQEhFlW6qcG9YCbFccngYnx3pFkLgi7DSIZ7u7nRzgaGe.....",
    "amount": 2000,
    "phone": "+79001234567",
    "subscription_id": "12345",
    "client_id": "67890"
}
```

##### 2. **Subscription Payment**
```json
{
    "type": "subscription_payment",
    "amount": 1500,
    "phone": "+79001234567",
    "subscription_id": "12345",
    "client_id": "67890",
    "type": "renewal"
}
```

##### 3. **Custom Payment**
```json
{
    "type": "custom_payment",
    "amount": 1000,
    "phone": "+79001234567",
    "description": "Оплата услуг",
    "qr_code": "CUSTOM_QR_CODE"
}
```

## 💰 Поддерживаемые платежные системы

### **MBank**
- **URL**: `https://app.mbank.kg/qr/#{qrCode}`
- **Иконка**: 🏦
- **Цвет**: #1E3A8A

### **ODengi**
- **URL**: `https://api.dengi.o.kg/#{qrCode}`
- **Иконка**: 💰
- **Цвет**: #059669

### **Balance**
- **URL**: `https://balance.kg/payment_qr/#{qrCode}`
- **Иконка**: ⚖️
- **Цвет**: #DC2626

### **Optima Bank**
- **URL**: `https://optimabank.kg/index.php?lang=ru#{qrCode}`
- **Иконка**: 🏛️
- **Цвет**: #7C3AED

## 🔧 Обработка QR-кодов

### **Optima Bank**
- **Вход**: Полный URL с QR-кодом после #
- **Обработка**: Извлечение QR-кода из URL
- **Выход**: Только QR-код для вшивания в диплинки

### **Пример обработки:**
```
Вход:  https://optimabank.kg/index.php?lang=ru#00020101021132650011QR.Optima.20103213101610918209361301831107176085812021113021233360032473798579d3144969cd3f75b299dadf65204999953034175907ZENTARA6304660E

Выход: 00020101021132650011QR.Optima.20103213101610918209361301831107176085812021113021233360032473798579d3144969cd3f75b299dadf65204999953034175907ZENTARA6304660E
```

## 📱 Страница оплаты

### **Что отображается:**
1. **Заголовок** с типом операции
2. **Сумма** платежа
3. **Детали**: ID абонемента, ID клиента, телефон
4. **QR-код** (если предоставлен)
5. **Диплинки** на платежные системы

### **Адаптивный дизайн:**
- Работает на всех устройствах
- Красивый градиентный дизайн
- Анимации появления элементов
- Hover эффекты для кнопок

## 🧪 Тестирование

### **1. Локальное тестирование**
```bash
php -S localhost:8000
```

### **2. Тестовая страница**
Откройте `test_payment_api.html` для тестирования всех типов запросов.

### **3. Проверка API**
```bash
curl -X GET http://localhost:8000/payment_api.php
```

## 📊 Логирование

Система создает логи в:
- **`payment_api.log`** - логи API запросов
- **`payment_page.log`** - логи страницы оплаты (если добавить)

## 🔒 Безопасность

- **Валидация** входящих данных
- **Экранирование** HTML символов
- **Логирование** всех операций
- **Обработка ошибок**

## 📈 Примеры использования

### **1. Интеграция с Altegio**
```php
// Отправка уведомления клиенту
$paymentData = [
    'type' => 'subscription_payment',
    'amount' => 2000,
    'phone' => $clientPhone,
    'subscription_id' => $subscriptionId,
    'client_id' => $clientId,
    'type' => 'renewal'
];

$response = sendPaymentRequest($paymentData);
$paymentPageUrl = $response['data']['payment_page_url'];
```

### **2. Обработка QR-кода от Optima**
```php
$optimaData = [
    'type' => 'optima_qr',
    'qrUrl' => $qrUrl,
    'qrBase64' => $qrBase64,
    'amount' => $amount,
    'phone' => $phone
];

$response = sendPaymentRequest($optimaData);
$deeplinks = $response['data']['deeplinks'];
```

## 🆘 Устранение неполадок

### **Частые проблемы:**

1. **API не отвечает**
   - Проверьте права на файлы
   - Убедитесь в правильности PHP синтаксиса
   - Проверьте логи ошибок

2. **Страница не загружается**
   - Проверьте правильность URL
   - Убедитесь в доступности файлов
   - Проверьте консоль браузера

3. **Диплинки не работают**
   - Проверьте правильность QR-кодов
   - Убедитесь в доступности платежных систем
   - Проверьте формат ссылок

## 📞 Поддержка

При проблемах проверьте:
1. Логи в `payment_api.log`
2. Консоль браузера
3. Сетевые запросы
4. Правильность данных в запросах

## 🎉 Готово к использованию!

Система полностью настроена и готова к интеграции с вашими проектами. Все файлы созданы, API работает, страница оплаты красиво оформлена.

**Наслаждайтесь автоматизацией платежей!** 🚀
