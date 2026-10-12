<?php
/**
 * Inquiries and Chat Messages API Endpoint
 * Handles lead inquiries, threaded chat messages, and agent responses
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/formatters.php';

$db = getDatabaseConnection();
$method = $_SERVER['REQUEST_METHOD'];
// Agents see every lead; buyers only the conversations they started
$user = requireUser($db);

switch ($method) {
    case 'GET':
        handleGetInquiries($db, $user);
        break;

    case 'POST':
        handleCreateOrReplyInquiry($db, $user);
        break;

    case 'PUT':
        handleUpdateInquiryStatus($db, $user);
        break;

    default:
        errorResponse("Unsupported HTTP method: $method", 405);
}

/**
 * Stops a buyer from reading or writing to someone else's inquiry thread.
 */
function requireInquiryAccess($user, $inquiry) {
    if (isBuyer($user) && !sameEmail($inquiry['sender_email'], $user['email'])) {
        errorResponse("Inquiry not found", 404);
    }
}

function handleGetInquiries($db, $user) {
    // Single inquiry lookup
    if (!empty($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM inquiries WHERE id = :id");
        $stmt->execute(['id' => $_GET['id']]);
        $inq = $stmt->fetch();
        if (!$inq) {
            errorResponse("Inquiry not found", 404);
        }
        requireInquiryAccess($user, $inq);

        // Fetch messages
        $mStmt = $db->prepare("SELECT * FROM inquiry_messages WHERE inquiry_id = :id ORDER BY created_at ASC");
        $mStmt->execute(['id' => $inq['id']]);
        $msgs = $mStmt->fetchAll();

        jsonResponse(formatInquiryWithMessages($inq, $msgs));
    }

    // List inquiries with messages
    if (isBuyer($user)) {
        $stmt = $db->prepare("SELECT * FROM inquiries WHERE LOWER(sender_email) = :email ORDER BY updated_at DESC");
        $stmt->execute(['email' => strtolower($user['email'])]);
    } else {
        $stmt = $db->query("SELECT * FROM inquiries ORDER BY updated_at DESC");
    }
    $inquiries = $stmt->fetchAll();

    $result = [];
    foreach ($inquiries as $inq) {
        $mStmt = $db->prepare("SELECT * FROM inquiry_messages WHERE inquiry_id = :id ORDER BY created_at ASC");
        $mStmt->execute(['id' => $inq['id']]);
        $msgs = $mStmt->fetchAll();
        $result[] = formatInquiryWithMessages($inq, $msgs);
    }

    jsonResponse($result);
}

function formatInquiryWithMessages($inq, $msgs) {
    $formattedMsgs = [];
    foreach ($msgs as $m) {
        $formattedMsgs[] = [
            'id' => (string)$m['id'],
            'sender' => (string)$m['sender'],
            'text' => (string)$m['text'],
            'time' => (string)$m['time'],
            'isFromMe' => filter_var($m['is_from_me'], FILTER_VALIDATE_BOOLEAN)
        ];
    }

    return [
        'id' => (string)$inq['id'],
        'propertyId' => (string)$inq['property_id'],
        'propertyTitle' => (string)$inq['property_title'],
        'propertyAddress' => (string)$inq['property_address'],
        'senderName' => (string)$inq['sender_name'],
        'senderEmail' => (string)$inq['sender_email'],
        'senderPhone' => (string)$inq['sender_phone'],
        'message' => (string)$inq['message'],
        'timeAgo' => (string)$inq['time_ago'],
        'isUnread' => filter_var($inq['is_unread'], FILTER_VALIDATE_BOOLEAN),
        'messages' => $formattedMsgs
    ];
}

function handleCreateOrReplyInquiry($db, $user) {
    $input = getJsonInput();

    // Check if this is a reply to an existing inquiry
    $action = $_GET['action'] ?? $input['action'] ?? null;
    if ($action === 'reply' || (!empty($input['inquiryId']) && !empty($input['replyText']))) {
        $inquiryId = $input['inquiryId'] ?? $_GET['id'] ?? null;
        $replyText = trim($input['replyText'] ?? $input['text'] ?? $input['message'] ?? '');
        $isAgent = isset($input['isAgent'])
            ? (filter_var($input['isAgent'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true)
            : true;
        if ($user !== null) {
            // The sender comes from the session, not from the request
            $isAgent = isAgent($user);
        }

        if (!$inquiryId || empty($replyText)) {
            errorResponse("Missing inquiryId or replyText.");
        }

        $checkStmt = $db->prepare("SELECT * FROM inquiries WHERE id = :id");
        $checkStmt->execute(['id' => $inquiryId]);
        $inquiry = $checkStmt->fetch();
        if (!$inquiry) {
            errorResponse("Inquiry thread not found", 404);
        }
        requireInquiryAccess($user, $inquiry);

        // Insert new message
        $msgId = 'msg_' . bin2hex(random_bytes(6));
        $msgStmt = $db->prepare("INSERT INTO inquiry_messages (id, inquiry_id, sender, text, time, is_from_me) 
                                 VALUES (:id, :inquiry_id, :sender, :text, 'Just now', :is_from_me)");
        $msgStmt->execute([
            'id' => $msgId,
            'inquiry_id' => $inquiryId,
            'sender' => $isAgent ? 'agent' : 'user',
            'text' => $replyText,
            'is_from_me' => $isAgent ? 'true' : 'false'
        ]);

        // Update inquiry header
        $uStmt = $db->prepare("UPDATE inquiries SET message = :msg, time_ago = 'Just now', is_unread = :is_unread, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $uStmt->execute([
            'msg' => $replyText,
            'is_unread' => $isAgent ? 'false' : 'true',
            'id' => $inquiryId
        ]);

        jsonResponse([
            'success' => true,
            'messageId' => $msgId,
            'text' => $replyText,
            'sender' => $isAgent ? 'agent' : 'user'
        ], 201);
    }

    // Otherwise, create a new inquiry or append to property inquiry
    if (empty($input['propertyId']) || empty($input['message'])) {
        errorResponse("Missing required fields: propertyId and message.");
    }

    $id = !empty($input['id']) ? $input['id'] : ('inq_' . bin2hex(random_bytes(6)));
    $propertyId = trim($input['propertyId']);
    $messageText = trim($input['message']);
    $senderName = trim($input['senderName'] ?? 'You (Prospective Buyer)');
    $senderEmail = trim($input['senderEmail'] ?? 'buyer@estateflow.com');
    if (isBuyer($user)) {
        // Buyers always write as themselves
        $senderEmail = $user['email'];
    }
    $senderPhone = trim($input['senderPhone'] ?? '+1 (555) 019-8234');

    // Get property details
    $pStmt = $db->prepare("SELECT title, address FROM properties WHERE id = :id");
    $pStmt->execute(['id' => $propertyId]);
    $prop = $pStmt->fetch();
    $propertyTitle = $input['propertyTitle'] ?? ($prop ? $prop['title'] : 'Property Inquiry');
    $propertyAddress = $input['propertyAddress'] ?? ($prop ? $prop['address'] : '');

    // Check if an inquiry already exists for this property and sender
    $existingStmt = $db->prepare("SELECT id FROM inquiries WHERE property_id = :pid AND sender_email = :email LIMIT 1");
    $existingStmt->execute(['pid' => $propertyId, 'email' => $senderEmail]);
    $existingInq = $existingStmt->fetch();

    $db->beginTransaction();
    try {
        if ($existingInq) {
            $inqId = $existingInq['id'];
            $uStmt = $db->prepare("UPDATE inquiries SET message = :msg, time_ago = 'Just now', is_unread = TRUE, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $uStmt->execute(['msg' => $messageText, 'id' => $inqId]);
        } else {
            $inqId = $id;
            $insStmt = $db->prepare("INSERT INTO inquiries (id, property_id, property_title, property_address, sender_name, sender_email, sender_phone, message, time_ago, is_unread)
                                     VALUES (:id, :property_id, :property_title, :property_address, :sender_name, :sender_email, :sender_phone, :message, 'Just now', TRUE)");
            $insStmt->execute([
                'id' => $inqId,
                'property_id' => $propertyId,
                'property_title' => $propertyTitle,
                'property_address' => $propertyAddress,
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'sender_phone' => $senderPhone,
                'message' => $messageText
            ]);
        }

        // Add message in thread
        $msgId = 'msg_' . bin2hex(random_bytes(6));
        $mStmt = $db->prepare("INSERT INTO inquiry_messages (id, inquiry_id, sender, text, time, is_from_me) 
                               VALUES (:id, :inquiry_id, 'user', :text, 'Just now', FALSE)");
        $mStmt->execute([
            'id' => $msgId,
            'inquiry_id' => $inqId,
            'text' => $messageText
        ]);

        // Insert notification
        $notifId = 'notif_' . bin2hex(random_bytes(6));
        $notifStmt = $db->prepare("INSERT INTO notifications (id, title, message, timestamp_formatted, type, is_read, target_id)
                                   VALUES (:id, 'New Lead Inquiry', :message, 'Just now', 'NEW_INQUIRY', FALSE, :target_id)");
        $notifStmt->execute([
            'id' => $notifId,
            'message' => "{$senderName} submitted an inquiry for {$propertyTitle}.",
            'target_id' => $inqId
        ]);

        $db->commit();

        // Return complete updated inquiry
        $fetchStmt = $db->prepare("SELECT * FROM inquiries WHERE id = :id");
        $fetchStmt->execute(['id' => $inqId]);
        $row = $fetchStmt->fetch();

        $msgsStmt = $db->prepare("SELECT * FROM inquiry_messages WHERE inquiry_id = :id ORDER BY created_at ASC");
        $msgsStmt->execute(['id' => $inqId]);
        $msgs = $msgsStmt->fetchAll();

        jsonResponse(formatInquiryWithMessages($row, $msgs), 201);
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Failed to create inquiry: ' . $e->getMessage());
        errorResponse("Failed to create inquiry. Please try again.", 500);
    }
}

function handleUpdateInquiryStatus($db, $user) {
    $input = getJsonInput();
    $id = $_GET['id'] ?? $input['id'] ?? null;
    $action = $_GET['action'] ?? $input['action'] ?? null;

    if (!$id) {
        errorResponse("Missing inquiry ID.");
    }

    if ($action === 'mark_read') {
        if (isBuyer($user)) {
            $check = $db->prepare("SELECT * FROM inquiries WHERE id = :id");
            $check->execute(['id' => $id]);
            $inquiry = $check->fetch();
            if (!$inquiry) {
                errorResponse("Inquiry not found", 404);
            }
            requireInquiryAccess($user, $inquiry);
        }
        $stmt = $db->prepare("UPDATE inquiries SET is_unread = FALSE WHERE id = :id");
        $stmt->execute(['id' => $id]);
        jsonResponse(['success' => true, 'id' => $id, 'isUnread' => false]);
    } else {
        errorResponse("Unsupported action.");
    }
}
