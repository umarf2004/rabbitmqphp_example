<?php

function fetchStockData($ticker)
{
    $apiKey = getenv("MASSIVE_API_KEY");

    if (!$apiKey) {
        return [
            "success" => false,
            "error" => "Massive API key is missing"
        ];
    }

    $ticker = strtoupper(trim($ticker));

    // Accept standard stock ticker characters only
    if (!preg_match('/^[A-Z0-9.\-]{1,15}$/', $ticker)) {
        return [
            "success" => false,
            "error" => "Invalid stock ticker"
        ];
    }

    $url = "https://api.massive.com/v3/reference/tickers/"
         . rawurlencode($ticker);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $apiKey,
            "Accept: application/json"
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            "success" => false,
            "error" => $error
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode !== 200 || !isset($data["results"])) {
        return [
            "success" => false,
            "http_code" => $httpCode,
            "error" => $data["error"]
                ?? "Failed to retrieve stock information"
        ];
    }

    return [
        "success" => true,
        "ticker" => $ticker,
        "data" => $data["results"],
        "fetched_at" => gmdate("Y-m-d\TH:i:s\Z")
    ];
}

// Retrieve previous trading day's stock price
function fetchStockPrice($ticker)
{
    $apiKey = getenv("MASSIVE_API_KEY");

    if (!$apiKey) {
        return [
            "success" => false,
            "error" => "Massive API key is missing"
        ];
    }

    $ticker = strtoupper(trim($ticker));

    if (!preg_match('/^[A-Z0-9.\-]{1,15}$/', $ticker)) {
        return [
            "success" => false,
            "error" => "Invalid stock ticker"
        ];
    }

    $url = "https://api.massive.com/v2/aggs/ticker/"
         . rawurlencode($ticker)
         . "/prev?adjusted=true";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $apiKey,
            "Accept: application/json"
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            "success" => false,
            "error" => $error
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if (
        $httpCode !== 200 ||
        !isset($data["results"][0]["c"])
    ) {
        return [
            "success" => false,
            "http_code" => $httpCode,
            "error" => $data["error"]
                ?? "Failed to retrieve stock price"
        ];
    }

    $stock = $data["results"][0];

    return [
	"success" => true,
	"ticker" => $ticker,
	"price" => $stock["c"],
	"open" => $stock["o"] ?? null,
	"high" => $stock["h"] ?? null,
	"low" => $stock["l"] ?? null,
	"volume" => $stock["v"] ?? null,
	"price_type" => "previous_close",
	"market_timestamp" => $stock["t"] ?? null,
	"fetched_at" => gmdate("Y-m-d\TH:i:s\Z")
    ];
}

// Retrieve real daily historical stock prices from Massive.
function fetchStockHistory($ticker)
{
    $apiKey = getenv("MASSIVE_API_KEY");

    if (!$apiKey) {
        return [
            "success" => false,
            "error" => "Massive API key is missing"
        ];
    }

    $ticker = strtoupper(trim($ticker));

    if (!preg_match('/^[A-Z0-9.\-]{1,15}$/', $ticker)) {
        return [
            "success" => false,
            "error" => "Invalid stock ticker"
        ];
    }

    $to = gmdate("Y-m-d");
    $from = gmdate("Y-m-d", strtotime("-30 days"));

    $url = "https://api.massive.com/v2/aggs/ticker/"
         . rawurlencode($ticker)
         . "/range/1/day/"
         . $from . "/" . $to
         . "?adjusted=true&sort=asc&limit=50";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $apiKey,
            "Accept: application/json"
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            "success" => false,
            "error" => $error
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if (
        $httpCode !== 200 ||
        !isset($data["results"]) ||
        !is_array($data["results"])
    ) {
        return [
            "success" => false,
            "http_code" => $httpCode,
            "error" => $data["error"]
                ?? "Failed to retrieve stock history"
        ];
    }

    $history = [];

    foreach ($data["results"] as $bar) {
        if (
            !isset($bar["c"], $bar["t"]) ||
            !is_numeric($bar["c"]) ||
            !is_numeric($bar["t"]) ||
            (float) $bar["c"] <= 0
        ) {
            continue;
        }

        $history[] = [
            "price" => (float) $bar["c"],
            "recorded_at" => gmdate(
                "Y-m-d H:i:s",
                (int) floor($bar["t"] / 1000)
            )
        ];
    }

    return [
        "success" => true,
        "ticker" => $ticker,
        "history" => $history,
        "fetched_at" => gmdate("Y-m-d\TH:i:s\Z")
    ];
}

// Search for stocks by company name or ticker
function searchStocks($query)
{
    $apiKey = getenv("MASSIVE_API_KEY");

    if (!$apiKey) {
        return [
            "success" => false,
            "error" => "Massive API key is missing"
        ];
    }

    $query = trim($query);

    if ($query === "" || strlen($query) > 100) {
        return [
            "success" => false,
            "error" => "Invalid search query"
        ];
    }

    $url = "https://api.massive.com/v3/reference/tickers"
         . "?search=" . rawurlencode($query)
         . "&active=true&limit=20";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $apiKey,
            "Accept: application/json"
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            "success" => false,
            "error" => $error
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode !== 200 || !isset($data["results"])) {
        return [
            "success" => false,
            "http_code" => $httpCode,
            "error" => $data["error"]
                ?? "Failed to search for stocks"
        ];
    }

    $results = [];

    foreach ($data["results"] as $stock) {
        $results[] = [
            "ticker" => $stock["ticker"] ?? "",
            "name" => $stock["name"] ?? "",
            "market" => $stock["market"] ?? "",
            "primary_exchange" =>
                $stock["primary_exchange"] ?? ""
        ];
    }

    return [
        "success" => true,
        "query" => $query,
        "results" => $results,
        "fetched_at" => gmdate("Y-m-d\TH:i:s\Z")
    ];
}
