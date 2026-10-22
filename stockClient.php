<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient(
    "rabbitmq.local.ini",
    "massiveWorker"
);
$ticker = strtoupper($argv[1] ?? "AAPL");

$request = [
    "type" => "fetch_stock",
    "ticker" => $ticker
];
echo "Sending stock request for $ticker...\n";

$response = $client->send_request($request);

echo "Response from DMZ:\n";
print_r($response);
