<?php
/**
 * Authentication & Profile API Endpoint
 * Handles Client sign in, Agent MLS authentication, password updates, and Agent profile
 */

require_once __DIR__ . '/../config/db.php';

$db = getDatabaseConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetProfile($db);
        break;

    case 'POST':
        handleAuthAction($db);
        break;

    default:
        errorResponse("Unsupported HTTP method: $method", 405);
}

function handleGetProfile($db) {
    $action = $_GET['action'] ?? 'agent_profile';

    if ($action === 'agent_profile') {
        $stmt = $db->query("SELECT * FROM agent_profiles LIMIT 1");
        $profile = $stmt->fetch();
        if ($profile) {
            // Count active listings
            $countStmt = $db->query("SELECT COUNT(*) FROM properties");
            $activeListings = (int)$countStmt->fetchColumn();

            jsonResponse([
                'name' => (string)$profile['name'],
                'title' => (string)$profile['title'],
                'licenseNo' => (string)$profile['license_no'],
                'phone' => (string)$profile['phone'],
                'email' => (string)$profile['email'],
                'rating' => (float)$profile['rating'],
                'reviewsCount' => (int)$profile['reviews_count'],
                'totalSalesVolume' => (string)$profile['total_sales_volume'],
                'activeListingsCount' => $activeListings,
                'bio' => (string)$profile['bio']
            ]);
        } else {
            errorResponse("Agent profile not found in database", 404);
        }
    } else {
        errorResponse("Unknown profile action", 400);
    }
}

