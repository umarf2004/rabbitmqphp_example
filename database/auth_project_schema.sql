-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: auth_project
-- ------------------------------------------------------
-- Server version	8.4.11-0ubuntu0.26.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `currency_alerts`
--

DROP TABLE IF EXISTS `currency_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `base_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_rate` decimal(18,8) NOT NULL,
  `condition_type` enum('above','below') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `currency_alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `currency_alerts_chk_1` CHECK ((`target_rate` > 0)),
  CONSTRAINT `currency_alerts_chk_2` CHECK ((`base_currency` <> `target_currency`))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `currency_transactions`
--

DROP TABLE IF EXISTS `currency_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `from_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_sent` decimal(18,8) NOT NULL,
  `exchange_rate` decimal(18,8) NOT NULL,
  `amount_received` decimal(18,8) NOT NULL,
  `transaction_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `currency_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `currency_transactions_chk_1` CHECK ((`amount_sent` > 0)),
  CONSTRAINT `currency_transactions_chk_2` CHECK ((`exchange_rate` > 0)),
  CONSTRAINT `currency_transactions_chk_3` CHECK ((`amount_received` > 0)),
  CONSTRAINT `currency_transactions_chk_4` CHECK ((`from_currency` <> `to_currency`))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exchange_rates`
--

DROP TABLE IF EXISTS `exchange_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `base_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exchange_rate` decimal(18,8) NOT NULL,
  `rate_date` date NOT NULL,
  `fetched_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_currency_pair_date` (`base_currency`,`target_currency`,`rate_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `alert_id` bigint unsigned NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_alert_notification` (`alert_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`alert_id`) REFERENCES `currency_alerts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `portfolio_history`
--

DROP TABLE IF EXISTS `portfolio_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portfolio_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `total_value_usd` decimal(18,8) NOT NULL,
  `recorded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_portfolio_user_date` (`user_id`,`recorded_at`),
  CONSTRAINT `portfolio_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `portfolio_history_chk_1` CHECK ((`total_value_usd` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `portfolio_holdings`
--

DROP TABLE IF EXISTS `portfolio_holdings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portfolio_holdings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `stock_id` bigint unsigned NOT NULL,
  `shares` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `average_buy_price` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_stock` (`user_id`,`stock_id`),
  KEY `stock_id` (`stock_id`),
  CONSTRAINT `portfolio_holdings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `portfolio_holdings_ibfk_2` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `portfolio_holdings_chk_1` CHECK ((`shares` >= 0)),
  CONSTRAINT `portfolio_holdings_chk_2` CHECK ((`average_buy_price` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `session_key` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sessions_key` (`session_key`),
  KEY `ix_sessions_user_id` (`user_id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stocks`
--

DROP TABLE IF EXISTS `stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `symbol` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_price` decimal(18,8) NOT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol` (`symbol`),
  CONSTRAINT `stocks_chk_1` CHECK ((`current_price` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trades`
--

DROP TABLE IF EXISTS `trades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trades` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `stock_id` bigint unsigned NOT NULL,
  `trade_type` enum('BUY','SELL') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(18,8) NOT NULL,
  `price_per_share` decimal(18,8) NOT NULL,
  `total_amount` decimal(18,8) NOT NULL,
  `executed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `stock_id` (`stock_id`),
  KEY `idx_user_trades` (`user_id`,`executed_at`),
  CONSTRAINT `trades_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trades_ibfk_2` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `trades_chk_1` CHECK ((`quantity` > 0)),
  CONSTRAINT `trades_chk_2` CHECK ((`price_per_share` > 0)),
  CONSTRAINT `trades_chk_3` CHECK ((`total_amount` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(254) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wallets`
--

DROP TABLE IF EXISTS `wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_currency` (`user_id`,`currency`),
  CONSTRAINT `wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wallets_chk_1` CHECK ((`balance` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `virtual_fund_refills`
--

DROP TABLE IF EXISTS `virtual_fund_refills`;
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

--
-- Dumping routines for database 'auth_project'
--
/*!50003 DROP PROCEDURE IF EXISTS `calculate_portfolio_value` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `calculate_portfolio_value`(
    IN p_user_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_total DECIMAL(18,8) DEFAULT 0;
    DECLARE v_stock_total DECIMAL(18,8) DEFAULT 0;
    DECLARE v_missing_rates INT DEFAULT 0;
    DECLARE v_missing_prices INT DEFAULT 0;

    -- Check for missing exchange rates
    SELECT COUNT(*)
    INTO v_missing_rates
    FROM wallets AS w
    WHERE w.user_id = p_user_id
      AND w.balance > 0
      AND w.currency <> 'USD'
      AND NOT EXISTS (
          SELECT 1
          FROM exchange_rates AS er
          WHERE er.base_currency = w.currency
            AND er.target_currency = 'USD'
      );

    IF v_missing_rates > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Missing exchange rates for portfolio valuation';
    END IF;

    -- Calculate currency wallet value in USD
    SELECT COALESCE(SUM(
        CASE
            WHEN w.currency = 'USD' THEN w.balance
            WHEN w.balance = 0 THEN 0
            ELSE w.balance * (
                SELECT er.exchange_rate
                FROM exchange_rates AS er
                WHERE er.base_currency = w.currency
                  AND er.target_currency = 'USD'
                ORDER BY er.rate_date DESC, er.fetched_at DESC, er.id DESC
                LIMIT 1
            )
        END
    ), 0)
    INTO v_total
    FROM wallets AS w
    WHERE w.user_id = p_user_id;

    -- Check for missing stock prices
    SELECT COUNT(*)
    INTO v_missing_prices
    FROM portfolio_holdings AS ph
    LEFT JOIN stocks AS s ON ph.stock_id = s.id
    WHERE ph.user_id = p_user_id
      AND ph.shares > 0
      AND (s.id IS NULL OR s.current_price IS NULL);

    IF v_missing_prices > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Missing stock prices for portfolio valuation';
    END IF;

    -- Calculate total stock holdings value
    SELECT COALESCE(SUM(
        ph.shares * s.current_price
    ), 0)
    INTO v_stock_total
    FROM portfolio_holdings AS ph
    JOIN stocks AS s ON ph.stock_id = s.id
    WHERE ph.user_id = p_user_id;

    -- Add stock value to currency wallet value
    SET v_total = v_total + v_stock_total;

    -- Save portfolio value to history
    INSERT INTO portfolio_history (
        user_id,
        total_value_usd
    )
    VALUES (
        p_user_id,
        v_total
    );

    -- Display portfolio value
    SELECT
        p_user_id AS user_id,
        v_total AS total_portfolio_value_usd;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `check_currency_alerts` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `check_currency_alerts`(
    IN p_base_currency VARCHAR(3),
    IN p_target_currency VARCHAR(3),
    IN p_current_rate DECIMAL(18,8)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF p_current_rate IS NULL OR p_current_rate <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Exchange rate must be positive';
    END IF;

    IF p_base_currency IS NULL
       OR p_target_currency IS NULL
       OR p_base_currency = p_target_currency THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid currency pair';
    END IF;

    START TRANSACTION;

    INSERT INTO notifications (
        user_id,
        alert_id,
        message
    )
    SELECT
        ca.user_id,
        ca.id,
        CONCAT(
            'Currency Alert: ',
            ca.base_currency,
            '/',
            ca.target_currency,
            ' reached ',
            p_current_rate,
            '. Your target was ',
            ca.target_rate
        )
    FROM currency_alerts AS ca
    WHERE ca.is_active = TRUE
      AND ca.base_currency = p_base_currency
      AND ca.target_currency = p_target_currency
      AND (
          (ca.condition_type = 'above'
           AND p_current_rate >= ca.target_rate)
          OR
          (ca.condition_type = 'below'
           AND p_current_rate <= ca.target_rate)
      )
    ON DUPLICATE KEY UPDATE
        message = notifications.message;

    UPDATE currency_alerts AS ca
    SET ca.is_active = FALSE
    WHERE ca.is_active = TRUE
      AND ca.base_currency = p_base_currency
      AND ca.target_currency = p_target_currency
      AND (
          (ca.condition_type = 'above'
           AND p_current_rate >= ca.target_rate)
          OR
          (ca.condition_type = 'below'
           AND p_current_rate <= ca.target_rate)
      );

    COMMIT;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `convert_currency` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `convert_currency`(
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `execute_stock_trade` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `execute_stock_trade`(
    IN p_user_id BIGINT UNSIGNED,
    IN p_stock_id BIGINT UNSIGNED,
    IN p_trade_type VARCHAR(4),
    IN p_quantity DECIMAL(18,8)
)
BEGIN
    DECLARE v_price DECIMAL(18,8);
    DECLARE v_total DECIMAL(18,8);
    DECLARE v_balance DECIMAL(18,8);
    DECLARE v_shares DECIMAL(18,8);
    DECLARE v_average_price DECIMAL(18,8);
    DECLARE v_new_average DECIMAL(18,8);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF p_quantity IS NULL OR p_quantity <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Quantity must be greater than zero';
    END IF;

    IF p_trade_type IS NULL
       OR p_trade_type NOT IN ('BUY', 'SELL') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Trade type must be BUY or SELL';
    END IF;

    START TRANSACTION;

    -- Get the stock price
    SELECT current_price
    INTO v_price
    FROM stocks
    WHERE id = p_stock_id;

    IF v_price IS NULL OR v_price <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid stock price';
    END IF;

    SET v_total = ROUND(v_price * p_quantity, 8);

    -- Lock the user's USD wallet
    SELECT balance
    INTO v_balance
    FROM wallets
    WHERE user_id = p_user_id
      AND currency = 'USD'
    FOR UPDATE;

    IF v_balance IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'USD wallet not found';
    END IF;

    -- Get existing stock holdings
    SELECT shares, average_buy_price
    INTO v_shares, v_average_price
    FROM portfolio_holdings
    WHERE user_id = p_user_id
      AND stock_id = p_stock_id
    FOR UPDATE;

    SET v_shares = COALESCE(v_shares, 0);
    SET v_average_price = COALESCE(v_average_price, 0);

    IF p_trade_type = 'BUY' THEN

        IF v_balance < v_total THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Insufficient virtual funds';
        END IF;

        SET v_new_average =
            ((v_shares * v_average_price)
            + (p_quantity * v_price))
            / (v_shares + p_quantity);

        UPDATE wallets
        SET balance = balance - v_total
        WHERE user_id = p_user_id
          AND currency = 'USD';

        INSERT INTO portfolio_holdings (
            user_id, stock_id, shares, average_buy_price
        )
        VALUES (
            p_user_id, p_stock_id, p_quantity, v_price
        )
        ON DUPLICATE KEY UPDATE
            shares = shares + p_quantity,
            average_buy_price = v_new_average;

    ELSEIF p_trade_type = 'SELL' THEN

        IF v_shares < p_quantity THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Insufficient shares';
        END IF;

        UPDATE portfolio_holdings
        SET shares = shares - p_quantity
        WHERE user_id = p_user_id
          AND stock_id = p_stock_id;

        UPDATE wallets
        SET balance = balance + v_total
        WHERE user_id = p_user_id
          AND currency = 'USD';

    END IF;

    INSERT INTO trades (
        user_id,
        stock_id,
        trade_type,
        quantity,
        price_per_share,
        total_amount
    )
    VALUES (
        p_user_id,
        p_stock_id,
        p_trade_type,
        p_quantity,
        v_price,
        v_total
    );

    COMMIT;

    SELECT
        'Trade executed successfully' AS message,
        p_trade_type AS trade_type,
        p_quantity AS quantity,
        v_price AS price_per_share,
        v_total AS total_amount;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-22 11:20:03

--
-- Procedure `refill_virtual_funds`
--

DROP PROCEDURE IF EXISTS `refill_virtual_funds`;
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
