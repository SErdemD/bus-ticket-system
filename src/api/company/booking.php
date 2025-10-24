<?php
// src/api/company/cancel_booking.php
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../scripts/auth_helper.php';

require_login();

if (!is_company()) {
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
    
    // Get user's company ID
    $user_stmt = $pdo->prepare("SELECT company_id FROM User WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_company_id = $user_data['company_id'] ?? null;
    
    if (!$user_company_id) {
        throw new Exception('Company information not found.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $ticket_id = $input['ticket_id'] ?? '';

    if (!$ticket_id) {
        throw new Exception('Ticket ID is required.');
    }

    $pdo->beginTransaction();

    // Fetch ticket with trip and user info
    $ticket_stmt = $pdo->prepare("
        SELECT t.id, t.user_id, t.status, t.total_price, 
               tr.departure_time, tr.company_id,
               u.full_name
        FROM Tickets t
        JOIN Trips tr ON t.trip_id = tr.id
        JOIN User u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $ticket_stmt->execute([$ticket_id]);
    $ticket = $ticket_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('Ticket not found.');
    }

    // Verify ticket belongs to this company's trip
    if ($ticket['company_id'] !== $user_company_id) {
        throw new Exception('You can only cancel bookings for your own company trips.');
    }

    // Check if already cancelled
    if ($ticket['status'] === 'CANCELLED') {
        throw new Exception('This ticket has already been cancelled.');
    }

    // Check if ticket is still active
    if ($ticket['status'] !== 'ACTIVE') {
        throw new Exception('This ticket cannot be cancelled.');
    }

    // Check 1 hour rule
    $departure_time = strtotime($ticket['departure_time']);
    $current_time = time();
    $time_until_departure = $departure_time - $current_time;

    if ($time_until_departure < 3600) { // 3600 seconds = 1 hour
        throw new Exception('Cannot cancel ticket within 1 hour of departure time.');
    }

    // Update ticket status
    $cancel_stmt = $pdo->prepare("UPDATE Tickets SET status = 'CANCELLED' WHERE id = ?");
    $cancel_stmt->execute([$ticket_id]);

    // Refund to user
    $refund_stmt = $pdo->prepare("UPDATE User SET balance = balance + ? WHERE id = ?");
    $refund_stmt->execute([$ticket['total_price'], $ticket['user_id']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Booking for {$ticket['full_name']} cancelled successfully. Customer has been refunded \$" . number_format($ticket['total_price'], 2) . "."
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Company cancel booking error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in company cancel booking: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}