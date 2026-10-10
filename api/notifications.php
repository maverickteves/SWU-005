<?php
/**
 * Notifications API Endpoint
 * Handles system and lead activity notifications
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/formatters.php';

$db = getDatabaseConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetNotifications($db);
        break;

    case 'PUT':
        handleUpdateNotification($db);
        break;

    case 'POST':
        handleCreateNotification($db);
        break;

    default:
        errorResponse("Unsupported HTTP method: $method", 405);
}

function formatNotificationRow($row) {
    return [
        'id' => (string)$row['id'],
        'title' => (string)$row['title'],
        'message' => (string)$row['message'],
        'timestampFormatted' => (string)$row['timestamp_formatted'],
        'type' => (string)$row['type'],
        'isRead' => filter_var($row['is_read'], FILTER_VALIDATE_BOOLEAN),
        'targetId' => $row['target_id'] ? (string)$row['target_id'] : null
    ];
}

function handleGetNotifications($db) {
    $stmt = $db->query("SELECT * FROM notifications ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();
    jsonResponse(array_map('formatNotificationRow', $rows));
}

function handleUpdateNotification($db) {
    $input = getJsonInput();
    $action = $_GET['action'] ?? $input['action'] ?? 'mark_read';

    if ($action === 'mark_all_read') {
        $db->query("UPDATE notifications SET is_read = TRUE");
        jsonResponse(['success' => true, 'message' => "All notifications marked as read."]);
    }

    $id = $_GET['id'] ?? $input['id'] ?? null;
    if (!$id) {
        errorResponse("Missing notification ID.");
    }

    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = :id");
    $stmt->execute(['id' => $id]);

    jsonResponse(['success' => true, 'id' => $id, 'isRead' => true]);
}

function handleCreateNotification($db) {
    $input = getJsonInput();
    if (empty($input['title']) || empty($input['message'])) {
        errorResponse("Title and message are required.");
    }

    $id = !empty($input['id']) ? $input['id'] : ('notif_' . bin2hex(random_bytes(6)));
    $title = trim($input['title']);
    $message = trim($input['message']);
    $type = trim($input['type'] ?? 'VIEWING_BOOKED');
    $targetId = $input['targetId'] ?? null;

    $stmt = $db->prepare("INSERT INTO notifications (id, title, message, timestamp_formatted, type, is_read, target_id)
                          VALUES (:id, :title, :message, 'Just now', :type, FALSE, :target_id) RETURNING *");
    $stmt->execute([
        'id' => $id,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'target_id' => $targetId
    ]);

    jsonResponse(formatNotificationRow($stmt->fetch()), 201);
}
