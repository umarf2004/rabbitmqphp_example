<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function requestProcessor($request)
{
    echo "Received request:\n";
    print_r($request);

    return array(
        "success" => true,
        "message" => "RabbitMQ on VM4 is working"
    );
}

$server = new rabbitMQServer("rabbitmq.local.ini", "testServer");

echo "Smoke server waiting for requests...\n";
$server->process_requests('requestProcessor');
?>
