<?php

class stockDB
{
    private $db;

    public function __construct()
    {
        // Use the same database configuration as login.php.inc
        $configFile = __DIR__ . "/db.local.ini";
        $config = parse_ini_file($configFile, true);

        if ($config === false || !isset($config["database"])) {
            throw new Exception("Could not read db.local.ini");
        }

        $settings = $config["database"];

        $this->db = new mysqli(
            $settings["HOST"],
            $settings["USER"],
            $settings["PASSWORD"],
            $settings["DATABASE"]
        );

        if ($this->db->connect_errno) {
            throw new Exception("Stock database connection failed");
        }

        $this->db->set_charset("utf8mb4");
    }

    // Validate and normalize stock ticker symbols
    private function normalizeTicker($ticker)
    {
        if (!is_string($ticker)) {
            return null;
        }

        $ticker = strtoupper(trim($ticker));

        // Match the existing stocks.symbol VARCHAR(10) column
        if (!preg_match('/^[A-Z0-9.\-]{1,10}$/', $ticker)) {
            return null;
        }

        return $ticker;
    }
    // Retrieve a stock from MySQL and check its cache age
    public function getCachedStock($ticker)
    {
        $ticker = $this->normalizeTicker($ticker);

        if ($ticker === null) {
            return [
                "success" => false,
                "message" => "Invalid stock ticker"
            ];
        }

        $stmt = $this->db->prepare(
            "SELECT
                id,
                symbol,
                company_name,
                current_price,
                last_updated,
                (last_updated >= NOW() - INTERVAL 5 MINUTE) AS is_fresh
             FROM stocks
             WHERE symbol = ?"
        );

        $stmt->bind_param("s", $ticker);
        $stmt->execute();

        $result = $stmt->get_result();
        $stock = $result->fetch_assoc();

        $stmt->close();

        if (!$stock) {
            return [
                "success" => true,
                "ticker" => $ticker,
                "found" => false,
                "cached" => false
            ];
        }

        return [
            "success" => true,
            "ticker" => $ticker,
            "found" => true,
            "cached" => true,
            "fresh" => (bool) $stock["is_fresh"],
            "stock" => [
                "id" => (int) $stock["id"],
                "symbol" => $stock["symbol"],
                "company_name" => $stock["company_name"],
                "current_price" => $stock["current_price"],
                "last_updated" => $stock["last_updated"]
            ]
        ];
    }
    // Request fresh stock information from the DMZ through RabbitMQ
    public function fetchStockFromDMZ($ticker)
    {
        $ticker = $this->normalizeTicker($ticker);

        if ($ticker === null) {
            return [
                "success" => false,
                "message" => "Invalid stock ticker"
            ];
        }

        require_once __DIR__ . "/path.inc";
        require_once __DIR__ . "/get_host_info.inc";
        require_once __DIR__ . "/rabbitMQLib.inc";

        try {
            $client = new rabbitMQClient(
                __DIR__ . "/rabbitmq.local.ini",
                "massiveWorker"
            );

            // Request company information
            $companyResponse = $client->send_request([
                "type" => "fetch_stock",
                "ticker" => $ticker
            ], 20);
             error_log("DMZ COMPANY RESPONSE: " . json_encode($companyResponse));

// Normalize DMZ response to the format expected by this function.
if (
    is_array($companyResponse) &&
    !isset($companyResponse["data"]) &&
    isset($companyResponse["name"])
) {
    $companyResponse["data"] = $companyResponse;
}

            if (
                !is_array($companyResponse) ||
                empty($companyResponse["success"]) ||
                !isset($companyResponse["data"]["name"])
            ) {
                return [
                    "success" => false,
                    "message" => "Unable to retrieve company information"
                ];
            }

            // Request the previous trading day's closing price
            $priceResponse = $client->send_request([
                "type" => "fetch_stock_price",
                "ticker" => $ticker
            ], 20);

            if (
                !is_array($priceResponse) ||
                empty($priceResponse["success"]) ||
                !isset($priceResponse["price"]) ||
                !is_numeric($priceResponse["price"])
            ) {
                return [
                    "success" => false,
                    "message" => "Unable to retrieve stock price"
                ];
            }

            $companyName = trim($companyResponse["data"]["name"]);
            $price = (float) $priceResponse["price"];

            if (
                $companyName === "" ||
                strlen($companyName) > 150 ||
                !is_finite($price) ||
                $price <= 0
            ) {
                return [
                    "success" => false,
                    "message" => "Invalid stock data received from DMZ"
                ];
            }

            return [
                "success" => true,
                "ticker" => $ticker,
                "company_name" => $companyName,
                "current_price" => $price,
                "price_type" => "previous_close",
                "fetched_at" => $priceResponse["fetched_at"] ?? null
            ];

        } catch (Throwable $e) {
            error_log("Stock DMZ request failed: " . $e->getMessage());

            return [
                "success" => false,
                "message" => "Stock data service unavailable"
            ];
        }
    }
    // Get stock information using MySQL cache and DMZ
    public function getStock($ticker)
    {
        // Check the local MySQL cache first
        $cached = $this->getCachedStock($ticker);

        if (!$cached["success"]) {
            return $cached;
        }

        // Return cached data if it is less than 5 minutes old
        if ($cached["found"] && $cached["fresh"]) {
            return [
                "success" => true,
                "ticker" => $cached["ticker"],
                "source" => "cache",
                "stock" => $cached["stock"]
            ];
        }

        // Cache is missing or outdated, so request DMZ data
        $fresh = $this->fetchStockFromDMZ($cached["ticker"]);

        if ($fresh["success"]) {
		// Save new stocks without overwriting existing simulated prices
            $saved = $this->saveStockToCache(
                $fresh["ticker"],
                $fresh["company_name"],
                $fresh["current_price"]
            );

            if (!$saved["success"]) {
                error_log("Could not cache stock: " . $fresh["ticker"]);
            }
            return [
                "success" => true,
                "ticker" => $fresh["ticker"],
                "source" => "dmz",
                "stock" => [
                    "symbol" => $fresh["ticker"],
                    "company_name" => $fresh["company_name"],
                    "current_price" => $fresh["current_price"]
                ],
                "price_type" => $fresh["price_type"]
            ];
        }

        // If DMZ fails, return outdated cache data if available
        if ($cached["found"]) {
            return [
                "success" => true,
                "ticker" => $cached["ticker"],
                "source" => "stale_cache",
                "stale" => true,
                "stock" => $cached["stock"]
            ];
        }

        // No cached data and DMZ unavailable
        return [
            "success" => false,
            "ticker" => $cached["ticker"],
            "message" => "Stock information is currently unavailable"
        ];
    }
    // Save a new stock to MySQL without overwriting existing stocks
    public function saveStockToCache($ticker, $companyName, $price)
    {
        $ticker = $this->normalizeTicker($ticker);

        if (
            $ticker === null ||
            !is_string($companyName) ||
            trim($companyName) === "" ||
            strlen($companyName) > 150 ||
            !is_numeric($price) ||
            !is_finite((float) $price) ||
            (float) $price <= 0
        ) {
            return [
                "success" => false,
                "message" => "Invalid stock data"
            ];
        }

        $companyName = trim($companyName);
        $price = (float) $price;

        try {
            // IGNORE prevents duplicate symbols from being overwritten
            $stmt = $this->db->prepare(
                "INSERT IGNORE INTO stocks
                    (symbol, company_name, current_price)
                 VALUES (?, ?, ?)"
            );

            $stmt->bind_param("ssd", $ticker, $companyName, $price);
            $stmt->execute();

            $inserted = $stmt->affected_rows === 1;
            $stmt->close();

            return [
                "success" => true,
                "ticker" => $ticker,
                "inserted" => $inserted
            ];

        } catch (Throwable $e) {
            error_log("Stock cache save failed: " . $e->getMessage());

            return [
                "success" => false,
                "message" => "Unable to save stock data"
            ];
        }
    }
// Synchronize real historical stock prices from the DMZ.
public function syncStockHistory($ticker)
{
    $ticker = $this->normalizeTicker($ticker);

    if ($ticker === null) {
        return ["success" => false, "message" => "Invalid stock ticker"];
    }

    $stmt = $this->db->prepare(
        "SELECT id FROM stocks WHERE symbol = ?"
    );
    $stmt->bind_param("s", $ticker);
    $stmt->execute();
    $stock = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$stock) {
        return ["success" => false, "message" => "Stock not found"];
    }

