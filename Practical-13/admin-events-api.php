<?php
require_once __DIR__ . "/../auth.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function sendEventApiResponse(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function eventApiInput(): array
{
    if (isset($_POST["action"])) {
        return $_POST;
    }

    $rawInput = file_get_contents("php://input");
    if (!is_string($rawInput) || $rawInput === "") {
        return [];
    }

    $data = json_decode($rawInput, true);
    return is_array($data) ? $data : [];
}

function eventApiReadAll(mysqli $conn): array
{
    $events = [];
    $stmt = $conn->prepare(
        "SELECT event_id, event_name, event_date, description, poster_path
         FROM events ORDER BY event_date, event_id"
    );
    $stmt->execute();
    $stmt->bind_result($eventId, $eventName, $eventDate, $description, $posterPath);
    while ($stmt->fetch()) {
        $events[] = [
            "event_id" => (int) $eventId,
            "event_name" => $eventName,
            "event_date" => $eventDate,
            "description" => $description ?? "",
            "poster_path" => $posterPath,
        ];
    }
    $stmt->close();
    return $events;
}

startAuthSession();
if (!isset($_SESSION["user_id"]) && !restoreRememberedLogin()) {
    sendEventApiResponse(401, ["success" => false, "message" => "Please log in as an administrator."]);
}
if (($_SESSION["role"] ?? "") !== "admin") {
    sendEventApiResponse(403, ["success" => false, "message" => "You are not authorized to manage events."]);
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    $csrfToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
    if (
        !is_string($csrfToken)
        || !isset($_SESSION["event_admin_csrf"])
        || !hash_equals($_SESSION["event_admin_csrf"], $csrfToken)
    ) {
        sendEventApiResponse(403, ["success" => false, "message" => "Your session expired. Refresh the page and try again."]);
    }
}

$uploadDirectory = __DIR__ . "/../uploads/event-posters";
$uploadUrl = "../uploads/event-posters/";
$maximumPosterSize = 3 * 1024 * 1024;
$allowedPosterTypes = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp",
];
$newPosterPath = null;

