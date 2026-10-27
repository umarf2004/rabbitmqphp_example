<?php
require_once __DIR__ . '/shared.php';
function site_begin(string $title): void
{
    $currentUser = must_be_logged_in();
    $csrfToken = esc(csrf_for($currentUser['token']));
    $displayName = esc($currentUser['username']);
    $pageTitle = esc($title);

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="$csrfToken">
    <title>$pageTitle — Virtual Trading</title>
    <link rel="stylesheet" href="app.css">
</head>
<body>
<header><strong>IT490 Virtual Exchange</strong><small>Signed in: $displayName</small></header>
<nav>
    <a href="home.php">Home</a><a href="stocks.php">Stocks</a><a href="trade.php">Trade</a>
    <a href="portfolio.php">Portfolio</a><a href="currency.php">Currency</a><a href="history.php">History</a>
    <a href="recommendations.php">Recommendations</a><a href="news.php">News</a><a href="alerts.php">FX Alerts</a>
    <a href="notifications.php">Notifications</a><a href="logout.php">Log out</a>
</nav>
<main><h1>$pageTitle</h1>
HTML;
}

function site_end(): void
{
    echo '</main><footer>For IT490 Project only/No real money trading.</footer></body></html>';
}
