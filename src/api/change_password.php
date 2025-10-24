<?php
// src/api/change_password.php
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
    
    $current_password = $input['current_password'] ?? '';
    $new_password = $input['new_password'] ?? '';

    // Validate required fields
    if (!$current_password || !$new_password) {
        throw new Exception('Current password and new password are required.');
    }

    // Validate new password length
    if (strlen($new_password) < 6) {
        throw new Exception('New password must be at least 6 characters long.');
    }

    if (strlen($new_password) > 255) {
        throw new Exception('New password is too long.');
    }

    // Check if new password is different from current
    if ($current_password === $new_password) {
        throw new Exception('New password must be different from current password.');
    }

    $pdo->beginTransaction();

    // Fetch current password hash
    $user_stmt = $pdo->prepare("SELECT id, password FROM User WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found.');
    }

    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        throw new Exception('Current password is incorrect.');
    }

    // Hash new password
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $update_stmt = $pdo->prepare("UPDATE User SET password = ? WHERE id = ?");
    $update_stmt->execute([$new_password_hash, $user_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully!'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Change password error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in change password: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}