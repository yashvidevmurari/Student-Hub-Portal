<?php
require_once __DIR__ . "/../auth.php";
requireRole("admin");

$conn = authDatabase();
$uploadDirectory = __DIR__ . "/../uploads/event-posters";
$uploadUrl = "../uploads/event-posters/";
$maximumPosterSize = 3 * 1024 * 1024;
$allowedPosterTypes = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp",
];
$events = [];
$editEvent = null;
$message = "";
$messageType = "success";

function eventPageEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function eventPageRedirect(string $status): void
{
    header("Location: admin-events.php?status=" . rawurlencode($status));
    exit;
}

if (!isset($_SESSION["event_admin_csrf"])) {
    $_SESSION["event_admin_csrf"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["event_admin_csrf"];

$statuses = [
    "created" => ["Event added successfully.", "success"],
    "updated" => ["Event updated successfully.", "success"],
    "deleted" => ["Event deleted successfully.", "success"],
    "invalid" => ["Enter a valid event name, date, and description.", "error"],
    "poster_required" => ["Please upload a poster image for the new event.", "error"],
    "poster_type" => ["Poster must be a JPEG, PNG, or WebP image.", "error"],
    "poster_size" => ["Poster image must not exceed 3 MB.", "error"],
    "poster_upload" => ["The poster could not be uploaded. Please try again.", "error"],
    "not_found" => ["The requested event was not found.", "error"],
    "duplicate" => ["An event with these details already exists.", "error"],
    "failed" => ["The operation could not be completed. Please try again.", "error"],
];
$status = $_GET["status"] ?? "";
if (is_string($status) && isset($statuses[$status])) {
    [$message, $messageType] = $statuses[$status];
}

$newPosterPath = null;
$oldPosterPath = null;

try {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $postedToken = $_POST["csrf_token"] ?? "";
        if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
            eventPageRedirect("failed");
        }

        $action = $_POST["action"] ?? "";
        $eventId = filter_var($_POST["event_id"] ?? null, FILTER_VALIDATE_INT);
        $posterData = $_FILES["poster"] ?? null;
        $posterError = is_array($posterData)
            ? ($posterData["error"] ?? UPLOAD_ERR_CANT_WRITE)
            : (array_key_exists("poster", $_FILES) ? UPLOAD_ERR_CANT_WRITE : UPLOAD_ERR_NO_FILE);
        $hasPosterUpload = $posterError !== UPLOAD_ERR_NO_FILE;

        if ($action === "delete") {
            if (!$eventId) {
                eventPageRedirect("not_found");
            }

            $stmt = $conn->prepare("SELECT poster_path FROM events WHERE event_id = ? LIMIT 1");
            $stmt->bind_param("i", $eventId);
            $stmt->execute();
            $stmt->bind_result($oldPosterPath);
            $found = $stmt->fetch();
            $stmt->close();
            if (!$found) {
                eventPageRedirect("not_found");
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
            if ($deleted) {
                $conn->commit();
            } else {
                $conn->rollback();
            }
            if (!$deleted) {
                eventPageRedirect("not_found");
            }

            if (is_string($oldPosterPath) && $oldPosterPath !== "") {
                $posterFilename = basename($oldPosterPath);
                $posterFile = $uploadDirectory . DIRECTORY_SEPARATOR . $posterFilename;
                if (is_file($posterFile) && !unlink($posterFile)) {
                    error_log("Unable to remove deleted event poster: " . $posterFile);
                }
            }
            eventPageRedirect("deleted");
        }

        if (!in_array($action, ["create", "update"], true)) {
            eventPageRedirect("failed");
        }

        $eventName = $_POST["event_name"] ?? "";
        $eventDate = $_POST["event_date"] ?? "";
        $description = $_POST["description"] ?? "";
        if (!is_string($eventName) || !is_string($eventDate) || !is_string($description)) {
            eventPageRedirect("invalid");
        }
        $eventName = trim($eventName);
        $eventDate = trim($eventDate);
        $description = trim($description);
        $parsedDate = DateTime::createFromFormat("!Y-m-d", $eventDate);

        if (
            $eventName === ""
            || strlen($eventName) > 150
            || $description === ""
            || strlen($description) > 10000
            || !$parsedDate
            || $parsedDate->format("Y-m-d") !== $eventDate
        ) {
            eventPageRedirect("invalid");
        }

        if ($action === "update") {
            if (!$eventId) {
                eventPageRedirect("not_found");
            }
            $stmt = $conn->prepare("SELECT poster_path FROM events WHERE event_id = ? LIMIT 1");
            $stmt->bind_param("i", $eventId);
            $stmt->execute();
            $stmt->bind_result($oldPosterPath);
            $found = $stmt->fetch();
            $stmt->close();
            if (!$found) {
                eventPageRedirect("not_found");
            }
        } elseif ($hasPosterUpload) {
            $oldPosterPath = null;
        } else {
            eventPageRedirect("poster_required");
        }

        if ($hasPosterUpload) {
            $poster = $posterData;
            if (
                !is_array($poster)
                || !isset($poster["error"], $poster["tmp_name"], $poster["size"])
                || !is_int($poster["error"])
                || !is_string($poster["tmp_name"])
                || !is_int($poster["size"])
            ) {
                eventPageRedirect("poster_upload");
            }
            if ($poster["error"] === UPLOAD_ERR_INI_SIZE || $poster["error"] === UPLOAD_ERR_FORM_SIZE || $poster["size"] > $maximumPosterSize) {
                eventPageRedirect("poster_size");
            }
            if ($poster["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($poster["tmp_name"])) {
                eventPageRedirect("poster_upload");
            }

            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($poster["tmp_name"]);
            if (!is_string($mimeType) || !isset($allowedPosterTypes[$mimeType])) {
                eventPageRedirect("poster_type");
            }

            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                throw new RuntimeException("Unable to create event poster directory.");
            }
            $posterFilename = bin2hex(random_bytes(16)) . "." . $allowedPosterTypes[$mimeType];
            if (!move_uploaded_file($poster["tmp_name"], $uploadDirectory . DIRECTORY_SEPARATOR . $posterFilename)) {
                eventPageRedirect("poster_upload");
            }
            $newPosterPath = $uploadUrl . $posterFilename;
        }

        if ($action === "create") {
            $stmt = $conn->prepare(
                "INSERT INTO events (event_name, event_date, description, poster_path)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $eventName, $eventDate, $description, $newPosterPath);
        } elseif ($newPosterPath !== null) {
            $stmt = $conn->prepare(
                "UPDATE events
                 SET event_name = ?, event_date = ?, description = ?, poster_path = ?
                 WHERE event_id = ?"
            );
            $stmt->bind_param("ssssi", $eventName, $eventDate, $description, $newPosterPath, $eventId);
        } else {
            $stmt = $conn->prepare(
                "UPDATE events
                 SET event_name = ?, event_date = ?, description = ?
                 WHERE event_id = ?"
            );
            $stmt->bind_param("sssi", $eventName, $eventDate, $description, $eventId);
        }
        $stmt->execute();
        $stmt->close();

        if ($newPosterPath !== null && is_string($oldPosterPath) && $oldPosterPath !== "") {
            $oldFilename = basename($oldPosterPath);
            $oldFile = $uploadDirectory . DIRECTORY_SEPARATOR . $oldFilename;
            if (is_file($oldFile) && !unlink($oldFile)) {
                error_log("Unable to remove replaced event poster: " . $oldFile);
            }
        }

        eventPageRedirect($action === "create" ? "created" : "updated");
    }

    $stmt = $conn->prepare(
        "SELECT event_id, event_name, event_date, description, poster_path
         FROM events ORDER BY event_date, event_id"
    );
    $stmt->execute();
    $stmt->bind_result($rowId, $rowName, $rowDate, $rowDescription, $rowPoster);
    while ($stmt->fetch()) {
        $events[] = [
            "event_id" => (int) $rowId,
            "event_name" => $rowName,
            "event_date" => $rowDate,
            "description" => $rowDescription,
            "poster_path" => $rowPoster,
        ];
    }
    $stmt->close();

    $editId = filter_var($_GET["edit"] ?? null, FILTER_VALIDATE_INT);
    if ($editId) {
        $stmt = $conn->prepare(
            "SELECT event_id, event_name, event_date, description, poster_path
             FROM events WHERE event_id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $stmt->bind_result($rowId, $rowName, $rowDate, $rowDescription, $rowPoster);
        if ($stmt->fetch()) {
            $editEvent = [
                "event_id" => (int) $rowId,
                "event_name" => $rowName,
                "event_date" => $rowDate,
                "description" => $rowDescription,
                "poster_path" => $rowPoster,
            ];
        }
        $stmt->close();
        if ($editEvent === null) {
            $message = $statuses["not_found"][0];
            $messageType = "error";
        }
    }
} catch (mysqli_sql_exception $exception) {
    if ($newPosterPath !== null) {
        $newFile = $uploadDirectory . DIRECTORY_SEPARATOR . basename($newPosterPath);
        if (is_file($newFile)) {
            unlink($newFile);
        }
    }
    error_log("Event management database error: " . $exception->getMessage());
    $message = (int) $exception->getCode() === 1062
        ? $statuses["duplicate"][0]
        : $statuses["failed"][0];
    $messageType = "error";
} catch (RuntimeException $exception) {
    if ($newPosterPath !== null) {
        $newFile = $uploadDirectory . DIRECTORY_SEPARATOR . basename($newPosterPath);
        if (is_file($newFile)) {
            unlink($newFile);
        }
    }
    error_log("Event management error: " . $exception->getMessage());
    $message = $statuses["failed"][0];
    $messageType = "error";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events | Student Hub</title>
    <link rel="stylesheet" href="../css/style.css?v=20261021-event-admin">
</head>
<body class="admin-events"
      data-api-url="admin-events-api.php"
      data-csrf-token="<?= eventPageEscape($csrfToken) ?>">
    <header class="header">
        <div class="logo">
            <img src="https://img.icons8.com/bubbles/1200/charotar-university.jpg"
                 alt="University Logo" width="50" height="50">
            <h2>Student Hub</h2>
        </div>
        <nav class="nav" aria-label="Admin navigation">
            <a href="admin-dashboard.php" class="nav-btn">Dashboard</a>
            <a href="admin-view.php" class="nav-btn">Students</a>
            <a href="admin-events.php" class="nav-btn active" aria-current="page">Events</a>
            <form action="auth-logout.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= eventPageEscape(getLogoutCsrfToken()) ?>">
                <button type="submit" class="nav-btn">Logout</button>
            </form>
        </nav>
    </header>

    <main class="event-management">
        <section class="event-page-heading">
            <div>
                <span class="event-eyebrow">ADMINISTRATION / EVENTS</span>
                <h1>Event Management</h1>
                <p>Create events, upload posters, and keep your student community informed.</p>
            </div>
            <div class="event-count" aria-label="<?= count($events) ?> events">
                <strong><?= count($events) ?></strong>
                <span><?= count($events) === 1 ? "event" : "events" ?> listed</span>
            </div>
        </section>
        <?php if ($message !== ""): ?>
            <p class="event-notice <?= eventPageEscape($messageType) ?>" role="status" data-event-notice>
                <?= eventPageEscape($message) ?>
            </p>
        <?php else: ?>
            <p class="event-notice" role="status" data-event-notice hidden></p>
        <?php endif; ?>

        <section class="event-panel event-editor">
            <div class="event-panel-heading">
                <div>
                    <span class="event-section-label"><?= $editEvent ? "UPDATE DETAILS" : "GET STARTED" ?></span>
                    <h2 data-form-heading><?= $editEvent ? "Edit event" : "Create an event" ?></h2>
                </div>
                <?php if ($editEvent): ?>
                    <button type="button" class="event-cancel-link" data-cancel-edit>Cancel editing</button>
                <?php endif; ?>
            </div>
            <form method="POST" action="admin-events.php" enctype="multipart/form-data"
                  class="event-form" data-event-form>
                <input type="hidden" name="csrf_token" value="<?= eventPageEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="<?= $editEvent ? "update" : "create" ?>">
                <input type="hidden" name="event_id" value="<?= $editEvent["event_id"] ?? "" ?>">
                <label>
                    <span>Event name <span class="event-required">*</span></span>
                    <input type="text" name="event_name" maxlength="150" required
                           placeholder="e.g. Annual Tech Symposium"
                           value="<?= eventPageEscape((string) ($editEvent["event_name"] ?? "")) ?>">
                </label>
                <label>
                    <span>Event date <span class="event-required">*</span></span>
                    <input type="date" name="event_date" required
                           value="<?= eventPageEscape(substr((string) ($editEvent["event_date"] ?? ""), 0, 10)) ?>">
                </label>
                <label class="event-description">
                    <span>Description <span class="event-required">*</span></span>
                    <textarea name="description" maxlength="10000" rows="4" required
                              placeholder="Tell students what this event is about..."><?= eventPageEscape((string) ($editEvent["description"] ?? "")) ?></textarea>
                </label>
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= $maximumPosterSize ?>">
                <div class="event-upload-row">
                    <?php if ($editEvent && !empty($editEvent["poster_path"])): ?>
                        <div class="event-current-poster">
                            <span class="event-upload-title">Current poster</span>
                            <img src="<?= eventPageEscape((string) $editEvent["poster_path"]) ?>"
                                 alt="Current poster for <?= eventPageEscape((string) $editEvent["event_name"]) ?>">
                        </div>
                    <?php endif; ?>
                    <label class="event-upload">
                        <span class="event-upload-title">Event poster <?= $editEvent ? "(optional)" : "<span class=\"event-required\">*</span>" ?></span>
                        <input type="file" name="poster" accept="image/jpeg,image/png,image/webp"
                               <?= $editEvent ? "" : "required" ?>>
                        <small>JPEG, PNG, or WebP. Maximum file size: 3 MB.</small>
                        <?php if ($editEvent): ?><small>Choose a new image only if you want to replace the current poster.</small><?php endif; ?>
                    </label>
                </div>
                <div class="event-form-actions">
                    <button type="submit"><?= $editEvent ? "Save changes" : "Create event" ?></button>
                    <?php if (!$editEvent): ?><span>Fields marked <span class="event-required">*</span> are required.</span><?php endif; ?>
                </div>
            </form>
        </section>

        <section class="event-panel event-list-panel">
            <div class="event-panel-heading">
                <div>
                    <span class="event-section-label">YOUR SCHEDULE</span>
                    <h2>All events</h2>
                </div>
                <span class="event-list-count" data-event-count><?= count($events) ?> total</span>
            </div>
            <div class="event-table-wrap">
                <table class="event-record-table">
                    <thead>
                        <tr>
                            <th scope="col">Poster</th>
                            <th scope="col">Event</th>
                            <th scope="col">Date</th>
                            <th scope="col">Description</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody data-event-list>
                        <?php if (!$events): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="event-empty-state">
                                        <strong>No events yet</strong>
                                        <span>Create your first event using the form above.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($event["poster_path"])): ?>
                                            <img class="event-thumbnail"
                                                 src="<?= eventPageEscape((string) $event["poster_path"]) ?>"
                                                 alt="Poster for <?= eventPageEscape((string) $event["event_name"]) ?>">
                                        <?php else: ?>
                                            <span class="event-no-poster">No poster</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="event-title-cell">
                                        <strong><?= eventPageEscape((string) $event["event_name"]) ?></strong>
                                        <span>Event #<?= $event["event_id"] ?></span>
                                    </td>
                                    <td class="event-date-cell">
                                        <time datetime="<?= eventPageEscape((string) $event["event_date"]) ?>">
                                            <?= eventPageEscape(date("M j, Y", strtotime((string) $event["event_date"]))) ?>
                                        </time>
                                    </td>
                                    <td class="event-description-cell">
                                        <?= eventPageEscape((string) $event["description"]) ?>
                                    </td>
                                    <td class="event-actions">
                                        <a class="event-edit" href="admin-events.php?edit=<?= $event["event_id"] ?>">Edit</a>
                                        <form method="POST" action="admin-events.php"
                                              onsubmit="return confirm('Delete this event?');">
                                            <input type="hidden" name="csrf_token" value="<?= eventPageEscape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="event_id" value="<?= $event["event_id"] ?>">
                                            <button type="submit" class="event-delete">Delete</button>
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
    <script src="../js/admin-events.js?v=20261021-event-api"></script>
</body>
</html>
