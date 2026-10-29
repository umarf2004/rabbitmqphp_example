<?php
require_once __DIR__ . '/shared.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function sendApiResponse(array $reply, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($reply, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sendApiResponse([
        'success' => false,
        'message' => 'POST required'
    ], 405);
}

$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (is_string($requestOrigin) && $requestOrigin !== '') {
    $usingHttps = !empty($_SERVER['HTTPS']) &&
                  $_SERVER['HTTPS'] !== 'off';

    $protocol = $usingHttps ? 'https://' : 'http://';
    $expectedOrigin = $protocol . ($_SERVER['HTTP_HOST'] ?? '');

    if (!hash_equals($expectedOrigin, $requestOrigin)) {
        sendApiResponse([
            'success' => false,
            'message' => 'Origin rejected'
        ], 403);
    }
}
try {
    $loggedInUser = auth_state();

    if ($loggedInUser === null) {
        sendApiResponse([
            'success' => false,
            'message' => 'Please log in again'
        ], 401);
    }
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expectedToken = csrf_for($loggedInUser['token']);
    if (!is_string($csrfHeader) ||
        !hash_equals($expectedToken, $csrfHeader)) {
        sendApiResponse([
            'success' => false,
            'message' => 'Invalid CSRF token'
        ], 403);
    }

    $requestFields = [
        'search_stocks' => ['query'],
        'fetch_stock' => ['ticker'],
        'get_stock_price' => ['ticker'],
        'fetch_stock_history' => ['ticker'],
        'get_fx_rate' => ['base', 'quote'],
        'get_fx_history' => ['base', 'quote', 'from', 'to'],
        'get_news' => ['symbols'],
        'get_portfolio' => [],
        'get_wallets' => [],
        'add_virtual_funds' => [],
        'convert_currency' => [
            'from_currency',
            'to_currency',
            'amount'
        ],
        'get_currency_transactions' => [],
        'get_currency_alerts' => [],
        'create_currency_alert' => [
            'base_currency',
            'target_currency',
            'condition_type',
            'target_rate'
        ],
        'execute_trade' => ['symbol', 'side', 'quantity'],
        'get_trade_history' => [],
        'get_portfolio_history' => [],
        'get_recommendations' => [],
        'get_notifications' => [],
        'mark_notification_read' => ['notification_id'],
        'get_email_preferences' => [],
        'update_email_preferences' => [
            'enabled',
            'trade_emails',
            'currency_emails',
            'opportunity_emails'
        ],
        'send_test_email' => []
    ];

    $dmzOperations = [
        'search_stocks',
        'fetch_stock',
        'get_stock_price',
        'fetch_stock_history',
        'get_fx_rate',
        'get_fx_history',
        'get_news'
    ];
    $requestType = $_POST['type'] ?? '';
    if (!is_string($requestType) ||
        !array_key_exists($requestType, $requestFields)) {

        sendApiResponse([
            'success' => false,
            'message' => 'Unsupported message type'
        ], 400);
    }


    $outgoingMessage = [
        'type' => $requestType
    ];

    foreach ($requestFields[$requestType] as $field) {
        if (!isset($_POST[$field])) {
            continue;
        }

        $postedValue = $_POST[$field];

        if (!is_string($postedValue) || strlen($postedValue) > 500) {
            sendApiResponse([
                'success' => false,
                'message' => 'Invalid ' . $field
            ], 400);
        }

        $outgoingMessage[$field] = trim($postedValue);
    }


    $needsTicker = in_array($requestType, [
        'fetch_stock',
        'get_stock_price',
        'fetch_stock_history',
        'execute_trade'
    ], true);

    if ($needsTicker) {
        $tickerKey = $requestType === 'execute_trade'
            ? 'symbol'
            : 'ticker';

        $ticker = strtoupper($outgoingMessage[$tickerKey] ?? '');

        if (!preg_match('/^[A-Z0-9.\-]{1,15}$/', $ticker)) {
            sendApiResponse([
                'success' => false,
                'message' => 'Invalid ticker'
            ], 400);
        }

        $outgoingMessage[$tickerKey] = $ticker;
    }


    if ($requestType === 'execute_trade') {
        $tradeSide = $outgoingMessage['side'] ?? '';
        $shareCount = $outgoingMessage['quantity'] ?? '';

        $validSide = in_array($tradeSide, ['BUY', 'SELL'], true);
        $validShares = preg_match('/^[1-9][0-9]{0,5}$/', $shareCount);

        if (!$validSide || !$validShares) {
            sendApiResponse([
                'success' => false,
                'message' => 'Invalid side or share quantity'
            ], 400);
        }
    }


    if ($requestType === 'convert_currency') {

        $enteredAmount = $outgoingMessage['amount'] ?? '';

        $amountLooksValid = preg_match(
            '/^\d{1,9}(?:\.\d{1,8})?$/',
            $enteredAmount
        );

        if (!$amountLooksValid || (float) $enteredAmount <= 0) {
            sendApiResponse([
                'success' => false,
                'message' => 'Invalid amount'
            ], 400);
        }
    }


    if ($requestType === 'convert_currency') {
        $fromCurrency = $outgoingMessage['from_currency'] ?? '';
        $toCurrency = $outgoingMessage['to_currency'] ?? '';

        if ($fromCurrency === $toCurrency) {
            sendApiResponse([
                'success' => false,
                'message' => 'Choose different currencies'
            ], 400);
        }
    }


    if ($requestType === 'create_currency_alert') {
        $alertCondition = $outgoingMessage['condition_type'] ?? '';
        $targetRate = $outgoingMessage['target_rate'] ?? '';

        $baseCurrency = $outgoingMessage['base_currency'] ?? '';
        $targetCurrency = $outgoingMessage['target_currency'] ?? '';

        $conditionValid = in_array(
            $alertCondition,
            ['above', 'below'],
            true
        );

        $rateValid = preg_match(
            '/^\d{1,9}(?:\.\d{1,8})?$/',
            $targetRate
        ) && (float) $targetRate > 0;

        if (!$conditionValid ||
            !$rateValid ||
            $baseCurrency === $targetCurrency) {

            sendApiResponse([
                'success' => false,
                'message' => 'Invalid alert'
            ], 400);
        }
    }


    if ($requestType === 'update_email_preferences') {
        $emailOptions = [
            'enabled',
            'trade_emails',
            'currency_emails',
            'opportunity_emails'
        ];

        foreach ($emailOptions as $option) {
            $settingValue = $outgoingMessage[$option] ?? null;

            if (!in_array($settingValue, ['0', '1'], true)) {
                sendApiResponse([
                    'success' => false,
                    'message' => 'Invalid email preference'
                ], 400);
            }
        }
    }


    if ($requestType === 'mark_notification_read') {
        $notificationId = $outgoingMessage['notification_id'] ?? '';

        if (!preg_match('/^[1-9][0-9]{0,17}$/', $notificationId)) {
            sendApiResponse([
                'success' => false,
                'message' => 'Invalid notification'
            ], 400);
        }
    }
    $useDmzWorker = in_array($requestType, $dmzOperations, true);

    if ($useDmzWorker) {
        $rabbitSection = 'massiveWorker';
    } else {
        $rabbitSection = 'testServer';
        $outgoingMessage['sessionId'] = $loggedInUser['token'];
    }
    $rabbitClient = mq_client($rabbitSection);
    $backendReply = $rabbitClient->send_request($outgoingMessage);

    if (!is_array($backendReply)) {
        sendApiResponse([
            'success' => false,
            'message' => 'Malformed response from backend'
        ], 502);
    }

    sendApiResponse($backendReply);

} catch (Throwable $problem) {
    error_log('VM1 api RabbitMQ error: ' . $problem->getMessage());

    sendApiResponse([
        'success' => false,
        'message' => 'Backend service unavailable; verify VM2/VM3 worker'
    ], 502);
}

