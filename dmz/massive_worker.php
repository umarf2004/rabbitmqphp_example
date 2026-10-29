<?php

// Search for company information
function fetchStockData($ticker)
{
    $ticker = strtoupper(trim($ticker));

    // Accept standard stock ticker characters only
    if (!preg_match('/^[A-Z0-9.\-]{1,15}$/', $ticker)) {
        return [
            "success" => false,
            "error" => "Invalid stock ticker"
        ];
    }

    //Build the Massive API URL
    $url = "https://api.massive.com/v3/reference/tickers/"
         . rawurlencode($ticker);

    $response = massiveApiGet($url);

    if (!$response["success"]) {
	return $response;
    }

    $data = $response["data"]["results"] ?? [];

    if (empty($data)) {
        return [
            "success" => false,
            "error" => $data["error"]
                ?? "Failed to retrieve stock information"
        ];
    }

    // Return the stock information to RabbitMQ
    return [
        "success" => true,
        "ticker" => $data["ticker"] ?? $ticker,
        "name" => $data["name"] ?? "",
        "market" => $data["market"] ?? "",
        "exchange" => $data["primary_exchange"] ?? "",
        "description" => $data["description"] ?? "",
        "homepage_url" => $data["homepage_url"] ?? "",
        "total_employees" => $data["total_employees"] ?? null,
        "fetched_at" => gmdate("Y-m-d\TH:i:s\Z")
    ];
}

// Retrieve previous trading day's stock price
function fetchStockPrice($ticker)
{
    $ticker = strtoupper(trim($ticker));

    if (!preg_match('/^[A-Z0-9.\-]{1,15}$/', $ticker)) {
        return [
            "success" => false,
            "error" => "Invalid stock ticker"
        ];
    }

    // Build the Massive API URL
    $url = "https://api.massive.com/v2/aggs/ticker/"
         . rawurlencode($ticker)
         . "/prev?adjusted=true";

    $response = massiveApiGet($url);

    if (!$response["success"]) {
        return $response;
    }

    $data = $response["data"]["results"][0] ?? null;

    if(!$data) {
	return [
	    "success" => false,
	    "error" => "Stock price information not found"
	];
    }

    return [
	"success" => true,
	"ticker" => $ticker,
	"price" => $data["c"] ?? null,
	"open" => $data["o"] ?? null,
	"high" => $data["h"] ?? null,
	"low" => $data["l"] ?? null,
	"volume" => $data["v"] ?? null,
	"price_type" => "previous_close",
	"market_timestamp" => $data["t"] ?? null,
	"fetched_at" => gmdate("Y-m-d\TH:i:s\Z")
    ];
}

// Search for stocks by company name or ticker
function searchStocks($query)
{
    $query = trim($query);

    if ($query === "") {
        return [
            "success" => false,
            "error" => "Invalid search query"
        ];
    }

    if (strlen($query) > 100) {
	return [
	    "success" => false,
	    "error" => "Search query is too long"
        ];
    }

    $params = [
	"search" => $query,
	"market" => "stocks",
	"active" => "true",
	"limit" => 100
    ];

    $url = "https://api.massive.com/v3/reference/tickers?"
         . http_build_query($params);

    $response = massiveApiGet($url);

    if (!$response["success"]) {
	return $response;
    }

    $data = $response["data"];

    $results = [];

    foreach ($data["results"] ?? []  as $stock) {
        $results[] = [
            "ticker" => $stock["ticker"] ?? "",
            "name" => $stock["name"] ?? "",
            "market" => $stock["market"] ?? "",
            "primary_exchange" => $stock["primary_exchange"] ?? "",
	    "active" => $stock["active"] ?? false
        ];
    }

    return [
        "success" => true,
        "query" => $query,
	"count" => count($results),
        "results" => $results,
	"next_url" => $data["next_url"] ?? null,
        "fetched_at" => gmdate("Y-m-d\TH:i:s\Z")
    ];
}

function fetchStockHistory($request)
{
    $ticker = strtoupper(trim($request["ticker"] ?? ""));

    if (!preg_match('/^[A-Z0-9.^-]{1,20}$/', $ticker)) {
        return [
            "success" => false,
            "error" => "Invalid stock ticker"
        ];
    }

    $today = new DateTimeImmutable(
	"now",
	new DateTimeZone("America/New_York")
    );

    $to = $today->format("Y-m-d");
    $from = $today->modify("-30 days")->format("Y-m-d");

    // Build the Massive API URL
    $url = "https://api.massive.com/v2/aggs/ticker/"
         . rawurlencode($ticker)
         . "/range/1/day/"
         . $from . "/" . $to
         . "?adjusted=true&sort=asc&limit=120";

    $response = massiveApiGet($url);

    if (!$response["success"]) {
        return $response;
    }

    $data = $response["data"];

    // Convert Massive's response into readable data
    $history = [];

    foreach ($data["results"] ?? [] as $day) {
        if (!isset($day["t"], $day["c"])) {
	    contiune;
	}

	$history[] = [
            "date" => gmdate("Y-m-d", (int) ($day["t"] / 1000)),
            "timestamp" => $day["t"],
            "open" => $day["o"] ?? null,
            "high" => $day["h"] ?? null,
            "low" => $day["l"] ?? null,
            "close" => $day["c"],
            "volume" => $day["v"] ?? null
        ];
    }

    if (empty($history)) {
	return [
	    "success" => false,
	    "ticker" => $ticker,
	    "error" => "No historical stock data avaiable"
	];
    }
    return [
        "success" => true,
        "ticker" => $ticker,
        "from" => $from,
        "to" => $to,
        "count" => count($history),
        "history" => $history,
        "fetched_at" => gmdate("Y-m-d\TH:i:s\Z")
    ];
}

