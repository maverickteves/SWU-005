<?php
/**
 * Properties API Endpoint (CRUD for Real Estate Listings)
 * Handlers for GET, POST, PUT, DELETE
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/formatters.php';

$db = getDatabaseConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetProperties($db);
        break;

    case 'POST':
        $action = $_GET['action'] ?? '';
        if ($action === 'delete') {
            handleDeleteProperty($db);
        } else {
            handleCreateProperty($db);
        }
        break;

    case 'PUT':
        handleUpdateProperty($db);
        break;

    case 'DELETE':
        handleDeleteProperty($db);
        break;

    default:
        errorResponse("Unsupported HTTP method: $method", 405);
}

/**
 * Format DB row to JSON-friendly structure
 */
function formatPropertyRow($row) {
    return [
        'id' => (string)$row['id'],
        'title' => (string)$row['title'],
        'address' => (string)$row['address'],
        'cityStateZip' => (string)$row['city_state_zip'],
        'price' => (int)$row['price'],
        'priceFormatted' => (string)$row['price_formatted'],
        'isRental' => filter_var($row['is_rental'], FILTER_VALIDATE_BOOLEAN),
        'beds' => (float)$row['beds'],
        'baths' => (float)$row['baths'],
        'sqft' => (int)$row['sqft'],
        'propertyType' => (string)$row['property_type'],
        'description' => (string)$row['description'],
        'imageResId' => (int)($row['image_res_id'] ?? 0),
        'mediaUris' => json_decode($row['media_uris'] ?? '[]', true) ?: [],
        'isFavorite' => filter_var($row['is_favorite'], FILTER_VALIDATE_BOOLEAN),
        'status' => (string)$row['status'],
        'availableDates' => json_decode($row['available_dates'] ?? '[]', true) ?: ["Mon, Oct 14", "Tue, Oct 15", "Wed, Oct 16", "Thu, Oct 17"],
        'availableTimeSlots' => json_decode($row['available_time_slots'] ?? '[]', true) ?: ["10:00 AM", "1:30 PM", "4:00 PM", "5:30 PM"],
        'amenities' => json_decode($row['amenities'] ?? '[]', true) ?: ["Modern Design", "High Speed Internet"],
        'yearBuilt' => (int)($row['year_built'] ?? 2022),
        'agentName' => (string)($row['agent_name'] ?? 'Sarah Jenkins'),
        'agentTitle' => (string)($row['agent_title'] ?? 'Premier Real Estate Broker • Cebu City Properties'),
        'agentPhone' => (string)($row['agent_phone'] ?? '+63 917 555 0192'),
        'agentEmail' => (string)($row['agent_email'] ?? 'sarah.jenkins@estateflow.com')
    ];
}

function handleGetProperties($db) {
    // Single property lookup
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM properties WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $_GET['id']]);
        $row = $stmt->fetch();
        if ($row) {
            jsonResponse(formatPropertyRow($row));
        } else {
            errorResponse("Property not found with ID: " . $_GET['id'], 404);
        }
    }

    // List properties with optional filters
    $sql = "SELECT * FROM properties WHERE 1=1";
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= " AND (LOWER(title) LIKE :search_title OR LOWER(address) LIKE :search_address OR LOWER(city_state_zip) LIKE :search_city OR LOWER(description) LIKE :search_description)";
        $search = '%' . strtolower(trim($_GET['search'])) . '%';
        $params['search_title'] = $search;
        $params['search_address'] = $search;
        $params['search_city'] = $search;
        $params['search_description'] = $search;
    }

    if (!empty($_GET['category']) && $_GET['category'] !== 'All') {
        $sql .= " AND LOWER(property_type) = :category";
        $params['category'] = strtolower(trim($_GET['category']));
    }

    if (!empty($_GET['min_price'])) {
        $sql .= " AND price >= :min_price";
        $params['min_price'] = (int)$_GET['min_price'];
    }

    if (!empty($_GET['max_price'])) {
        $sql .= " AND price <= :max_price";
        $params['max_price'] = (int)$_GET['max_price'];
    }

    if (!empty($_GET['beds'])) {
        $sql .= " AND beds >= :beds";
        $params['beds'] = (float)$_GET['beds'];
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = array_map('formatPropertyRow', $rows);
    jsonResponse($result);
}

