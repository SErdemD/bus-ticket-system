<?php
// src/api/validate_coupon.php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../scripts/auth_helper.php';

require_login();

if (is_admin() || is_company()) {
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
    
    $coupon_code = trim($input['coupon_code'] ?? '');
    $booking_data = $input['booking_data'] ?? null;
    $total_amount = floatval($input['total_amount'] ?? 0);

    if (!$coupon_code) {
        throw new Exception('Coupon code is required.');
    }

    if (!$booking_data || $total_amount <= 0) {
        throw new Exception('Invalid booking data.');
    }

    // Fetch and validate coupon
    $stmt = $pdo->prepare("
        SELECT id, code, discount, company_id, usage_limit, expire_date
        FROM Coupons 
        WHERE code = ?
    ");
    $stmt->execute([$coupon_code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        throw new Exception('Invalid coupon code.');
    }

    // Check expiration
    if ($coupon['expire_date'] && strtotime($coupon['expire_date']) < time()) {
        throw new Exception('This coupon has expired.');
    }

    // Check usage limit
    if ($coupon['usage_limit']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM User_Coupons WHERE coupon_id = ?");
        $stmt->execute([$coupon['id']]);
        $usage_count = $stmt->fetchColumn();
        
        if ($usage_count >= $coupon['usage_limit']) {
            throw new Exception('Coupon usage limit has been reached.');
        }
    }

    // Check if user already used this coupon
    $stmt = $pdo->prepare("SELECT id FROM User_Coupons WHERE coupon_id = ? AND user_id = ?");
    $stmt->execute([$coupon['id'], $user_id]);
    if ($stmt->fetch()) {
        throw new Exception('You have already used this coupon.');
    }

    // VALIDATE COUPON BELONGS TO THE COMPANY OF THE TRIPS
    $trip_ids = [];
    if ($booking_data['trip_type'] === 'one_way') {
        $trip_ids[] = $booking_data['trip_id'];
    } else if ($booking_data['trip_type'] === 'round') {
        $trip_ids[] = $booking_data['departure_trip'];
        $trip_ids[] = $booking_data['return_trip'];
    }

    // Get company IDs for all trips in booking
    $placeholders = implode(',', array_fill(0, count($trip_ids), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT company_id FROM Trips WHERE id IN ($placeholders)");
    $stmt->execute($trip_ids);
    $trip_companies = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($trip_companies)) {
        throw new Exception('Trip not found.');
    }

    // Ensure all trips belong to the same company
    if (count($trip_companies) > 1) {
        throw new Exception('Cannot apply coupon to trips from different companies.');
    }

    // Check if coupon belongs to the company
    if ($coupon['company_id'] != $trip_companies[0] && $coupon['company_id'] != null) {
        // Get company name for better error message
        $stmt = $pdo->prepare("SELECT name FROM Bus_Company WHERE id = ?");
        $stmt->execute([$coupon['company_id']]);
        $coupon_company = $stmt->fetchColumn();
        
        throw new Exception('This coupon is only valid for ' . ($coupon_company ?: 'a different company') . ' trips.');
    }

    // Calculate discount (percentage from database)
    $discount_percentage = floatval($coupon['discount']);
    $discount_amount = ($total_amount * $discount_percentage) / 100;
    $final_amount = max(0, $total_amount - $discount_amount);

    echo json_encode([
        'success' => true,
        'message' => 'Coupon applied successfully!',
        'discount_percentage' => $discount_percentage,
        'discount_amount' => $discount_amount,
        'discount_type' => 'percentage',
        'original_amount' => $total_amount,
        'final_amount' => $final_amount
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Coupon validation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred.'
    ]);
}