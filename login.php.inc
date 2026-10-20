<?php

class loginDB
{
    private $logindb;

    public function __construct()
    {
        $configFile = __DIR__ . "/db.local.ini";
        $config = parse_ini_file($configFile, true);

        if ($config === false || !isset($config["database"])) {
            throw new Exception("Could not read db.local.ini");
        }

        $db = $config["database"];

        $this->logindb = new mysqli(
            $db["HOST"],
            $db["USER"],
            $db["PASSWORD"],
            $db["DATABASE"]
        );

        if ($this->logindb->connect_errno) {
            throw new Exception(
                "Database connection failed: " .
                $this->logindb->connect_error
            );
        }

        $this->logindb->set_charset("utf8mb4");

        echo "Database connection successful" . PHP_EOL;
    }


    // ---------------------------------------------------
    // REGISTER USER
    // ---------------------------------------------------
    public function register(
        $username,
        $password,
        $firstName = null,
        $lastName = null,
        $email = null
    )
    {
        $username = trim($username);
        $firstName = $firstName !== null ? trim($firstName) : null;
        $lastName = $lastName !== null ? trim($lastName) : null;
        $email = $email !== null ? trim($email) : null;

        if ($email === "") {
            $email = null;
        }

        if ($username === "" || $password === "") {
            return [
                "success" => false,
                "message" => "Username and password are required"
            ];
        }

        // Check username
        $stmt = $this->logindb->prepare(
            "SELECT id
             FROM users
             WHERE username = ?"
        );

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return [
                "success" => false,
                "message" => "Username already exists"
            ];
        }

        $stmt->close();

        // Check email if one was supplied
        if ($email !== null) {
            $stmt = $this->logindb->prepare(
                "SELECT id
                 FROM users
                 WHERE email = ?"
            );

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                return [
                    "success" => false,
                    "message" => "Email already exists"
                ];
            }

            $stmt->close();
        }

        // Hash password
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $this->logindb->prepare(
            "INSERT INTO users
             (
                username,
                password_hash,
                first_name,
                last_name,
                email
             )
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "sssss",
            $username,
            $passwordHash,
            $firstName,
            $lastName,
            $email
        );

        if (!$stmt->execute()) {
            return [
                "success" => false,
                "message" => "Registration failed: " . $stmt->error
            ];
        }

        $stmt->close();

        return [
            "success" => true,
            "message" => "Registration successful"
        ];
    }


    // ---------------------------------------------------
    // LOGIN
    // ---------------------------------------------------
    public function validateLogin($username, $password)
    {
        $username = trim($username);

        if ($username === "" || $password === "") {
            return [
                "success" => false,
                "message" => "Username and password are required"
            ];
        }

        $stmt = $this->logindb->prepare(
            "SELECT
                id,
                username,
                password_hash
             FROM users
             WHERE username = ?"
        );

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            $stmt->close();

            return [
                "success" => false,
                "message" => "Invalid username or password"
            ];
        }

        $user = $result->fetch_assoc();
        $stmt->close();

        if (!password_verify(
            $password,
            $user["password_hash"]
        )) {
            return [
                "success" => false,
                "message" => "Invalid username or password"
            ];
        }

        // Generate 32 random bytes.
        // bin2hex makes this exactly 64 characters.
        $sessionKey = bin2hex(random_bytes(32));

        $stmt = $this->logindb->prepare(
            "INSERT INTO sessions
             (
                user_id,
                session_key,
                expires_at
             )
             VALUES (
                ?,
                ?,
                DATE_ADD(NOW(), INTERVAL 1 DAY)
             )"
        );

        $stmt->bind_param(
            "is",
            $user["id"],
            $sessionKey
        );

        if (!$stmt->execute()) {
            return [
                "success" => false,
                "message" => "Unable to create session: " .
                             $stmt->error
            ];
        }

        $stmt->close();

        return [
            "success" => true,
            "message" => "Login successful",
            "username" => $user["username"],
            "sessionId" => $sessionKey
        ];
    }


    // ---------------------------------------------------
    // VALIDATE SESSION
    // ---------------------------------------------------
    public function validateSession($sessionKey)
    {
        $sessionKey = trim($sessionKey);

        if ($sessionKey === "") {
            return [
                "success" => false,
                "message" => "Missing session key"
            ];
        }

        $stmt = $this->logindb->prepare(
            "SELECT
                users.id,
                users.username
             FROM sessions
             INNER JOIN users
                ON users.id = sessions.user_id
             WHERE sessions.session_key = ?
               AND sessions.expires_at > NOW()"
        );

        $stmt->bind_param("s", $sessionKey);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            $stmt->close();

            return [
                "success" => false,
                "message" => "Invalid or expired session"
            ];
        }

        $user = $result->fetch_assoc();
        $stmt->close();

        return [
            "success" => true,
            "message" => "Session valid",
            "username" => $user["username"]
        ];
    }


    // ---------------------------------------------------
    // LOGOUT
    // ---------------------------------------------------
    public function logout($sessionKey)
    {
        $sessionKey = trim($sessionKey);

        if ($sessionKey === "") {
            return [
                "success" => false,
                "message" => "Missing session key"
            ];
        }

        $stmt = $this->logindb->prepare(
            "DELETE FROM sessions
             WHERE session_key = ?"
        );

        $stmt->bind_param("s", $sessionKey);
        $stmt->execute();

        $deleted = $stmt->affected_rows;
        $stmt->close();

        if ($deleted === 0) {
            return [
                "success" => false,
                "message" => "Session not found"
            ];
        }

        return [
            "success" => true,
            "message" => "Logout successful"
        ];
    }
}
