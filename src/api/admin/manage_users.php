<?php
// src/api/admin/manage_users.php
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../scripts/auth_helper.php';
require_once __DIR__ . '/../../scripts/uuid_create.php';

require_login();

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if (!$action) {
        throw new Exception('Action is required.');
    }

    if ($action === 'create_company_admin') {
        // Create new company admin user
        $full_name = trim($input['full_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $gender = trim($input['gender'] ?? '');
        $company_id = trim($input['company_id'] ?? '');

        // Validate required fields
        if (!$full_name || !$email || !$password || !$company_id) {
            throw new Exception('Full name, email, password, and company are required.');
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }

        // Validate name length
        if (strlen($full_name) < 2 || strlen($full_name) > 100) {
            throw new Exception('Full name must be between 2 and 100 characters.');
        }

        // Validate password length
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters.');
        }

        // Validate gender if provided
        if ($gender && !in_array($gender, ['male', 'female'])) {
            throw new Exception('Invalid gender value.');
        }

        $pdo->beginTransaction();

        // Check if email already exists
        $email_check = $pdo->prepare("SELECT id FROM User WHERE email = ?");
        $email_check->execute([$email]);
        if ($email_check->fetch()) {
            throw new Exception('This email is already registered.');
        }

        // Verify company exists
        $company_check = $pdo->prepare("SELECT id, name FROM Bus_Company WHERE id = ?");
        $company_check->execute([$company_id]);
        $company = $company_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$company) {
            throw new Exception('Company not found.');
        }

        // Create user with company admin role
        $user_id = uuidv4();
        $password_hash = password_hash($password, PASSWORD_ARGON2ID);
        $created_at = date('Y-m-d H:i:s');

        $insert_stmt = $pdo->prepare("
            INSERT INTO User (
                id, full_name, email, password, role, company_id, gender, balance, created_at
            ) VALUES (?, ?, ?, ?, 'company', ?, ?, 800, ?)
        ");
        $insert_stmt->execute([
            $user_id,
            $full_name,
            $email,
            $password_hash,
            $company_id,
            $gender ?: null,
            $created_at
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Company admin account created successfully! {$full_name} is now an admin for {$company['name']}.",
            'data' => [
                'user_id' => $user_id
            ]
        ]);

    } elseif ($action === 'assign_company') {
        // Assign existing user to company
        $user_id = $input['user_id'] ?? '';
        $company_id = $input['company_id'] ?? '';

        if (!$user_id || !$company_id) {
            throw new Exception('User ID and company ID are required.');
        }

        $pdo->beginTransaction();

        // Verify user exists and is regular user
        $user_stmt = $pdo->prepare("SELECT id, role, full_name FROM User WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('User not found.');
        }

        if ($user['role'] !== 'user') {
            throw new Exception('Only regular users can be assigned to companies.');
        }

        // Verify company exists
        $company_stmt = $pdo->prepare("SELECT id, name FROM Bus_Company WHERE id = ?");
        $company_stmt->execute([$company_id]);
        $company = $company_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            throw new Exception('Company not found.');
        }

        // Update user role and assign company
        $update_stmt = $pdo->prepare("
            UPDATE User 
            SET role = 'company', company_id = ? 
            WHERE id = ?
        ");
        $update_stmt->execute([$company_id, $user_id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "{$user['full_name']} has been assigned to {$company['name']} as a company admin."
        ]);

    } elseif ($action === 'remove_company') {
        // Remove user from company (demote to regular user)
        $user_id = $input['user_id'] ?? '';

        if (!$user_id) {
            throw new Exception('User ID is required.');
        }

        $pdo->beginTransaction();

        // Verify user exists and is company admin
        $user_stmt = $pdo->prepare("SELECT id, role, full_name FROM User WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('User not found.');
        }

        if ($user['role'] !== 'company') {
            throw new Exception('User is not a company admin.');
        }

        // Update user role back to regular user and remove company
        $update_stmt = $pdo->prepare("
            UPDATE User 
            SET role = 'user', company_id = NULL 
            WHERE id = ?
        ");
        $update_stmt->execute([$user_id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "{$user['full_name']} has been removed from their company and demoted to regular user."
        ]);

    } else {
        throw new Exception('Invalid action.');
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Admin manage users error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in admin manage users: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}