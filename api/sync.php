<?php
/**
 * Bulk Bidirectional Sync Endpoint for Android App
 * Enables fast synchronization between local Room DB and PostgreSQL via XAMPP
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/formatters.php';

$db = getDatabaseConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return full server state
    handleGetFullSyncState($db);
} elseif ($method === 'POST') {
    // Receive client changes, apply to Postgres, and return latest merged state
    handlePostSync($db);
} else {
    errorResponse("Unsupported HTTP method: $method", 405);
}

function handleGetFullSyncState($db) {
    // 1. Properties
    $pStmt = $db->query("SELECT * FROM properties ORDER BY created_at DESC");
    $properties = array_map('formatPropertyRow', $pStmt->fetchAll());

    // 2. Appointments
    $aStmt = $db->query("SELECT * FROM viewing_appointments ORDER BY created_timestamp DESC");
    $appointments = array_map('formatAppointmentRow', $aStmt->fetchAll());

    // 3. Inquiries
    $iStmt = $db->query("SELECT * FROM inquiries ORDER BY updated_at DESC");
    $inquiries = [];
    foreach ($iStmt->fetchAll() as $inq) {
        $mStmt = $db->prepare("SELECT * FROM inquiry_messages WHERE inquiry_id = :id ORDER BY created_at ASC");
        $mStmt->execute(['id' => $inq['id']]);
        $inquiries[] = formatInquiryWithMessages($inq, $mStmt->fetchAll());
    }

    // 4. Notifications
    $nStmt = $db->query("SELECT * FROM notifications ORDER BY created_at DESC");
    $notifications = array_map('formatNotificationRow', $nStmt->fetchAll());

    // 5. Agent Profile
    $agStmt = $db->query("SELECT * FROM agent_profiles LIMIT 1");
    $ag = $agStmt->fetch();
    $agentProfile = $ag ? [
        'name' => (string)$ag['name'],
        'title' => (string)$ag['title'],
        'licenseNo' => (string)$ag['license_no'],
        'phone' => (string)$ag['phone'],
        'email' => (string)$ag['email'],
        'rating' => (float)$ag['rating'],
        'reviewsCount' => (int)$ag['reviews_count'],
        'totalSalesVolume' => (string)$ag['total_sales_volume'],
        'activeListingsCount' => count($properties),
        'bio' => (string)$ag['bio']
    ] : null;

    jsonResponse([
        'success' => true,
        'timestamp' => time() * 1000,
        'database' => 'PostgreSQL via PHP XAMPP',
        'properties' => $properties,
        'appointments' => $appointments,
        'inquiries' => $inquiries,
        'notifications' => $notifications,
        'agentProfile' => $agentProfile
    ]);
}

function handlePostSync($db) {
    $input = getJsonInput();
    $db->beginTransaction();

    try {
    // Sync inbound properties
    if (!empty($input['properties']) && is_array($input['properties'])) {
        foreach ($input['properties'] as $prop) {
            $id = $prop['id'] ?? ('prop_' . bin2hex(random_bytes(6)));
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
                is_favorite = EXCLUDED.is_favorite,
                status = EXCLUDED.status,
                media_uris = EXCLUDED.media_uris,
                available_dates = EXCLUDED.available_dates,
                available_time_slots = EXCLUDED.available_time_slots,
                amenities = EXCLUDED.amenities,
                year_built = EXCLUDED.year_built,
                agent_name = EXCLUDED.agent_name,
                agent_title = EXCLUDED.agent_title,
                agent_phone = EXCLUDED.agent_phone,
                agent_email = EXCLUDED.agent_email,
                updated_at = CURRENT_TIMESTAMP";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'title' => $prop['title'] ?? 'Listing',
                'address' => $prop['address'] ?? '',
                'city_state_zip' => $prop['cityStateZip'] ?? 'Cebu City, Philippines',
                'price' => (int)($prop['price'] ?? 0),
                'price_formatted' => $prop['priceFormatted'] ?? '₱0',
                'is_rental' => !empty($prop['isRental']) ? 'true' : 'false',
                'beds' => (float)($prop['beds'] ?? 1.0),
                'baths' => (float)($prop['baths'] ?? 1.0),
                'sqft' => (int)($prop['sqft'] ?? 500),
                'property_type' => $prop['propertyType'] ?? 'Condominium',
                'description' => $prop['description'] ?? '',
                'image_res_id' => (int)($prop['imageResId'] ?? 0),
                'media_uris' => json_encode($prop['mediaUris'] ?? []),
                'is_favorite' => !empty($prop['isFavorite']) ? 'true' : 'false',
                'status' => $prop['status'] ?? 'Active',
                'available_dates' => json_encode($prop['availableDates'] ?? []),
                'available_time_slots' => json_encode($prop['availableTimeSlots'] ?? []),
                'amenities' => json_encode($prop['amenities'] ?? []),
                'year_built' => (int)($prop['yearBuilt'] ?? 2022),
                'agent_name' => $prop['agentName'] ?? 'Sarah Jenkins',
                'agent_title' => $prop['agentTitle'] ?? 'Premier Real Estate Broker',
                'agent_phone' => $prop['agentPhone'] ?? '+63 917 555 0192',
                'agent_email' => $prop['agentEmail'] ?? 'sarah.jenkins@estateflow.com'
            ]);
        }
    }

    // Sync inbound appointments
    if (!empty($input['appointments']) && is_array($input['appointments'])) {
        foreach ($input['appointments'] as $appt) {
            $id = $appt['id'] ?? ('appt_' . bin2hex(random_bytes(6)));
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
                notes = EXCLUDED.notes";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'property_id' => $appt['propertyId'] ?? null,
                'property_title' => $appt['propertyTitle'] ?? 'Viewing',
                'property_address' => $appt['propertyAddress'] ?? '',
                'client_name' => $appt['clientName'] ?? 'Client',
                'client_phone' => $appt['clientPhone'] ?? '',
                'client_email' => $appt['clientEmail'] ?? '',
                'date' => $appt['date'] ?? '',
                'time_slot' => $appt['timeSlot'] ?? '',
                'type' => $appt['type'] ?? 'Viewing',
                'status' => strtoupper($appt['status'] ?? 'UPCOMING'),
                'notes' => $appt['notes'] ?? '',
                'created_timestamp' => (int)($appt['timestamp'] ?? (time() * 1000))
            ]);
        }
    }

    $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        errorResponse("Bulk sync failed: " . $e->getMessage(), 500);
    }

    // Return the full updated state
    handleGetFullSyncState($db);
}
