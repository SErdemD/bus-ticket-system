<?php
// src/api/update_profile.php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../scripts/auth_helper.php';

require_login();

// Block admin from using this
if (is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    $full_name = trim($input['full_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $gender = trim($input['gender'] ?? '');

    // Validate required fields
    if (!$full_name || !$email) {
        throw new Exception('Full name and email are required.');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format.');
    }

    // Validate name length
    if (strlen($full_name) < 2 || strlen($full_name) > 100) {
        throw new Exception('Full name must be between 2 and 100 characters.');
    }

    // Validate gender if provided
    if ($gender && !in_array($gender, ['male', 'female', ''])) {
        throw new Exception('Invalid gender value.');
    }

    $pdo->beginTransaction();

    // Check if email already exists for another user
    $email_check = $pdo->prepare("SELECT id FROM User WHERE email = ? AND id != ?");
    $email_check->execute([$email, $user_id]);
    if ($email_check->fetch()) {
        throw new Exception('This email is already in use by another account.');
    }

    // Update user profile
    $update_stmt = $pdo->prepare("
        UPDATE User 
        SET full_name = ?, 
            email = ?, 
            gender = ?
        WHERE id = ?
    ");
    $update_stmt->execute([
        $full_name,
        $email,
        $gender ?: null,
        $user_id
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully!'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Update profile error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in update profile: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}