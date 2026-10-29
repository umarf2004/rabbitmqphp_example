<?php

require_once __DIR__ . "/login.php.inc";
require_once __DIR__ . "/stockDB.php.inc";

class tradingDB
{
    private $db;
    private $login;
    private $stocks;

    public function __construct()
    {
        $config = parse_ini_file(__DIR__ . "/db.local.ini", true);

        if ($config === false || !isset($config["database"])) {
            throw new Exception("Could not read database configuration");
        }

        $settings = $config["database"];

        $this->db = new mysqli(
            $settings["HOST"],
            $settings["USER"],
            $settings["PASSWORD"],
            $settings["DATABASE"]
        );

        $this->db->set_charset("utf8mb4");

        $this->login = new loginDB();
        $this->stocks = new stockDB();
    }

    // Get the authenticated user ID from the session.
    // Never trust a user_id supplied by the Webserver.
    private function authenticate($sessionId)
    {
        if (!is_string($sessionId) || trim($sessionId) === "") {
            return null;
        }

        $result = $this->login->validateSession($sessionId);

        if (
            !is_array($result) ||
            empty($result["success"]) ||
            !isset($result["user_id"])
        ) {
            return null;
        }

        return (int) $result["user_id"];
    }

    // Accept positive quantities with up to 8 decimal places.
    private function normalizeQuantity($quantity)
    {
        if (!is_string($quantity) && !is_int($quantity)) {
            return null;
        }

        $quantity = trim((string) $quantity);

        if (!preg_match('/^\d{1,10}(\.\d{1,8})?$/', $quantity)) {
            return null;
        }

        if ((float) $quantity <= 0) {
            return null;
        }

        return $quantity;
    }

    // Retrieve a fresh available price from the existing DMZ worker.
    private function getExecutionQuote($ticker)
    {
        $quote = $this->stocks->fetchStockFromDMZ($ticker);

        if (empty($quote["success"])) {
            return [
                "success" => false,
                "message" => $quote["message"] ?? "Stock quote unavailable"
            ];
        }

        $price = $quote["current_price"] ?? null;

        if (
            !is_numeric($price) ||
            !is_finite((float) $price) ||
            (float) $price <= 0 ||
            (float) $price >= 10000000000
        ) {
            return [
                "success" => false,
                "message" => "Invalid execution price"
            ];
        }

        return [
            "success" => true,
            "ticker" => $quote["ticker"],
            "company_name" => $quote["company_name"],
            "price" => number_format((float) $price, 8, ".", ""),
            "price_type" => $quote["price_type"] ?? "previous_close"
        ];
    }

    public function getWallet($sessionId)
    {
        $userId = $this->authenticate($sessionId);

        if ($userId === null) {
            return ["success" => false, "message" => "Invalid session"];
        }

        try {
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
        } catch (Throwable $e) {
            error_log("Get wallet failed: " . $e->getMessage());

            return [
                "success" => false,
                "message" => "Unable to retrieve wallet"
            ];
        }
    }

    public function buyStock($sessionId, $ticker, $quantity)
    {
        return $this->executeTrade(
            $sessionId,
            $ticker,
            $quantity,
            "BUY"
        );
    }

    public function sellStock($sessionId, $ticker, $quantity)
    {
        return $this->executeTrade(
            $sessionId,
            $ticker,
            $quantity,
            "SELL"
        );
    }

    private function executeTrade($sessionId, $ticker, $quantity, $type)
    {
        $userId = $this->authenticate($sessionId);

        if ($userId === null) {
            return ["success" => false, "message" => "Invalid session"];
        }

        $quantity = $this->normalizeQuantity($quantity);

        if ($quantity === null) {
            return [
                "success" => false,
                "message" => "Quantity must be positive with at most 8 decimal places"
            ];
        }

        // Do not hold database locks while waiting for the DMZ.
        $quote = $this->getExecutionQuote($ticker);

        if (!$quote["success"]) {
            return $quote;
        }

        $symbol = $quote["ticker"];
        $companyName = $quote["company_name"];
        $price = $quote["price"];

        try {
            $this->db->begin_transaction();

            // Serialize trades and virtual-fund refills for this user.
            $stmt = $this->db->prepare(
                "SELECT id FROM users WHERE id = ? FOR UPDATE"
            );
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $lockedUser = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$lockedUser) {
                throw new DomainException("User not found");
            }

            // Lock the USD wallet after the user record.
            // All trades for this user will acquire locks in this order.
            $stmt = $this->db->prepare(
                "SELECT balance
                 FROM wallets
                 WHERE user_id = ? AND currency = 'USD'
                 FOR UPDATE"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $wallet = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$wallet) {
                throw new DomainException("USD wallet not found");
            }

            // Calculate trade value using MySQL DECIMAL arithmetic.
            $stmt = $this->db->prepare(
                "SELECT ROUND(
                    CAST(? AS DECIMAL(18,8)) *
                    CAST(? AS DECIMAL(18,8)), 8
                ) AS total"
            );

