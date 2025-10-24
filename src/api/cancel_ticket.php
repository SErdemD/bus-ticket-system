<?php
// src/api/cancel_ticket.php
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
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $user_id = $_SESSION['user_id'];
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    $ticket_id = $input['ticket_id'] ?? '';

    if (!$ticket_id) {
        throw new Exception('Ticket ID is required.');
    }

    $pdo->beginTransaction();

    // Fetch ticket details with trip info
    $ticket_stmt = $pdo->prepare("
        SELECT t.id, t.user_id, t.status, t.total_price, tr.departure_time
        FROM Tickets t
        JOIN Trips tr ON t.trip_id = tr.id
        WHERE t.id = ?
    ");
    $ticket_stmt->execute([$ticket_id]);
    $ticket = $ticket_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('Ticket not found.');
    }

    // Verify ownership
    if ($ticket['user_id'] !== $user_id) {
        throw new Exception('You do not have permission to cancel this ticket.');
    }

    // Check if already cancelled
    if ($ticket['status'] === 'CANCELLED') {
        throw new Exception('This ticket has already been cancelled.');
    }

    // Check if ticket is still active
    if ($ticket['status'] !== 'ACTIVE') {
        throw new Exception('This ticket cannot be cancelled.');
    }

    // Check if departure time allows cancellation (must be at least 1 hour before)
    $departure_time = strtotime($ticket['departure_time']);
    $current_time = time();
    $time_until_departure = $departure_time - $current_time;

    if ($time_until_departure < 3600) { // 3600 seconds = 1 hour
        throw new Exception('Cannot cancel ticket within 1 hour of departure time.');
    }

    // Update ticket status to CANCELLED
    $cancel_stmt = $pdo->prepare("
        UPDATE Tickets 
        SET status = 'CANCELLED' 
        WHERE id = ?
    ");
    $cancel_stmt->execute([$ticket_id]);

    // Refund the amount to user's balance
    $refund_stmt = $pdo->prepare("
        UPDATE User 
        SET balance = balance + ? 
        WHERE id = ?
    ");
    $refund_stmt->execute([$ticket['total_price'], $user_id]);

    // Get new balance
    $balance_stmt = $pdo->prepare("SELECT balance FROM User WHERE id = ?");
    $balance_stmt->execute([$user_id]);
    $new_balance = $balance_stmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ticket cancelled successfully. Refund has been added to your balance.',
        'data' => [
            'refund_amount' => $ticket['total_price'],
            'new_balance' => $new_balance
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Cancel ticket error [User: {$user_id}]: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in cancel ticket: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}