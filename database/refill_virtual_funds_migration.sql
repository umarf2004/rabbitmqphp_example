-- Apply once to an existing auth_project database.
-- Do not run the full schema dump as a migration.

CREATE TABLE `virtual_fund_refills` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `account_value_before` decimal(18,8) NOT NULL,
  `amount_added` decimal(18,8) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_refills_user_date` (`user_id`,`created_at`),
  CONSTRAINT `virtual_fund_refills_user_fk`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `virtual_fund_refills_value_chk`
    CHECK (`account_value_before` >= 0),
  CONSTRAINT `virtual_fund_refills_amount_chk`
    CHECK (`amount_added` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER ;;
CREATE PROCEDURE `refill_virtual_funds`(
    IN p_user_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_locked_user BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_usd_balance DECIMAL(18,8) DEFAULT 0;
    DECLARE v_wallet_total DECIMAL(18,8) DEFAULT 0;
    DECLARE v_stock_total DECIMAL(18,8) DEFAULT 0;
    DECLARE v_total DECIMAL(18,8) DEFAULT 0;
    DECLARE v_added DECIMAL(18,8) DEFAULT 0;
    DECLARE v_missing_rates INT DEFAULT 0;
    DECLARE v_missing_prices INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- Serialize refill requests for this user.
    SELECT id INTO v_locked_user
    FROM users
    WHERE id = p_user_id
    FOR UPDATE;

    IF v_locked_user IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'User not found';
    END IF;

    -- Ensure the USD wallet exists.
    INSERT INTO wallets (user_id, currency, balance)
    VALUES (p_user_id, 'USD', 0)
    ON DUPLICATE KEY UPDATE id = id;

    -- Lock the USD wallet before calculating and crediting.
    SELECT balance INTO v_usd_balance
    FROM wallets
    WHERE user_id = p_user_id AND currency = 'USD'
    FOR UPDATE;

    -- Every nonzero foreign-currency wallet needs a recent USD rate.
    SELECT COUNT(*) INTO v_missing_rates
    FROM wallets AS w
    WHERE w.user_id = p_user_id
      AND w.balance > 0
      AND w.currency <> 'USD'
      AND NOT EXISTS (
          SELECT 1
          FROM exchange_rates AS er
          WHERE er.base_currency = w.currency
            AND er.target_currency = 'USD'
            AND er.exchange_rate > 0
            AND er.rate_date BETWEEN CURRENT_DATE - INTERVAL 4 DAY
                                 AND CURRENT_DATE
            AND er.fetched_at BETWEEN NOW() - INTERVAL 4 DAY
                                  AND NOW()
      );

    IF v_missing_rates > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Missing or stale currency exchange rates';
    END IF;

    -- Every owned stock needs a valid price updated within 24 hours.
    SELECT COUNT(*) INTO v_missing_prices
    FROM portfolio_holdings AS ph
    LEFT JOIN stocks AS s ON s.id = ph.stock_id
    WHERE ph.user_id = p_user_id
      AND ph.shares > 0
      AND (
          s.id IS NULL
          OR s.current_price IS NULL
          OR s.current_price <= 0
          OR s.last_updated IS NULL
          OR s.last_updated < NOW() - INTERVAL 24 HOUR
          OR s.last_updated > NOW()
      );

    IF v_missing_prices > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Missing or stale stock prices';
    END IF;

    -- Value every currency wallet in USD.
    SELECT COALESCE(SUM(
        CASE
            WHEN w.currency = 'USD' THEN w.balance
            WHEN w.balance = 0 THEN 0
            ELSE w.balance * (
                SELECT er.exchange_rate
                FROM exchange_rates AS er
                WHERE er.base_currency = w.currency
                  AND er.target_currency = 'USD'
                  AND er.exchange_rate > 0
                  AND er.rate_date BETWEEN CURRENT_DATE - INTERVAL 4 DAY
                                       AND CURRENT_DATE
                  AND er.fetched_at BETWEEN NOW() - INTERVAL 4 DAY
                                        AND NOW()
                ORDER BY er.rate_date DESC, er.fetched_at DESC, er.id DESC
                LIMIT 1
            )
        END
    ), 0)
    INTO v_wallet_total
    FROM wallets AS w
    WHERE w.user_id = p_user_id;

    -- Add the value of owned stocks.
    SELECT COALESCE(SUM(ph.shares * s.current_price), 0)
    INTO v_stock_total
    FROM portfolio_holdings AS ph
    JOIN stocks AS s ON s.id = ph.stock_id
    WHERE ph.user_id = p_user_id
      AND ph.shares > 0;

    SET v_total = ROUND(v_wallet_total + v_stock_total, 8);

    IF v_total < 10000.00000000 THEN
        SET v_added = 10000.00000000 - v_total;

        UPDATE wallets
        SET balance = balance + v_added
        WHERE user_id = p_user_id AND currency = 'USD';

        INSERT INTO virtual_fund_refills (
            user_id, account_value_before, amount_added
        )
        VALUES (p_user_id, v_total, v_added);
    END IF;

    COMMIT;

    SELECT
        p_user_id AS user_id,
        v_total AS account_value_before,
        v_added AS amount_added,
        v_total + v_added AS account_value_after;
END ;;
DELIMITER ;
