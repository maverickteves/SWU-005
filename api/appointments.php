<?php
/**
 * Viewing Appointments API Endpoint
 * Handles viewing schedules, status transitions (UPCOMING, COMPLETED, CANCELLED), and notifications
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/formatters.php';

$db = getDatabaseConnection();
$method = $_SERVER['REQUEST_METHOD'];
// Agents see every appointment; buyers only their own
$user = requireUser($db);

switch ($method) {
    case 'GET':
        handleGetAppointments($db, $user);
        break;

    case 'POST':
        handleBookAppointment($db, $user);
        break;

    case 'PUT':
        handleUpdateAppointmentStatus($db, $user);
        break;

    case 'DELETE':
        handleDeleteAppointment($db, $user);
        break;

    default:
        errorResponse("Unsupported HTTP method: $method", 405);
}

function formatAppointmentRow($row) {
    return [
        'id' => (string)$row['id'],
        'propertyId' => (string)$row['property_id'],
        'propertyTitle' => (string)$row['property_title'],
        'propertyAddress' => (string)$row['property_address'],
        'clientName' => (string)$row['client_name'],
        'clientPhone' => (string)$row['client_phone'],
        'clientEmail' => (string)$row['client_email'],
        'date' => (string)$row['date'],
        'timeSlot' => (string)$row['time_slot'],
        'type' => (string)($row['type'] ?? 'Viewing'),
        'status' => (string)($row['status'] ?? 'UPCOMING'),
        'notes' => (string)($row['notes'] ?? ''),
        'timestamp' => (int)($row['created_timestamp'] ?? (time() * 1000))
    ];
}

/**
 * Stops a buyer from reading or changing someone else's appointment.
 */