try {
    $conn = authDatabase();
    $method = $_SERVER["REQUEST_METHOD"];

    if ($method === "GET") {
        sendEventApiResponse(200, ["success" => true, "events" => eventApiReadAll($conn)]);
    }

    if ($method !== "POST") {
        sendEventApiResponse(405, ["success" => false, "message" => "Method not allowed."]);
    }

    $input = eventApiInput();
    $action = $input["action"] ?? "";
    if (!is_string($action) || !in_array($action, ["create", "update", "delete"], true)) {
        sendEventApiResponse(400, ["success" => false, "message" => "Choose a valid event action."]);
    }

    $eventId = filter_var($input["event_id"] ?? null, FILTER_VALIDATE_INT);
    if ($action === "delete") {
        if (!$eventId || $eventId < 1) {
            sendEventApiResponse(400, ["success" => false, "message" => "A valid event ID is required."]);
        }

        $stmt = $conn->prepare("SELECT poster_path FROM events WHERE event_id = ? LIMIT 1");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $stmt->bind_result($oldPosterPath);
        $found = $stmt->fetch();
        $stmt->close();
        if (!$found) {
            sendEventApiResponse(404, ["success" => false, "message" => "Event not found."]);
        }

        $conn->begin_transaction();
        $registrations = $conn->prepare("DELETE FROM registrations WHERE event_id = ?");
        $registrations->bind_param("i", $eventId);
        $registrations->execute();
        $registrations->close();

        $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $deleted = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$deleted) {
            $conn->rollback();
            sendEventApiResponse(404, ["success" => false, "message" => "Event not found."]);
        }
        $conn->commit();

        if (is_string($oldPosterPath) && $oldPosterPath !== "") {
            $posterFile = $uploadDirectory . DIRECTORY_SEPARATOR . basename($oldPosterPath);
            if (is_file($posterFile) && !unlink($posterFile)) {
                error_log("Unable to remove deleted event poster: " . $posterFile);
            }
        }
        sendEventApiResponse(200, [
            "success" => true,
            "message" => "Event deleted successfully.",
            "events" => eventApiReadAll($conn),
        ]);
    }

    $eventName = $input["event_name"] ?? "";
    $eventDate = $input["event_date"] ?? "";
    $description = $input["description"] ?? "";
    if (!is_string($eventName) || !is_string($eventDate) || !is_string($description)) {
        sendEventApiResponse(422, ["success" => false, "message" => "Enter a valid event name, date, and description."]);
    }
    $eventName = trim($eventName);
    $eventDate = trim($eventDate);
    $description = trim($description);
    $parsedDate = DateTime::createFromFormat("!Y-m-d", $eventDate);
    if (
        $eventName === ""
        || strlen($eventName) > 150
        || strlen($description) > 10000
        || !$parsedDate
        || $parsedDate->format("Y-m-d") !== $eventDate
    ) {
        sendEventApiResponse(422, ["success" => false, "message" => "Enter a valid event name, date, and description."]);
    }

    $oldPosterPath = null;
    if ($action === "update") {
        if (!$eventId || $eventId < 1) {
            sendEventApiResponse(400, ["success" => false, "message" => "A valid event ID is required."]);
        }
        $stmt = $conn->prepare("SELECT poster_path FROM events WHERE event_id = ? LIMIT 1");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $stmt->bind_result($oldPosterPath);
        $found = $stmt->fetch();
        $stmt->close();
        if (!$found) {
            sendEventApiResponse(404, ["success" => false, "message" => "Event not found."]);
        }
    }

    $poster = $_FILES["poster"] ?? null;
    $posterError = is_array($poster) ? ($poster["error"] ?? UPLOAD_ERR_CANT_WRITE) : UPLOAD_ERR_NO_FILE;
    if ($action === "create" && $posterError === UPLOAD_ERR_NO_FILE) {
        sendEventApiResponse(422, ["success" => false, "message" => "Please upload a poster image."]);
    }
    if ($posterError !== UPLOAD_ERR_NO_FILE) {
        if (
            !is_array($poster)
            || !isset($poster["error"], $poster["tmp_name"], $poster["size"])
            || !is_int($poster["error"])
            || !is_string($poster["tmp_name"])
            || !is_int($poster["size"])
        ) {
            sendEventApiResponse(422, ["success" => false, "message" => "The poster upload is invalid."]);
        }
        if (
            $poster["error"] === UPLOAD_ERR_INI_SIZE
            || $poster["error"] === UPLOAD_ERR_FORM_SIZE
            || $poster["size"] > $maximumPosterSize
        ) {
            sendEventApiResponse(422, ["success" => false, "message" => "Poster image must not exceed 3 MB."]);
        }
        if ($poster["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($poster["tmp_name"])) {
            sendEventApiResponse(422, ["success" => false, "message" => "The poster could not be uploaded."]);
        }
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($poster["tmp_name"]);
        if (!is_string($mimeType) || !isset($allowedPosterTypes[$mimeType])) {
            sendEventApiResponse(422, ["success" => false, "message" => "Poster must be a JPEG, PNG, or WebP image."]);
        }
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException("Unable to create event poster directory.");
        }
        $filename = bin2hex(random_bytes(16)) . "." . $allowedPosterTypes[$mimeType];
        if (!move_uploaded_file($poster["tmp_name"], $uploadDirectory . DIRECTORY_SEPARATOR . $filename)) {
            sendEventApiResponse(500, ["success" => false, "message" => "The poster could not be uploaded."]);
        }
        $newPosterPath = $uploadUrl . $filename;
    }

    if ($action === "create") {
        $stmt = $conn->prepare(
            "INSERT INTO events (event_name, event_date, description, poster_path)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $eventName, $eventDate, $description, $newPosterPath);
        $stmt->execute();
        $eventId = $conn->insert_id;
        $stmt->close();
        $message = "Event created successfully.";
    } elseif ($newPosterPath !== null) {
        $stmt = $conn->prepare(
            "UPDATE events SET event_name = ?, event_date = ?, description = ?, poster_path = ?
             WHERE event_id = ?"
        );
        $stmt->bind_param("ssssi", $eventName, $eventDate, $description, $newPosterPath, $eventId);
        $stmt->execute();
        $stmt->close();
        $message = "Event updated successfully.";
    } else {
        $stmt = $conn->prepare(
            "UPDATE events SET event_name = ?, event_date = ?, description = ?
             WHERE event_id = ?"
        );
        $stmt->bind_param("sssi", $eventName, $eventDate, $description, $eventId);
        $stmt->execute();
        $stmt->close();
        $message = "Event updated successfully.";
    }

    if ($newPosterPath !== null && is_string($oldPosterPath) && $oldPosterPath !== "") {
        $oldFile = $uploadDirectory . DIRECTORY_SEPARATOR . basename($oldPosterPath);
        if (is_file($oldFile) && !unlink($oldFile)) {
            error_log("Unable to remove replaced event poster: " . $oldFile);
        }
    }

    sendEventApiResponse(200, [
        "success" => true,
        "message" => $message,
        "events" => eventApiReadAll($conn),
    ]);
} catch (mysqli_sql_exception $exception) {
    if ($newPosterPath !== null) {
        $newFile = $uploadDirectory . DIRECTORY_SEPARATOR . basename($newPosterPath);
        if (is_file($newFile) && !unlink($newFile)) {
            error_log("Unable to remove event poster after failed database operation: " . $newFile);
        }
    }
    error_log("Event API database error: " . $exception->getMessage());
    sendEventApiResponse(500, ["success" => false, "message" => "The event could not be saved. Please try again."]);
} catch (RuntimeException $exception) {
    error_log("Event API error: " . $exception->getMessage());
    sendEventApiResponse(500, ["success" => false, "message" => "The event operation could not be completed."]);
}
