<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

$loginError = null;
if (($_POST['do'] ?? '') === 'logout' && csrf_check($_POST['csrf'] ?? null)) {
    admin_logout();
    header('Location: ./');
    exit;
}
if (($_POST['do'] ?? '') === 'login') {
    if (admin_login(trim((string)($_POST['user'] ?? '')), (string)($_POST['password'] ?? ''))) {
        header('Location: ./');
        exit;
    }
    app_log('admin', 'Неудачный вход', ['user' => $_POST['user'] ?? '', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    usleep(500000);
    $loginError = 'Неверный логин или пароль';
}
$user = admin_user();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= h(app_config()['app']['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=2">
</head>
<body>
<?php if (!$user): ?>
  <div class="login-wrap"><div class="login card">
    <h1><?= h(app_config()['app']['name']) ?></h1>
    <p class="hint">Войдите, чтобы создавать ссылки на оплату</p>
    <form method="post" autocomplete="on">
      <input type="hidden" name="do" value="login">
      <label>Логин</label><input name="user" required autofocus autocomplete="username">
      <label>Пароль</label><input name="password" type="password" required autocomplete="current-password">
      <button class="btn primary" type="submit">Войти</button>
      <?php if ($loginError): ?><div class="msg err"><?= h($loginError) ?></div><?php endif; ?>
      <?php if (!app_config()['admin']['users']): ?>
        <div class="msg err">Нет ни одного пользователя: добавьте admin.users в config.local.php</div>
      <?php endif; ?>
    </form>
  </div></div>
<?php else: ?>
<header class="topbar"><div class="in">
  <a class="brand" href="./"><?= h(app_config()['app']['name']) ?><?php if (app_demo()): ?><span class="tag">Демо</span><?php endif; ?></a>
  <nav class="nav">
    <a href="./" class="active">Счета</a>
    <?php if (app_demo()): ?><a href="demo_bank.php">Симулятор банка</a><?php endif; ?>
  </nav>
  <div class="user"><span><?= h($user) ?></span>
    <form method="post"><input type="hidden" name="do" value="logout"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <button type="submit" class="linklike">Выйти</button></form>
  </div>
</div></header>
<main>
  <section class="card">
    <h2>Новая ссылка на оплату</h2>
    <form id="findForm" class="row">
      <div class="grow"><label for="phone">Телефон клиента</label><input id="phone" inputmode="tel" placeholder="0555 123 456" required></div>
      <button class="btn primary" id="findBtn">Найти</button>
    </form>
    <div id="findMsg"></div>

    <div id="pick" hidden>
      <div class="client" id="clientInfo"></div>
      <div class="abons" id="abons"></div>
      <form id="invoiceForm" class="row" style="margin-top:14px">
        <div class="grow"><label for="amount">Сумма к оплате, сом</label><input id="amount" type="number" min="1" step="1" required></div>
        <button class="btn primary" id="createBtn" disabled>Создать ссылку</button>
      </form>
      <div id="createMsg"></div>
    </div>

    <div class="result" id="result" hidden>
      <b>Счёт №<span id="rId"></span> создан</b> <span class="sub" style="display:inline">· ссылка действует, пока счёт не оплачен или не отменён</span>
      <code id="rLink"></code>
      <div class="actions">
        <a class="btn wa" id="rWa" target="_blank" rel="noopener">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 1.9s.8 2.2.9 2.4c.1.2 1.6 2.5 4 3.5 1.5.6 2 .7 2.8.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2l-.5-.2z"/></svg>
          Отправить в WhatsApp</a>
        <button class="btn secondary" id="rCopyLink" type="button">Скопировать ссылку</button>
        <button class="btn secondary" id="rCopy" type="button">Скопировать сообщение</button>
        <a class="btn secondary" id="rOpen" target="_blank" rel="noopener">Открыть</a>
      </div>
    </div>
  </section>

  <section class="card" id="problemsCard" hidden>
    <h2>Требуют внимания</h2>
    <p class="hint">Деньги поступили, но автоматически провести их нельзя — разберите вручную.</p>
    <div class="scroll"><table class="tbl"><thead><tr><th>Когда</th><th>Транзакция</th><th>Note</th><th class="num">Сумма</th><th>Проблема</th></tr></thead>
    <tbody id="problems"></tbody></table></div>
  </section>

  <section class="card">
    <div class="card-head">
      <h2>Последние счета</h2>
      <button class="btn secondary small" id="reload" type="button">Обновить</button>
    </div>
    <div class="scroll"><table class="tbl">
      <thead><tr><th>№</th><th>Клиент</th><th class="hide-m">Что оплачивают</th><th class="num">Сумма</th><th>Оплата</th><th>Altegio</th><th></th></tr></thead>
      <tbody id="invoices"><tr><td colspan="7" class="sub">Загрузка…</td></tr></tbody>
    </table></div>
  </section>
</main>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = (n) => Number(n).toLocaleString('ru-RU', {maximumFractionDigits: 2}) + ' сом';
let state = { phone: '', documentId: null };

async function api(action, data = {}) {
  const res = await fetch('api.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': CSRF},
    body: JSON.stringify({action, ...data})
  });
  let body = {};
  try { body = await res.json(); } catch {}
  if (res.status === 401) location.reload();
  if (!res.ok) throw new Error(body.error || ('Ошибка ' + res.status));
  return body;
}
function showMsg(el, text, kind = 'err') { $(el).innerHTML = text ? `<div class="msg ${kind}">${esc(text)}</div>` : ''; }

$('findForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  showMsg('findMsg', ''); $('pick').hidden = true; $('result').hidden = true;
  $('findBtn').disabled = true;
  try {
    const r = await api('payables', {phone: $('phone').value});
    state = { phone: $('phone').value, documentId: null };
    $('clientInfo').innerHTML = `Клиент: <b>${esc(r.client.name || 'без имени')}</b> · ${esc(r.client.phone)}`;
    $('createBtn').disabled = true;
    if (!r.items.length) {
      $('abons').innerHTML = '<div class="msg err">У клиента нет неоплаченных записей или абонементов. Оформите продажу в Altegio (без оплаты) и повторите поиск.</div>';
    } else {
      $('abons').innerHTML = r.items.map(a => `
        <label class="abon" data-id="${a.document_id}" data-remaining="${a.remaining}">
          <input type="radio" name="abon" value="${a.document_id}">
          <div><div class="t">${esc(a.title)}${a.number ? ' · №' + esc(a.number) : ''}</div>
            <div class="m">${[a.kind === 'membership' ? 'Абонемент' : 'Запись', a.date ? a.date.replace('T', ' ') : '', 'к оплате ' + money(a.remaining)]
              .map(esc).filter(Boolean).join(' · ')}</div></div>
        </label>`).join('');
    }
    $('amount').value = '';
    $('pick').hidden = false;
  } catch (err) { showMsg('findMsg', err.message); }
  finally { $('findBtn').disabled = false; }
});

$('abons').addEventListener('change', (e) => {
  if (e.target.name !== 'abon') return;
  document.querySelectorAll('.abon').forEach(el => el.classList.toggle('sel', el.dataset.id === e.target.value));
  const el = e.target.closest('.abon');
  state.documentId = Number(e.target.value);
  $('amount').value = Number(el.dataset.remaining);
  $('amount').max = Number(el.dataset.remaining);
  $('createBtn').disabled = false;
});

$('invoiceForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  showMsg('createMsg', '');
  if (!state.documentId) return showMsg('createMsg', 'Выберите, что оплачивать');
  $('createBtn').disabled = true;
  try {
    const r = await api('create_invoice', {phone: state.phone, document_id: state.documentId, amount: Number($('amount').value)});
    $('rId').textContent = r.invoice_id;
    $('rLink').textContent = r.link;
    $('rWa').href = r.whatsapp;
    $('rOpen').href = r.link;
    const copy = (btn, text) => async () => {
      const label = btn.textContent;
      try { await navigator.clipboard.writeText(text); btn.textContent = 'Скопировано'; }
      catch { prompt('Скопируйте:', text); return; }
      setTimeout(() => btn.textContent = label, 2000);
    };
    $('rCopyLink').onclick = copy($('rCopyLink'), r.link);
    $('rCopy').onclick = copy($('rCopy'), r.message);
    $('pick').hidden = true; $('result').hidden = false;
    loadInvoices();
  } catch (err) { showMsg('createMsg', err.message); }
  finally { $('createBtn').disabled = false; }
});

