<?php

require_once __DIR__ . "/massive_worker.php";

require_once __DIR__ . "/../path.inc";
require_once __DIR__ . "/../get_host_info.inc";
require_once __DIR__ . "/../rabbitMQLib.inc";

function massiveRequestProcessor($request)
{
    echo "\nReceived Massive API request:\n";

    $type = $request["type"] ?? "";
    $ticker = $request["ticker"] ?? "";

    echo "Type: " . $type . "\n";
    echo "Ticker: " . $ticker . "\n";

    switch ($type) {

        // Search for stocks
        case "search_stocks":
            echo "Searching for stocks...\n";
            $query = $request["query"] ?? "";
            $result = searchStocks($query);
            break;

        // Retrieve company information
        case "fetch_stock":
        case "get_stock":
            echo "Fetching company information...\n";
            $result = fetchStockData($ticker);
            break;

        // Retrieve stock price
        case "fetch_stock_price":
        case "get_stock_price":
            echo "Fetching stock price...\n";
            $result = fetchStockPrice($ticker);
            break;

        // Retrieve historical stock prices
        case "fetch_stock_history":
            echo "Fetching stock history...\n";
            $result = fetchStockHistory($request);
            break;

        // Retrieve currency exchange rates
        case "get_fx_rate":
            echo "Fetching currency exchange rate...\n";
            $result = getFxRate($request);
            break;

        // Retrieve historical currency exchange rates
        case "get_fx_history":
            echo "Fetching currency exchange history...\n";
            $result = getFxHistory($request);
            break;

        // Retrieve stock news
        case "get_news":
            echo "Fetching stock news...\n";
            $result = getStockNews($request);
            break;

        default:
            return [
                "success" => false,
                "error" => "Unsupported request type"
            ];
    }

    echo !empty($result["success"])
        ? "Massive API request successful\n"
        : "Massive API request failed\n";

    return $result;
}

if (in_array("--test", $argv, true)) {

    // Test 1: Company information
    echo "\n===== COMPANY INFORMATION =====\n";

    $response = massiveRequestProcessor([
        "type" => "fetch_stock",
        "ticker" => "AAPL"
    ]);

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n";

    // Test 2: Stock price
    echo "\n===== STOCK PRICE =====\n";

    $response = massiveRequestProcessor([
        "type" => "fetch_stock_price",
        "ticker" => "AAPL"
    ]);

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n";

    // Test 3: Stock search
    echo "\n===== STOCK SEARCH =====\n";

    $response = massiveRequestProcessor([
        "type" => "search_stocks",
        "query" => "Apple"
    ]);

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n";

    // Test 4: Stock History
    echo "\n===== STOCK History =====\n";

    $response = massiveRequestProcessor([
        "type" => "fetch_stock_history",
        "ticker" => "NVDA"
    ]);

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n";

    // Test 5: Currency Exchange Rate
    echo "\n===== CURRENCY EXCHANGE RATE =====\n";

    $response = massiveRequestProcessor([
        "type" => "get_fx_rate",
        "from" => "USD",
        "to" => "EUR",
        "amount" => 100
    ]);

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n";

    // Test 6: Currency Exchange History
    echo "\n===== CURRENCY EXCHANGE HISTORY =====\n";

    $response = massiveRequestProcessor([
        "type" => "get_fx_history",
        "from" => "USD",
        "to" => "GBP"
    ]);

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n";

    // Test 7: Stock News
    echo "\n===== STOCK NEWS =====\n";

    $response = massiveRequestProcessor([
        "type" => "get_news",
        "ticker" => "NVDA"
    ]);

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n";

    exit;
}

$server = new rabbitMQServer(
    __DIR__ . "/rabbitmq.local.ini",
    "massiveWorker"
);

echo "Massive API RabbitMQ worker running...\n";

$server->process_requests("massiveRequestProcessor");
