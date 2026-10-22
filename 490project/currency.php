<?php

require_once('/home/umarf/git/rabbitmqphp_example/rabbitMQLib.inc');
require_once('/home/umarf/git/rabbitmqphp_example/get_host_info.inc');

$sessionId =
    $_COOKIE["auth_token"] ?? "";

if ($sessionId === "") {
    header("Location: index.html");
    exit;
}
