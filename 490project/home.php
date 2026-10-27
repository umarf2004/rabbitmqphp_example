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

$name = htmlspecialchars($response["first_name"]) ." ". htmlspecialchars($response["last_name"]);
?>

<!DOCTYPE html>
    <html>
    <head>
        <title>Home</title>
    </head>

    <body>

        <h1>
            Welcome <?php echo $name; ?>
        </h1>

        <form id="stockSearchForm" action="stocks.php" method="GET">
        <input id="stockSymbol" name="ticker" type="text" placeholder="Search stock symbol, e.g. AAPL" required>
            <button type="submit"> Search </button>
            <p id="searchMessage"></p>
        </form>

        <p>
            Login successful.
        </p>

        <a href="currency.php">My Currency Wallet</a>
        <br><br>
        <a href="portfolio.php"> My Portfolio</a>
        <br><br>
        <a href="logout.php">Logout</a>

        <script>
            document.getElementById("stockSearchForm").addEventListener("submit", function() {
            
            let ticker = document.getElementById("stockSymbol").value;
                document.getElementById("searchMessage").textContent = "Searching for " + ticker + "...";
            });
        </script>

    </body>
</html>
