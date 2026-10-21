<?php
require_once __DIR__ . "/../auth.php";
requireRole("admin");

$conn = authDatabase();
$courses = ["BCA", "BBA", "B.Tech", "MCA", "DCS"];
$years = [1, 2, 3, 4];
$message = "";
$messageType = "success";
$editStudent = null;
$students = [];
$search = "";
$courseFilter = "";

function studentPageEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function studentFormValue(string $key): string
{
    $value = $_POST[$key] ?? "";
    return is_string($value) ? trim($value) : "";
}

function studentPageRedirect(string $status): void
{
    header("Location: admin-view.php?status=" . rawurlencode($status));
    exit;
}

if (!isset($_SESSION["student_admin_csrf"])) {
    $_SESSION["student_admin_csrf"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["student_admin_csrf"];

$statuses = [
    "created" => ["Student added successfully.", "success"],
    "updated" => ["Student updated successfully.", "success"],
    "deleted" => ["Student deleted successfully.", "success"],
    "not_found" => ["The requested student record was not found.", "error"],
    "invalid" => ["Please enter valid student details and try again.", "error"],
    "duplicate" => ["A student with that email address already exists.", "error"],
    "failed" => ["The operation could not be completed. Please try again.", "error"],
];
$status = $_GET["status"] ?? "";
if (is_string($status) && isset($statuses[$status])) {
    [$message, $messageType] = $statuses[$status];
}

try {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $postedToken = $_POST["csrf_token"] ?? "";
        if (
            !is_string($postedToken)
            || !hash_equals($csrfToken, $postedToken)
        ) {
            studentPageRedirect("failed");
        }

        $action = $_POST["action"] ?? "";
        $studentId = filter_var($_POST["student_id"] ?? null, FILTER_VALIDATE_INT);

        if ($action === "delete") {
            if (!$studentId || $studentId === (int) ($_SESSION["user_id"] ?? 0)) {
                studentPageRedirect("not_found");
            }

            $stmt = $conn->prepare(
                "DELETE FROM students WHERE student_id = ? AND role = 'student'"
            );
            $stmt->bind_param("i", $studentId);
            $stmt->execute();
            $deleted = $stmt->affected_rows === 1;
            $stmt->close();

            studentPageRedirect($deleted ? "deleted" : "not_found");
        }

        if (!in_array($action, ["create", "update"], true)) {
            studentPageRedirect("failed");
        }

        $name = studentFormValue("name");
        $email = strtolower(studentFormValue("email"));
        $mobile = studentFormValue("mobile");
        $course = studentFormValue("course");
        $year = studentFormValue("year");

        if (
            !preg_match("/^[A-Za-z ]{3,50}$/", $name)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($email) > 254
            || !preg_match("/^[6-9][0-9]{9}$/", $mobile)
            || !in_array($course, $courses, true)
            || !in_array($year, array_map("strval", $years), true)
        ) {
            studentPageRedirect("invalid");
        }

        $yearNumber = (int) $year;
        if ($action === "create") {
            $stmt = $conn->prepare(
                "INSERT INTO students (name, email, mobile, course, year)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssssi", $name, $email, $mobile, $course, $yearNumber);
            $stmt->execute();
            $stmt->close();

            studentPageRedirect("created");
        }

        if (!$studentId) {
            studentPageRedirect("not_found");
        }

        $stmt = $conn->prepare(
            "UPDATE students
             SET name = ?, email = ?, mobile = ?, course = ?, year = ?
             WHERE student_id = ? AND role = 'student'"
        );
        $stmt->bind_param(
            "ssssii",
            $name,
            $email,
            $mobile,
            $course,
            $yearNumber,
            $studentId
        );
        $stmt->execute();
        $updated = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$updated) {
            $check = $conn->prepare(
                "SELECT student_id FROM students
                 WHERE student_id = ? AND role = 'student' LIMIT 1"
            );
            $check->bind_param("i", $studentId);
            $check->execute();
            $check->store_result();
            $exists = $check->num_rows === 1;
            $check->close();
            studentPageRedirect($exists ? "updated" : "not_found");
        }

        studentPageRedirect("updated");
    }

    $search = $_GET["search"] ?? "";
    $courseFilter = $_GET["course"] ?? "";
    if (!is_string($search)) {
        $search = "";
    }
    if (!is_string($courseFilter) || !in_array($courseFilter, $courses, true)) {
        $courseFilter = "";
    }
    $search = trim($search);

    if ($search !== "" && $courseFilter !== "") {
        $pattern = "%" . $search . "%";
        $stmt = $conn->prepare(
            "SELECT student_id, name, email, mobile, course, year
             FROM students
             WHERE role = 'student' AND course = ?
               AND (CAST(student_id AS CHAR) LIKE ? OR name LIKE ?
                    OR email LIKE ? OR mobile LIKE ?)
             ORDER BY student_id DESC"
        );
        $stmt->bind_param("sssss", $courseFilter, $pattern, $pattern, $pattern, $pattern);
    } elseif ($search !== "") {
        $pattern = "%" . $search . "%";
        $stmt = $conn->prepare(
            "SELECT student_id, name, email, mobile, course, year
             FROM students
             WHERE role = 'student'
               AND (CAST(student_id AS CHAR) LIKE ? OR name LIKE ?
                    OR email LIKE ? OR mobile LIKE ?)
             ORDER BY student_id DESC"
        );
        $stmt->bind_param("ssss", $pattern, $pattern, $pattern, $pattern);
    } elseif ($courseFilter !== "") {
        $stmt = $conn->prepare(
            "SELECT student_id, name, email, mobile, course, year
             FROM students
             WHERE role = 'student' AND course = ?
             ORDER BY student_id DESC"
        );
        $stmt->bind_param("s", $courseFilter);
    } else {
        $stmt = $conn->prepare(
            "SELECT student_id, name, email, mobile, course, year
             FROM students
             WHERE role = 'student'
             ORDER BY student_id DESC"
        );
    }
    $stmt->execute();
    $stmt->bind_result($recordId, $recordName, $recordEmail, $recordMobile, $recordCourse, $recordYear);
    while ($stmt->fetch()) {
        $students[] = [
            "student_id" => (int) $recordId,
            "name" => $recordName,
            "email" => $recordEmail,
            "mobile" => $recordMobile,
            "course" => $recordCourse,
            "year" => (int) $recordYear,
        ];
    }
    $stmt->close();

    $editId = filter_var($_GET["edit"] ?? null, FILTER_VALIDATE_INT);
    if ($editId) {
        $stmt = $conn->prepare(
            "SELECT student_id, name, email, mobile, course, year
             FROM students WHERE student_id = ? AND role = 'student' LIMIT 1"
        );
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $stmt->bind_result($recordId, $recordName, $recordEmail, $recordMobile, $recordCourse, $recordYear);
        if ($stmt->fetch()) {
            $editStudent = [
                "student_id" => (int) $recordId,
                "name" => $recordName,
                "email" => $recordEmail,
                "mobile" => $recordMobile,
                "course" => $recordCourse,
                "year" => (int) $recordYear,
            ];
        }
        $stmt->close();
        if ($editStudent === null) {
            $message = $statuses["not_found"][0];
            $messageType = "error";
        }
    }
} catch (mysqli_sql_exception $exception) {
    error_log("Student management database error: " . $exception->getMessage());
    if ((int) $exception->getCode() === 1062) {
        $message = $statuses["duplicate"][0];
    } else {
        $message = $statuses["failed"][0];
    }
    $messageType = "error";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students | Student Hub</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-students">
    <header class="header">
        <div class="logo">
            <img src="https://img.icons8.com/bubbles/1200/charotar-university.jpg"
                 alt="University Logo" width="50" height="50">
            <h2>Student Hub</h2>
        </div>
        <nav class="nav" aria-label="Admin navigation">
            <a href="admin-dashboard.php" class="nav-btn">Dashboard</a>
            <form action="auth-logout.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= studentPageEscape(getLogoutCsrfToken()) ?>">
                <button type="submit" class="nav-btn">Logout</button>
            </form>
        </nav>
    </header>

    <main class="student-management">
        <h1>Student Records</h1>
        <p>Add, view, edit, delete, search, and filter student records.</p>

        <?php if ($message !== ""): ?>
            <p class="student-notice <?= studentPageEscape($messageType) ?>" role="status">
                <?= studentPageEscape($message) ?>
            </p>
        <?php endif; ?>

        <section class="student-panel" aria-labelledby="student-form-heading">
            <h2 id="student-form-heading"><?= $editStudent ? "Edit Student" : "Add Student" ?></h2>
            <form method="POST" action="admin-view.php" class="student-record-form">
                <input type="hidden" name="csrf_token" value="<?= studentPageEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="<?= $editStudent ? "update" : "create" ?>">
                <?php if ($editStudent): ?>
                    <input type="hidden" name="student_id" value="<?= $editStudent["student_id"] ?>">
                <?php endif; ?>

                <label>
                    Full name
                    <input type="text" name="name" minlength="3" maxlength="50"
                           pattern="[A-Za-z ]{3,50}" required
                           value="<?= studentPageEscape($editStudent["name"] ?? "") ?>">
                </label>
                <label>
                    Email
                    <input type="email" name="email" maxlength="254" required
                           value="<?= studentPageEscape($editStudent["email"] ?? "") ?>">
                </label>
                <label>
                    Phone number
                    <input type="tel" name="mobile" inputmode="numeric"
                           pattern="[6-9][0-9]{9}" maxlength="10" required
                           value="<?= studentPageEscape($editStudent["mobile"] ?? "") ?>">
                </label>
                <label>
                    Course
                    <select name="course" required>
                        <option value="">Select course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= studentPageEscape($course) ?>"
                                <?= ($editStudent["course"] ?? "") === $course ? "selected" : "" ?>>
                                <?= studentPageEscape($course) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Year
                    <select name="year" required>
                        <option value="">Select year</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?= $year ?>"
                                <?= (int) ($editStudent["year"] ?? 0) === $year ? "selected" : "" ?>>
                                Year <?= $year ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="student-form-actions">
                    <button type="submit"><?= $editStudent ? "Save Changes" : "Add Student" ?></button>
                    <?php if ($editStudent): ?>
                        <a class="student-cancel" href="admin-view.php">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="student-panel" aria-labelledby="student-list-heading">
            <h2 id="student-list-heading">All Students</h2>
            <form method="GET" action="admin-view.php" class="student-filters">
                <label>
                    Search by ID, name, email, or phone
                    <input type="search" name="search" value="<?= studentPageEscape($search) ?>"
                           placeholder="Search student records">
                </label>
                <label>
                    Filter by course
                    <select name="course">
                        <option value="">All courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= studentPageEscape($course) ?>"
                                <?= $courseFilter === $course ? "selected" : "" ?>>
                                <?= studentPageEscape($course) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Search / Filter</button>
                <a class="student-cancel" href="admin-view.php">Clear</a>
            </form>

            <div class="student-table-wrap">
                <table class="student-record-table">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Course</th>
                            <th scope="col">Year</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$students): ?>
                            <tr><td colspan="7">No student records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= $student["student_id"] ?></td>
                                    <td><?= studentPageEscape((string) $student["name"]) ?></td>
                                    <td><?= studentPageEscape((string) $student["email"]) ?></td>
                                    <td><?= studentPageEscape((string) $student["mobile"]) ?></td>
                                    <td><?= studentPageEscape((string) $student["course"]) ?></td>
                                    <td><?= $student["year"] ?></td>
                                    <td class="student-actions">
                                        <a href="admin-view.php?edit=<?= $student["student_id"] ?>">Edit</a>
                                        <form method="POST" action="admin-view.php"
                                              onsubmit="return confirm('Delete this student record?');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= studentPageEscape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="student_id"
                                                   value="<?= $student["student_id"] ?>">
                                            <button type="submit" class="student-delete">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
