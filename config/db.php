<?php
/**
 * Estateflow Backend Database Connection Configuration
 * Connects to PostgreSQL database via PDO (PHP Data Objects).
 * Compatible with XAMPP Apache + PHP with pdo_pgsql extension enabled.
 */

// Start output buffering to prevent any accidental whitespace or notices from corrupting JSON
if (!ob_get_level()) {
    ob_start();
}

// Suppress HTML display of PHP errors so output is never corrupted with <br /><b>
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Allow cross-origin requests from Android app, web clients, and API testers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

// Convert unhandled PHP errors and exceptions into JSON responses
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "PHP Error: $message in " . basename($file) . " on line $line"
    ], JSON_UNESCAPED_SLASHES);
    exit();
});

set_exception_handler(function($e) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "Server Exception: " . $e->getMessage()
    ], JSON_UNESCAPED_SLASHES);
    exit();
});

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// PostgreSQL Database Credentials
// Configure these values in the server environment. Do not hardcode secrets here.
$dbHost = getenv('DB_HOST') ?: 'psql.fr-roub1.bengt.wasmernet.com';
$dbPort = getenv('DB_PORT') ?: '20184';
$dbName = getenv('DB_NAME') ?: 'estateflow';
$dbUser = getenv('DB_USER') ?: 'user_9137c366';
$dbPass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'pw_oTbclj2azWq0NEQb9g85yylGvcUXVcnh';

// Wasmer's managed PostgreSQL only accepts encrypted connections
$dbSslMode = getenv('DB_SSLMODE') ?: 'require';

foreach (['DB_HOST' => $dbHost, 'DB_PORT' => $dbPort, 'DB_NAME' => $dbName, 'DB_USER' => $dbUser, 'DB_PASSWORD' => $dbPass] as $variable => $value) {
    if ($value === false || $value === '') {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => "Required database environment variable is missing: {$variable}"
        ], JSON_UNESCAPED_SLASHES);
        exit();
    }
}

/**
 * Returns a singleton PDO connection to the PostgreSQL database.
 * 
 * @return PDO
 * @throws PDOException
 */
function getDatabaseConnection() {
    global $dbHost, $dbPort, $dbName, $dbUser, $dbPass;
    static $pdo = null;

    if ($pdo === null) {
        // Check if pdo_pgsql extension is loaded
        if (!extension_loaded('pdo_pgsql')) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'PostgreSQL PDO driver (pdo_pgsql) is not enabled in PHP.',
                'solution' => 'Open XAMPP php.ini, uncomment "extension=pdo_pgsql" and "extension=pgsql", then restart Apache.'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName};sslmode={$dbSslMode}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5
        ];

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        } catch (PDOException $e) {
            // Full details go to the server log only; clients get a generic message
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database connection failed'
            ], JSON_UNESCAPED_SLASHES);
            exit();
        }
    }

    return $pdo;
}

/**
 * Send JSON response and exit
 */
function jsonResponse($data, $statusCode = 200) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Send Error response and exit
 */
function errorResponse($message, $statusCode = 400, $extra = []) {
    $response = array_merge([
        'success' => false,
        'error' => $message
    ], $extra);
    jsonResponse($response, $statusCode);
}

/**
 * Parse incoming JSON body for POST, PUT, DELETE
 */
function getJsonInput() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Helper to generate UUID v4
 */
function generateUuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
