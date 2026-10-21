<?php

const AUTH_SESSION_TIMEOUT = 1800;
const AUTH_REMEMBER_COOKIE = "studenthub_remember";
const AUTH_REMEMBER_DAYS = 30;

function startAuthSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/WDF/StudentHub/",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax",
    ]);

    session_start();

    if (isset($_SESSION["last_activity"]) && time() - $_SESSION["last_activity"] > AUTH_SESSION_TIMEOUT) {
        $expiredUserId = isset($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null;
        $_SESSION = [];
        session_regenerate_id(true);
        if ($expiredUserId !== null) {
            deleteRememberToken($expiredUserId);
        }
    }

    $_SESSION["last_activity"] = time();
}

function authDatabase(): mysqli
{
    global $conn;
    require_once __DIR__ . "/db_mysqli.php";
    return $conn;
}

function setRememberCookie(string $value, int $expires): void
{
    setcookie(AUTH_REMEMBER_COOKIE, $value, [
        "expires" => $expires,
        "path" => "/WDF/StudentHub/",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
}

function deleteRememberToken(?int $userId = null): void
{
    $selector = null;
    if (isset($_COOKIE[AUTH_REMEMBER_COOKIE])) {
        $parts = explode(":", $_COOKIE[AUTH_REMEMBER_COOKIE], 2);
        if (count($parts) === 2 && preg_match("/^[a-f0-9]{32}$/", $parts[0])) {
            $selector = $parts[0];
        }
    }

    if ($selector !== null) {
        $conn = authDatabase();
        $stmt = $conn->prepare(
            "UPDATE students
             SET remember_selector = NULL, remember_token_hash = NULL, remember_expires_at = NULL
             WHERE remember_selector = ?"
        );
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $stmt->close();
    }

    if ($userId !== null) {
        $conn = authDatabase();
        $stmt = $conn->prepare(
            "UPDATE students
             SET remember_selector = NULL, remember_token_hash = NULL, remember_expires_at = NULL
             WHERE student_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
    }

    setRememberCookie("", time() - 3600);
}

function issueRememberToken(int $userId): void
{
    $expires = time() + AUTH_REMEMBER_DAYS * 24 * 60 * 60;
    $expiresAt = date("Y-m-d H:i:s", $expires);
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash("sha256", $validator);

    $conn = authDatabase();
    $stmt = $conn->prepare(
        "UPDATE students
         SET remember_selector = ?, remember_token_hash = ?, remember_expires_at = ?
         WHERE student_id = ?"
    );
    $stmt->bind_param("sssi", $selector, $tokenHash, $expiresAt, $userId);
    $stmt->execute();
    $stmt->close();

    setRememberCookie($selector . ":" . $validator, $expires);
}

function restoreRememberedLogin(): bool
{
    if (!isset($_COOKIE[AUTH_REMEMBER_COOKIE])) {
        return false;
    }

    $parts = explode(":", $_COOKIE[AUTH_REMEMBER_COOKIE], 2);
    if (
        count($parts) !== 2
        || !preg_match("/^[a-f0-9]{32}$/", $parts[0])
        || !preg_match("/^[a-f0-9]{64}$/", $parts[1])
    ) {
        deleteRememberToken();
        return false;
    }

    $conn = authDatabase();
    $stmt = $conn->prepare(
        "SELECT student_id, username, email, role, remember_token_hash
         FROM students
         WHERE remember_selector = ? AND remember_expires_at > NOW()
         LIMIT 1"
    );
    $stmt->bind_param("s", $parts[0]);
    $stmt->execute();
    $stmt->bind_result($userId, $username, $email, $role, $storedHash);

    if (
        !$stmt->fetch()
        || !is_string($storedHash)
        || !hash_equals($storedHash, hash("sha256", $parts[1]))
    ) {
        $stmt->close();
        deleteRememberToken();
        return false;
    }

    $stmt->close();
    session_regenerate_id(true);
    $_SESSION["user_id"] = (int) $userId;
    $_SESSION["username"] = $username;
    $_SESSION["email"] = $email;
    $_SESSION["role"] = $role;
    $_SESSION["last_activity"] = time();
    issueRememberToken((int) $userId);

    return true;
}

function requireRole(string $role): void
{
    startAuthSession();

    if (!isset($_SESSION["user_id"]) && !restoreRememberedLogin()) {
        $login = $role === "admin" ? "admin-login.html" : "student-login.html";
        header("Location: " . $login);
        exit;
    }

    if (($_SESSION["role"] ?? "") !== $role) {
        http_response_code(403);
        exit("You are not authorized to view this page.");
    }
}

function getLogoutCsrfToken(): string
{
    startAuthSession();
    if (!isset($_SESSION["logout_csrf"])) {
        $_SESSION["logout_csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["logout_csrf"];
}

?>
