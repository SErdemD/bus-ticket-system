<?php
// src/api/process_booking.php
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
    
    // Parse input
    $booking_data = json_decode($_POST['booking_data'] ?? '', true);
    $coupon_code = trim($_POST['coupon_code'] ?? '');

    if (!$booking_data) {
        throw new Exception('Invalid booking data provided.');
    }

    // Validate trip type
    if (!in_array($booking_data['trip_type'], ['one_way', 'round'])) {
        throw new Exception('Invalid trip type.');
    }

    $pdo->beginTransaction();

    // Fetch user
    $user_stmt = $pdo->prepare("SELECT id, balance, full_name FROM User WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User account not found.');
    }

    // Helper to create ticket & calculate price
    $create_ticket = function($trip_id, $seats, $pdo, $user_id) {
        $ticket_id = uniqid('tkt_', true);
        $seat_count = count($seats);

        // Fetch trip details including company_id
        $price_stmt = $pdo->prepare("SELECT price, capacity, company_id FROM Trips WHERE id = ?");
        $price_stmt->execute([$trip_id]);
        $trip = $price_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trip) {
            throw new Exception('Trip not found.');
        }

        // Check seat availability
        $check_stmt = $pdo->prepare("
            SELECT bs.seat_number
            FROM Booked_Seats bs
            JOIN Tickets t ON bs.ticket_id = t.id
            WHERE t.trip_id = ? AND t.status = 'ACTIVE'
        ");
        $check_stmt->execute([$trip_id]);
        $booked = $check_stmt->fetchAll(PDO::FETCH_COLUMN);

        // Verify requested seats are not already booked
        foreach ($seats as $seat) {
            if (in_array($seat, $booked)) {
                throw new Exception("Seat {$seat} is no longer available. Please select different seats.");
            }
        }

        // Check capacity
        $current_booked_count = count($booked);
        if (($current_booked_count + $seat_count) > $trip['capacity']) {
            throw new Exception("Not enough seats available on this trip.");
        }

        $total = $trip['price'] * $seat_count;

        // Create ticket
        $insert_ticket = $pdo->prepare("
            INSERT INTO Tickets (id, trip_id, user_id, total_price, status, created_at) 
            VALUES (?, ?, ?, ?, 'ACTIVE', datetime('now'))
        ");
        $insert_ticket->execute([$ticket_id, $trip_id, $user_id, $total]);

        // Book seats
        $insert_seat = $pdo->prepare("
            INSERT INTO Booked_Seats (id, ticket_id, seat_number, created_at) 
            VALUES (?, ?, ?, datetime('now'))
        ");
        
        foreach ($seats as $seat) {
            $insert_seat->execute([uniqid('seat_', true), $ticket_id, $seat]);
        }

        return ['ticket_id' => $ticket_id, 'total' => $total, 'company_id' => $trip['company_id']];
    };

    // Calculate total amount from trips (SERVER-SIDE CALCULATION)
    $ticket_ids = [];
    $total_amount = 0;
    $company_ids = [];
    
    if ($booking_data['trip_type'] === 'one_way') {
        if (!isset($booking_data['trip_id']) || !isset($booking_data['seats'])) {
            throw new Exception('Missing trip information.');
        }
        
        $result = $create_ticket($booking_data['trip_id'], $booking_data['seats'], $pdo, $user_id);
        $ticket_ids[] = $result['ticket_id'];
        $total_amount += $result['total'];
        $company_ids[] = $result['company_id'];
        
    } else if ($booking_data['trip_type'] === 'round') {
        if (!isset($booking_data['departure_trip']) || 
            !isset($booking_data['return_trip']) ||
            !isset($booking_data['departure_seats']) || 
            !isset($booking_data['return_seats'])) {
            throw new Exception('Missing trip information.');
        }
        
        $result = $create_ticket($booking_data['departure_trip'], $booking_data['departure_seats'], $pdo, $user_id);
        $ticket_ids[] = $result['ticket_id'];
        $total_amount += $result['total'];
        $company_ids[] = $result['company_id'];
        
        $result = $create_ticket($booking_data['return_trip'], $booking_data['return_seats'], $pdo, $user_id);
        $ticket_ids[] = $result['ticket_id'];
        $total_amount += $result['total'];
        $company_ids[] = $result['company_id'];
    }

    // Validate and apply coupon discount (SERVER-SIDE)
    $coupon_discount = 0;
    $coupon_id = null;
    
    if ($coupon_code) {
        $coupon_stmt = $pdo->prepare("
            SELECT id, discount, company_id, usage_limit, expire_date
            FROM Coupons 
            WHERE code = ?
        ");
        $coupon_stmt->execute([$coupon_code]);
        $coupon = $coupon_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            throw new Exception('Invalid or expired coupon code.');
        }

        // Check expiration
        if ($coupon['expire_date'] && strtotime($coupon['expire_date']) < time()) {
            throw new Exception('Coupon has expired.');
        }

        // Check usage limit
        if ($coupon['usage_limit']) {
            $usage_stmt = $pdo->prepare("SELECT COUNT(*) FROM User_Coupons WHERE coupon_id = ?");
            $usage_stmt->execute([$coupon['id']]);
            $usage_count = $usage_stmt->fetchColumn();
            
            if ($usage_count >= $coupon['usage_limit']) {
                throw new Exception('Coupon usage limit reached.');
            }
        }

        // Check if user already used this coupon
        $user_usage_stmt = $pdo->prepare("SELECT id FROM User_Coupons WHERE coupon_id = ? AND user_id = ?");
        $user_usage_stmt->execute([$coupon['id'], $user_id]);
        if ($user_usage_stmt->fetch()) {
            throw new Exception('You have already used this coupon.');
        }

        // CRITICAL: Validate coupon belongs to the company of the trips
        $unique_companies = array_unique($company_ids);
        
        // Ensure all trips are from the same company
        if (count($unique_companies) > 1) {
            throw new Exception('Cannot apply coupon to trips from different companies.');
        }
        
        // Check if coupon belongs to the trip's company
        if ($coupon['company_id'] != $unique_companies[0] && $coupon['company_id'] != null) {
            // Get company name for better error message
            $company_stmt = $pdo->prepare("SELECT name FROM Bus_Company WHERE id = ?");
            $company_stmt->execute([$coupon['company_id']]);
            $coupon_company = $company_stmt->fetchColumn();
            
            throw new Exception('This coupon is only valid for ' . ($coupon_company ?: 'a different company') . ' trips.');
        }

        // Apply discount (percentage)
        $discount_percentage = floatval($coupon['discount']);
        $coupon_discount = ($total_amount * $discount_percentage) / 100;
        $coupon_id = $coupon['id'];
    }

    // Calculate final amount after discount
    $final_amount = max(0, $total_amount - $coupon_discount);

    // Check sufficient balance
    if ($user['balance'] < $final_amount) {
        throw new Exception('Insufficient balance. Please add funds to your account.');
    }

    // Deduct balance
    $new_balance = $user['balance'] - $final_amount;
    $_SESSION['user_balance'] = $user['balance'] - $final_amount;
    $update_balance = $pdo->prepare("UPDATE User SET balance = ? WHERE id = ?");
    $update_balance->execute([$new_balance, $user_id]);
    // Record coupon usage if applicable
    if ($coupon_id) {
        $usage_id = uniqid('uc_', true);
        $record_usage = $pdo->prepare("
            INSERT INTO User_Coupons (id, coupon_id, user_id, created_at) 
            VALUES (?, ?, ?, datetime('now'))
        ");
        $record_usage->execute([$usage_id, $coupon_id, $user_id]);
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Booking completed successfully!',
        'data' => [
            'ticket_ids' => $ticket_ids,
            'amount_paid' => $final_amount,
            'new_balance' => $new_balance,
            'coupon_used' => $coupon_code ?: null,
            'discount_applied' => $coupon_discount
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Booking process error [User: {$user_id}]: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in booking process: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}