            $stmt->bind_param("ss", $quantity, $price);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()["total"];
            $stmt->close();

            if ($total === null || (float) $total <= 0) {
                throw new DomainException(
                    "Trade amount is too small or invalid"
                );
            }

            // Ensure the stock exists and update its simulated price.
            $stmt = $this->db->prepare(
                "INSERT INTO stocks
                    (symbol, company_name, current_price)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    company_name = VALUES(company_name),
                    current_price = VALUES(current_price),
                    last_updated = CURRENT_TIMESTAMP"
            );

            $stmt->bind_param("sss", $symbol, $companyName, $price);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->db->prepare(
                "SELECT id FROM stocks WHERE symbol = ?"
            );

            $stmt->bind_param("s", $symbol);
            $stmt->execute();
            $stock = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$stock) {
                throw new RuntimeException("Stock record not found");
            }

            $stockId = (int) $stock["id"];

            // Lock the user's existing holding, if any.
            $stmt = $this->db->prepare(
                "SELECT shares, average_buy_price
                 FROM portfolio_holdings
                 WHERE user_id = ? AND stock_id = ?
                 FOR UPDATE"
            );

            $stmt->bind_param("ii", $userId, $stockId);
            $stmt->execute();
            $holding = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($type === "BUY") {
                // The WHERE condition prevents overspending.
                $stmt = $this->db->prepare(
                    "UPDATE wallets
                     SET balance = balance - CAST(? AS DECIMAL(18,8))
                     WHERE user_id = ?
                       AND currency = 'USD'
                       AND balance >= CAST(? AS DECIMAL(18,8))"
                );

                $stmt->bind_param("sis", $total, $userId, $total);
                $stmt->execute();
                $updated = $stmt->affected_rows;
                $stmt->close();

                if ($updated !== 1) {
                    throw new DomainException("Insufficient USD balance");
                }

                if ($holding) {
                    // Weighted average purchase price.
                    $stmt = $this->db->prepare(
                        "UPDATE portfolio_holdings
                         SET average_buy_price = ROUND(
                             (
                                 shares * average_buy_price +
                                 CAST(? AS DECIMAL(18,8)) *
                                 CAST(? AS DECIMAL(18,8))
                             ) /
                             (shares + CAST(? AS DECIMAL(18,8))),
                             8
                         ),
                         shares = shares + CAST(? AS DECIMAL(18,8))
                         WHERE user_id = ? AND stock_id = ?"
                    );

                    $stmt->bind_param(
                        "ssssii",
                        $quantity,
                        $price,
                        $quantity,
                        $quantity,
                        $userId,
                        $stockId
                    );
                } else {
                    $stmt = $this->db->prepare(
                        "INSERT INTO portfolio_holdings
                            (user_id, stock_id, shares, average_buy_price)
                         VALUES (?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "iiss",
                        $userId,
                        $stockId,
                        $quantity,
                        $price
                    );
                }

                $stmt->execute();
                $stmt->close();

            } else {
                if (
                    !$holding ||
                    (float) $holding["shares"] < (float) $quantity
                ) {
                    throw new DomainException("Insufficient shares");
                }

                $stmt = $this->db->prepare(
                    "UPDATE portfolio_holdings
                     SET shares = shares - CAST(? AS DECIMAL(18,8))
                     WHERE user_id = ?
                       AND stock_id = ?
                       AND shares >= CAST(? AS DECIMAL(18,8))"
                );

                $stmt->bind_param(
                    "siis",
                    $quantity,
                    $userId,
                    $stockId,
                    $quantity
                );
                $stmt->execute();
                $updated = $stmt->affected_rows;
                $stmt->close();

                if ($updated !== 1) {
                    throw new DomainException("Insufficient shares");
                }

                // Remove fully sold positions.
                $stmt = $this->db->prepare(
                    "DELETE FROM portfolio_holdings
                     WHERE user_id = ?
                       AND stock_id = ?
                       AND shares = 0"
                );

                $stmt->bind_param("ii", $userId, $stockId);
                $stmt->execute();
                $stmt->close();

                $stmt = $this->db->prepare(
                    "UPDATE wallets
                     SET balance = balance + CAST(? AS DECIMAL(18,8))
                     WHERE user_id = ? AND currency = 'USD'"
                );

                $stmt->bind_param("si", $total, $userId);
                $stmt->execute();
                $stmt->close();
            }

            // Record the completed simulated transaction.
            $stmt = $this->db->prepare(
                "INSERT INTO trades
                    (user_id, stock_id, trade_type,
                     quantity, price_per_share, total_amount)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iissss",
                $userId,
                $stockId,
                $type,
                $quantity,
                $price,
                $total
            );

            $stmt->execute();
            $tradeId = $this->db->insert_id;
            $stmt->close();

            $stmt = $this->db->prepare(
                "SELECT balance FROM wallets
                 WHERE user_id = ? AND currency = 'USD'"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $newBalance = $stmt->get_result()->fetch_assoc()["balance"];
            $stmt->close();

// Create a notification for the completed trade.
$notificationMessage = sprintf(
    "%s %s shares of %s at $%s per share.",
    $type === "BUY" ? "Purchased" : "Sold",
    $quantity,
    $symbol,
    $price
);

$notificationType = "trade_executed";

$stmt = $this->db->prepare(
    "INSERT INTO notifications
        (user_id, trade_id, notification_type, message)
     VALUES (?, ?, ?, ?)"
);

$stmt->bind_param(
    "iiss",
    $userId,
    $tradeId,
    $notificationType,
    $notificationMessage
);

$stmt->execute();
$stmt->close();

$this->db->commit();

            return [
                "success" => true,
                "message" => $type === "BUY"
                    ? "Stock purchase completed"
                    : "Stock sale completed",
                "trade_id" => (int) $tradeId,
                "user_id" => $userId,
                "stock_id" => $stockId,
                "ticker" => $symbol,
                "trade_type" => $type,
                "quantity" => $quantity,
                "price_per_share" => $price,
                "total_amount" => $total,
                "balance" => $newBalance,
                "currency" => "USD",
                "price_type" => $quote["price_type"]
            ];

        } catch (Throwable $e) {
            try {
                $this->db->rollback();
            } catch (Throwable $rollbackError) {
                error_log(
                    "Trade rollback failed: " .
                    $rollbackError->getMessage()
                );
            }

            if ($e instanceof DomainException) {
                return [
                    "success" => false,
                    "message" => $e->getMessage()
                ];
            }

            error_log("Trade failed: " . $e->getMessage());

            return [
                "success" => false,
                "message" => "Unable to complete trade"
            ];
        }
    }

    public function getPortfolio($sessionId)
    {
        $userId = $this->authenticate($sessionId);

        if ($userId === null) {
            return ["success" => false, "message" => "Invalid session"];
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT
                    s.id AS stock_id,
                    s.symbol AS ticker,
                    s.company_name,
                    h.shares,
                    h.average_buy_price,
                    s.current_price,
                    ROUND(h.shares * s.current_price, 8)
                        AS market_value,
                    ROUND(
                        h.shares *
                        (s.current_price - h.average_buy_price),
                        8
                    ) AS unrealized_profit_loss,
                    s.last_updated
                 FROM portfolio_holdings h
                 JOIN stocks s ON s.id = h.stock_id
                 WHERE h.user_id = ? AND h.shares > 0
                 ORDER BY s.symbol"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $holdings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return [
                "success" => true,
                "user_id" => $userId,
                "holdings" => $holdings,
                "valuation_note" =>
                    "Values use stored stock prices, which may be delayed"
            ];
        } catch (Throwable $e) {
            error_log("Get portfolio failed: " . $e->getMessage());

            return [
                "success" => false,
                "message" => "Unable to retrieve portfolio"
            ];
        }
    }

    public function getTradeHistory($sessionId)
    {
        $userId = $this->authenticate($sessionId);

        if ($userId === null) {
            return ["success" => false, "message" => "Invalid session"];
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT
                    t.id AS trade_id,
                    s.symbol AS ticker,
                    t.trade_type,
                    t.quantity,
                    t.price_per_share,
                    t.total_amount,
                    t.executed_at
                 FROM trades t
                 JOIN stocks s ON s.id = t.stock_id
                 WHERE t.user_id = ?
                 ORDER BY t.executed_at DESC, t.id DESC
                 LIMIT 100"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $trades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return [
                "success" => true,
                "user_id" => $userId,
                "trades" => $trades
            ];
        } catch (Throwable $e) {
            error_log("Get trade history failed: " . $e->getMessage());

            return [
                "success" => false,
                "message" => "Unable to retrieve trade history"
            ];
        }
    }

public function getPortfolioHistory($sessionId)
{
    $userId = $this->authenticate($sessionId);

    if ($userId === null) {
        return [
            "success" => false,
            "message" => "Invalid session"
        ];
    }

    try {
        $stmt = $this->db->prepare(
            "SELECT
                DATE(recorded_at) AS date,
                total_value_usd AS value_usd
             FROM portfolio_history
             WHERE user_id = ?
             ORDER BY recorded_at ASC, id ASC"
        );

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

	$stmt->close();

	return [
            "success" => true,
            "history" => $history
      ];

    } catch (Throwable $error) {
        error_log(
            "Portfolio history error: " . $error->getMessage()
        );

        return [
            "success" => false,
            "message" => "Unable to retrieve portfolio history"
        ];
    }
}

    
}
