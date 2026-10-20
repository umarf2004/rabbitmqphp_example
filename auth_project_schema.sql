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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
-- Dumping events for database 'auth_project'
--

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
    DECLARE v_missing_rates INT DEFAULT 0;

    -- Check whether any positive wallet balances lack exchange rates
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

    -- Calculate the total portfolio value using the latest rates
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

    -- Save the calculated portfolio value
    INSERT INTO portfolio_history (
        user_id,
        total_value_usd
    )
    VALUES (
        p_user_id,
        v_total
    );

    -- Display the result
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

    SELECT COUNT(*)
    INTO v_wallet_count
    FROM wallets
    WHERE user_id = p_user_id
      AND currency = p_to_currency;

    IF v_wallet_count = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Destination wallet does not exist';
    END IF;

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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-20 11:40:30