function handleAuthAction($db) {
    $input = getJsonInput();
    $action = $_GET['action'] ?? $input['action'] ?? 'client_login';

    switch ($action) {
        case 'client_login':
            $email = trim($input['email'] ?? '');
            $password = trim($input['password'] ?? '');

            if (empty($email)) {
                errorResponse("Email address is required to log in.", 400);
            }
            if (empty($password)) {
                errorResponse("Password is required to log in.", 400);
            }

            // Look up existing buyer account by email
            $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = :email AND role = 'BUYER' LIMIT 1");
            $stmt->execute(['email' => strtolower($email)]);
            $user = $stmt->fetch();

            if (!$user) {
                errorResponse("Account not found for '$email'. Please create an account first.", 401);
            }

            // Verify password if hash exists
            if (!empty($user['password_hash'])) {
                if (!password_verify($password, $user['password_hash'])) {
                    errorResponse("Incorrect password for '$email'. Please check your password and try again.", 401);
                }
            }

            jsonResponse([
                'success' => true,
                'user' => [
                    'id' => (string)$user['id'],
                    'name' => (string)$user['name'],
                    'email' => (string)$user['email'],
                    'phone' => (string)$user['phone'],
                    'role' => 'BUYER',
                    'licenseNo' => '',
                    'agency' => '',
                    'professionalRole' => 'Client Buyer'
                ]
            ]);
            break;

        case 'client_signup':
            $email = trim($input['email'] ?? '');
            $name = trim($input['name'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $password = trim($input['password'] ?? '');

            if (empty($email) || empty($name)) {
                errorResponse("Full name and email address are required to create an account.", 400);
            }
            if (strlen($password) < 4) {
                errorResponse("Password must be at least 4 characters.", 400);
            }

            // Strictly check if an account with this email already exists
            $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = :email LIMIT 1");
            $stmt->execute(['email' => strtolower($email)]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                errorResponse("An account with email '$email' already exists. Please log in instead.", 400);
            }

            $id = 'client_' . bin2hex(random_bytes(4));
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("INSERT INTO users (id, name, email, phone, role, password_hash, updated_at)
                                  VALUES (:id, :name, :email, :phone, 'BUYER', :pass, CURRENT_TIMESTAMP)
                                  RETURNING *");
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : '+1 (555) 019-8234',
                'pass' => $passwordHash
            ]);
            $user = $stmt->fetch();

            jsonResponse([
                'success' => true,
                'user' => [
                    'id' => (string)$user['id'],
                    'name' => (string)$user['name'],
                    'email' => (string)$user['email'],
                    'phone' => (string)$user['phone'],
                    'role' => 'BUYER',
                    'licenseNo' => '',
                    'agency' => '',
                    'professionalRole' => 'Client Buyer'
                ]
            ], 201);
            break;

        case 'agent_login':
            $inputIdentifier = trim($input['emailOrLicense'] ?? $input['email'] ?? $input['licenseNo'] ?? '');
            $password = trim($input['password'] ?? '');

            if (empty($inputIdentifier)) {
                errorResponse("Please enter your agent email or MLS license number.", 400);
            }
            if (strlen($password) < 4) {
                errorResponse("Password must be at least 4 characters.", 400);
            }

            // Look up agent by email or license
            $stmt = $db->prepare("SELECT * FROM users WHERE (LOWER(email) = :email_ident OR LOWER(license_no) = :license_ident) AND role = 'AGENT' LIMIT 1");
            $identifier = strtolower($inputIdentifier);
            $stmt->execute(['email_ident' => $identifier, 'license_ident' => $identifier]);
            $user = $stmt->fetch();

            if (!$user) {
                errorResponse("Agent account not found for '$inputIdentifier'. Please register as a licensed agent first.", 401);
            }

            if (empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
                errorResponse("Incorrect agent password.", 401);
            }

            jsonResponse([
                'success' => true,
                'user' => [
                    'id' => (string)$user['id'],
                    'name' => (string)$user['name'],
                    'email' => (string)$user['email'],
                    'phone' => (string)$user['phone'],
                    'role' => 'AGENT',
                    'licenseNo' => (string)$user['license_no'],
                    'agency' => (string)($user['agency'] ?: 'Estateflow Luxury Realty'),
                    'professionalRole' => (string)($user['professional_role'] ?: 'Licensed Agent')
                ]
            ]);
            break;

        case 'agent_signup':
            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $licenseNo = trim($input['licenseNo'] ?? '');
            $agency = trim($input['agency'] ?? 'Estateflow Luxury Realty');
            $professionalRole = trim($input['professionalRole'] ?? 'Licensed Real Estate Agent');
            $password = trim($input['password'] ?? '');

            if (empty($name) || empty($email) || empty($licenseNo) || strlen($password) < 4) {
                errorResponse("Name, email, license number, and a password of at least 4 chars are required.", 400);
            }

            // Check if agent account already exists
            $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = :email OR (license_no != '' AND LOWER(license_no) = :license) LIMIT 1");
            $stmt->execute(['email' => strtolower($email), 'license' => strtolower($licenseNo)]);
            $existing = $stmt->fetch();

            if ($existing) {
                errorResponse("An agent account with this email or license number already exists. Please log in instead.", 400);
            }

            $id = 'agent_' . bin2hex(random_bytes(4));
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("INSERT INTO users (id, name, email, phone, role, license_no, agency, professional_role, password_hash)
                                  VALUES (:id, :name, :email, '+63 917 555 0192', 'AGENT', :license, :agency, :role, :pass)
                                  RETURNING *");
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'license' => $licenseNo,
                'agency' => $agency,
                'role' => $professionalRole,
                'pass' => $passwordHash
            ]);
            $user = $stmt->fetch();

            // Update agent profile
            $upStmt = $db->prepare("UPDATE agent_profiles SET name = :name, email = :email, license_no = :license, title = :title WHERE id = 'agent_verified_sarah'");
            $upStmt->execute([
                'name' => $name,
                'email' => $email,
                'license' => $licenseNo,
                'title' => "$professionalRole • $agency"
            ]);

            jsonResponse([
                'success' => true,
                'user' => [
                    'id' => (string)$user['id'],
                    'name' => (string)$user['name'],
                    'email' => (string)$user['email'],
                    'phone' => (string)$user['phone'],
                    'role' => 'AGENT',
                    'licenseNo' => (string)$user['license_no'],
                    'agency' => (string)$user['agency'],
                    'professionalRole' => (string)$user['professional_role']
                ]
            ], 201);
            break;

        case 'update_password':
            $email = trim($input['email'] ?? '');
            $newPassword = trim($input['newPassword'] ?? '');
            if (empty($email) || strlen($newPassword) < 4) {
                errorResponse("Email and new password of at least 4 chars are required.");
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE LOWER(email) = :email");
            $stmt->execute(['hash' => $hash, 'email' => strtolower($email)]);

            if ($stmt->rowCount() === 0) {
                errorResponse("User account not found.", 404);
            }

            jsonResponse(['success' => true, 'message' => "Password updated successfully in PostgreSQL."]);
            break;

        case 'update_profile':
            $email = trim($input['email'] ?? '');
            $name = trim($input['name'] ?? '');
            $licenseNo = trim($input['licenseNo'] ?? '');
            $title = trim($input['title'] ?? '');

            if ($email === '' || $name === '' || $licenseNo === '') {
                errorResponse("Email, name, and license number are required.");
            }

            $userStmt = $db->prepare("UPDATE users
                                      SET name = :name, email = :email, license_no = :license,
                                          updated_at = CURRENT_TIMESTAMP
                                      WHERE id = 'agent_verified_sarah'");
            $userStmt->execute([
                'name' => $name,
                'email' => $email,
                'license' => $licenseNo
            ]);

            $stmt = $db->prepare("UPDATE agent_profiles
                                  SET name = :name, email = :email, license_no = :license, title = :title,
                                      updated_at = CURRENT_TIMESTAMP
                                  WHERE id = 'agent_verified_sarah'
                                  RETURNING *");
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'license' => $licenseNo,
                'title' => $title !== '' ? $title : 'Licensed Real Estate Agent'
            ]);
            $profile = $stmt->fetch();
            if (!$profile) {
                errorResponse("Agent profile not found.", 404);
            }

            jsonResponse([
                'name' => (string)$profile['name'],
                'title' => (string)$profile['title'],
                'licenseNo' => (string)$profile['license_no'],
                'phone' => (string)$profile['phone'],
                'email' => (string)$profile['email'],
                'rating' => (float)$profile['rating'],
                'reviewsCount' => (int)$profile['reviews_count'],
                'totalSalesVolume' => (string)$profile['total_sales_volume'],
                'activeListingsCount' => 0,
                'bio' => (string)$profile['bio']
            ]);
            break;

        default:
            errorResponse("Unknown auth action: $action", 400);
    }
}
