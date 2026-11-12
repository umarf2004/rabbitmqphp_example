#!/usr/bin/php
<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('login.php.inc');
require_once('stockDB.php.inc');
require_once('tradingDB.php.inc');
require_once('currencyDB.php.inc');
require_once('recommendationDB.php.inc');
require_once('notificationDB.php.inc');

function requestProcessor($request)
{
    // Log only the request type, never passwords or session tokens.
    echo PHP_EOL;
    echo "Received request type: "
        . (is_array($request) && is_string($request["type"] ?? null)
            ? $request["type"]
            : "unknown")
        . PHP_EOL;

    if (!isset($request["type"])) {
        return [
            "success" => false,
            "message" => "Missing request type"
        ];
    }

    try {
        $db = new loginDB();

        switch ($request["type"]) {

            case "register":
                return $db->register(
                    $request["username"] ?? "",
                    $request["password"] ?? "",
                    $request["first_name"] ?? null,
                    $request["last_name"] ?? null,
                    $request["email"] ?? null
                );

            case "login":
                $result = $db->validateLogin($request["username"], $request["password"]);
    		echo "DEBUG LOGIN: success = " . (($result["success"] ?? false) ? "YES" : "NO") . PHP_EOL;
    		return $result;

                echo "LOGIN RESULT: " .
                     (($result["success"] ?? false) ? "SUCCESS" : "FAILED") .
                     PHP_EOL;

                echo "LOGIN MESSAGE: " .
                     ($result["message"] ?? "No message") .
                     PHP_EOL;

                echo "SESSION CREATED: " .
                     (isset($result["sessionId"]) ? "YES" : "NO") .
                     PHP_EOL;

                return $result;

            case "validate_session":
                return $db->validateSession(
                    $request["sessionId"] ?? ""
                );

            case "validate_session":
    		$result = $db->validateSession(
        		$request["sessionId"] ?? ""
    		);

    		echo "DEBUG SESSION: success = "
        		. (($result["success"] ?? false) ? "YES" : "NO")
        		. PHP_EOL;

    		return $result;

            case "logout":
                return $db->logout(
                    $request["sessionId"] ?? ""
                );

	    case "get_stock":
	    case "fetch_stock":
	    case "search_stock":
   	    	echo "DEBUG: Starting stock lookup" . PHP_EOL;

   		 $stockDb = new stockDB();

    		 $result = $stockDb->getStock(
       		     $request["ticker"] ?? ""
    );

    echo "DEBUG: Stock lookup finished" . PHP_EOL;

    return $result;


            case "get_wallet":
                $tradingDb = new tradingDB();
                return $tradingDb->getWallet(
                    $request["sessionId"] ?? ""
                );

            case "buy_stock":
                $tradingDb = new tradingDB();
                return $tradingDb->buyStock(
                    $request["sessionId"] ?? "",
                    $request["ticker"] ?? "",
                    $request["quantity"] ?? ""
                );

            case "sell_stock":
                $tradingDb = new tradingDB();
                return $tradingDb->sellStock(
                    $request["sessionId"] ?? "",
                    $request["ticker"] ?? "",
                    $request["quantity"] ?? ""
                );

            case "get_wallets":
                $currencyDb = new currencyDB();
                return $currencyDb->getWallets(
                    $request["sessionId"] ?? ""
                );

            case "add_virtual_funds":
                $currencyDb = new currencyDB();
                return $currencyDb->refillVirtualFunds(
                    $request["sessionId"] ?? ""
                );

            case "get_currency_transactions":
                $currencyDb = new currencyDB();
                return $currencyDb->getCurrencyTransactions(
                    $request["sessionId"] ?? ""
                );

            case "convert_currency":
                $currencyDb = new currencyDB();
                return $currencyDb->convertCurrency(
                    $request["sessionId"] ?? "",
                    $request["from_currency"] ?? "",
                    $request["to_currency"] ?? "",
                    $request["amount"] ?? ""
                );

            case "execute_trade":
                $side = strtoupper(trim((string) ($request["side"] ?? "")));
                $symbol = $request["symbol"] ?? "";
                $quantity = $request["quantity"] ?? "";
                $sessionId = $request["sessionId"] ?? "";

                $tradingDb = new tradingDB();

                if ($side === "BUY") {
                    return $tradingDb->buyStock(
                        $sessionId, $symbol, $quantity
                    );
                }

                if ($side === "SELL") {
                    return $tradingDb->sellStock(
                        $sessionId, $symbol, $quantity
                    );
                }

                return [
                    "success" => false,
                    "message" => "Invalid trade side"
                ];

            case "get_portfolio":
                $tradingDb = new tradingDB();
                return $tradingDb->getPortfolio(
                    $request["sessionId"] ?? ""
                );
            case "get_recommendations":
                $recommendationDb = new recommendationDB();
                return $recommendationDb->getRecommendations(
                    $request["sessionId"] ?? ""
                );
            case "get_portfolio_history":
               $tradingDb = new tradingDB();
               return $tradingDb->getPortfolioHistory(
	            $request["sessionId"] ?? ""
                );           
	    case "get_trade_history":
                $tradingDb = new tradingDB();
                return $tradingDb->getTradeHistory(
                    $request["sessionId"] ?? ""
                );

            case "get_email_preferences":
                return (new notificationDB())->getEmailPreferences($request["sessionId"] ?? "");

            case "update_email_preferences":
                return (new notificationDB())->updateEmailPreferences($request);

            case "send_test_email":
                return (new notificationDB())->sendTestEmail($request["sessionId"] ?? "");

            case "get_notifications":
                return (new notificationDB())->getNotifications($request["sessionId"] ?? "");

            case "mark_notification_read":
                return (new notificationDB())->markNotificationRead(
                    $request["sessionId"] ?? "",
                    $request["notification_id"] ?? ""
                );

            default:
                return [
                    "success" => false,
                    "message" => "Unsupported request type"
                ];
        }
    }
    catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . PHP_EOL;

        return [
            "success" => false,
            "message" => "Internal server error"
        ];
    }
}

// Start the RabbitMQ listener only when this file is run directly.
// This allows other PHP scripts to include it for local testing.
if (realpath($_SERVER["SCRIPT_FILENAME"] ?? "") === realpath(__FILE__)) {

    $server = new rabbitMQServer(
        "rabbitmq.local.ini",
        "testServer"
    );

    echo "Database RabbitMQ listener running..." . PHP_EOL;

    $server->process_requests(
        'requestProcessor'
    );
}

?>
