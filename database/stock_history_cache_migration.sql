-- Track successful historical stock API fetches for 5-minute caching.
CREATE TABLE IF NOT EXISTS stock_history_cache (
    stock_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    last_fetched_at DATETIME NOT NULL,
    CONSTRAINT fk_stock_history_cache_stock
        FOREIGN KEY (stock_id)
        REFERENCES stocks(id)
        ON DELETE CASCADE
);
