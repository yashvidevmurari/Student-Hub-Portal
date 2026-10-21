<?php

require_once __DIR__ . "/../auth.php";
startAuthSession();

function showLoginError(string $message, string $role): void
{
    $loginPage = $role === "admin" ? "admin-login.html" : "student-login.html";
    http_response_code(401);
    echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\">";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
    echo "<title>Login failed</title></head><body><main><h1>Login failed</h1><p>"
        . htmlspecialchars($message, ENT_QUOTES, "UTF-8")
        . "</p><a href=\""
        . htmlspecialchars($loginPage, ENT_QUOTES, "UTF-8")
        . "\">Try again</a></main></body></html>";
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: student-login.html");
    exit;
}

$login = $_POST["login"] ?? "";
$password = $_POST["password"] ?? "";
$requestedRole = $_POST["role"] ?? "";

if (
    !is_string($login)
    || !is_string($password)
    || !in_array($requestedRole, ["student", "admin"], true)
    || trim($login) === ""
    || $password === ""
) {
    showLoginError("Enter a valid username/email and password.", is_string($requestedRole) ? $requestedRole : "student");
    exit;
}

$login = trim($login);
$conn = authDatabase();
$stmt = $conn->prepare(
    "SELECT student_id, username, email, password, role
     FROM students
     WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)
     LIMIT 1"
);
$stmt->bind_param("ss", $login, $login);
$stmt->execute();
$stmt->bind_result($userId, $username, $email, $passwordHash, $role);
$found = $stmt->fetch();
$stmt->close();

if (
    !$found
    || !is_string($passwordHash)
    || !password_verify($password, $passwordHash)
    || $role !== $requestedRole
) {
    showLoginError("The username/email or password is incorrect.", $requestedRole);
    exit;
}

session_regenerate_id(true);
$_SESSION["user_id"] = (int) $userId;
$_SESSION["username"] = $username;
$_SESSION["email"] = $email;
$_SESSION["role"] = $role;
$_SESSION["last_activity"] = time();

if (($_POST["remember"] ?? "") === "1") {
    issueRememberToken((int) $userId);
} else {
    deleteRememberToken((int) $userId);
}

$destination = $role === "admin" ? "admin-dashboard.php" : "student-dashboard.php";
header("Location: " . $destination);
exit;

?>
