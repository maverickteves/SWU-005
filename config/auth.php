<?php
/**
 * Estateflow API Authentication
 * Issues signed access tokens at login and checks them on every protected request.
 *
 * Server environment:
 *   AUTH_SECRET   - random string of at least 32 characters used to sign tokens (required)
 *   AUTH_REQUIRED - "true" rejects requests that carry no token. Leave unset while older
 *                   app builds that do not send a token are still in use.
 */

const AUTH_TOKEN_LIFETIME = 2592000; // 30 days

function authSecret() {
    $secret = getenv('AUTH_SECRET');
    return ($secret !== false && strlen($secret) >= 32) ? $secret : null;
}

function authRequired() {
    return strtolower((string)getenv('AUTH_REQUIRED')) === 'true';
}

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    $decoded = base64_decode(strtr($data, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

/**
 * Ties a token to the user's current password, so changing the password signs out every
 * device that was using the old one.
 */
function passwordVersion($passwordHash) {
    return substr(hash('sha256', (string)$passwordHash), 0, 16);
}

/**
 * Returns a signed token for a users row, or null when AUTH_SECRET is not configured.
 */
function issueAuthToken($user) {
    $secret = authSecret();
    if ($secret === null) {
        error_log('AUTH_SECRET is missing or shorter than 32 characters; no access token issued.');
        return null;
    }

    $payload = base64UrlEncode(json_encode([
        'sub' => (string)$user['id'],
        'role' => strtoupper((string)$user['role']),
        'pwv' => passwordVersion($user['password_hash']),
        'exp' => time() + AUTH_TOKEN_LIFETIME
    ]));
    $signature = base64UrlEncode(hash_hmac('sha256', $payload, $secret, true));

    return "$payload.$signature";
}

function readAuthToken() {
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Returns the signed-in user as ['id', 'email', 'role', 'name'], or null when the request
 * has no token. A token that is present but invalid, expired, or revoked is always rejected.
 */
function currentUser($db) {
    static $resolved = false;
    static $user = null;
    if ($resolved) {
        return $user;
    }
    $resolved = true;

    $token = readAuthToken();
    if ($token === null) {
        return null;
    }

    $secret = authSecret();
    $parts = explode('.', $token);
    if ($secret === null || count($parts) !== 2) {
        errorResponse('Your session is invalid. Please sign in again.', 401);
    }

    [$payload, $signature] = $parts;
    $expected = base64UrlEncode(hash_hmac('sha256', $payload, $secret, true));
    if (!hash_equals($expected, $signature)) {
        errorResponse('Your session is invalid. Please sign in again.', 401);
    }

    $claims = json_decode(base64UrlDecode($payload) ?? '', true);
    if (!is_array($claims) || empty($claims['sub']) || (int)($claims['exp'] ?? 0) < time()) {
        errorResponse('Your session has expired. Please sign in again.', 401);
    }

    $stmt = $db->prepare("SELECT id, name, email, role, password_hash FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $claims['sub']]);
    $row = $stmt->fetch();
    if (!$row
        || strtoupper((string)$row['role']) !== ($claims['role'] ?? '')
        || !hash_equals(passwordVersion($row['password_hash']), (string)($claims['pwv'] ?? ''))) {
        errorResponse('Your session has expired. Please sign in again.', 401);
    }

    $user = [
        'id' => (string)$row['id'],
        'name' => (string)$row['name'],
        'email' => (string)$row['email'],
        'role' => strtoupper((string)$row['role'])
    ];
    return $user;
}

/**
 * Requires a signed-in user with one of $roles (any role when empty).
 * Returns the user, or null for a request without a token while AUTH_REQUIRED is off,
 * which keeps older app builds working until every phone sends a token.
 */
function requireUser($db, $roles = []) {
    $user = currentUser($db);
    if ($user === null) {
        if (authRequired()) {
            errorResponse('Please sign in to continue.', 401);
        }
        return null;
    }
    if (!empty($roles) && !in_array($user['role'], $roles, true)) {
        errorResponse('You do not have permission to do this.', 403);
    }
    return $user;
}

function isAgent($user) {
    return $user !== null && $user['role'] === 'AGENT';
}

function isBuyer($user) {
    return $user !== null && $user['role'] === 'BUYER';
}

function sameEmail($a, $b) {
    return strtolower(trim((string)$a)) === strtolower(trim((string)$b));
}

// ---------------------------------------------------------------------------
// Failed sign-in limit. Needs the login_attempts table (database/schema.sql).
// ---------------------------------------------------------------------------

const LOGIN_MAX_FAILURES = 5;
const LOGIN_LOCK_MINUTES = 15;

/**
 * Runs a login_attempts query. If the table has not been created yet, the limit is
 * skipped (and logged) so sign-in keeps working.
 */
function withLoginAttempts($callback, $fallback) {
    try {
        return $callback();
    } catch (PDOException $e) {
        if ($e->getCode() === '42P01') { // undefined_table
            error_log('login_attempts table is missing; failed sign-in limit is off. Run the database update.');
            return $fallback;
        }
        throw $e;
    }
}

/**
 * Stops the request with 429 when $key (for example "client:jane@example.com") has had
 * too many failed sign-ins in the last LOGIN_LOCK_MINUTES.
 */
function enforceLoginLimit($db, $key) {
    $failures = withLoginAttempts(function () use ($db, $key) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts
                              WHERE attempt_key = :key
                                AND attempted_at > CURRENT_TIMESTAMP - make_interval(mins => :minutes)");
        $stmt->execute(['key' => $key, 'minutes' => LOGIN_LOCK_MINUTES]);
        return (int)$stmt->fetchColumn();
    }, 0);

    if ($failures >= LOGIN_MAX_FAILURES) {
        errorResponse("Too many failed attempts. Please wait " . LOGIN_LOCK_MINUTES . " minutes and try again.", 429);
    }
}

function recordLoginFailure($db, $key) {
    withLoginAttempts(function () use ($db, $key) {
        $db->prepare("INSERT INTO login_attempts (attempt_key) VALUES (:key)")->execute(['key' => $key]);
        // Old rows are no longer needed once they fall outside the window
        $db->exec("DELETE FROM login_attempts WHERE attempted_at < CURRENT_TIMESTAMP - INTERVAL '1 day'");
        return null;
    }, null);
}

function clearLoginFailures($db, $key) {
    withLoginAttempts(function () use ($db, $key) {
        $db->prepare("DELETE FROM login_attempts WHERE attempt_key = :key")->execute(['key' => $key]);
        return null;
    }, null);
}