const PAY = {
  pending: ['Ожидает', 'p-mute'], paid: ['Оплачен', 'p-ok'],
  amount_mismatch: ['Сумма не совпала', 'p-warn'], cancelled: ['Отменён', 'p-mute']
};
const ALT = { none: ['—', ''], ok: ['Проведён', 'p-ok'], error: ['Ошибка', 'p-err'] };
const PROBLEM = {
  invoice_not_found: 'Счёт не найден', duplicate_payment: 'Повторная оплата', invoice_cancelled: 'Оплата отменённого счёта',
  amount_mismatch: 'Сумма не совпала', paid_altegio_error: 'Не проведён в Altegio', processing: 'Обработка прервалась'
};
const pill = ([t, c]) => c ? `<span class="pill ${c}">${esc(t)}</span>` : esc(t);

async function loadInvoices() {
  try {
    const r = await api('invoices');
    $('invoices').innerHTML = r.invoices.length ? r.invoices.map(i => `
      <tr>
        <td>${i.id}<div class="sub">${esc(i.created_at.slice(5,16))}</div></td>
        <td>${esc(i.client_name || '')}<div class="sub">${esc(i.phone)}</div></td>
        <td class="hide-m">${esc(i.item_title || '')}<div class="sub">${i.item_type === 'membership' ? 'Абонемент' : 'Запись'}${i.item_number ? ' №' + esc(i.item_number) : ''}</div></td>
        <td class="num">${money(i.amount)}${i.paid_amount && Number(i.paid_amount) !== Number(i.amount) ? '<div class="sub">пришло ' + money(i.paid_amount) + '</div>' : ''}</td>
        <td>${pill(PAY[i.status] || [i.status, 'p-mute'])}${i.paid_at ? '<div class="sub">' + esc(i.paid_at.slice(5,16)) + '</div>' : ''}</td>
        <td>${pill(ALT[i.altegio_status] || [i.altegio_status, 'p-mute'])}${i.altegio_error ? '<div class="sub" title="' + esc(i.altegio_error) + '">' + esc(i.altegio_error.slice(0, 60)) + '</div>' : ''}</td>
        <td>${i.status === 'paid' && i.altegio_status === 'error' ? `<button class="btn secondary small" data-retry="${i.id}">Повторить</button>` : ''}
            ${i.status === 'pending' ? `<button class="btn secondary small" data-cancel="${i.id}">Отменить</button>` : ''}</td>
      </tr>`).join('') : '<tr><td colspan="7" class="sub">Счетов пока нет</td></tr>';

    $('problemsCard').hidden = !r.problems.length;
    $('problems').innerHTML = r.problems.map(p => `
      <tr><td>${esc(p.received_at.slice(5,16))}</td><td>${esc(p.transaction_id)}</td><td>${esc(p.note)}</td>
      <td class="num">${money(p.sum)}</td><td>${pill([PROBLEM[p.result] || p.result, 'p-warn'])}</td></tr>`).join('');
  } catch (err) {
    $('invoices').innerHTML = `<tr><td colspan="7"><div class="msg err">${esc(err.message)}</div></td></tr>`;
  }
}

$('invoices').addEventListener('click', async (e) => {
  const retry = e.target.dataset.retry, cancel = e.target.dataset.cancel;
  if (!retry && !cancel) return;
  if (cancel && !confirm('Отменить счёт №' + cancel + '?')) return;
  e.target.disabled = true;
  try {
    await api(retry ? 'retry_altegio' : 'cancel', {invoice_id: Number(retry || cancel)});
  } catch (err) { alert(err.message); }
  loadInvoices();
});
$('reload').addEventListener('click', loadInvoices);
loadInvoices();
setInterval(loadInvoices, 15000);
</script>
<?php endif; ?>
</body>
</html>
