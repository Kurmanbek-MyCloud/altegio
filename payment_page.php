<?php
/**
 * Страница оплаты счёта: GET /payment_page.php?i=<token>
 * Сумма, QR и описание берутся из счёта в БД — из адреса ничего, кроме токена, не читается.
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$invoice = null;
try {
    $invoice = (new Invoices(app_db()))->findByToken((string)($_GET['i'] ?? ''));
} catch (Throwable $ex) {
    app_log('payment_page', 'Ошибка БД', ['error' => $ex->getMessage()]);
}

if (!$invoice || $invoice['status'] === 'cancelled' || empty($invoice['qr_url'])) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Ссылка недействительна</title></head>'
       . '<body style="font-family:-apple-system,sans-serif;text-align:center;padding:72px 16px;background:#F5F4F0;color:#1B1B19">'
       . '<h2 style="font-weight:600">Ссылка на оплату недействительна</h2>'
       . '<p style="color:#6E6C66">Запросите новую ссылку у администратора.</p></body></html>';
    exit;
}

$formattedAmount = number_format((float)$invoice['amount'], 0, '.', ' ');
$isMobile = (bool)preg_match('/android|iphone|ipad|mobile|tablet/i', $_SERVER['HTTP_USER_AGENT'] ?? '');

// QR-картинка из ссылки Optima; хеш после # — для перехода в приложения банков
$qrHash = (string)$invoice['qr_hash'];
$qrCode = qr_data_uri((string)$invoice['qr_url']);

// Приложения банков (ссылки в base64, раскодируются по нажатию)
$paymentLinks = [];
foreach ([
    ['Optima Bank', 'https://optimabank.kg/index.php?lang=ru#', 'optima.png'],
    ['MBank',       'https://app.mbank.kg/qr/#',               'mbank.jpeg'],
    ['O!Деньги',    'https://api.dengi.o.kg/#',                'odengi.png'],
    ['Balance',     'https://balance.kg/payment_qr/#',         'balance_kg.png'],
    ['Bakai Bank',  'https://bakai24.app/#',                   'bakai_bank.png'],
    ['Demir Bank',  'https://apps.demirbank.kg/ib//#',         'demir_bank.png'],
    ['KICB',        'https://bank.kicb.net/#',                 'KICB.png'],
] as [$name, $prefix, $icon]) {
    $paymentLinks[] = ['name' => $name, 'url' => base64_encode($prefix . $qrHash), 'icon' => 'image_pay_system/' . $icon];
}

// ---------- Данные для шаблона ----------
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$appName = (string)(app_config()['app']['name'] ?? '');
$logo = (string)(app_config()['app']['logo'] ?? '');
$isDemo = app_demo();
$isPaid = in_array($invoice['status'], ['paid', 'amount_mismatch'], true);
$paidAmount = $isPaid ? number_format((float)$invoice['paid_amount'], 0, '.', ' ') : '';
$paidAt = $isPaid && $invoice['paid_at'] ? date('d.m.Y, H:i', strtotime((string)$invoice['paid_at'])) : '';
$itemTitle = (string)$invoice['item_title'];
$invoiceNo = (int)$invoice['id'];
$qrIsImage = $qrCode !== '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#F5F4F0">
<title>Оплата<?= $invoiceNo ? ' · счёт №' . $invoiceNo : '' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #F5F4F0;
    --card: #FFFFFF;
    --ink: #1B1B19;
    --muted: #6E6C66;
    --faint: #A3A19A;
    --line: #E6E4DE;
    --line-strong: #D6D3CB;
    --accent: #1F6B4E;
    --accent-soft: #E7F1EC;
    --warn-bg: #FBF3DF;
    --warn-ink: #7A5A12;
    --radius: 14px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { -webkit-text-size-adjust: 100%; }
  body {
    font-family: "Onest", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--ink);
    font-size: 15px;
    line-height: 1.45;
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
  }
  .demo {
    background: var(--warn-bg); color: var(--warn-ink);
    font-size: 13px; text-align: center; padding: 8px 16px;
    border-bottom: 1px solid #EFE2BF;
  }
  .page { max-width: 440px; margin: 0 auto; padding: 20px 16px 40px; }
  @media (min-width: 820px) { .page { max-width: 760px; padding-top: 48px; } }

  /* Шапка */
  .merchant { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; min-height: 28px; }
  .merchant img { width: 28px; height: 28px; border-radius: 7px; object-fit: contain; background: var(--card); border: 1px solid var(--line); }
  .merchant .name { font-weight: 600; font-size: 15px; }
  .merchant .no { margin-left: auto; color: var(--muted); font-size: 13px; font-variant-numeric: tabular-nums; }

  .card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); }

  /* Сумма */
  .summary { padding: 20px 20px 18px; }
  .summary .label { color: var(--muted); font-size: 13px; }
  .summary .amount {
    font-size: 40px; font-weight: 600; letter-spacing: -0.02em; line-height: 1.1;
    margin-top: 4px; font-variant-numeric: tabular-nums;
  }
  .summary .amount span { font-size: 22px; font-weight: 500; color: var(--muted); letter-spacing: 0; margin-left: 4px; }
  .summary .item { margin-top: 10px; padding-top: 12px; border-top: 1px dashed var(--line-strong); color: var(--ink); display: flex; justify-content: space-between; gap: 12px; }
  .summary .item span:last-child { color: var(--muted); }

  .layout { display: grid; gap: 12px; margin-top: 12px; }
  @media (min-width: 820px) {
    .layout { grid-template-columns: 1fr 1fr; align-items: start; }
    .layout .qr-card { order: -1; }
  }

  .section-title { font-size: 13px; color: var(--muted); padding: 16px 20px 8px; }

  /* Банки */
  .banks { list-style: none; padding-bottom: 6px; }
  .bank {
    display: flex; align-items: center; gap: 14px; width: 100%;
    padding: 11px 20px; background: none; border: 0; font: inherit; color: inherit;
    text-align: left; cursor: pointer; position: relative;
  }
  .bank + .bank::before { content: ""; position: absolute; top: 0; left: 70px; right: 0; border-top: 1px solid var(--line); }
  .bank img { width: 36px; height: 36px; border-radius: 9px; object-fit: contain; border: 1px solid var(--line); background: #fff; flex: none; }
  .bank .t { flex: 1; font-weight: 500; }
  .bank .t small { display: block; font-weight: 400; color: var(--muted); font-size: 13px; }
  .bank svg { color: var(--faint); flex: none; }
  .bank:active { background: #FAFAF7; }
  @media (hover: hover) { .bank:hover { background: #FAFAF7; } }
  .bank-grid { display: flex; flex-wrap: wrap; gap: 8px; padding: 4px 20px 18px; }
  .bank-grid img { width: 34px; height: 34px; border-radius: 8px; object-fit: contain; border: 1px solid var(--line); background: #fff; }

  /* QR */
  .qr-card { padding: 20px; text-align: center; }
  .qr-frame { display: inline-block; padding: 10px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
  .qr-frame img { display: block; width: 220px; height: 220px; image-rendering: pixelated; }
  .qr-hint { color: var(--muted); font-size: 13px; margin-top: 12px; }
  .steps { list-style: none; counter-reset: s; text-align: left; margin-top: 16px; display: grid; gap: 8px; }
  .steps li { counter-increment: s; display: flex; gap: 10px; font-size: 14px; }
  .steps li::before {
    content: counter(s); flex: none; width: 22px; height: 22px; border-radius: 50%;
    background: var(--bg); border: 1px solid var(--line); font-size: 12px; font-weight: 600;
    display: flex; align-items: center; justify-content: center; color: var(--muted);
  }
  .link-btn {
    margin-top: 14px; background: none; border: 1px solid var(--line-strong); border-radius: 10px;
    padding: 9px 14px; font: inherit; font-size: 14px; font-weight: 500; color: var(--ink); cursor: pointer;
    display: inline-flex; align-items: center; gap: 8px;
  }
  .link-btn:hover { background: #FAFAF7; }

  /* Статус */
  .status { display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-top: 1px solid var(--line); color: var(--muted); font-size: 14px; }
  .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--faint); position: relative; flex: none; }
  .dot::after { content: ""; position: absolute; inset: -4px; border-radius: 50%; border: 1px solid var(--faint); animation: ping 1.8s ease-out infinite; }
  @keyframes ping { from { transform: scale(.6); opacity: 1; } to { transform: scale(1.6); opacity: 0; } }
  @media (prefers-reduced-motion: reduce) { .dot::after { animation: none; } }

  /* Оплачено */
  .done { padding: 28px 20px 24px; text-align: center; }
  .done .check { width: 52px; height: 52px; border-radius: 50%; background: var(--accent-soft); color: var(--accent); display: inline-flex; align-items: center; justify-content: center; }
  .done h2 { font-size: 20px; font-weight: 600; margin-top: 14px; }
  .done p { color: var(--muted); margin-top: 4px; }
  .done dl { margin-top: 18px; border-top: 1px dashed var(--line-strong); padding-top: 14px; display: grid; gap: 6px; text-align: left; font-size: 14px; }
  .done dl div { display: flex; justify-content: space-between; gap: 12px; }
  .done dt { color: var(--muted); }
  .done dd { font-variant-numeric: tabular-nums; text-align: right; }

  .foot { margin-top: 20px; text-align: center; color: var(--faint); font-size: 12px; }
  .hidden { display: none !important; }
  .fade-in { animation: fade .3s ease; }
  @keyframes fade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
</style>
</head>
<body>
<?php if ($isDemo): ?>
  <div class="demo">Демо-режим: QR не настоящий, оплату имитирует симулятор банка</div>
<?php endif; ?>

<main class="page">
  <header class="merchant">
    <?php if ($logo !== ''): ?><img src="<?= $e($logo) ?>" alt=""><?php endif; ?>
    <?php if ($appName !== ''): ?><span class="name"><?= $e($appName) ?></span><?php endif; ?>
    <?php if ($invoiceNo): ?><span class="no">Счёт №<?= $invoiceNo ?></span><?php endif; ?>
  </header>

  <!-- Оплачено -->
  <section class="card done <?= $isPaid ? '' : 'hidden' ?>" id="done" aria-live="polite">
    <div class="check">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
    </div>
    <h2>Оплата получена</h2>
    <p>Спасибо! Можно закрыть страницу.</p>
    <dl>
      <div><dt>Сумма</dt><dd id="doneSum"><?= $paidAmount ?: $formattedAmount ?> сом</dd></div>
      <?php if ($itemTitle !== ''): ?><div><dt>За что</dt><dd><?= $e($itemTitle) ?></dd></div><?php endif; ?>
      <div><dt>Когда</dt><dd id="doneAt"><?= $e($paidAt) ?></dd></div>
      <?php if ($invoiceNo): ?><div><dt>Счёт</dt><dd>№<?= $invoiceNo ?></dd></div><?php endif; ?>
    </dl>
  </section>

  <!-- К оплате -->
  <div id="pay" class="<?= $isPaid ? 'hidden' : '' ?>">
    <section class="card summary">
      <div class="label">К оплате</div>
      <div class="amount"><?= $formattedAmount ?><span>сом</span></div>
      <?php if ($itemTitle !== ''): ?>
        <div class="item"><span><?= $e($itemTitle) ?></span></div>
      <?php endif; ?>
    </section>

    <div class="layout">
      <section class="card">
        <?php if ($isMobile): ?>
          <div class="section-title">Оплатить в приложении банка</div>
          <div class="banks">
            <?php foreach ($paymentLinks as $p): ?>
              <button type="button" class="bank" data-url="<?= $e($p['url']) ?>">
                <img src="<?= $e($p['icon']) ?>" alt="">
                <span class="t"><?= $e($p['name']) ?></span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
              </button>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="section-title">Как оплатить</div>
          <ol class="steps" style="padding: 0 20px 4px; margin-top: 0">
            <li>Откройте приложение своего банка на телефоне</li>
            <li>Выберите оплату по QR-коду</li>
            <li>Наведите камеру на код и подтвердите платёж</li>
          </ol>
          <div class="section-title" style="padding-top: 18px">Принимаем</div>
          <div class="bank-grid">
            <?php foreach ($paymentLinks as $p): ?>
              <img src="<?= $e($p['icon']) ?>" alt="<?= $e($p['name']) ?>" title="<?= $e($p['name']) ?>">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($invoiceNo): ?>
          <div class="status" id="status"><span class="dot"></span><span>Ожидаем оплату — страница обновится сама</span></div>
        <?php endif; ?>
      </section>

      <section class="card qr-card">
        <?php if ($qrIsImage): ?>
          <div class="qr-frame"><img src="<?= $e($qrCode) ?>" alt="QR-код для оплаты" id="qrImg"></div>
          <p class="qr-hint"><?= $isMobile ? 'Или сохраните QR и загрузите его в приложение банка' : 'Отсканируйте камерой в приложении банка' ?></p>
          <button type="button" class="link-btn" id="saveQr">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0l-4-4m4 4l4-4M5 20h14"/></svg>
            Сохранить QR
          </button>
        <?php else: ?>
          <p class="qr-hint">QR-код недоступен. Запросите новую ссылку у администратора.</p>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <p class="foot">Платёж обрабатывает Optima Bank</p>
</main>

<script>
(function () {
  // Переход в приложение банка
  document.querySelectorAll('.bank[data-url]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      try { window.open(atob(btn.dataset.url), '_blank'); } catch (e) {}
    });
  });

  // Сохранить QR
  var save = document.getElementById('saveQr');
  if (save) save.addEventListener('click', function () {
    var a = document.createElement('a');
    a.href = document.getElementById('qrImg').src;
    a.download = 'qr-oplata<?= $invoiceNo ? '-' . $invoiceNo : '' ?>.png';
    document.body.appendChild(a); a.click(); a.remove();
  });

  // Ожидание подтверждения от банка
  var token = <?= json_encode($invoice && !$isPaid ? $invoice['token'] : '') ?>;
  if (!token) return;
  var timer = setInterval(async function () {
    try {
      var res = await fetch('payment_status.php?' + new URLSearchParams({ i: token }), { cache: 'no-store' });
      var data = await res.json();
      if (data.status !== 'paid') return;
      clearInterval(timer);
      document.getElementById('doneSum').textContent = Number(data.sum).toLocaleString('ru-RU') + ' сом';
      var now = new Date();
      document.getElementById('doneAt').textContent = now.toLocaleDateString('ru-RU') + ', ' + now.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
      document.getElementById('pay').classList.add('hidden');
      var done = document.getElementById('done');
      done.classList.remove('hidden');
      done.classList.add('fade-in');
    } catch (e) { /* сеть моргнула — проверим на следующем шаге */ }
  }, 3000);
})();
</script>
</body>
</html>
