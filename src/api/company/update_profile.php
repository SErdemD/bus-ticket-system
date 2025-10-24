<?php
// src/api/update_company_profile.php
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../scripts/auth_helper.php';

require_login();

// Only company admins can access
if (!is_company()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Company admin only.']);
    exit();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $user_id = $_SESSION['user_id'];
    
    // Get user's company ID
    $user_stmt = $pdo->prepare("SELECT company_id FROM User WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $company_id = $user_data['company_id'] ?? null;
    
    if (!$company_id) {
        throw new Exception('Company information not found.');
    }

    $company_name = trim($_POST['company_name'] ?? '');

    // Validate company name
    if (!$company_name) {
        throw new Exception('Company name is required.');
    }

    if (strlen($company_name) < 2 || strlen($company_name) > 100) {
        throw new Exception('Company name must be between 2 and 100 characters.');
    }

    $pdo->beginTransaction();

    // Check if company name already exists for another company
    $name_check = $pdo->prepare("SELECT id FROM Bus_Company WHERE name = ? AND id != ?");
    $name_check->execute([$company_name, $company_id]);
    if ($name_check->fetch()) {
        throw new Exception('This company name is already in use.');
    }

    // Handle logo upload if provided
    $logo_path = null;
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['company_logo'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception('Invalid file type. Please upload an image (JPEG, PNG, GIF, or WebP).');
        }

        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File is too large. Maximum size is 5MB.');
        }

        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/../../uploads/company_logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('logo_' . $company_id . '_') . '.' . $extension;
        $upload_path = $upload_dir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Failed to upload logo. Please try again.');
        }

        $logo_path = '/uploads/company_logos/' . $filename;

        // Delete old logo if exists
        $old_logo_stmt = $pdo->prepare("SELECT logo_path FROM Bus_Company WHERE id = ?");
        $old_logo_stmt->execute([$company_id]);
        $old_logo = $old_logo_stmt->fetchColumn();
        
        if ($old_logo && file_exists(__DIR__ . '/..' . $old_logo)) {
            @unlink(__DIR__ . '/..' . $old_logo);
        }
    }

    // Update company profile
    if ($logo_path) {
        $update_stmt = $pdo->prepare("
            UPDATE Bus_Company 
            SET name = ?, logo_path = ?
            WHERE id = ?
        ");
        $update_stmt->execute([$company_name, $logo_path, $company_id]);
    } else {
        $update_stmt = $pdo->prepare("
            UPDATE Bus_Company 
            SET name = ?
            WHERE id = ?
        ");
        $update_stmt->execute([$company_name, $company_id]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Company profile updated successfully!',
        'data' => [
            'logo_path' => $logo_path
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Clean up uploaded file if transaction failed
    if (isset($upload_path) && file_exists($upload_path)) {
        @unlink($upload_path);
    }
    
    error_log("Update company profile error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Clean up uploaded file if transaction failed
    if (isset($upload_path) && file_exists($upload_path)) {
        @unlink($upload_path);
    }
    
    error_log("Database error in update company profile: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}