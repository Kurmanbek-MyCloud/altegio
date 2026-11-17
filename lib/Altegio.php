<?php
/**
 * Клиент API Altegio для оплаты абонементов.
 */

declare(strict_types=1);

class AltegioException extends RuntimeException {
    public $response;
    public function __construct(string $message, $response = null) {
        parent::__construct($message);
        $this->response = $response;
    }
}

class Altegio {
    private string $baseUrl;
    private string $partnerToken;
    private string $userToken;
    private int $companyId;

    public function __construct(string $baseUrl, string $partnerToken, string $userToken, int $companyId) {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->partnerToken = $partnerToken;
        $this->userToken = $userToken;
        $this->companyId = $companyId;
    }

    /**
     * @return array декодированный ответ Altegio (с ключами success/data/meta)
     * @throws AltegioException
     */
    public function request(string $method, string $path, ?array $body = null, array $query = []): array {
        $url = $this->baseUrl . ltrim($path, '/');
        if ($query) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/vnd.api.v2+json',
                'Authorization: Bearer ' . $this->partnerToken . ', User ' . $this->userToken,
            ],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);

        app_log('altegio', "$method $path → HTTP $code" . ($err ? " ($err)" : ''));

        if ($raw === false) {
            throw new AltegioException("Altegio недоступен: $err");
        }
        $data = json_decode((string)$raw, true);
        if ($code < 200 || $code >= 300 || !is_array($data) || (isset($data['success']) && $data['success'] === false)) {
            app_log('altegio', "Ошибка $method $path", ['http' => $code, 'response' => $data ?? substr((string)$raw, 0, 500)]);
            $msg = $data['meta']['message'] ?? $data['message'] ?? "HTTP $code";
            throw new AltegioException("Altegio: $msg", $data);
        }
        return $data;
    }

    /**
     * Клиент по телефону: ['id', 'name', 'phone'] или null.
     */
    public function findClientByPhone(string $phone): ?array {
        $key = phone_key($phone);
        $match = function (array $clients) use ($key): ?array {
            foreach ($clients as $client) {
                if (phone_key((string)($client['phone'] ?? '')) === $key) {
                    return ['id' => (int)$client['id'], 'name' => (string)($client['name'] ?? ''), 'phone' => (string)$client['phone']];
                }
            }
            return null;
        };

        // 1. Быстрый поиск
        try {
            $res = $this->request('POST', "company/{$this->companyId}/clients/search", [
                'page' => 1,
                'page_size' => 50,
                'fields' => ['id', 'name', 'phone'],
                'filters' => [['type' => 'quick_search', 'state' => ['value' => normalize_phone($phone)]]],
            ]);
            if ($client = $match($res['data'] ?? [])) {
                return $client;
            }
        } catch (AltegioException $e) {
            // пробуем второй способ
        }

        // 2. Старый метод списка клиентов с фильтром по телефону
        $res = $this->request('GET', "clients/{$this->companyId}", null, ['phone' => normalize_phone($phone), 'count' => 50]);
        return $match($res['data'] ?? []);
    }

    /**
     * Что клиент может оплатить: неоплаченные документы продажи Altegio.
     *  - записи (визиты), например с услугой «Абонемент»;
     *  - абонементы из «Лояльности», проданные, но не оплаченные полностью.
     *
     * @param int $accountId касса, через которую будем платить (для расчёта остатка)
     * @return array<int, array{document_id:int, kind:string, item_id:int, title:string, number:string, date:?string, remaining:float}>
     */
    public function getPayables(int $clientId, string $phone, int $accountId): array {
        $docs = []; // document_id => описание

        // 1. Записи клиента за последние 90 и следующие 60 дней
        $res = $this->request('GET', "records/{$this->companyId}", null, [
            'client_id' => $clientId,
            'start_date' => date('Y-m-d', strtotime('-90 days')),
            'end_date' => date('Y-m-d', strtotime('+60 days')),
            'count' => 100,
        ]);
        foreach ($res['data'] ?? [] as $r) {
            if (!empty($r['deleted']) || (int)($r['paid_full'] ?? 0) === 1) {
                continue;
            }
            foreach ($r['documents'] ?? [] as $doc) {
                if ((int)($doc['type_id'] ?? 0) === 7) { // 7 = Visit
                    $titles = array_filter(array_map(fn($s) => (string)($s['title'] ?? ''), $r['services'] ?? []));
                    $docs[(int)$doc['id']] = [
                        'kind' => 'record',
                        'item_id' => (int)$r['id'],
                        'title' => $titles ? implode(', ', $titles) : 'Запись',
                        'number' => '',
                        'date' => isset($r['datetime']) ? substr((string)$r['datetime'], 0, 16) : null,
                    ];
                }
            }
        }

        // 2. Абонементы (memberships) → транзакция товара → документ продажи
        $res = $this->request('GET', 'loyalty/memberships', null, [
            'location_id' => $this->companyId,
            'phone' => normalize_phone($phone),
        ]);
        foreach ($res['data'] ?? [] as $m) {
            $txId = (int)($m['goods_transaction_id'] ?? 0);
            if (!$txId) {
                continue;
            }
            $tx = $this->request('GET', "storage_operations/goods_transactions/{$this->companyId}/$txId");
            $docId = (int)($tx['data']['document_id'] ?? 0);
            if ($docId && !isset($docs[$docId])) {
                $docs[$docId] = [
                    'kind' => 'membership',
                    'item_id' => (int)$m['id'],
                    'title' => (string)($m['type']['title'] ?? 'Абонемент'),
                    'number' => (string)($m['number'] ?? ''),
                    'date' => isset($m['created_date']) ? substr((string)$m['created_date'], 0, 16) : null,
                ];
            }
        }

        // 3. Остаток к оплате по каждому документу
        $list = [];
        foreach ($docs as $docId => $d) {
            $remaining = $this->remainingToPay($docId, $accountId);
            if ($remaining > 0) {
                $list[] = ['document_id' => $docId] + $d + ['remaining' => $remaining];
            }
        }
        return $list;
    }

    /**
     * Запись (визит) как позиция к оплате — для вкладки в окне записи.
     * @return array{client: array, item: ?array, paid_full: bool}|null  null — записи нет
     */
    public function getRecordPayable(int $recordId, int $accountId): ?array {
        try {
            $res = $this->request('GET', "record/{$this->companyId}/$recordId");
        } catch (AltegioException $e) {
            return null;
        }
        $r = $res['data'] ?? null;
        if (!$r || !empty($r['deleted'])) {
            return null;
        }
        $c = $r['client'] ?? [];
        $client = [
            'id' => (int)($c['id'] ?? 0),
            'name' => trim((string)($c['display_name'] ?? $c['name'] ?? '')),
            'phone' => (string)($c['phone'] ?? ''),
        ];
        $item = null;
        foreach ($r['documents'] ?? [] as $doc) {
            if ((int)($doc['type_id'] ?? 0) === 7) {
                $titles = array_filter(array_map(fn($s) => (string)($s['title'] ?? ''), $r['services'] ?? []));
                $item = [
                    'document_id' => (int)$doc['id'],
                    'kind' => 'record',
                    'item_id' => (int)$r['id'],
                    'title' => $titles ? implode(', ', $titles) : 'Запись',
                    'number' => '',
                    'date' => isset($r['datetime']) ? substr((string)$r['datetime'], 0, 16) : null,
                    'remaining' => $this->remainingToPay((int)$doc['id'], $accountId),
                ];
                break;
            }
        }
        return ['client' => $client, 'item' => $item, 'paid_full' => (int)($r['paid_full'] ?? 0) === 1];
    }

    /**
     * Сколько ещё можно оплатить по документу через указанную кассу.
     */
    public function remainingToPay(int $documentId, int $accountId): float {
        $res = $this->request('GET', "locations/{$this->companyId}/sale/$documentId");
        foreach ($res['data']['payment_methods'] ?? [] as $m) {
            if (($m['slug'] ?? '') === 'account' && (int)($m['account_id'] ?? 0) === $accountId) {
                return !empty($m['is_applicable']) ? round((float)($m['applicable_amount'] ?? 0), 2) : 0.0;
            }
        }
        return 0.0;
    }

    /**
     * Оплата документа продажи (визита / продажи абонемента) на кассу.
     * Altegio сам отмечает запись/абонемент оплаченными.
     */
    public function payDocument(int $documentId, int $accountId, float $amount): array {
        $res = $this->request('POST', "locations/{$this->companyId}/sale/$documentId/payment", [
            'payment' => [
                'method' => ['slug' => 'account', 'account_id' => $accountId],
                'amount' => $amount,
            ],
        ]);
        return $res['data'] ?? [];
    }
}