function handleCreateProperty($db) {
    $input = getJsonInput();

    if (empty($input['title']) || empty($input['address']) || !isset($input['price'])) {
        errorResponse("Missing required fields: title, address, and price are mandatory.");
    }

    $id = !empty($input['id']) ? $input['id'] : ('prop_' . bin2hex(random_bytes(6)));
    $title = trim($input['title']);
    $address = trim($input['address']);
    $cityStateZip = trim($input['cityStateZip'] ?? 'Cebu City, Philippines');
    $price = (int)$input['price'];
    $isRental = !empty($input['isRental']);
    
    // Auto-format price if not supplied
    $priceFormatted = !empty($input['priceFormatted']) 
        ? $input['priceFormatted'] 
        : ($isRental ? '₱' . number_format($price) . ' / mo' : '₱' . number_format($price));
    
    $beds = (float)($input['beds'] ?? 1.0);
    $baths = (float)($input['baths'] ?? 1.0);
    $sqft = (int)($input['sqft'] ?? 500);
    $propertyType = trim($input['propertyType'] ?? 'Condominium');
    $description = trim($input['description'] ?? '');
    $imageResId = (int)($input['imageResId'] ?? 0);
    $mediaUris = json_encode($input['mediaUris'] ?? []);
    $isFavorite = !empty($input['isFavorite']);
    $status = trim($input['status'] ?? 'Active');
    $availableDates = json_encode($input['availableDates'] ?? ["Mon, Oct 14", "Tue, Oct 15", "Wed, Oct 16", "Thu, Oct 17"]);
    $availableTimeSlots = json_encode($input['availableTimeSlots'] ?? ["10:00 AM", "1:30 PM", "4:00 PM", "5:30 PM"]);
    $amenities = json_encode($input['amenities'] ?? ["Modern Design", "High Speed Internet", "Near Transit"]);
    $yearBuilt = (int)($input['yearBuilt'] ?? 2023);
    $agentName = trim($input['agentName'] ?? 'Sarah Jenkins');
    $agentTitle = trim($input['agentTitle'] ?? 'Premier Real Estate Broker • Cebu City Properties');
    $agentPhone = trim($input['agentPhone'] ?? '+63 917 555 0192');
    $agentEmail = trim($input['agentEmail'] ?? 'sarah.jenkins@estateflow.com');

    $sql = "INSERT INTO properties (
        id, title, address, city_state_zip, price, price_formatted, is_rental,
        beds, baths, sqft, property_type, description, image_res_id, media_uris,
        is_favorite, status, available_dates, available_time_slots, amenities,
        year_built, agent_name, agent_title, agent_phone, agent_email
    ) VALUES (
        :id, :title, :address, :city_state_zip, :price, :price_formatted, :is_rental,
        :beds, :baths, :sqft, :property_type, :description, :image_res_id, :media_uris,
        :is_favorite, :status, :available_dates, :available_time_slots, :amenities,
        :year_built, :agent_name, :agent_title, :agent_phone, :agent_email
    ) ON CONFLICT (id) DO UPDATE SET
        title = EXCLUDED.title,
        address = EXCLUDED.address,
        city_state_zip = EXCLUDED.city_state_zip,
        price = EXCLUDED.price,
        price_formatted = EXCLUDED.price_formatted,
        is_rental = EXCLUDED.is_rental,
        beds = EXCLUDED.beds,
        baths = EXCLUDED.baths,
        sqft = EXCLUDED.sqft,
        property_type = EXCLUDED.property_type,
        description = EXCLUDED.description,
        image_res_id = EXCLUDED.image_res_id,
        media_uris = EXCLUDED.media_uris,
        is_favorite = EXCLUDED.is_favorite,
        status = EXCLUDED.status,
        available_dates = EXCLUDED.available_dates,
        available_time_slots = EXCLUDED.available_time_slots,
        amenities = EXCLUDED.amenities,
        year_built = EXCLUDED.year_built,
        agent_name = EXCLUDED.agent_name,
        agent_title = EXCLUDED.agent_title,
        agent_phone = EXCLUDED.agent_phone,
        agent_email = EXCLUDED.agent_email,
        updated_at = CURRENT_TIMESTAMP
    RETURNING *";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'id' => $id,
        'title' => $title,
        'address' => $address,
        'city_state_zip' => $cityStateZip,
        'price' => $price,
        'price_formatted' => $priceFormatted,
        'is_rental' => $isRental ? 'true' : 'false',
        'beds' => $beds,
        'baths' => $baths,
        'sqft' => $sqft,
        'property_type' => $propertyType,
        'description' => $description,
        'image_res_id' => $imageResId,
        'media_uris' => $mediaUris,
        'is_favorite' => $isFavorite ? 'true' : 'false',
        'status' => $status,
        'available_dates' => $availableDates,
        'available_time_slots' => $availableTimeSlots,
        'amenities' => $amenities,
        'year_built' => $yearBuilt,
        'agent_name' => $agentName,
        'agent_title' => $agentTitle,
        'agent_phone' => $agentPhone,
        'agent_email' => $agentEmail
    ]);

    $created = $stmt->fetch();

    // Auto-create notification for new listing
    $notifId = 'notif_' . bin2hex(random_bytes(6));
    $notifStmt = $db->prepare("INSERT INTO notifications (id, title, message, timestamp_formatted, type, is_read, target_id) 
                               VALUES (:id, :title, :message, 'Just now', 'LISTING_UPDATE', FALSE, :target_id)");
    $notifStmt->execute([
        'id' => $notifId,
        'title' => 'New Property Published',
        'message' => "{$title} is now live on Estateflow with active viewing bookings.",
        'target_id' => $id
    ]);

    jsonResponse(formatPropertyRow($created), 201);
}

