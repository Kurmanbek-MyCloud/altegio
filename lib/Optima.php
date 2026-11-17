<?php
/**
 * Генерация QR через Optima Business API.
 */

declare(strict_types=1);

class Optima {
    private string $apiUrl;
    private string $apiKey;
    private string $account;

    public function __construct(string $apiUrl, string $apiKey, string $account) {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->account = $account;
    }

    /**
     * @param string $note то, что банк вернёт в колбэке (у нас — номер счёта)
     * @return array ['url' => полная ссылка Optima для QR, 'hash' => часть после # для диплинков]
     * @throws RuntimeException
     */
    public function generateQr(float $amount, string $note): array {
        $payload = [
            'account' => (int)$this->account,
            'sum' => $amount,
            'note' => $note,
            'qrType' => 'png',
            'qrSize' => 300,
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['X-API-KEY: ' . $this->apiKey, 'Content-Type: application/json'],
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);

        // Ключ API в лог не пишем
        app_log('optima', "generate/qr sum=$amount note=$note → HTTP $code" . ($err ? " ($err)" : ''));

        $data = is_string($raw) ? json_decode($raw, true) : null;
        $qr = $data['qrUrl'] ?? $data['qr_code'] ?? null;
        if ($code !== 200 || !is_string($qr) || $qr === '') {
            app_log('optima', 'Ошибка генерации QR', ['http' => $code, 'response' => is_string($raw) ? substr($raw, 0, 500) : $err]);
            throw new RuntimeException('Optima не выдала QR-код (HTTP ' . $code . ')');
        }

        if (strpos($qr, 'http') === 0) {
            $pos = strpos($qr, '#');
            return ['url' => $qr, 'hash' => $pos !== false ? substr($qr, $pos + 1) : $qr];
        }
        return ['url' => 'https://optimabank.kg/index.php?lang=ru#' . $qr, 'hash' => $qr];
    }
}

/**
 * ДЕМО: QR без обращения к банку. Оплатить такой QR нельзя —
 * оплату имитирует симулятор банка (admin/demo_bank.php).
 */
class DemoOptima extends Optima {
    public function __construct() {
        parent::__construct('', '', '');
    }

    public function generateQr(float $amount, string $note): array {
        $hash = 'DEMO-' . $note . '-' . bin2hex(random_bytes(4));
        app_log('optima', "DEMO generate/qr sum=$amount note=$note");
        return ['url' => 'https://optimabank.kg/index.php?lang=ru#' . $hash, 'hash' => $hash];
    }
}
