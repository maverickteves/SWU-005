<?php
/**
 * Shared Entity Formatters for Estateflow REST API
 * Converts PostgreSQL database rows to clean JSON responses
 */

if (!function_exists('formatPropertyRow')) {
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
}

if (!function_exists('formatAppointmentRow')) {
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
}

if (!function_exists('formatInquiryWithMessages')) {
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
}

if (!function_exists('formatNotificationRow')) {
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
}

if (!function_exists('buyerNotificationFilter')) {
    /**
     * Notifications have no recipient column yet, so a buyer sees listing updates plus
     * anything about their own appointments and inquiries. Returns [SQL condition, params].
     */
    function buyerNotificationFilter($user) {
        return [
            "(type = 'LISTING_UPDATE'
              OR target_id IN (SELECT id FROM viewing_appointments WHERE LOWER(client_email) = :buyer_email_appt)
              OR target_id IN (SELECT id FROM inquiries WHERE LOWER(sender_email) = :buyer_email_inq))",
            [
                'buyer_email_appt' => strtolower($user['email']),
                'buyer_email_inq' => strtolower($user['email'])
            ]
        ];
    }
}
