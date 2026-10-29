-- Apply to the existing database after reviewing the change.
-- Replaces only the convert_currency stored procedure.

DROP PROCEDURE IF EXISTS `convert_currency`;
DELIMITER ;;
CREATE PROCEDURE `convert_currency`(
    IN p_user_id BIGINT UNSIGNED,
    IN p_from_currency VARCHAR(3),
    IN p_to_currency VARCHAR(3),
    IN p_amount DECIMAL(18,8),
    IN p_rate DECIMAL(18,8)
)
BEGIN
    DECLARE v_balance DECIMAL(18,8);
    DECLARE v_received DECIMAL(18,8);
    DECLARE v_wallet_count INT DEFAULT 0;
    DECLARE v_locked_user BIGINT UNSIGNED DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF p_amount <= 0 OR p_rate <= 0
       OR p_amount IS NULL OR p_rate IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Amount and rate must be positive';
    END IF;

    IF p_from_currency = p_to_currency
       OR p_from_currency IS NULL
       OR p_to_currency IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid currency pair';
    END IF;

    SET v_received = ROUND(p_amount * p_rate, 8);

    IF v_received <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Converted amount is too small';
    END IF;

    START TRANSACTION;

    -- Serialize conversions, stock trades, and refills for this user.
    SELECT id INTO v_locked_user
    FROM users
    WHERE id = p_user_id
    FOR UPDATE;

    IF v_locked_user IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'User not found';
    END IF;

    INSERT INTO wallets (user_id, currency, balance)
    VALUES (p_user_id, p_to_currency, 0)
    ON DUPLICATE KEY UPDATE id = id;

    UPDATE wallets
    SET balance = balance - p_amount
    WHERE user_id = p_user_id
      AND currency = p_from_currency
      AND balance >= p_amount;

    IF ROW_COUNT() <> 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Insufficient funds or source wallet missing';
    END IF;

    UPDATE wallets
    SET balance = balance + v_received
    WHERE user_id = p_user_id
      AND currency = p_to_currency;

    IF ROW_COUNT() <> 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Destination wallet update failed';
    END IF;

    INSERT INTO currency_transactions (
        user_id,
        from_currency,
        to_currency,
        amount_sent,
        exchange_rate,
        amount_received
    )
    VALUES (
        p_user_id,
        p_from_currency,
        p_to_currency,
        p_amount,
        p_rate,
        v_received
    );

    COMMIT;
END ;;
DELIMITER ;
