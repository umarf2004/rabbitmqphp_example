<?php

require_once('/home/umarf/git/rabbitmqphp_example/rabbitMQLib.inc');
require_once('/home/umarf/git/rabbitmqphp_example/get_host_info.inc');

$sessionId = $_COOKIE["auth_token"] ?? "";

if ($sessionId === "") 
    {
        header("Location: index.html");
        exit;
    }

$client = new rabbitMQClient
    (
        "/home/umarf/git/rabbitmqphp_example/rabbitmq.local.ini", "testServer"
    ); 

$response = $client->send_request
([
    "type" => "validate_session",
    "sessionId" => $sessionId
]);

if (empty($response["success"])) {
    setcookie("auth_token", "", time() - 3600, "/");
    header("Location: index.html");
    exit;
}
?> 

<!DOCTYPE html>
<html>
    <head>
        <title> Currency Wallet</title> 
    </head>

    <body>
        <h1>My Currency Wallet</h1> 

        <h2> Currency Balance </h2> 


        <p>USD: -- </p>
        <p>EUR: -- </p>
        <p>GBP: -- </p>

        <a href="home.php"> Back to the Dashboard </a>
    </body>
</html>