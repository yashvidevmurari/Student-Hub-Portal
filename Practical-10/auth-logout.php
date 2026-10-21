<?php

require_once __DIR__ . "/../auth.php";
startAuthSession();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit("Logout must be submitted using POST.");
}

$csrfToken = $_POST["csrf_token"] ?? "";
if (
    !is_string($csrfToken)
    || !isset($_SESSION["logout_csrf"])
    || !hash_equals($_SESSION["logout_csrf"], $csrfToken)
) {
    http_response_code(403);
    exit("Invalid logout request.");
}

deleteRememberToken(isset($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null);
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", [
        "expires" => time() - 42000,
        "path" => $params["path"],
        "domain" => $params["domain"],
        "secure" => $params["secure"],
        "httponly" => $params["httponly"],
        "samesite" => "Lax",
    ]);
}

session_destroy();
header("Location: choose.html");
exit;

?>
