<?php
require_once  __DIR__ . '/layout.php';
require_once(__DIR__ . '/../rabbitMQLib.inc');
require_once(__DIR__ . '/../get_host_info.inc');

site_begin("Portfolio");

$sessionId = $_COOKIE["auth_token"] ?? "";
if ($sessionId === "") 
    {
        header("Location: index.html");
        exit;
    }

$client = new rabbitMQClient
    (
        __DIR__ . '/../rabbitmq.local.ini', "testServer"
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
        <title> My Portfolio</title> 
    </head>

    <body>
        <h1>My Portfolio </h1> 

        <h2>Portfolio Overview  </h2> 
        <p>Total Portfolio Value: --</p>
        <p>Available Cash: --</p>
        <p>Total Invested Value: --</p>
        <p>Total Profit/Loss: --</p>

        <a href="home.php"> Back to the Dashboard </a>
    </body>
</html>
