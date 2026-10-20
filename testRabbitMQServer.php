#!/usr/bin/php
<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('login.php.inc');

function requestProcessor($request)
{
    echo PHP_EOL;
    echo "Received request:" . PHP_EOL;
    print_r($request);

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
                return $db->validateLogin(
                    $request["username"] ?? "",
                    $request["password"] ?? ""
                );

            case "validate_session":
                return $db->validateSession(
                    $request["sessionId"] ?? ""
                );

            case "logout":
                return $db->logout(
                    $request["sessionId"] ?? ""
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

$server = new rabbitMQServer(
    "rabbitmq.local.ini",
    "testServer"
);

echo "Authentication DB listener running..." . PHP_EOL;

$server->process_requests(
    'requestProcessor'
);

?>
