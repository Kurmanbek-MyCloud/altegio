<?php
/**
 * ДЕМО: симулятор Optima Bank.
 * Отправляет настоящий HTTP-колбэк на optima_callback.php в формате Optima, с Basic Auth из конфига.
 * Работает только при demo.enabled = true и после входа в админку.
 */
declare(strict_types=1);

require __DIR__ . '/auth.php';

if (!admin_user()) {
    header('Location: ./');
    exit;
}
if (!app_demo()) {
    http_response_code(404);
    exit('Демо-режим выключен');
}

$cfg = app_config();
$pending = array_values(array_filter((new Invoices(app_db()))->recent(50), fn($i) => $i['status'] === 'pending'));
$base = rtrim($cfg['app']['base_url'], '/');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Симулятор Optima Bank</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=2">
</head>
<body>
<header class="topbar"><div class="in">
  <a class="brand" href="./"><?= h($cfg['app']['name']) ?><span class="tag">Демо</span></a>
  <nav class="nav">
    <a href="./">Счета</a>
    <a href="demo_bank.php" class="active">Симулятор банка</a>
  </nav>
  <div class="user"><span><?= h(admin_user()) ?></span></div>
</div></header>
<main>
  <h1 class="page-title">Симулятор Optima Bank</h1>
  <div class="msg warn" style="margin:0 0 16px">Отправляет на наш сервер настоящий колбэк в формате Optima — как будто клиент оплатил QR. Деньги не списываются.</div>

  <section class="card">
    <h2>Колбэк от банка</h2>
    <?php if (!$pending): ?>
      <p class="hint">Нет неоплаченных счетов. Создайте ссылку в разделе «Счета» — счёт появится здесь.</p>
    <?php endif; ?>
    <label for="inv">Счёт</label>
    <select id="inv" style="margin-bottom:14px">
      <?php foreach ($pending as $i): ?>
        <option value="<?= (int)$i['id'] ?>" data-sum="<?= h($i['amount']) ?>" data-link="<?= h($base . '/payment_page.php?i=' . $i['token']) ?>">
          №<?= (int)$i['id'] ?> · <?= h($i['client_name']) ?> · <?= h($i['item_title']) ?> · <?= h(number_format((float)$i['amount'], 0, '.', ' ')) ?> сом
        </option>
      <?php endforeach; ?>
      <option value="">Другой номер — ввести вручную</option>
    </select>
    <div class="grid2">
      <div><label for="note">note (номер счёта)</label><input id="note"></div>
      <div><label for="sum">sum, сом</label><input id="sum" type="number" min="0.01" step="0.01"></div>
    </div>
    <p class="hint" style="margin-top:10px">Поменяйте сумму — проверите «сумма не совпала». Несуществующий номер — «счёт не найден».</p>
    <div class="actions">
      <button class="btn wa" id="paid">Оплата прошла</button>
      <button class="btn secondary" id="failed">Отклонено банком</button>
      <button class="btn secondary" id="repeat" disabled title="Тот же transactionId — проверка защиты от дублей">Повторить последний</button>
      <a class="btn secondary" id="open" target="_blank" rel="noopener">Страница оплаты ↗</a>
    </div>
  </section>

  <section class="card">
    <h2>Журнал</h2>
    <div id="log"><p class="hint" style="margin:0">Здесь появятся запросы «банка» и ответы нашего сервера.</p></div>
  </section>
</main>

<script>
const AUTH = 'Basic ' + btoa(<?= json_encode($cfg['optima']['callback_username'] . ':' . $cfg['optima']['callback_password']) ?>);
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let last = null;

function syncInvoice() {
  const o = $('inv').selectedOptions[0];
  $('note').value = o.value;
  $('sum').value = o.dataset.sum ? Number(o.dataset.sum) : '';
  $('open').style.display = o.dataset.link ? '' : 'none';
  $('open').href = o.dataset.link || '#';
}
$('inv').addEventListener('change', syncInvoice);
syncInvoice();

async function send(payload) {
  const btns = document.querySelectorAll('.actions button');
  btns.forEach(b => b.disabled = true);
  let status = 0, body = '';
  try {
    const res = await fetch('../optima_callback.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': AUTH },
      body: JSON.stringify(payload)
    });
    status = res.status;
    body = await res.text();
    try { body = JSON.stringify(JSON.parse(body), null, 2); } catch {}
  } catch (e) { body = 'Ошибка сети: ' + e.message; }
  btns.forEach(b => b.disabled = false);
  $('repeat').disabled = !last;

  if ($('log').querySelector('.hint')) $('log').innerHTML = '';
  const good = status === 200 && /"result": "(paid|failed)"/.test(body);
  $('log').insertAdjacentHTML('afterbegin', `
    <div class="log-item">
      <div class="log-head"><span class="pill ${good ? 'p-ok' : 'p-warn'}">HTTP ${status}</span><span class="sub" style="margin:0">${new Date().toLocaleTimeString('ru-RU')} · ${esc(payload.status)} · счёт ${esc(payload.note || '—')} · ${esc(payload.sum)} сом</span></div>
      <label>Банк → наш сервер</label><pre>${esc(JSON.stringify(payload, null, 2))}</pre>
      <label>Ответ</label><pre>${esc(body)}</pre>
    </div>`);
}

function build(ok) {
  const now = Date.now();
  return {
    status: ok ? 'success' : 'failed',
    sum: Number($('sum').value),
    note: $('note').value.trim(),
    transactionId: 'DEMO_' + now,
    transactionProcessedDateTime: new Date().toISOString().replace(/\.\d{3}Z$/, 'Z'),
    orderId: 'ORDER_' + now,
    currency: 'KGS',
    paymentMethod: 'qr',
    responseCode: ok ? '00' : '51',
    responseMessage: ok ? 'Approved' : 'Insufficient funds',
    ...(ok ? {} : { errorCode: '51', errorMessage: 'Недостаточно средств' })
  };
}

$('paid').addEventListener('click', () => { last = build(true); send(last); });
$('failed').addEventListener('click', () => { last = build(false); send(last); });
$('repeat').addEventListener('click', () => last && send(last));
</script>
</body>
</html>
