<?php
require_once __DIR__ . '/login.php.inc';

class notificationDB
{
    private mysqli $db;
    private loginDB $login;

    public function __construct()
    {
        $config = parse_ini_file(__DIR__ . '/db.local.ini', true);
        if (!is_array($config) || !isset($config['database'])) {
            throw new RuntimeException('Missing VM2 database configuration');
        }
        $settings = $config['database'];
        $this->db = new mysqli(
            $settings['HOST'], $settings['USER'],
            $settings['PASSWORD'], $settings['DATABASE']
        );
        $this->db->set_charset('utf8mb4');
        $this->login = new loginDB();
    }

    private function userId($sessionId): ?int
    {
        if (!is_string($sessionId) || $sessionId === '') return null;
        $session = $this->login->validateSession($sessionId);
        if (!is_array($session) || empty($session['success']) || !isset($session['user_id'])) {
            return null;
        }
        return (int) $session['user_id'];
    }

    public function getEmailPreferences($sessionId): array
    {
        $userId = $this->userId($sessionId);
        if ($userId === null) return ['success' => false, 'message' => 'Invalid session'];

        $stmt = $this->db->prepare(
            'SELECT u.email, COALESCE(p.enabled, 0) AS enabled,
                    COALESCE(p.trade_emails, 1) AS trade_emails,
                    COALESCE(p.currency_emails, 1) AS currency_emails,
                    COALESCE(p.opportunity_emails, 0) AS opportunity_emails
             FROM users u
             LEFT JOIN email_notification_preferences p ON p.user_id = u.id
             WHERE u.id = ?'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return ['success' => false, 'message' => 'User not found'];
        return [
            'success' => true,
            'email' => (string) ($row['email'] ?? ''),
            'enabled' => (int) $row['enabled'],
            'trade_emails' => (int) $row['trade_emails'],
            'currency_emails' => (int) $row['currency_emails'],
            'opportunity_emails' => (int) $row['opportunity_emails']
        ];
    }

    public function updateEmailPreferences(array $request): array
    {
        $userId = $this->userId($request['sessionId'] ?? null);
        if ($userId === null) return ['success' => false, 'message' => 'Invalid session'];

        $fields = ['enabled', 'trade_emails', 'currency_emails', 'opportunity_emails'];
        foreach ($fields as $field) {
            if (!in_array($request[$field] ?? null, ['0', '1'], true)) {
                return ['success' => false, 'message' => 'Invalid email preference'];
            }
        }
        $prefs = $this->getEmailPreferences($request['sessionId']);
        if (empty($prefs['success'])) return $prefs;
        if ($request['enabled'] === '1' && !filter_var($prefs['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'No valid account email address'];
        }

        $enabled = (int) $request['enabled'];
        $trade = (int) $request['trade_emails'];
        $currency = (int) $request['currency_emails'];
        $opportunity = (int) $request['opportunity_emails'];
        $stmt = $this->db->prepare(
            'INSERT INTO email_notification_preferences
               (user_id, enabled, trade_emails, currency_emails, opportunity_emails)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               enabled = VALUES(enabled), trade_emails = VALUES(trade_emails),
               currency_emails = VALUES(currency_emails),
               opportunity_emails = VALUES(opportunity_emails)'
        );
        $stmt->bind_param('iiiii', $userId, $enabled, $trade, $currency, $opportunity);
        $stmt->execute();
        $stmt->close();
        return ['success' => true, 'message' => 'Email preferences saved'];
    }

    public function getNotifications($sessionId): array
    {
        $userId = $this->userId($sessionId);
        if ($userId === null) return ['success' => false, 'message' => 'Invalid session'];
        $stmt = $this->db->prepare(
            'SELECT id, message, is_read, created_at
             FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC, id DESC LIMIT 100'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['is_read'] = (int) $row['is_read'];
        }
        unset($row);
        return ['success' => true, 'notifications' => $rows];
    }

    public function markNotificationRead($sessionId, $notificationId): array
    {
        $userId = $this->userId($sessionId);
        if ($userId === null) return ['success' => false, 'message' => 'Invalid session'];
        if (!is_string($notificationId) || !preg_match('/^[1-9][0-9]{0,17}$/', $notificationId)) {
            return ['success' => false, 'message' => 'Invalid notification ID'];
        }
        $stmt = $this->db->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id = ?'
        );
        $stmt->bind_param('is', $userId, $notificationId);
        $stmt->execute();
        $stmt->close();
        return ['success' => true, 'message' => 'Marked as read'];
    }

    public function sendTestEmail($sessionId): array
    {
        $userId = $this->userId($sessionId);
        if ($userId === null) return ['success' => false, 'message' => 'Invalid session'];
        $prefs = $this->getEmailPreferences($sessionId);
        if (empty($prefs['success'])) return $prefs;
        if (empty($prefs['enabled']) || !filter_var($prefs['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Enable email alerts and set a valid account email'];
        }

        // Reserve one test request per user per 60 seconds.
        $stmt = $this->db->prepare(
            'UPDATE email_notification_preferences
             SET last_test_email_at = NOW()
             WHERE user_id = ? AND enabled = 1
               AND (last_test_email_at IS NULL
                    OR last_test_email_at < NOW() - INTERVAL 60 SECOND)'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $reserved = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$reserved) {
            return ['success' => false, 'message' => 'Please wait 60 seconds before another test'];
        }

        $settings = parse_ini_file(__DIR__ . '/rabbitmq.local.ini', true);
        $broker = is_array($settings) ? ($settings['emailDelivery'] ?? null) : null;
        if (!is_array($broker)) {
            return ['success' => false, 'message' => 'VM2 emailDelivery RabbitMQ configuration missing'];
        }
        $job = [
            'type' => 'deliver_email',
            'event_id' => bin2hex(random_bytes(16)),
            'recipient' => $prefs['email'],
            'subject' => 'IT490 test notification',
            'body' => 'This is a test email from your IT490 virtual exchange.'
        ];
        $connection = null;
        try {
            $connection = new AMQPConnection([
                'host' => $broker['BROKER_HOST'],
                'port' => (int) $broker['BROKER_PORT'],
                'login' => $broker['USER'],
                'password' => $broker['PASSWORD'],
                'vhost' => $broker['VHOST']
            ]);
            $connection->connect();
            $channel = new AMQPChannel($connection);
            $exchange = new AMQPExchange($channel);
            $exchange->setName($broker['EXCHANGE']);
            $exchange->setType('topic');
            $sent = $exchange->publish(
                json_encode($job, JSON_THROW_ON_ERROR),
                $broker['ROUTING_KEY'],
                AMQP_NOPARAM,
                ['delivery_mode' => 2, 'content_type' => 'application/json']
            );
            $connection->disconnect();
            if (!$sent) throw new RuntimeException('AMQP publish failed');
        } catch (Throwable $error) {
            if ($connection instanceof AMQPConnection && $connection->isConnected()) {
                $connection->disconnect();
            }
            error_log('Test email publish failed: ' . $error->getMessage());
            return ['success' => false, 'message' => 'Unable to queue test email'];
        }

        // Save an in-app history entry. Queue acceptance is not email delivery.
        $stmt = $this->db->prepare(
            "INSERT INTO notifications (user_id, alert_id, message, notification_type)
             VALUES (?, NULL, 'Test email queued for delivery', 'test_email')"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        return ['success' => true, 'message' => 'Test email queued for delivery'];
    }
}
