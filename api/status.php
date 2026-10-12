<?php
/**
 * Estateflow Backend Health & Connection Diagnostic Endpoint
 * Checks PHP version, PostgreSQL PDO connection, and database record statistics.
 * Call from browser, cURL, or the Android app to verify connection.
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDatabaseConnection();
    
    // Query PostgreSQL version
    $versionStmt = $db->query('SELECT version()');
    $pgVersion = $versionStmt->fetchColumn();

    // Query record counts from all tables
    $counts = [];
    $tables = ['properties', 'viewing_appointments', 'inquiries', 'inquiry_messages', 'notifications', 'users', 'agent_profiles'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM {$table}");
            $counts[$table] = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            $counts[$table] = 'Table not created yet';
        }
    }

    jsonResponse([
        'success' => true,
        'status' => 'ONLINE',
        'backend' => 'PHP (XAMPP / Apache)',
        'database' => 'PostgreSQL',
        'postgres_version' => $pgVersion,
        'server_time' => date('Y-m-d H:i:s T'),
        'table_stats' => $counts,
        'endpoints' => [
            'properties' => 'api/properties.php',
            'appointments' => 'api/appointments.php',
            'inquiries' => 'api/inquiries.php',
            'auth' => 'api/auth.php',
            'notifications' => 'api/notifications.php',
            'sync' => 'api/sync.php'
        ]
    ]);
} catch (Exception $e) {
    error_log('Status check failed: ' . $e->getMessage());
    errorResponse('Status check failed', 500, [
        'status' => 'OFFLINE',
        'troubleshooting' => 'Verify PostgreSQL is running and pdo_pgsql extension is enabled in XAMPP php.ini.'
    ]);
}