function requireAppointmentAccess($db, $user, $id) {
    if (!isBuyer($user)) {
        return;
    }
    $stmt = $db->prepare("SELECT client_email FROM viewing_appointments WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $owner = $stmt->fetchColumn();
    if ($owner === false) {
        errorResponse("Appointment not found", 404);
    }
    if (!sameEmail($owner, $user['email'])) {
        errorResponse("You do not have permission to change this appointment.", 403);
    }
}

function handleGetAppointments($db, $user) {
    $sql = "SELECT * FROM viewing_appointments WHERE 1=1";
    $params = [];

    if (isBuyer($user)) {
        $sql .= " AND LOWER(client_email) = :email";
        $params['email'] = strtolower($user['email']);
    } elseif (!empty($_GET['client_email'])) {
        $sql .= " AND LOWER(client_email) = :email";
        $params['email'] = strtolower(trim($_GET['client_email']));
    }

    if (!empty($_GET['status'])) {
        $sql .= " AND UPPER(status) = :status";
        $params['status'] = strtoupper(trim($_GET['status']));
    }

    $sql .= " ORDER BY created_timestamp DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    jsonResponse(array_map('formatAppointmentRow', $rows));
}

function handleBookAppointment($db, $user) {
    $input = getJsonInput();

    if (empty($input['propertyId']) || empty($input['date']) || empty($input['timeSlot'])) {
        errorResponse("Missing required fields: propertyId, date, and timeSlot are required.");
    }

    $id = !empty($input['id']) ? $input['id'] : ('appt_' . bin2hex(random_bytes(6)));
    $propertyId = trim($input['propertyId']);
    
    // Look up property title & address if not provided
    $propertyTitle = $input['propertyTitle'] ?? null;
    $propertyAddress = $input['propertyAddress'] ?? null;

    if (empty($propertyTitle) || empty($propertyAddress)) {
        $pStmt = $db->prepare("SELECT title, address FROM properties WHERE id = :id");
        $pStmt->execute(['id' => $propertyId]);
        $prop = $pStmt->fetch();
        if ($prop) {
            $propertyTitle = $prop['title'];
            $propertyAddress = $prop['address'];
        } else {
            $propertyTitle = $propertyTitle ?: "Property Viewing";
            $propertyAddress = $propertyAddress ?: "Address";
        }
    }

    $clientName = trim($input['clientName'] ?? 'You (Verified Buyer)');
    $clientPhone = trim($input['clientPhone'] ?? '+1 (555) 019-8234');
    $clientEmail = trim($input['clientEmail'] ?? 'buyer@estateflow.com');
    if (isBuyer($user)) {
        // Buyers always book as themselves
        $clientEmail = $user['email'];
        if (!empty($input['id'])) {
            $existing = $db->prepare("SELECT client_email FROM viewing_appointments WHERE id = :id");
            $existing->execute(['id' => $id]);
            $owner = $existing->fetchColumn();
            if ($owner !== false && !sameEmail($owner, $user['email'])) {
                errorResponse("You do not have permission to change this appointment.", 403);
            }
        }
    }
    $date = trim($input['date']);
    $timeSlot = trim($input['timeSlot']);
    $type = trim($input['type'] ?? 'Viewing');
    $status = strtoupper(trim($input['status'] ?? 'UPCOMING'));
    $notes = trim($input['notes'] ?? 'Scheduled via Estateflow instant booking.');
    $timestamp = (int)($input['timestamp'] ?? (time() * 1000));

    $sql = "INSERT INTO viewing_appointments (
        id, property_id, property_title, property_address, client_name, client_phone, client_email,
        date, time_slot, type, status, notes, created_timestamp
    ) VALUES (
        :id, :property_id, :property_title, :property_address, :client_name, :client_phone, :client_email,
        :date, :time_slot, :type, :status, :notes, :created_timestamp
    ) ON CONFLICT (id) DO UPDATE SET
        status = EXCLUDED.status,
        date = EXCLUDED.date,
        time_slot = EXCLUDED.time_slot,
        notes = EXCLUDED.notes
    RETURNING *";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'id' => $id,
        'property_id' => $propertyId,
        'property_title' => $propertyTitle,
        'property_address' => $propertyAddress,
        'client_name' => $clientName,
        'client_phone' => $clientPhone,
        'client_email' => $clientEmail,
        'date' => $date,
        'time_slot' => $timeSlot,
        'type' => $type,
        'status' => $status,
        'notes' => $notes,
        'created_timestamp' => $timestamp
    ]);

    $created = $stmt->fetch();

    // Insert alert notification
    $notifId = 'notif_' . bin2hex(random_bytes(6));
    $notifStmt = $db->prepare("INSERT INTO notifications (id, title, message, timestamp_formatted, type, is_read, target_id)
                               VALUES (:id, 'Viewing Appointment Booked', :message, 'Just now', 'VIEWING_BOOKED', FALSE, :target_id)");
    $notifStmt->execute([
        'id' => $notifId,
        'message' => "Viewing for {$propertyTitle} confirmed on {$date} at {$timeSlot}.",
        'target_id' => $id
    ]);

    jsonResponse(formatAppointmentRow($created), 201);
}

function handleUpdateAppointmentStatus($db, $user) {
    $input = getJsonInput();
    $id = $_GET['id'] ?? $input['id'] ?? null;
    $status = $_GET['status'] ?? $input['status'] ?? null;

    if (!$id || !$status) {
        errorResponse("Missing appointment ID or status parameter.");
    }

    $validStatuses = ['UPCOMING', 'COMPLETED', 'PENDING', 'CANCELLED'];
    $status = strtoupper(trim($status));

    if (!in_array($status, $validStatuses)) {
        errorResponse("Invalid status value. Must be one of: " . implode(', ', $validStatuses));
    }

    requireAppointmentAccess($db, $user, $id);

    $stmt = $db->prepare("UPDATE viewing_appointments SET status = :status WHERE id = :id RETURNING *");
    $stmt->execute(['id' => $id, 'status' => $status]);
    $updated = $stmt->fetch();

    if ($updated) {
        jsonResponse([
            'success' => true,
            'appointment' => formatAppointmentRow($updated)
        ]);
    } else {
        errorResponse("Appointment not found", 404);
    }
}

function handleDeleteAppointment($db, $user) {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        $input = getJsonInput();
        $id = $input['id'] ?? null;
    }

    if (!$id) {
        errorResponse("Missing appointment ID.");
    }

    requireAppointmentAccess($db, $user, $id);

    $stmt = $db->prepare("DELETE FROM viewing_appointments WHERE id = :id");
    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() > 0) {
        jsonResponse(['success' => true, 'message' => "Appointment $id deleted."]);
    } else {
        errorResponse("Appointment not found", 404);
    }
}
