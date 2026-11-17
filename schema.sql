-- Оплата абонементов: счета и журнал колбэков Optima Bank (MySQL 5.7+/MariaDB 10.3+)

CREATE TABLE IF NOT EXISTS invoices (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token                   CHAR(32)      NOT NULL,             -- секрет в ссылке на оплату
    phone                   VARCHAR(20)   NOT NULL,
    client_id               BIGINT UNSIGNED NULL,               -- клиент Altegio
    client_name             VARCHAR(255)  NULL,
    document_id             BIGINT UNSIGNED NOT NULL,           -- документ продажи Altegio, который оплачиваем
    item_type               VARCHAR(20)   NOT NULL,             -- record (запись) | membership (абонемент)
    item_id                 BIGINT UNSIGNED NOT NULL,           -- ID записи или абонемента
    item_number             VARCHAR(64)   NULL,                 -- номер абонемента
    item_title              VARCHAR(255)  NULL,
    amount                  DECIMAL(12,2) NOT NULL,
    status                  VARCHAR(20)   NOT NULL DEFAULT 'pending', -- pending | paid | amount_mismatch | cancelled
    qr_url                  TEXT          NULL,
    qr_hash                 VARCHAR(255)  NULL,
    paid_amount             DECIMAL(12,2) NULL,
    paid_at                 DATETIME      NULL,
    optima_transaction_id   VARCHAR(64)   NULL,
    altegio_status          VARCHAR(20)   NOT NULL DEFAULT 'none',    -- none | ok | error
    altegio_transaction_id  BIGINT UNSIGNED NULL,               -- ID платёжной транзакции в документе
    altegio_error           TEXT          NULL,
    created_by              VARCHAR(64)   NULL,
    created_at              DATETIME      NOT NULL,
    updated_at              DATETIME      NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoices_token (token),
    KEY idx_invoices_phone (phone),
    KEY idx_invoices_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Номера счетов начинаются с 10001, чтобы в note банку уходило не «1», а узнаваемое число
ALTER TABLE invoices AUTO_INCREMENT = 10001;

CREATE TABLE IF NOT EXISTS optima_callbacks (
    transaction_id  VARCHAR(64)   NOT NULL,                     -- уникален → защита от повторных колбэков
    invoice_id      INT UNSIGNED  NULL,
    bank_status     VARCHAR(20)   NOT NULL,
    sum             DECIMAL(12,2) NOT NULL,
    note            VARCHAR(255)  NOT NULL,
    payload         TEXT          NOT NULL,
    result          VARCHAR(40)   NOT NULL,                     -- processing | paid | failed | amount_mismatch | duplicate_payment | invoice_not_found | ...
    received_at     DATETIME      NOT NULL,
    PRIMARY KEY (transaction_id),
    KEY idx_callbacks_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
