# Оплата абонементов: Altegio + Optima Bank QR

Администратор создаёт ссылку на оплату для клиента, клиент платит по QR через приложение любого банка КР, а оплата сама проводится в Altegio.

```
Админка (admin/)                  Клиент                       Optima Bank
──────────────────                ──────                       ───────────
телефон клиента
  → Altegio: неоплаченные записи
    и абонементы клиента
  → выбрать, указать сумму
  → счёт №10001 в БД
  → QR в Optima (note = 10001) ─────────────────────────────▶  QR-код
  → ссылка в WhatsApp ──────────▶ payment_page.php?i=<token>
                                   QR + кнопки банков
                                   «Ожидаем оплату…»  ──────▶  оплата
                                                               │
optima_callback.php  ◀──────────────────────────────────────── колбэк (note=10001, sum)
  → счёт 10001 найден, сумма совпала
  → Altegio: оплата документа продажи на кассу
    (запись / абонемент становятся оплаченными)
                                   «Оплата получена» ✓
```

## Структура

| Путь | Что это |
|---|---|
| `admin/index.php` | Админка: поиск клиента, создание ссылки, список счетов, «Требуют внимания» |
| `admin/api.php` | JSON API админки (вход + CSRF) |
| `admin/demo_bank.php` | Симулятор колбэков Optima (только при `demo.enabled`) |
| `altegio_tab.php` | Вкладка «Оплата по ссылке» прямо в окне записи Altegio (iframe) |
| `payment_page.php` | Страница оплаты для клиента (`?i=<token>`) |
| `payment_status.php` | Статус счёта для автообновления страницы оплаты |
| `optima_callback.php` | Приём колбэков Optima Bank (Basic Auth) |
| `lib/Altegio.php` | Клиент API Altegio: поиск клиента, неоплаченные документы, оплата документа |
| `lib/Optima.php` | Генерация QR в Optima (+ `DemoOptima` для демо-режима) |
| `lib/Invoices.php` | Счета и журнал колбэков |
| `lib/PaymentLinks.php` | Создание ссылки на оплату (общее для админки и вкладки) |
| `schema.sql` | Таблицы MySQL (`schema.sqlite.sql` — то же для локальной разработки) |
| `tests/` | Автотесты |
| `config.php` | Настройки по умолчанию; секреты — в `config.local.php` (не в git) |

## Локальный запуск

```bash
cp config.local.example.php config.local.php   # заполнить
mkdir -p data
sqlite3 data/billing.sqlite < schema.sqlite.sql
sqlite3 data/billing.sqlite "INSERT INTO sqlite_sequence(name, seq) VALUES ('invoices', 10000);"
php -S localhost:8000
```

В `config.local.php` для локальной работы:

```php
'db' => ['dsn' => 'sqlite:' . __DIR__ . '/data/billing.sqlite', 'username' => '', 'password' => ''],
'demo' => ['enabled' => true],  // пока банк не подключён
```

Админка: http://localhost:8000/admin/

Чтобы открыть страницу оплаты с телефона в той же Wi-Fi сети, запустите `php -S 0.0.0.0:8000` и укажите в `app.base_url` IP компьютера, например `http://192.168.0.108:8000`.

### Токены Altegio

- **Partner token**: Altegio Marketplace → Developer account → Account settings.
- **User token**: `POST https://api.alteg.io/api/v1/auth` с логином и паролем сотрудника и заголовком `Authorization: Bearer <partner_token>`. **После смены пароля токен нужно получить заново.**

### Демо-режим

`demo.enabled = true`:
- QR создаётся локально, без запроса в Optima, и оплатить его нельзя;
- в админке появляется «Симулятор банка». Он отправляет настоящий колбэк в формате Optima, так что проверяется тот же путь, что и с банком.

## Вкладка в окне записи Altegio

Администратор создаёт ссылку прямо в записи: **окно записи → «Оплата по ссылке»** → сумма к оплате → «Создать ссылку» → «Отправить в WhatsApp». Статус оплаты обновляется во вкладке сам.

Подключение (кабинет разработчика Altegio → приложение → **Добавление iframe**):
- «Раздел о визите в окне записи», название «Оплата по ссылке»;
- ссылка: `https://<домен>/altegio_tab.php`;
- приложение должно быть установлено в локацию. **После смены ссылки iframe переустановите приложение**: Altegio запоминает адрес при установке.

Как это устроено:
- Altegio открывает `altegio_tab.php?salon_id=…&hash=…&entity_type=visit&entity_id=<ID записи>&user_id=…`;
- `hash = sha1(salon_id + partner_token)` — проверка, что вкладку открыл Altegio;
- страница должна отправить родителю `postMessage({type: 'iframe_ready'})`, иначе Altegio показывает пустую вкладку размером 0×0. По `{type: 'iframe_request_entity'}` Altegio отвечает `{type: 'yclients_response_entity', payload: {entity_type, entity}}`;
- сумма, клиент и документ к оплате берутся из API Altegio, а не из браузера.

Для локального теста вкладке нужен https: `cloudflared tunnel --url http://localhost:8000` (ngrok на бесплатном тарифе не подходит, его страница-предупреждение не открывается в iframe).

## Тесты

```bash
php tests/run.php
```

Поднимают приложение и заглушку Altegio/Optima на портах 18081–18082, используют временную SQLite и прогоняют 64 проверки: вход и CSRF, поиск, создание счёта, страницу оплаты, все варианты колбэков (успех, отказ, повтор, двойная оплата, несовпадение суммы, отменённый счёт, ошибка Altegio с повтором, восстановление после сбоя, вкладка в окне записи). Настоящие API не вызываются.

## Запуск на сервере

См. [DEPLOY.md](DEPLOY.md).
