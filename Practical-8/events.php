<?php

include "db.php";

$stmt = $pdo->prepare("SELECT * FROM events ORDER BY event_date");
$stmt->execute();

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>

<head>

    <title>Student Hub - Events</title>

    <style>

        body {
            font-family: Arial;
            background: #f4f7fc;
            padding: 30px;
        }

        h1 {
            text-align: center;
            color: #003366;
        }

        .event {
            background: white;
            padding: 20px;
            margin: 20px auto;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #ccc;
        }

        .event h2 {
            color: #0056b3;
        }

        .register-btn {
            display: inline-block;
            background: #007BFF;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
        }

    </style>

</head>

<body>

<h1>Student Hub Events</h1>

<?php foreach ($events as $event) { ?>

    <div class="event">

        <h2>
            <?php echo htmlspecialchars($event["event_name"]); ?>
        </h2>

        <p>
            <b>Date:</b>
            <?php echo htmlspecialchars($event["event_date"]); ?>
        </p>

        <p>
            <?php echo htmlspecialchars($event["description"]); ?>
        </p>

        <a
            class="register-btn"
            href="event-register.php?event_id=<?php echo $event["event_id"]; ?>"
        >
            Register
        </a>

    </div>

<?php } ?>

</body>

</html>
