<?php
/**
 * Создание ссылки на оплату — общее для админки и вкладки в окне записи Altegio.
 */

declare(strict_types=1);

class PaymentLinkException extends RuntimeException {
    public int $httpCode;
    public function __construct(string $message, int $httpCode = 400) {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }
}

/**
 * @param array $client ['id', 'name', 'phone']
 * @param array $item   позиция из Altegio::getPayables()/getRecordPayable(): document_id, kind, item_id, number, title, remaining
 * @return array ['invoice_id', 'token', 'link', 'whatsapp', 'message']
 * @throws PaymentLinkException
 */
function create_payment_link(array $client, array $item, float $amount, string $createdBy): array {
    $amount = round($amount, 2);
    $invoices = new Invoices(app_db());
    // Уже выданные неоплаченные ссылки по этому документу — иначе клиент может оплатить одно и то же дважды
    $pending = $invoices->pendingAmount((int)$item['document_id']);
    $available = round((float)$item['remaining'] - $pending, 2);
    if ($pending > 0 && $available < 1) {
        throw new PaymentLinkException('По этой позиции уже есть неоплаченная ссылка на ' . $pending . ' сом. Отмените её или дождитесь оплаты', 409);
    }
    if ($amount < 1 || $amount > $available) {
        throw new PaymentLinkException('Сумма должна быть от 1 до ' . $available . ' сом');
    }

    $phone = normalize_phone((string)$client['phone']);
    $invoice = $invoices->create([
        'phone' => $phone,
        'client_id' => $client['id'],
        'client_name' => $client['name'],
        'document_id' => $item['document_id'],
        'item_type' => $item['kind'],
        'item_id' => $item['item_id'],
        'item_number' => $item['number'],
        'item_title' => $item['title'],
        'amount' => $amount,
        'created_by' => $createdBy,
    ]);

    try {
        $qr = app_optima()->generateQr($amount, (string)$invoice['id']);
    } catch (Throwable $e) {
        $invoices->update((int)$invoice['id'], ['status' => 'cancelled', 'altegio_error' => 'QR не создан: ' . $e->getMessage()]);
        throw new PaymentLinkException('Банк не выдал QR-код, попробуйте ещё раз', 502);
    }
    $invoices->update((int)$invoice['id'], ['qr_url' => $qr['url'], 'qr_hash' => $qr['hash']]);

    $link = payment_page_url($invoice['token']);
    $message = payment_message($client['name'], $item['title'], $amount, $link);
    app_log('billing', 'Создан счёт', ['invoice' => $invoice['id'], 'by' => $createdBy, 'amount' => $amount, 'document' => $item['document_id']]);

    return [
        'invoice_id' => (int)$invoice['id'],
        'token' => $invoice['token'],
        'qr_url' => $qr['url'],
        'link' => $link,
        'whatsapp' => whatsapp_url($phone, $message),
        'message' => $message,
    ];
}

function payment_page_url(string $token): string {
    return rtrim(app_config()['app']['base_url'], '/') . '/payment_page.php?i=' . $token;
}

function payment_message(string $clientName, string $title, float $amount, string $link): string {
    return sprintf(
        "Здравствуйте%s! Ссылка на оплату «%s» на сумму %s сом:\n%s",
        $clientName !== '' ? ', ' . $clientName : '',
        $title,
        number_format($amount, 0, '.', ' '),
        $link
    );
}

function whatsapp_url(string $phone, string $message): string {
    return 'https://wa.me/' . normalize_phone($phone) . '?text=' . rawurlencode($message);
}

/**
 * PNG-картинка QR (data: URI) из ссылки Optima. Пустая строка, если не удалось.
 */
function qr_data_uri(string $data, int $size = 300): string {
    if ($data === '') {
        return '';
    }
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $qr = new \Endroid\QrCode\QrCode(
            data: $data,
            size: $size,
            margin: 10,
            errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::High
        );
        return (new \Endroid\QrCode\Writer\PngWriter())->write($qr)->getDataUri();
    } catch (Throwable $e) {
        app_log('billing', 'Не удалось нарисовать QR', ['error' => $e->getMessage()]);
        return '';
    }
}
