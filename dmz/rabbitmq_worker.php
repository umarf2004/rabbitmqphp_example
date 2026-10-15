<?php

require_once __DIR__ . "/massive_worker.php";

require_once __DIR__ . "/../path.inc";
require_once __DIR__ . "/../get_host_info.inc";
require_once __DIR__ . "/rabbitMQLib.inc";

function massiveRequestProcessor($request)
{
    echo "\nReceived Massive API request:\n";

    echo "Type: " . ($request["type"] ?? "missing") . "\n";

    if (($request["type"] ?? "") !== "fetch_stock") {
        return [
            "success" => false,
            "error" => "Unsupported request type"
        ];
    }

    $ticker = $request["ticker"] ?? "";

    echo "Fetching stock: " . $ticker . "\n";

    $result = fetchStockData($ticker);

    echo $result["success"]
        ? "Massive API request successful\n"
        : "Massive API request failed\n";

    return $result;
}

if (in_array("--test", $argv, true)) {

    $response = massiveRequestProcessor([
        "type" => "fetch_stock",
        "ticker" => "AAPL"
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
