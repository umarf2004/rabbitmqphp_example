<?php

require_once(__DIR__ . '/../rabbitMQLib.inc');
require_once(__DIR__ . '/../get_host_info.inc');

$sessionId =
    $_COOKIE["auth_token"] ?? "";

if ($sessionId !== "") {

    $client = new rabbitMQClient(
        __DIR__ . "/../rabbitmq.local.ini",
        "testServer"
    );

    $client->send_request([
        "type" => "logout",
        "sessionId" => $sessionId
    ]);
}

setcookie(
    "auth_token",
    "",
    time() - 3600,
    "/"
);

header("Location: index.html");
exit;