function getFxRate($request)
{
    $from = strtoupper(trim($request["from"] ?? "USD"));
    $to = strtoupper(trim($request["to"] ?? "EUR"));
    $amount = $request["amount"] ?? 1;

    if (!preg_match('/^[A-Z]{3}$/', $from) ||
        !preg_match('/^[A-Z]{3}$/', $to)) {
        return [
            "success" => false,
            "error" => "Invalid currency code"
        ];
    }

    if (!is_numeric($amount) ||
        !is_finite((float) $amount) ||
        (float) $amount < 0) {
        return [
            "success" => false,
            "error" => "Invalid conversion amount"
        ];
    }

    $amount = (float) $amount;

    if ($from === $to) {
        return [
            "success" => true,
            "from" => $from,
            "to" => $to,
            "rate" => 1,
            "amount" => $amount,
            "converted_amount" => $amount
        ];
    }

    $url = "https://api.massive.com/v1/conversion/"
         . rawurlencode($from)
         . "/"
         . rawurlencode($to);

    $response = massiveApiGet($url);

    if (!$response["success"]) {
        return $response;
    }

    $data = $response["data"];

    $rate = $data["converted"] ?? null;

    if (!is_numeric($rate)) {
        return [
            "success" => false,
            "error" => "Conversion rate missing from API response"
        ];
    }

    $rate = (float) $rate;

    return [
        "success" => true,
        "from" => $from,
        "to" => $to,
        "rate" => $rate,
        "amount" => $amount,
        "converted_amount" => $rate * $amount,
        "fetched_at" => gmdate("Y-m-d\TH:i:s\Z")
    ];
}


function getFxHistory($request)
{
    $from = strtoupper(trim($request["from"] ?? "USD"));
    $to = strtoupper(trim($request["to"] ?? "EUR"));

    if (!preg_match('/^[A-Z]{3}$/', $from) ||
        !preg_match('/^[A-Z]{3}$/', $to)) {
        return [
            "success" => false,
            "error" => "Invalid currency code"
        ];
    }

    $today = new DateTimeImmutable(
        "now",
        new DateTimeZone("America/New_York")
    );

    $endDate = $today->format("Y-m-d");
    $startDate = $today->modify("-30 days")->format("Y-m-d");

    $pair = "C:" . $from . $to;

    $url = "https://api.massive.com/v2/aggs/ticker/"
         . rawurlencode($pair)
         . "/range/1/day/"
         . $startDate . "/" . $endDate
         . "?sort=asc&limit=120";

    $response = massiveApiGet($url);

    if (!$response["success"]) {
        return $response;
    }

    $data = $response["data"];
    $history = [];

    foreach ($data["results"] ?? [] as $day) {
        if (!isset($day["t"], $day["c"])) {
            continue;
        }

        $history[] = [
            "date" => gmdate("Y-m-d", (int) ($day["t"] / 1000)),
            "timestamp" => $day["t"],
            "open" => $day["o"] ?? null,
            "high" => $day["h"] ?? null,
            "low" => $day["l"] ?? null,
            "close" => $day["c"]
        ];
    }

    return [
        "success" => true,
        "from" => $from,
        "to" => $to,
        "pair" => $pair,
        "count" => count($history),
        "history" => $history,
        "fetched_at" => gmdate("Y-m-d\TH:i:s\Z")
    ];
}


function getStockNews($request)
{
    $ticker = strtoupper(trim($request["ticker"] ?? ""));

    if (!preg_match('/^[A-Z0-9.^-]{1,20}$/', $ticker)) {
        return [
            "success" => false,
            "error" => "Invalid stock ticker"
        ];
    }

    $params = [
        "ticker" => $ticker,
        "limit" => 10,
        "order" => "desc",
        "sort" => "published_utc"
    ];

    $url = "https://api.massive.com/v2/reference/news?"
         . http_build_query($params);

    $response = massiveApiGet($url);

    if (!$response["success"]) {
        return $response;
    }

    $articles = [];

    foreach ($response["data"]["results"] ?? [] as $article) {
        $articles[] = [
            "id" => $article["id"] ?? null,
            "title" => $article["title"] ?? "",
            "description" => $article["description"] ?? "",
            "url" => $article["article_url"] ?? "",
            "published_at" => $article["published_utc"] ?? "",
            "publisher" => $article["publisher"]["name"] ?? "",
            "tickers" => $article["tickers"] ?? []
        ];
    }

    return [
        "success" => true,
        "ticker" => $ticker,
        "count" => count($articles),
        "articles" => $articles,
        "fetched_at" => gmdate("Y-m-d\TH:i:s\Z")
    ];
}

function massiveApiGet($url)
{
    $apiKey = getenv("MASSIVE_API_KEY");

    if (!$apiKey) {
        return [
            "success" => false,
            "error" => "Massive API key is missing"
        ];
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $apiKey,
            "Accept: application/json"
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            "success" => false,
            "error" => "Connection failed: " . $error
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $data = json_decode($response, true);

    if (!is_array($data)) {
        return [
            "success" => false,
            "http_code" => $httpCode,
            "error" => "Invalid JSON from Massive API"
        ];
    }

    if ($httpCode !== 200) {
        return [
            "success" => false,
            "http_code" => $httpCode,
            "error" => $data["error"]
                ?? $data["message"]
                ?? "Massive API returned an error"
        ];
    }

    return [
        "success" => true,
        "data" => $data
    ];
}
