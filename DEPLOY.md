# Оплата абонементов: запуск на сервере

Цепочка: **админка** (`admin/`) → администратор выбирает абонемент клиента из Altegio → **счёт** в MySQL → QR в **Optima** (`note` = номер счёта) → ссылка клиенту (WhatsApp) → **страница оплаты** (`payment_page.php?i=<token>`) → банк присылает **колбэк** (`optima_callback.php`) → оплата **в документ продажи Altegio** (запись или абонемент становятся оплаченными).

## 1. Файлы на сервер

Нужны:

```
admin/  lib/  vendor/  image_pay_system/
payment_page.php  payment_status.php  optima_callback.php
config.php  config.local.php  .htaccess  composer.json  composer.lock
```

Не нужны на сервере: `tests/`, `data/`, `schema.sqlite.sql`, `config.local.example.php`, `*.md`.

Перед выкладкой прогоните тесты: `php tests/run.php`.

## 2. База данных

```sql
CREATE DATABASE billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'billing'@'localhost' IDENTIFIED BY '<пароль>';
GRANT SELECT, INSERT, UPDATE ON billing.* TO 'billing'@'localhost';
```

```bash
mysql -u root -p billing < schema.sql
```

## 3. config.local.php

Скопировать `config.local.example.php` в `config.local.php` и заполнить:

| Параметр | Откуда |
|---|---|
| `app.base_url`, `app.name`, `app.logo` | адрес сайта, название в админке, логотип на странице оплаты |
| `db.*` | шаг 2 |
| `altegio.company_id` | ID боевой компании |
| `altegio.account_id` | касса, куда падают оплаты Optima |
| `altegio.partner_token`, `user_token` | кабинет разработчика Altegio. Пользователь токена должен иметь доступ к боевой компании |
| `optima.api_key` | **новый** ключ: старый засвечен в git, его нужно перевыпустить |
| `optima.account` | счёт получателя в Optima |
| `optima.callback_username/password` | придумать (длинный случайный пароль) и передать банку |
| `admin.users` | `php -r 'echo password_hash("пароль", PASSWORD_DEFAULT), "\n";'` |

**`demo.enabled` на боевом сервере должен быть `false`** (это значение по умолчанию). В демо-режиме QR не настоящий, а в админке есть симулятор банка (`admin/demo_bank.php`), который может «оплатить» любой счёт.

Права: `chmod 640 config.local.php`. Папка `logs/` создаётся сама, у PHP должно быть право записи.

## 4. Веб-сервер

- Только HTTPS.
- **Apache**: правила в `.htaccess` закрывают `lib/`, `logs/`, `data/`, `vendor/`, конфиги, `*.log`, `*.md`.
- **nginx**: добавить вручную:
  ```nginx
  location ~ ^/(lib|logs|data|tests|vendor|\.git)/ { deny all; }
  location ~ (config.*\.php|schema\.sql|\.log|\.md)$ { deny all; }
  ```

## 5. Передать в Optima

- URL колбэка: `https://<домен>/optima_callback.php`
- Метод `POST`, `Content-Type: application/json`, Basic Auth (логин и пароль из шага 3)
- В `note` приходит номер нашего счёта (5 цифр, начиная с 10001)

Ответы: `200` — принято (повтор с тем же `transactionId` безопасен); `401` — неверная авторизация; `400` — неверные данные; `5xx` — временная ошибка, банк должен повторить (если обработка прервалась, повтор её доделает).

## 6. Проверка после запуска

1. Войти в `https://<домен>/admin/`, найти тестового клиента, выбрать запись или абонемент, создать счёт на 1 сом.
2. Оплатить QR с телефона.
3. Страница оплаты должна показать «Оплата получена», в админке счёт станет «Оплачен» и «Проведён», а в Altegio запись или абонемент станут оплаченными.

## Что происходит в нестандартных случаях

| Случай | Что делает система |
|---|---|
| Сумма не совпала со счётом | Счёт получает статус «Сумма не совпала», в Altegio **не проводится**, попадает в блок «Требуют внимания» |
| Второй платёж по оплаченному счёту | «Повторная оплата», в Altegio не проводится, нужен разбор |
| Оплачен отменённый счёт | «Оплата отменённого счёта», нужен разбор |
| Номер счёта не найден | «Счёт не найден», нужен разбор |
| Altegio не принял платёж | Счёт «Оплачен», в колонке Altegio «Ошибка», в админке есть кнопка «Повторить» |
| Банк отклонил оплату | Счёт остаётся «Ожидает», клиент может заплатить снова |
| Обработка колбэка прервалась (сбой БД/сети) | Повтор колбэка от банка доделает обработку; если повтора нет — через 5 минут попадает в «Требуют внимания» |

Логи: `logs/callback.log`, `logs/altegio.log`, `logs/optima.log`, `logs/admin.log`.
