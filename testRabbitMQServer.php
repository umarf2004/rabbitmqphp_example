#!/usr/bin/php
<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('login.php.inc');
require_once('stockDB.php.inc');

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
