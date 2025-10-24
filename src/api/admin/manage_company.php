<?php
// src/api/admin/manage_company.php
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../scripts/auth_helper.php';
require_once __DIR__ . '/../../scripts/uuid_create.php';

require_login();

// Only admins can manage companies
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // Determine action (create, edit, delete)
    $action = $_POST['action'] ?? json_decode(file_get_contents('php://input'), true)['action'] ?? '';

    if ($action === 'delete') {
        // Handle delete (JSON)
        $input = json_decode(file_get_contents('php://input'), true);
        $company_id = $input['company_id'] ?? '';

        if (!$company_id) {
            throw new Exception('Company ID is required.');
        }

        $pdo->beginTransaction();

        // Check if company exists
        $check_stmt = $pdo->prepare("SELECT name FROM Bus_Company WHERE id = ?");
        $check_stmt->execute([$company_id]);
        $company = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            throw new Exception('Company not found.');
        }

        // Delete company (cascading will handle related records)
        $delete_stmt = $pdo->prepare("DELETE FROM Bus_Company WHERE id = ?");
        $delete_stmt->execute([$company_id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Company '{$company['name']}' has been deleted successfully."
        ]);

    } else {
        // Handle create/edit (FormData with file upload)
        $company_id = $_POST['company_id'] ?? '';
        $company_name = trim($_POST['company_name'] ?? '');

        if (!$company_name) {
            throw new Exception('Company name is required.');
        }

        // Handle logo upload
        $logo_path = null;
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/company_logos/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, SVG, WEBP');
            }

            // Generate unique filename
            $new_filename = uniqid('logo_') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $upload_path)) {
                $logo_path = '/uploads/company_logos/' . $new_filename;
            } else {
                throw new Exception('Failed to upload logo.');
            }
        }

        $pdo->beginTransaction();

        if ($action === 'edit' && $company_id) {
            // Edit existing company
            $check_stmt = $pdo->prepare("SELECT name, logo_path FROM Bus_Company WHERE id = ?");
            $check_stmt->execute([$company_id]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                throw new Exception('Company not found.');
            }

            // Check if name is already taken by another company
            $name_check = $pdo->prepare("SELECT id FROM Bus_Company WHERE name = ? AND id != ?");
            $name_check->execute([$company_name, $company_id]);
            if ($name_check->fetch()) {
                throw new Exception('A company with this name already exists.');
            }

            // Update company
            if ($logo_path) {
                // New logo uploaded
                $update_stmt = $pdo->prepare("
                    UPDATE Bus_Company 
                    SET name = ?, logo_path = ? 
                    WHERE id = ?
                ");
                $update_stmt->execute([$company_name, $logo_path, $company_id]);

                // Delete old logo file if exists
                if ($existing['logo_path'] && file_exists(__DIR__ . '/../../' . $existing['logo_path'])) {
                    unlink(__DIR__ . '/../../' . $existing['logo_path']);
                }
            } else {
                // Keep existing logo
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
                'message' => "Company '{$company_name}' has been updated successfully."
            ]);

        } else {
            // Create new company
            // Check if name already exists
            $name_check = $pdo->prepare("SELECT id FROM Bus_Company WHERE name = ?");
            $name_check->execute([$company_name]);
            if ($name_check->fetch()) {
                throw new Exception('A company with this name already exists.');
            }

            $new_company_id = uuidv4();

            $insert_stmt = $pdo->prepare("
                INSERT INTO Bus_Company (id, name, logo_path, created_at) 
                VALUES (?, ?, ?, datetime('now'))
            ");
            $insert_stmt->execute([$new_company_id, $company_name, $logo_path]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Company '{$company_name}' has been created successfully.",
                'data' => [
                    'company_id' => $new_company_id
                ]
            ]);
        }
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Manage company error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in manage company: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}