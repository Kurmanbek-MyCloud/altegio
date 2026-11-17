-- SQLite-версия schema.sql для локальной разработки (генерируется из schema.sql)
CREATE TABLE IF NOT EXISTS invoices (
    id                      INTEGER,
    token                   CHAR(32)      NOT NULL,             
    phone                   VARCHAR(20)   NOT NULL,
    client_id               INTEGER NULL,               
    client_name             VARCHAR(255)  NULL,
    document_id             INTEGER NOT NULL,           
    item_type               VARCHAR(20)   NOT NULL,             
    item_id                 INTEGER NOT NULL,           
    item_number             VARCHAR(64)   NULL,                 
    item_title              VARCHAR(255)  NULL,
    amount                  DECIMAL(12,2) NOT NULL,
    status                  VARCHAR(20)   NOT NULL DEFAULT 'pending', 
    qr_url                  TEXT          NULL,
    qr_hash                 VARCHAR(255)  NULL,
    paid_amount             DECIMAL(12,2) NULL,
    paid_at                 DATETIME      NULL,
    optima_transaction_id   VARCHAR(64)   NULL,
    altegio_status          VARCHAR(20)   NOT NULL DEFAULT 'none',    
    altegio_transaction_id  INTEGER NULL,               
    altegio_error           TEXT          NULL,
    created_by              VARCHAR(64)   NULL,
    created_at              DATETIME      NOT NULL,
    updated_at              DATETIME      NOT NULL,
    PRIMARY KEY (id AUTOINCREMENT), UNIQUE (token)
);

CREATE TABLE IF NOT EXISTS optima_callbacks (
    transaction_id  VARCHAR(64)   NOT NULL,                     
    invoice_id      INTEGER  NULL,
    bank_status     VARCHAR(20)   NOT NULL,
    sum             DECIMAL(12,2) NOT NULL,
    note            VARCHAR(255)  NOT NULL,
    payload         TEXT          NOT NULL,
    result          VARCHAR(40)   NOT NULL,                     
    received_at     DATETIME      NOT NULL,
    PRIMARY KEY (transaction_id)
);
