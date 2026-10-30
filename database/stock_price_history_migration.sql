CREATE TABLE IF NOT EXISTS stock_price_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stock_id BIGINT UNSIGNED NOT NULL,
    price DECIMAL(18,8) NOT NULL,
    recorded_at DATETIME NOT NULL,

    PRIMARY KEY (id),

    UNIQUE KEY unique_stock_timestamp (
        stock_id,
        recorded_at
    ),

    KEY idx_stock_history_date (
        stock_id,
        recorded_at
    ),

    CONSTRAINT fk_stock_price_history_stock
        FOREIGN KEY (stock_id)
        REFERENCES stocks(id)
        ON DELETE CASCADE,

    CONSTRAINT chk_stock_history_price
        CHECK (price >= 0)
) ENGINE=InnoDB;
