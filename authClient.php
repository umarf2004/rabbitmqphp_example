<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient(
    "rabbitmq.local.ini",
    "testServer"
);

$type = $argv[1] ?? "";

$request = [
    "type" => $type
];

switch ($type) {

    case "register":

        $request["username"] =
            $argv[2] ?? "";

        $request["password"] =
            $argv[3] ?? "";

        $request["first_name"] =
            $argv[4] ?? null;

        $request["last_name"] =
            $argv[5] ?? null;

        $request["email"] =
            $argv[6] ?? null;

        break;

    case "login":

        $request["username"] =
            $argv[2] ?? "";

        $request["password"] =
            $argv[3] ?? "";

        break;

    case "validate_session":
    case "logout":

        $request["sessionId"] =
            $argv[2] ?? "";

        break;

    default:

        echo "Usage:" . PHP_EOL;
        echo " register USER PASS FIRST LAST EMAIL" .
             PHP_EOL;
        echo " login USER PASS" . PHP_EOL;
        echo " validate_session SESSION_KEY" .
             PHP_EOL;
        echo " logout SESSION_KEY" . PHP_EOL;

        exit(1);
}

$response =
    $client->send_request($request);

echo PHP_EOL;
echo "Response:" . PHP_EOL;
print_r($response);