    $stockId = (int) $stock["id"];

    // Reuse historical prices fetched within the last five minutes.
    $stmt = $this->db->prepare(
        "SELECT h.price
         FROM stock_history_cache c
         JOIN stock_price_history h ON h.stock_id = c.stock_id
         WHERE c.stock_id = ?
           AND c.last_fetched_at >= NOW() - INTERVAL 5 MINUTE
         ORDER BY h.recorded_at DESC
         LIMIT 5"
    );
    $stmt->bind_param("i", $stockId);
    $stmt->execute();
    $cachedPrices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($cachedPrices) === 5) {
        return [
            "success" => true,
            "ticker" => $ticker,
            "source" => "cache",
            "saved_prices" => 5,
            "latest_price" => (float) $cachedPrices[0]["price"]
        ];
    }

    require_once __DIR__ . "/path.inc";
    require_once __DIR__ . "/get_host_info.inc";
    require_once __DIR__ . "/rabbitMQLib.inc";

    try {
        $client = new rabbitMQClient(
            __DIR__ . "/rabbitmq.local.ini",
            "massiveWorker"
        );

        $response = $client->send_request([
            "type" => "fetch_stock_history",
            "ticker" => $ticker
        ], 20);

        if (
            !is_array($response) ||
            empty($response["success"]) ||
            empty($response["history"]) ||
            !is_array($response["history"])
        ) {
            return [
                "success" => false,
                "message" => "Historical stock data unavailable"
            ];
        }

        $rows = [];

        foreach ($response["history"] as $entry) {
            $price = $entry["price"] ?? $entry["close"] ?? null;
            $recordedAt = $entry["recorded_at"] ?? null;

            if ($recordedAt === null && isset($entry["date"]) &&
                is_string($entry["date"]) &&
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry["date"])) {
                $recordedAt = $entry["date"] . " 00:00:00";
            }

            if (
                !is_numeric($price) ||
                !is_finite((float) $price) ||
                (float) $price <= 0 ||
                !is_string($recordedAt) ||
                !preg_match(
                    '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
                    $recordedAt
                )
            ) {
                continue;
            }

            $rows[] = [
                "price" => (float) $price,
                "recorded_at" => $recordedAt
            ];
        }

        if (count($rows) < 5) {
            return [
                "success" => false,
                "message" => "Insufficient historical stock data"
            ];
        }

        usort(
            $rows,
            fn($a, $b) => strcmp(
                $a["recorded_at"],
                $b["recorded_at"]
            )
        );

        $stockId = (int) $stock["id"];

        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO stock_price_history
                    (stock_id, price, recorded_at)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE price = VALUES(price)"
            );

            foreach ($rows as $row) {
                $stmt->bind_param(
                    "ids",
                    $stockId,
                    $row["price"],
                    $row["recorded_at"]
                );
                $stmt->execute();
            }

            $stmt->close();

            $latest = end($rows);

            // Record when historical prices were successfully fetched.
            // Do not overwrite the stock price used by the Trade page.
            $stmt = $this->db->prepare(
                "INSERT INTO stock_history_cache (stock_id, last_fetched_at)
                 VALUES (?, NOW())
                 ON DUPLICATE KEY UPDATE last_fetched_at = NOW()"
            );
            $stmt->bind_param("i", $stockId);
            $stmt->execute();
            $stmt->close();

            $this->db->commit();

            return [
                "success" => true,
                "ticker" => $ticker,
                "saved_prices" => count($rows),
                "latest_price" => $latest["price"]
            ];

        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

    } catch (Throwable $e) {
        error_log("Stock history sync failed: " . $e->getMessage());

        return [
            "success" => false,
            "message" => "Unable to synchronize stock history"
        ];
    }
}
}
