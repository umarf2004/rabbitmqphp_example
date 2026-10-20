<?php

require_once('/home/umarf/git/rabbitmqphp_example/rabbitMQLib.inc');
require_once('/home/umarf/git/rabbitmqphp_example/get_host_info.inc');

$sessionId =
    $_COOKIE["auth_token"] ?? "";

if ($sessionId === "") {
    header("Location: index.html");
    exit;
}

$client = new rabbitMQClient(
    "/home/umarf/git/rabbitmqphp_example/rabbitmq.local.ini",
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

        <form id="stockSearchForm">
            <input id="stockSymbol" type="text" placeholder="Search stock symbol, e.g. AAPL" required>

            <button type="submit">
                Search
            </button>

            <p id="searchMessage"></p>
        </form>

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
