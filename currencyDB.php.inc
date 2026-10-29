<?php

require_once __DIR__ . "/login.php.inc";

class currencyDB
{
    private mysqli $db;
    private loginDB $login;

    public function __construct()
    {
        $config = parse_ini_file(__DIR__ . "/db.local.ini", true);

        if ($config === false || !isset($config["database"])) {
            throw new Exception("Database configuration unavailable");
        }

        $settings = $config["database"];

        $this->db = new mysqli(
            $settings["HOST"],
            $settings["USER"],
            $settings["PASSWORD"],
            $settings["DATABASE"]
        );

        if ($this->db->connect_errno) {
            throw new Exception("Currency database connection failed");
        }

        $this->db->set_charset("utf8mb4");
        $this->login = new loginDB();
    }

    private function authenticate($sessionId): array
    {
        if (!is_string($sessionId)) {
            return ["success" => false, "message" => "Invalid session"];
        }

        return $this->login->validateSession($sessionId);
    }

    private function normalizeCurrency($currency): ?string
    {
        if (!is_string($currency)) {
            return null;
        }

        $currency = strtoupper(trim($currency));

        return preg_match('/^[A-Z]{3}$/', $currency)
            ? $currency
            : null;
    }

    private function normalizeAmount($amount): ?string
    {
        if (!is_string($amount) && !is_int($amount)) {
            return null;
        }

        $amount = trim((string) $amount);

        if (!preg_match('/^\d{1,9}(?:\.\d{1,8})?$/', $amount)) {
            return null;
        }

        if (!preg_match('/[1-9]/', $amount)) {
            return null;
        }

        return $amount;
    }

    public function getWallets($sessionId): array
    {
        $session = $this->authenticate($sessionId);

        if (empty($session["success"])) {
            return $session;
        }

        $userId = $session["user_id"];

        $stmt = $this->db->prepare(
            "SELECT currency, balance
             FROM wallets
             WHERE user_id = ?
             ORDER BY currency"
        );

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $wallets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            "success" => true,
            "user_id" => $userId,
            "wallets" => $wallets
        ];
    }

    public function getCurrencyTransactions($sessionId): array
    {
        $session = $this->authenticate($sessionId);

        if (empty($session["success"])) {
            return $session;
        }

        $userId = $session["user_id"];

        $stmt = $this->db->prepare(
            "SELECT id, from_currency, to_currency,
                    amount_sent, exchange_rate,
                    amount_received, transaction_date
             FROM currency_transactions
             WHERE user_id = ?
             ORDER BY transaction_date DESC, id DESC
             LIMIT 100"
        );

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            "success" => true,
            "user_id" => $userId,
            "transactions" => $transactions
        ];
    }

    private function fetchRateFromDMZ(string $from, string $to): array
    {
        require_once __DIR__ . "/path.inc";
        require_once __DIR__ . "/get_host_info.inc";
        require_once __DIR__ . "/rabbitMQLib.inc";

        try {
            $client = new rabbitMQClient(
                __DIR__ . "/rabbitmq.local.ini",
                "massiveWorker"
            );

            $response = $client->send_request([
                "type" => "get_fx_rate",
                "base" => $from,
                "quote" => $to
            ], 20);

            if (!is_array($response) || empty($response["success"])) {
                return [
                    "success" => false,
                    "message" => "Exchange-rate service unavailable"
                ];
            }

            /*
             * Expected DMZ response:
             * {
             *   "success": true,
             *   "base": "USD",
             *   "quote": "JPY",
             *   "rate": "150.12345678",
             *   "rate_date": "YYYY-MM-DD"
             * }
             *
             * Confirm this contract with the DMZ teammate.
             */
            if (
                ($response["base"] ?? null) !== $from ||
                ($response["quote"] ?? null) !== $to ||
                !isset($response["rate"], $response["rate_date"])
            ) {
                return [
                    "success" => false,
                    "message" => "Unexpected exchange-rate response"
                ];
            }

            $rate = (string) $response["rate"];
            $rateDate = $response["rate_date"];

            if (
                !preg_match('/^\d{1,10}(?:\.\d{1,8})?$/', $rate) ||
                !preg_match('/[1-9]/', $rate) ||
                !is_string($rateDate) ||
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rateDate)
            ) {
                return [
                    "success" => false,
                    "message" => "Invalid exchange-rate data"
                ];
            }

            // Do not execute trades using stale or future-dated rates.
            $today = new DateTimeImmutable("today");
            $quotedDate = DateTimeImmutable::createFromFormat(
                "!Y-m-d",
                $rateDate
            );

            if (
                $quotedDate === false ||
                $quotedDate->format("Y-m-d") !== $rateDate ||
                $quotedDate > $today ||
                $quotedDate < $today->modify("-4 days")
            ) {
                return [
                    "success" => false,
                    "message" => "Exchange rate is not recent enough"
                ];
            }

            return [
                "success" => true,
                "rate" => $rate,
                "rate_date" => $rateDate
            ];
        } catch (Throwable $error) {
            error_log("Currency DMZ request failed");

            return [
                "success" => false,
                "message" => "Exchange-rate service unavailable"
            ];
        }
    }

    public function convertCurrency(
        $sessionId,
        $fromCurrency,
        $toCurrency,
        $amount
    ): array {
        $session = $this->authenticate($sessionId);

        if (empty($session["success"])) {
            return $session;
        }

        $from = $this->normalizeCurrency($fromCurrency);
        $to = $this->normalizeCurrency($toCurrency);
        $amount = $this->normalizeAmount($amount);

        if ($from === null || $to === null || $from === $to) {
            return [
                "success" => false,
                "message" => "Invalid currency pair"
            ];
        }

        if ($amount === null) {
            return [
                "success" => false,
                "message" => "Invalid conversion amount"
            ];
        }

        $quote = $this->fetchRateFromDMZ($from, $to);

        if (empty($quote["success"])) {
            return $quote;
        }

        $userId = $session["user_id"];
        $rate = $quote["rate"];

        try {
            $stmt = $this->db->prepare(
                "CALL convert_currency(?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "issss",
                $userId,
                $from,
                $to,
                $amount,
                $rate
            );

            $stmt->execute();
            $stmt->close();

            // Clear any remaining stored-procedure result sets.
            while ($this->db->more_results()) {
                $this->db->next_result();
                $result = $this->db->store_result();

                if ($result !== false) {
                    $result->free();
                }
            }

            return [
                "success" => true,
                "message" => "Currency converted successfully",
                "user_id" => $userId,
                "from_currency" => $from,
                "to_currency" => $to,
                "amount_sent" => $amount,
                "exchange_rate" => $rate,
                "rate_date" => $quote["rate_date"]
            ];
        } catch (mysqli_sql_exception $error) {
            error_log("Currency conversion database operation failed");

            return [
                "success" => false,
                "message" => "Conversion failed: check funds and amount"
            ];
        }
    }
}
