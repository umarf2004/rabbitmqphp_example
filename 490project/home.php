<?php

require_once('/var/www/authlib/rabbitMQLib.inc');
require_once('/var/www/authlib/get_host_info.inc');

$sessionId =
    $_COOKIE["auth_token"] ?? "";

if ($sessionId === "") {
    header("Location: index.html");
    exit;
}

$client = new rabbitMQClient(
    "/var/www/authlib/rabbitmq.local.ini",
    "testServer"
);

$response = $client->send_request([
    "type" => "validate_session",
    "sessionId" => $sessionId
]);

if (empty($response["success"])) {

    setcookie(
        "auth_token",
        "",
        time() - 3600,
        "/"
    );

    header("Location: index.html");
    exit;
}

$username = htmlspecialchars(
    $response["username"]
);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Home</title>
</head>

<body>

<h1>
    Welcome <?php echo $username; ?>
</h1>

<p>
    Login successful.
</p>

<p>
    Your session was validated through
    RabbitMQ and the database.
</p>

<a href="logout.php">
    Logout
</a>

</body>
</html>
