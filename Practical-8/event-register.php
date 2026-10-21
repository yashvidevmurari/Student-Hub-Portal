<?php

include "db.php";

$event_id = $_GET["event_id"];

$stmt = $pdo->prepare(
    "SELECT * FROM events WHERE event_id = ?"
);

$stmt->execute([$event_id]);

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event not found.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST["name"];
    $email = $_POST["email"];
    $mobile = $_POST["mobile"];
    $course = $_POST["course"];
    $year = $_POST["year"];

    // Insert student
    $stmt = $pdo->prepare(
        "INSERT INTO students (name, email, mobile, course, year)
         VALUES (?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        $name,
        $email,
        $mobile,
        $course,
        $year
    ]);

    $student_id = $pdo->lastInsertId();

    // Register student for event
    $stmt = $pdo->prepare(
        "INSERT INTO registrations (student_id, event_id)
         VALUES (?, ?)"
    );

    $stmt->execute([
        $student_id,
        $event_id
    ]);

    echo "<script>
            alert('Registration successful!');
            window.location.href='events.php';
          </script>";

    exit;
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Student Hub - Event Registration</title>

    <style>

        body {
            font-family: Arial;
            background: #f4f7fc;
            padding: 30px;
        }

        .form-box {
            background: white;
            max-width: 500px;
            margin: auto;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #ccc;
        }

        h1 {
            text-align: center;
            color: #003366;
        }

        label {
            display: block;
            margin-top: 15px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            margin-top: 20px;
            padding: 10px;
            background: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

    </style>

</head>

<body>

<div class="form-box">

    <h1>Event Registration</h1>

    <h3>
        <?php echo htmlspecialchars($event["event_name"]); ?>
    </h3>

    <p>
        Date:
        <?php echo htmlspecialchars($event["event_date"]); ?>
    </p>

    <form method="POST">

        <label>Name</label>
        <input type="text" name="name" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Mobile</label>
        <input type="text" name="mobile" required>

        <label>Course</label>
        <input type="text" name="course" required>

        <label>Year</label>
        <input type="number" name="year" required>

        <button type="submit">
            Register for Event
        </button>

    </form>

</div>

</body>

</html>
