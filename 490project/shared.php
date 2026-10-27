<?php
const LIBDIR = '/var/www/authlib';
function mq_client(string $section): rabbitMQClient
{
    static $libraryReady = false;

    if (!$libraryReady) {
        if (!is_file(LIBDIR . '/rabbitmq.local.ini')) {
            throw new RuntimeException('RabbitMQ configuration unavailable');
        }
        if (!chdir(LIBDIR)) {
            throw new RuntimeException('Authlib directory unavailable');
        }

        require_once LIBDIR . '/get_host_info.inc';
        require_once LIBDIR . '/rabbitMQLib.inc';
        $libraryReady = true;
    }

    return new rabbitMQClient(LIBDIR . '/rabbitmq.local.ini', $section);
}

function auth_state(): ?array
{
    $sessionKey = $_COOKIE['auth_token'] ?? '';
    if (!is_string($sessionKey) || !preg_match('/^[a-f0-9]{64}$/i', $sessionKey)) {
        return null;
    }

    $sessionCheck = mq_client('testServer')->send_request([
        'type' => 'validate_session',
        'sessionId' => $sessionKey
    ]);

    if (!is_array($sessionCheck) || empty($sessionCheck['success'])) {
        return null;
    }

    return [
        'token' => $sessionKey,
        'username' => (string) ($sessionCheck['username'] ?? 'User')
    ];
}

function csrf_for(string $token): string
{
    $siteSecret = @file_get_contents(LIBDIR . '/csrf.key');
    if (!is_string($siteSecret) || strlen(trim($siteSecret)) < 32) {
        throw new RuntimeException('Create private /var/www/authlib/csrf.key first');
    }

    return hash_hmac('sha256', $token, trim($siteSecret));
}

function esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function must_be_logged_in(): array
{
    $currentUser = auth_state();
    if ($currentUser !== null) {
        return $currentUser;
    }

    header('Location: index.html');
    exit;
}
