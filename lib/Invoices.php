<?php
/**
 * Счета на оплату абонементов (таблица invoices) и журнал колбэков Optima (optima_callbacks).
 *
 * Статусы счёта:
 *   pending          — ждём оплату
 *   paid             — оплачено, сумма совпала
 *   amount_mismatch  — деньги пришли, но сумма не совпала (разобрать вручную)
 *   cancelled        — отменён администратором
 *
 * altegio_status: none | ok | error | skipped
 */

declare(strict_types=1);

class Invoices {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $d): array {
        $token = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'INSERT INTO invoices (token, phone, client_id, client_name, document_id, item_type, item_id, item_number, item_title,
                                   amount, status, altegio_status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', \'none\', ?, ?, ?)'
        );
        $stmt->execute([
            $token, $d['phone'], $d['client_id'], $d['client_name'], $d['document_id'], $d['item_type'], $d['item_id'],
            $d['item_number'], $d['item_title'], $d['amount'], $d['created_by'], $now, $now,
        ]);
        return $this->find((int)$this->db->lastInsertId());
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByToken(string $token): ?array {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE token = ?');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public function recent(int $limit = 50): array {
        $stmt = $this->db->prepare('SELECT * FROM invoices ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Счета по документу Altegio (для вкладки в окне записи), новые сверху */
    public function byDocument(int $documentId, int $limit = 10): array {
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE document_id = ? ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $documentId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Сумма ссылок по документу, которые ещё ждут оплату */
    public function pendingAmount(int $documentId): float {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE document_id = ? AND status = 'pending'");
        $stmt->execute([$documentId]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    public function update(int $id, array $fields): void {
        $fields['updated_at'] = date('Y-m-d H:i:s');
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $stmt = $this->db->prepare("UPDATE invoices SET $set WHERE id = ?");
        $stmt->execute([...array_values($fields), $id]);
    }

    /**
     * Атомарный переход статуса (защита от гонки двух колбэков по одному счёту).
     * true — если счёт был в статусе $from и обновлён.
     */
    public function transition(int $id, string $from, array $fields): bool {
        $fields['updated_at'] = date('Y-m-d H:i:s');
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $stmt = $this->db->prepare("UPDATE invoices SET $set WHERE id = ? AND status = ?");
        $stmt->execute([...array_values($fields), $id, $from]);
        return $stmt->rowCount() === 1;
    }

    public function problemCallbacks(int $limit = 20): array {
        // Всё, кроме нормальных исходов; «processing» — только зависшие дольше 5 минут
        $stmt = $this->db->prepare(
            "SELECT * FROM optima_callbacks
             WHERE result NOT IN ('paid', 'failed', 'processing') OR (result = 'processing' AND received_at < ?)
             ORDER BY received_at DESC LIMIT ?"
        );
        $stmt->bindValue(1, date('Y-m-d H:i:s', time() - 300));
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Проводит оплату счёта в документ продажи Altegio (запись/абонемент становятся оплаченными).
     */
    public function postToAltegio(array $invoice, Altegio $altegio, array $altegioConfig): array {
        if (!$altegioConfig['company_id'] || !$altegioConfig['account_id']) {
            $error = 'В config.local.php не заданы altegio.company_id / account_id';
            $this->update((int)$invoice['id'], ['altegio_status' => 'error', 'altegio_error' => $error]);
            return ['ok' => false, 'error' => $error];
        }
        try {
            // Защита от двойного проведения: если документ уже оплачен (повтор после сбоя, оплата на кассе) — не платим
            $remaining = $altegio->remainingToPay((int)$invoice['document_id'], (int)$altegioConfig['account_id']);
            if ($remaining + 0.009 < (float)$invoice['paid_amount']) {
                throw new AltegioException(sprintf(
                    'Остаток к оплате в Altegio (%s) меньше суммы платежа (%s) — проверьте документ вручную',
                    $remaining, (float)$invoice['paid_amount']
                ));
            }
            $data = $altegio->payDocument(
                (int)$invoice['document_id'],
                (int)$altegioConfig['account_id'],
                (float)$invoice['paid_amount']
            );
            // ID последней платёжной транзакции документа
            $txs = $data['state']['payment_transactions'] ?? [];
            $last = $txs ? end($txs) : null;
            $txId = isset($last['id']) ? (int)$last['id'] : null;
            $this->update((int)$invoice['id'], [
                'altegio_status' => 'ok', 'altegio_transaction_id' => $txId, 'altegio_error' => null,
            ]);
            return ['ok' => true, 'altegio_transaction_id' => $txId];
        } catch (Throwable $e) {
            $this->update((int)$invoice['id'], ['altegio_status' => 'error', 'altegio_error' => $e->getMessage()]);
            app_log('billing', 'Не удалось провести в Altegio', ['invoice' => $invoice['id'], 'error' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Регистрирует колбэк. false — если такой transactionId уже был (повтор от банка).
     */
    public function registerCallback(string $transactionId, array $payload): bool {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO optima_callbacks (transaction_id, bank_status, sum, note, payload, result, received_at)
                 VALUES (?, ?, ?, ?, ?, \'processing\', ?)'
            );
            $stmt->execute([
                $transactionId,
                (string)$payload['status'],
                (float)$payload['sum'],
                (string)$payload['note'],
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (PDOException $e) {
            // 23000 — нарушение уникальности (MySQL и SQLite)
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    public function finishCallback(string $transactionId, string $result, ?int $invoiceId): void {
        $stmt = $this->db->prepare('UPDATE optima_callbacks SET result = ?, invoice_id = ? WHERE transaction_id = ?');
        $stmt->execute([$result, $invoiceId, $transactionId]);
    }

    public function callback(string $transactionId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM optima_callbacks WHERE transaction_id = ?');
        $stmt->execute([$transactionId]);
        return $stmt->fetch() ?: null;
    }
}
