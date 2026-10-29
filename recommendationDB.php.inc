<?php

require_once __DIR__ . "/login.php.inc";
require_once __DIR__ . "/stockDB.php.inc";

class recommendationDB
{
    private $db;
    private $login;

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
    }

    // Identify the user through their authenticated session.
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
    public function getRecommendations($sessionId)
    {
        $userId = $this->authenticate($sessionId);

        if ($userId === null) {
            return [
                "success" => false,
                "message" => "Invalid or expired session"
            ];
        }

        // Retrieve available virtual USD.
        $stmt = $this->db->prepare(
            "SELECT balance
             FROM wallets
             WHERE user_id = ? AND currency = 'USD'"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $wallet = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $usdBalance = (float) ($wallet["balance"] ?? 0);

        // Retrieve existing stock holdings.
        $stmt = $this->db->prepare(
            "SELECT s.symbol, h.shares
             FROM portfolio_holdings h
             JOIN stocks s ON s.id = h.stock_id
             WHERE h.user_id = ? AND h.shares > 0"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $holdings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

                // Get stocks and their five most recent historical prices.
        $stocks = $this->db->query(
            "SELECT id, symbol, current_price, last_updated
             FROM stocks
             ORDER BY symbol"
        )->fetch_all(MYSQLI_ASSOC);

        $recommendations = [];
$stockDb = new stockDB();

foreach ($stocks as $stock) {
    $stockId = (int) $stock["id"];
    $symbol = $stock["symbol"];

    // Retrieve real historical prices from Massive through RabbitMQ.
    $syncResult = $stockDb->syncStockHistory($symbol);

    if (empty($syncResult["success"])) {
        error_log(
            "Recommendation history sync failed for " . $symbol .
            ": " . ($syncResult["message"] ?? "Unknown error")
        );
        continue;
    }

    // Use the newly synchronized closing price.
    $stock["current_price"] = $syncResult["latest_price"];
    $stock["last_updated"] = date("Y-m-d H:i:s");

            $stmt = $this->db->prepare(
                "SELECT price, recorded_at
                 FROM stock_price_history
                 WHERE stock_id = ?
                 ORDER BY recorded_at DESC
                 LIMIT 5"
            );
            $stmt->bind_param("i", $stockId);
            $stmt->execute();
            $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Require five historical observations.
            if (count($history) < 5) {
                continue;
            }

            // Do not use outdated market data.
            $latestTimestamp = strtotime($history[0]["recorded_at"]);
            $stockTimestamp = strtotime($stock["last_updated"]);

            if (
                $latestTimestamp === false ||
                $stockTimestamp === false ||
                time() - $latestTimestamp > 72 * 3600 ||
                time() - $stockTimestamp > 72 * 3600
            ) {
                continue;
            }

            $prices = array_map(
                fn($row) => (float) $row["price"],
                $history
            );

            if (min($prices) <= 0) {
                continue;
            }

            $average = array_sum($prices) / count($prices);
            $currentPrice = (float) $stock["current_price"];

            if ($currentPrice <= 0 || $average <= 0) {
                continue;
            }

            $changePercent = (($currentPrice - $average) / $average) * 100;

            $ownedShares = 0.0;

            foreach ($holdings as $holding) {
                if ($holding["symbol"] === $symbol) {
                    $ownedShares = (float) $holding["shares"];
                    break;
                }
            }

            $action = "HOLD";
            $quantity = 0;
            $reason = "Price is close to its recent five-price average.";

            if ($changePercent >= 2 && $usdBalance >= $currentPrice) {
                $quantity = (int) floor(
                    ($usdBalance * 0.10) / $currentPrice
                );

                if ($quantity > 0) {
                    $action = "BUY";
                    $reason = sprintf(
                        "Price is %.2f%% above its recent average.",
                        $changePercent
                    );
                }
            } elseif ($changePercent <= -2 && $ownedShares > 0) {
                $action = "SELL";
                $quantity = (int) floor($ownedShares * 0.25);

                if ($quantity < 1) {
                    $action = "HOLD";
                    $quantity = 0;
                    $reason = "Downward trend detected, but holdings are too small for the suggested sale.";
                } else {
                    $reason = sprintf(
                        "Price is %.2f%% below its recent average.",
                        abs($changePercent)
                    );
                }
            }

            $recommendations[] = [
                "symbol" => $symbol,
                "action" => $action,
                "suggested_quantity" => $quantity,
                "reason" => $reason
            ];
        }

        return [
            "success" => true,
            "as_of" => date("Y-m-d H:i:s"),
            "recommendations" => $recommendations,
            "message" => empty($recommendations)
                ? "Recommendations unavailable: fresh historical stock data is required."
                : "Recommendations calculated using recent stock price trends.",
            "available_usd" => $usdBalance,
            "holdings_count" => count($holdings)
        ];
    }
}