function handleUpdateProperty($db) {
    $input = getJsonInput();

    // Handle toggle favorite action
    if ((isset($_GET['action']) && $_GET['action'] === 'toggle_favorite') || (isset($input['action']) && $input['action'] === 'toggle_favorite')) {
        $id = $_GET['id'] ?? $input['id'] ?? null;
        if (!$id) {
            errorResponse("Missing property ID for toggle favorite");
        }

        $stmt = $db->prepare("UPDATE properties SET is_favorite = NOT is_favorite, updated_at = CURRENT_TIMESTAMP WHERE id = :id RETURNING is_favorite");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            jsonResponse([
                'success' => true,
                'id' => $id,
                'isFavorite' => filter_var($row['is_favorite'], FILTER_VALIDATE_BOOLEAN)
            ]);
        } else {
            errorResponse("Property not found", 404);
        }
    }

    // Full property update
    handleCreateProperty($db);
}

function handleDeleteProperty($db) {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        $input = getJsonInput();
        $id = $input['id'] ?? null;
    }

    if (!$id) {
        errorResponse("Missing property ID for deletion");
    }

    // Get title before deletion for notification and appointment handling
    $titleStmt = $db->prepare("SELECT title FROM properties WHERE id = :id");
    $titleStmt->execute(['id' => $id]);
    $title = $titleStmt->fetchColumn();

    if (!$title) {
        errorResponse("Property '$id' not found in PostgreSQL database. It may have only been created locally or was already deleted.", 404);
    }

    $db->beginTransaction();
    try {
        // 1. Handle any viewing appointments related to this deleted property
        $apptStmt = $db->prepare("UPDATE viewing_appointments
                                  SET status = 'CANCELLED',
                                  notes = CONCAT(COALESCE(notes, ''), ' [Listing \"', CAST(:title AS TEXT), '\" was removed by agent. Viewing cancelled.]')
                                  WHERE property_id = :id AND status != 'CANCELLED'");
        $apptStmt->execute(['title' => $title, 'id' => $id]);
        $cancelledApptsCount = $apptStmt->rowCount();

        // 2. Delete the matching PostgreSQL record using: DELETE FROM properties WHERE id = :id;
        $stmt = $db->prepare("DELETE FROM properties WHERE id = :id");
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            $db->rollBack();
            errorResponse("Property not found or already deleted", 404);
        }

        // 3. Create notification only after the delete succeeds
        $notifId = 'notif_' . bin2hex(random_bytes(6));
        $notifMsg = "{$title} has been removed from active listings in PostgreSQL.";
        if ($cancelledApptsCount > 0) {
            $notifMsg .= " {$cancelledApptsCount} related viewing appointment(s) were cancelled.";
        }
        $notifStmt = $db->prepare("INSERT INTO notifications (id, title, message, timestamp_formatted, type, is_read, target_id)
                                   VALUES (:id, 'Listing Removed', :message, 'Just now', 'LISTING_UPDATE', FALSE, :target_id)");
        $notifStmt->execute([
            'id' => $notifId,
            'message' => $notifMsg,
            'target_id' => $id
        ]);

        $db->commit();
        jsonResponse([
            'success' => true,
            'id' => $id,
            'message' => "Property $id deleted successfully."
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        errorResponse("Failed to delete property: " . $e->getMessage(), 500);
    }
}
