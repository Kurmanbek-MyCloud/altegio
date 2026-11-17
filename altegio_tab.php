<?php
/**
 * Вкладка «Оплата по ссылке» в окне записи Altegio
 * (Маркетплейс → Добавление iframe → «Раздел о визите в окне записи»).
 *
 * Altegio открывает: ?salon_id=…&hash=…&entity_type=visit&entity_id=<ID записи>&user_id=…
 *   hash = sha1(salon_id . partner_token) — проверяем, что вкладку открыл Altegio.
 * Протокол iframe: страница шлёт родителю {type:'iframe_ready'}, иначе Altegio не показывает вкладку.
 *
 * Сумма, клиент и документ к оплате берутся только из API Altegio, а не из браузера.
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

header('Content-Security-Policy: frame-ancestors https://app.alteg.io');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$cfg = app_config();

/** Проверка, что запрос пришёл из Altegio для нашей локации */
function tab_auth(array $p, array $cfg): ?string {
    $salon = (string)($p['salon_id'] ?? '');
    $hash = (string)($p['hash'] ?? '');
    if ($salon === '' || $salon !== (string)$cfg['altegio']['company_id']) {
        return 'Вкладка подключена к другой локации';
    }
    if (!hash_equals(sha1($salon . $cfg['altegio']['partner_token']), $hash)) {
        return 'Неверная подпись Altegio';
    }
    if (($p['entity_type'] ?? '') !== 'visit' || !ctype_digit((string)($p['entity_id'] ?? ''))) {
        return 'Не указана запись';
    }
    return null;
}

// ---------- POST: создать ссылку / отменить ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    if ($err = tab_auth($in, $cfg)) {
        json_response(403, ['error' => $err]);
    }
    $recordId = (int)$in['entity_id'];
    $by = 'altegio:' . (int)($in['user_id'] ?? 0);
    try {
        $data = app_altegio()->getRecordPayable($recordId, (int)$cfg['altegio']['account_id']);
        $item = $data['item'] ?? null;
        if (!$data || !$item) {
            json_response(404, ['error' => 'Запись не найдена в Altegio']);
        }
        if (($in['action'] ?? '') === 'create') {
            if ($item['remaining'] <= 0) {
                json_response(400, ['error' => 'Визит уже оплачен']);
            }
            if (normalize_phone($data['client']['phone']) === '') {
                json_response(400, ['error' => 'У клиента не указан телефон']);
            }
            $link = create_payment_link($data['client'], $item, (float)($in['amount'] ?? 0), $by);
            $link['qr'] = qr_data_uri($link['qr_url']);
            json_response(200, $link);
        }
        if (($in['action'] ?? '') === 'cancel') {
            $invoices = new Invoices(app_db());
            $inv = $invoices->find((int)($in['invoice_id'] ?? 0));
            if (!$inv || (int)$inv['document_id'] !== $item['document_id'] || !$invoices->transition((int)$inv['id'], 'pending', ['status' => 'cancelled'])) {
                json_response(400, ['error' => 'Отменить можно только неоплаченную ссылку этого визита']);
            }
            app_log('billing', 'Счёт отменён', ['invoice' => $inv['id'], 'by' => $by]);
            json_response(200, ['ok' => true]);
        }
        json_response(400, ['error' => 'Неизвестное действие']);
    } catch (PaymentLinkException $e) {
        json_response($e->httpCode, ['error' => $e->getMessage()]);
    } catch (Throwable $e) {
        app_log('tab', 'Ошибка', ['record' => $recordId, 'error' => $e->getMessage()]);
        json_response(502, ['error' => 'Не удалось связаться с Altegio, попробуйте ещё раз']);
    }
}

// ---------- GET: вкладка ----------
$error = tab_auth($_GET, $cfg);
$data = null;
$invoices = [];
if (!$error) {
    try {
        $data = app_altegio()->getRecordPayable((int)$_GET['entity_id'], (int)$cfg['altegio']['account_id']);
        if (!$data) {
            $error = 'Запись не найдена в Altegio';
        } elseif ($data['item']) {
            $invoices = (new Invoices(app_db()))->byDocument($data['item']['document_id']);
        }
    } catch (Throwable $e) {
        app_log('tab', 'Ошибка', ['record' => $_GET['entity_id'] ?? null, 'error' => $e->getMessage()]);
        $error = 'Не удалось получить данные из Altegio';
    }
}

