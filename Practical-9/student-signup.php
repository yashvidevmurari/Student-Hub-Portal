<?php

require_once __DIR__ . "/../db_mysqli.php";

function getRegistrationField(string $key): string
{
    $value = $_POST[$key] ?? "";
    return is_string($value) ? trim($value) : "";
}

function renderRegistrationMessage(array $messages, bool $success = false): void
{
    $title = $success ? "Registration Successful" : "Registration Failed";
    $destination = $success ? "student-login.html" : "student-signup.html";
    $linkText = $success ? "Continue to login" : "Go back";

    echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\">";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
    echo "<title>" . htmlspecialchars($title, ENT_QUOTES, "UTF-8") . "</title>";
    echo "</head><body><main><h1>"
        . htmlspecialchars($title, ENT_QUOTES, "UTF-8")
        . "</h1><ul>";

    foreach ($messages as $message) {
        echo "<li>" . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . "</li>";
    }

    echo "</ul><a href=\""
        . htmlspecialchars($destination, ENT_QUOTES, "UTF-8")
        . "\">"
        . htmlspecialchars($linkText, ENT_QUOTES, "UTF-8")
        . "</a></main></body></html>";
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    renderRegistrationMessage(["Submit the registration form to create an account."]);
    exit;
}

$name = getRegistrationField("name");
$username = strtolower(getRegistrationField("username"));
$email = strtolower(getRegistrationField("email"));
$mobile = getRegistrationField("mobile");
$password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";
$confirmPassword = is_string($_POST["confirmPassword"] ?? null) ? $_POST["confirmPassword"] : "";
$course = getRegistrationField("course");
$year = getRegistrationField("year");
$gender = getRegistrationField("gender");
$terms = ($_POST["terms"] ?? "") === "accepted";

$errors = [];

if (!preg_match("/^[A-Za-z ]{3,50}$/", $name)) {
    $errors[] = "Name must contain 3-50 letters and spaces.";
}

if (!preg_match("/^[a-zA-Z0-9_]{3,30}$/", $username)) {
    $errors[] = "Username must contain 3-30 letters, numbers, or underscores.";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    $errors[] = "Enter a valid email address of no more than 254 characters.";
}

if (!preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
    $errors[] = "Phone number must be 10 digits and start with 6-9.";
}

if (
    strlen($password) > 72
    || !preg_match("/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[@#$%!]).{8,}$/", $password)
) {
    $errors[] = "Password must be 8-72 characters and include uppercase, lowercase, number, and special character (@#$%!).";
}

if ($confirmPassword === "" || $password !== $confirmPassword) {
    $errors[] = "Passwords are required and must match.";
}

if (!in_array($course, ["BCA", "BBA", "B.Tech", "MCA"], true)) {
    $errors[] = "Please select a valid course.";
}

if (!in_array($year, ["1", "2", "3", "4"], true)) {
    $errors[] = "Please select a valid year.";
}

if (!in_array($gender, ["Male", "Female", "Other"], true)) {
    $errors[] = "Please select a valid gender.";
}

if (!$terms) {
    $errors[] = "Please accept the Terms and Conditions.";
}

if ($errors) {
    renderRegistrationMessage($errors);
    exit;
}

$year = (int) $year;

try {
    $check = $conn->prepare(
        "SELECT email, username FROM students
         WHERE LOWER(email) = ? OR LOWER(username) = ?
         LIMIT 1"
    );
    $check->bind_param("ss", $email, $username);
    $check->execute();
    $check->bind_result($existingEmail, $existingUsername);

    if ($check->fetch()) {
        $duplicates = [];
        if (strtolower($existingEmail) === $email) {
            $duplicates[] = "This email is already registered.";
        }
        if (strtolower($existingUsername ?? "") === $username) {
            $duplicates[] = "This username is already taken.";
        }
        $check->close();
        $conn->close();
        renderRegistrationMessage($duplicates);
        exit;
    }

    $check->close();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        throw new RuntimeException("Password hashing failed.");
    }

    $stmt = $conn->prepare(
        "INSERT INTO students
         (name, username, email, mobile, course, year, gender, password)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "sssssiss",
        $name,
        $username,
        $email,
        $mobile,
        $course,
        $year,
        $gender,
        $hashedPassword
    );
    $stmt->execute();
    $stmt->close();
    $conn->close();

    renderRegistrationMessage(["Your account has been created. You can now log in."], true);
} catch (mysqli_sql_exception $exception) {
    error_log("Student registration database error: " . $exception->getMessage());
    $conn->close();

    if ((int) $exception->getCode() === 1062) {
        renderRegistrationMessage(["That email or username is already registered."]);
    } else {
        renderRegistrationMessage(["Unable to register right now. Please try again later."]);
    }
} catch (RuntimeException $exception) {
    error_log("Student registration error: " . $exception->getMessage());
    $conn->close();
    renderRegistrationMessage(["Unable to register right now. Please try again later."]);
}

?>
