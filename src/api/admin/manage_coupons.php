<?php
// src/api/admin/manage_coupons.php
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../scripts/auth_helper.php';
require_once __DIR__ . '/../../scripts/uuid_create.php';

require_login();

// Only admins can manage coupons
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

    // Determine action
    $action = $_POST['action'] ?? json_decode(file_get_contents('php://input'), true)['action'] ?? '';

    if ($action === 'delete') {
        // Handle delete (JSON)
        $input = json_decode(file_get_contents('php://input'), true);
        $coupon_id = $input['coupon_id'] ?? '';

        if (!$coupon_id) {
            throw new Exception('Coupon ID is required.');
        }

        $pdo->beginTransaction();

        // Check if coupon exists
        $check_stmt = $pdo->prepare("SELECT code FROM Coupons WHERE id = ?");
        $check_stmt->execute([$coupon_id]);
        $coupon = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            throw new Exception('Coupon not found.');
        }

        // Delete coupon (cascading will handle User_Coupons)
        $delete_stmt = $pdo->prepare("DELETE FROM Coupons WHERE id = ?");
        $delete_stmt->execute([$coupon_id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Coupon '{$coupon['code']}' has been deleted successfully."
        ]);

    } else {
        // Handle create/edit (FormData)
        $coupon_id = $_POST['coupon_id'] ?? '';
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $discount = floatval($_POST['discount'] ?? 0);
        $company_id = trim($_POST['company_id'] ?? '');
        $usage_limit = intval($_POST['usage_limit'] ?? 0);
        $expire_date = trim($_POST['expire_date'] ?? '');

        // Validate required fields
        if (!$code || $discount <= 0 || $usage_limit < 1 || !$expire_date) {
            throw new Exception('All required fields must be filled.');
        }

        // Validate code format (letters and numbers only)
        if (!preg_match('/^[A-Z0-9]+$/', $code)) {
            throw new Exception('Coupon code must contain only letters and numbers.');
        }

        if (strlen($code) > 20) {
            throw new Exception('Coupon code must not exceed 20 characters.');
        }

        // Validate discount
        if ($discount <= 0) {
            throw new Exception('Discount must be greater than zero.');
        }

        // Validate usage limit
        if ($usage_limit < 1) {
            throw new Exception('Usage limit must be at least 1.');
        }

        // Validate and format expire date
        $expire_dt = DateTime::createFromFormat('Y-m-d\TH:i', $expire_date);
        if (!$expire_dt) {
            throw new Exception('Invalid expiration date format.');
        }

        if ($expire_dt <= new DateTime()) {
            throw new Exception('Expiration date must be in the future.');
        }

        $expire_formatted = $expire_dt->format('Y-m-d H:i:s');

        // Validate company if provided
        if ($company_id) {
            $company_check = $pdo->prepare("SELECT id FROM Bus_Company WHERE id = ?");
            $company_check->execute([$company_id]);
            if (!$company_check->fetch()) {
                throw new Exception('Invalid company selected.');
            }
        } else {
            $company_id = null; // Set to null for all companies
        }

        $pdo->beginTransaction();

        if ($action === 'edit' && $coupon_id) {
            // Edit existing coupon
            $check_stmt = $pdo->prepare("SELECT code FROM Coupons WHERE id = ?");
            $check_stmt->execute([$coupon_id]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                throw new Exception('Coupon not found.');
            }

            // Check if code is already taken by another coupon
            $code_check = $pdo->prepare("SELECT id FROM Coupons WHERE code = ? AND id != ?");
            $code_check->execute([$code, $coupon_id]);
            if ($code_check->fetch()) {
                throw new Exception('A coupon with this code already exists.');
            }

            // Update coupon
            $update_stmt = $pdo->prepare("
                UPDATE Coupons 
                SET code = ?, 
                    discount = ?, 
                    company_id = ?, 
                    usage_limit = ?, 
                    expire_date = ?
                WHERE id = ?
            ");
            $update_stmt->execute([
                $code, 
                $discount, 
                $company_id, 
                $usage_limit, 
                $expire_formatted, 
                $coupon_id
            ]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Coupon '{$code}' has been updated successfully."
            ]);

        } else {
            // Create new coupon
            // Check if code already exists
            $code_check = $pdo->prepare("SELECT id FROM Coupons WHERE code = ?");
            $code_check->execute([$code]);
            if ($code_check->fetch()) {
                throw new Exception('A coupon with this code already exists.');
            }

            $new_coupon_id = uuidv4();

            $insert_stmt = $pdo->prepare("
                INSERT INTO Coupons (
                    id, 
                    code, 
                    discount, 
                    company_id, 
                    usage_limit, 
                    expire_date, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
            ");
            $insert_stmt->execute([
                $new_coupon_id, 
                $code, 
                $discount, 
                $company_id, 
                $usage_limit, 
                $expire_formatted
            ]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Coupon '{$code}' has been created successfully.",
                'data' => [
                    'coupon_id' => $new_coupon_id
                ]
            ]);
        }
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Manage coupons error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in manage coupons: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}