$item = $data['item'] ?? null;
$client = $data['client'] ?? ['name' => '', 'phone' => ''];
$remaining = $item ? (float)$item['remaining'] : 0.0;
$pending = array_values(array_filter($invoices, fn($i) => $i['status'] === 'pending'));
$active = $pending[0] ?? null;
$money = fn($n) => number_format((float)$n, 0, '.', ' ') . ' сом';
$authParams = array_intersect_key($_GET, array_flip(['salon_id', 'hash', 'entity_type', 'entity_id', 'user_id']));
$statusLabel = [
    'pending' => ['Ожидает оплату', 'p-mute'], 'paid' => ['Оплачен', 'p-ok'],
    'amount_mismatch' => ['Сумма не совпала', 'p-warn'], 'cancelled' => ['Отменён', 'p-mute'],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Оплата по ссылке</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin/admin.css?v=3">
<style>
  body { background: #fff; }
  .tab { padding: 4px 4px 24px; max-width: 640px; }
  .who { color: var(--muted); margin-bottom: 14px; }
  .who b { color: var(--ink); font-weight: 600; }
  .due { border: 1px solid var(--line); border-radius: 12px; padding: 16px 18px; }
  .due .label { color: var(--muted); font-size: 13px; }
  .due .amount { font-size: 30px; font-weight: 600; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; margin: 2px 0 2px; }
  .due .title { color: var(--muted); font-size: 13px; }
  .create { margin-top: 14px; }
  .link-box { margin-top: 14px; border: 1px solid var(--line); background: var(--bg); border-radius: 12px; padding: 14px 16px; }
  .link-box .head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
  .link-body { display: flex; gap: 16px; align-items: flex-start; margin-top: 12px; }
  .link-body .qr { flex: none; background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 6px; }
  .link-body .qr img { display: block; width: 168px; height: 168px; image-rendering: pixelated; }
  .link-body .qr p { margin: 4px 0 2px; text-align: center; font-size: 12px; color: var(--muted); }
  .link-body .side { flex: 1; min-width: 0; }
  .link-body .side code { margin-top: 0; }
  @media (max-width: 520px) { .link-body { flex-direction: column; align-items: stretch; } .link-body .qr { align-self: center; } }
  .link-box code { display: block; word-break: break-all; font: 12.5px ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--muted); background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px; margin: 10px 0 12px; }
  .paid { text-align: center; padding: 22px 16px; border: 1px solid var(--line); border-radius: 12px; }
  .paid .check { width: 44px; height: 44px; border-radius: 50%; background: var(--accent-soft); color: var(--accent); display: inline-flex; align-items: center; justify-content: center; }
  .paid h3 { margin: 10px 0 2px; font-size: 16px; font-weight: 600; }
  .paid p { margin: 0; color: var(--muted); }
  .history { margin-top: 20px; }
  .history h4 { font-size: 13px; font-weight: 500; color: var(--muted); margin: 0 0 6px; }
  .history .row-i { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-top: 1px solid var(--line); font-size: 13.5px; }
  .history .row-i span:first-child { color: var(--muted); font-variant-numeric: tabular-nums; }
  [hidden] { display: none !important; }
</style>
</head>
<body>
<div class="tab">
<?php if ($error): ?>
  <div class="msg err" style="margin-top:0"><?= h($error) ?></div>
<?php elseif (!$item): ?>
  <div class="msg warn" style="margin-top:0">У этой записи нет визита для оплаты.</div>
<?php else: ?>
  <div class="who">Клиент: <b><?= h($client['name'] ?: 'без имени') ?></b><?= $client['phone'] ? ' · ' . h($client['phone']) : '' ?></div>

  <?php if ($remaining <= 0 && !$active): ?>
    <div class="paid">
      <div class="check"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
      <h3>Визит оплачен</h3>
      <p>Оплачивать нечего.</p>
    </div>
  <?php else: ?>
    <div class="due">
      <div class="label">К оплате</div>
      <div class="amount"><?= h($money($remaining)) ?></div>
      <div class="title"><?= h($item['title']) ?></div>
    </div>

    <div id="activeBox" class="link-box" <?= $active ? '' : 'hidden' ?>>
      <div class="head">
        <b>Ссылка №<span id="aId"><?= $active ? (int)$active['id'] : '' ?></span> · <span id="aSum"><?= $active ? h($money($active['amount'])) : '' ?></span></b>
        <span class="pill p-mute" id="aStatus">Ожидает оплату</span>
      </div>
      <div class="link-body">
        <div class="qr"><img id="aQr" alt="QR-код для оплаты" src="<?= $active ? h(qr_data_uri((string)$active['qr_url'])) : '' ?>"><p>Клиент сканирует в приложении банка</p></div>
        <div class="side">
      <code id="aLink"><?= $active ? h(payment_page_url($active['token'])) : '' ?></code>
      <div class="actions">
        <a class="btn wa" id="aWa" target="_blank" rel="noopener" href="<?= $active ? h(whatsapp_url($client['phone'], payment_message($client['name'], $active['item_title'], (float)$active['amount'], payment_page_url($active['token'])))) : '#' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 1.9s.8 2.2.9 2.4c.1.2 1.6 2.5 4 3.5 1.5.6 2 .7 2.8.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2l-.5-.2z"/></svg>
          Отправить в WhatsApp</a>
        <button class="btn secondary" id="aCopyLink" type="button">Скопировать ссылку</button>
        <button class="btn secondary" id="aCopy" type="button">Скопировать сообщение</button>
        <button class="btn secondary" id="aCancel" type="button">Отменить</button>
      </div>
        </div>
      </div>
    </div>

    <div id="createBox" class="create" <?= $active ? 'hidden' : '' ?>>
      <form id="createForm" class="row">
        <div class="grow"><label for="amount">Сумма, сом</label>
          <input id="amount" type="number" min="1" step="1" max="<?= h($remaining) ?>" value="<?= h((int)round($remaining)) ?>" required></div>
        <button class="btn primary" id="createBtn">Создать ссылку</button>
      </form>
      <div id="msg"></div>
    </div>
  <?php endif; ?>

  <?php if ($invoices): ?>
    <div class="history">
      <h4>Ссылки по этому визиту</h4>
      <?php foreach ($invoices as $i): [$label, $cls] = $statusLabel[$i['status']] ?? [$i['status'], 'p-mute']; ?>
        <div class="row-i">
          <span>№<?= (int)$i['id'] ?> · <?= h(date('d.m H:i', strtotime((string)$i['created_at']))) ?></span>
          <span><?= h($money($i['amount'])) ?> · <span class="pill <?= $cls ?>"><?= h($label) ?></span></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
</div>

<script>
(function () {
  // Протокол Altegio: без iframe_ready вкладка остаётся пустой (0×0). Слушать Altegio начинает после load — повторяем.
  var parentOrigin = document.referrer ? new URL(document.referrer).origin : 'https://app.alteg.io';
  var ready = false, tries = 0;
  window.addEventListener('message', function (e) {
    if (e.origin === parentOrigin && e.data && e.data.type === 'yclients_response_entity') ready = true;
  });
  function ping() {
    if (ready || tries++ > 20) return;
    window.parent.postMessage({ type: 'iframe_ready' }, parentOrigin);
    window.parent.postMessage({ type: 'iframe_request_entity' }, parentOrigin);
    setTimeout(ping, 500);
  }
  window.addEventListener('load', function () { setTimeout(ping, 50); });

  var AUTH = <?= json_encode($authParams) ?>;
  var $ = function (id) { return document.getElementById(id); };
  var money = function (n) { return Number(n).toLocaleString('ru-RU') + ' сом'; };
  var active = <?= json_encode($active ? ['id' => (int)$active['id'], 'token' => $active['token'], 'link' => payment_page_url($active['token']), 'message' => payment_message($client['name'], $active['item_title'], (float)$active['amount'], payment_page_url($active['token']))] : null, JSON_UNESCAPED_UNICODE) ?>;

  async function call(payload) {
    var res = await fetch('altegio_tab.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({}, AUTH, payload)) });
    var body = {}; try { body = await res.json(); } catch (e) {}
    if (!res.ok) throw new Error(body.error || ('Ошибка ' + res.status));
    return body;
  }

  var form = $('createForm');
  if (form) form.addEventListener('submit', async function (e) {
    e.preventDefault();
    $('msg').innerHTML = '';
    $('createBtn').disabled = true;
    try {
      var r = await call({ action: 'create', amount: Number($('amount').value) });
      active = { id: r.invoice_id, token: r.token, link: r.link, message: r.message };
      $('aId').textContent = r.invoice_id;
      $('aSum').textContent = money($('amount').value);
      $('aLink').textContent = r.link;
      $('aQr').src = r.qr;
      $('aWa').href = r.whatsapp;
      $('createBox').hidden = true;
      $('activeBox').hidden = false;
      watch();
    } catch (err) {
      $('msg').innerHTML = '<div class="msg err"></div>';
      $('msg').firstChild.textContent = err.message;
    } finally { $('createBtn').disabled = false; }
  });

  // Копирование: в iframe Clipboard API может быть запрещён — тогда запасной способ через выделение текста
  async function copyText(text, btn) {
    var label = btn.textContent;
    var ok = false;
    try { await navigator.clipboard.writeText(text); ok = true; } catch (e) {
      var ta = document.createElement('textarea');
      ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { ok = document.execCommand('copy'); } catch (e2) {}
      ta.remove();
    }
    if (!ok) { prompt('Скопируйте:', text); return; }
    btn.textContent = 'Скопировано';
    setTimeout(function () { btn.textContent = label; }, 2000);
  }
  if ($('aCopyLink')) $('aCopyLink').addEventListener('click', function () { copyText(active.link, this); });
  if ($('aCopy')) $('aCopy').addEventListener('click', function () { copyText(active.message, this); });

  if ($('aCancel')) $('aCancel').addEventListener('click', async function () {
    if (!active || !confirm('Отменить ссылку №' + active.id + '? Клиент не сможет по ней оплатить.')) return;
    try { await call({ action: 'cancel', invoice_id: active.id }); location.reload(); }
    catch (err) { alert(err.message); }
  });

  // Статус оплаты активной ссылки
  var timer = null;
  function watch() {
    clearInterval(timer);
    if (!active) return;
    timer = setInterval(async function () {
      try {
        var res = await fetch('payment_status.php?' + new URLSearchParams({ i: active.token }), { cache: 'no-store' });
        var d = await res.json();
        if (d.status === 'paid') {
          clearInterval(timer);
          $('aStatus').textContent = 'Оплачено';
          $('aStatus').className = 'pill p-ok';
          $('aCancel').hidden = true;
        }
      } catch (e) {}
    }, 4000);
  }
  watch();
})();
</script>
</body>
</html>
