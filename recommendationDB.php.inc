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
            //average is the five day average
            $changePercent = (($currentPrice - $average) / $average) * 100;
            $oldestPrice = $prices[count($prices) - 1];

            $trendPercent = (($currentPrice - $oldestPrice) / $oldestPrice) * 100;

            // how many times the stock price wen up
            $upDays = 0;
            for ($i = 0; $i < count($prices) - 1; $i++) {
                if ($prices[$i] > $prices[$i + 1]) {
                    $upDays++;
                }
            }   
            $ownedShares = 0;
            foreach ($holdings as $holding) {
                if ($holding["symbol"] === $symbol) {
                    $ownedShares = (float) $holding["shares"];
                    break;
                }
            }

            //calculate scrore based on change
            $score = 0;
            if ($changePercent >= 0.5) {
                $score++;
            } elseif ($changePercent <= -0.5) {
                $score--;
            }

            if ($trendPercent >= 0.25) {
                $score++;
            } elseif ($trendPercent <= -0.25) {
                $score--;
            }
            // if the price went up more than 3 days, increases score
            if ($upDays >= 3) {
                $score++;
            } elseif ($upDays <= 1) {
                $score--;
            }

            // Default recommendation to hold
            $action = "HOLD";
            $quantity = 0;

            $reason = sprintf(
                "Price vs average: %+.2f%%. Overall trend: %+.2f%%. Upward sessions: %d/4. Score: %d.",
                $changePercent,
                $trendPercent,
                $upDays,
                $score
            );

            if ($score >= 2) {

                //use no more than 10% of your budget on it
                $budget = $usdBalance * 0.10;

                $quantity = (int) floor($budget / $currentPrice);

                if ($quantity >= 1) {
                    $action = "BUY";
                    $reason .= " Positive momentum. Purchase fits the 10% cash allocation.";
                } else {
                    $quantity = 0;
                    $reason .= " Positive momentum, but the allocated budget cannot afford one share.";
                }

                
                } elseif ($score <= -2 && $ownedShares >= 1) {

                    $action = "SELL";

                    // Suggest selling approximately 25% of owned shares.
                    $quantity = max(1, (int) floor($ownedShares * 0.25));

                    $reason .= " Negative momentum. Consider reducing the existing position.";

                } else {

                    $reason .= " No actionable trade under the current strategy.";
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
