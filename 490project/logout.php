<?php

require_once('/home/umarf/git/rabbitmqphp_example/rabbitMQLib.inc');
require_once('/home/umarf/git/rabbitmqphp_example/get_host_info.inc');

$sessionId =
    $_COOKIE["auth_token"] ?? "";

if ($sessionId !== "") {

    $client = new rabbitMQClient(
        "/home/umarf/git/rabbitmqphp_example/rabbitmq.local.ini",
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
