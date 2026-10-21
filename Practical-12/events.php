<?php
require_once __DIR__ . "/db_mysqli.php";

$events = [];
$stmt = $conn->prepare(
    "SELECT event_id, event_name, event_date, description, poster_path
     FROM events ORDER BY event_date"
);
$stmt->execute();
$stmt->bind_result($eventId, $eventName, $eventDate, $description, $posterPath);
while ($stmt->fetch()) {
    $events[] = [
        "event_id" => (int) $eventId,
        "event_name" => $eventName,
        "event_date" => $eventDate,
        "description" => $description,
        "poster_path" => $posterPath,
    ];
}
$stmt->close();
$conn->close();
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

        .event-poster {
            display: block;
            width: 100%;
            max-height: 320px;
            margin-bottom: 16px;
            border-radius: 8px;
            object-fit: cover;
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

        <?php if (!empty($event["poster_path"])) { ?>
            <img class="event-poster"
                 src="<?php echo htmlspecialchars($event["poster_path"], ENT_QUOTES, "UTF-8"); ?>"
                 alt="Poster for <?php echo htmlspecialchars($event["event_name"], ENT_QUOTES, "UTF-8"); ?>">
        <?php } ?>

        <h2><?php echo htmlspecialchars($event["event_name"], ENT_QUOTES, "UTF-8"); ?></h2>

        <p>
            <b>Date:</b>
            <?php echo htmlspecialchars($event["event_date"], ENT_QUOTES, "UTF-8"); ?>
        </p>

        <p>
            <?php echo nl2br(htmlspecialchars($event["description"], ENT_QUOTES, "UTF-8")); ?>
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