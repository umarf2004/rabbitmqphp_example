<?php

require_once('/var/www/authlib/rabbitMQLib.inc');
require_once('/var/www/authlib/get_host_info.inc');

$sessionId =
    $_COOKIE["auth_token"] ?? "";

if ($sessionId !== "") {

    $client = new rabbitMQClient(
        "/var/www/authlib/rabbitmq.local.ini",
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
