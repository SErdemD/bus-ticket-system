<?php
// src/api/company/get_trip.php
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
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

    $trip_id = $_GET['id'] ?? '';

    if (!$trip_id) {
        throw new Exception('Trip ID is required.');
    }

    // Fetch trip
    $stmt = $pdo->prepare("
        SELECT id, departure_city, destination_city, departure_time, arrival_time,
               bus_type, capacity, price, company_id
        FROM Trips 
        WHERE id = ?
    ");
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        throw new Exception('Trip not found.');
    }

    // Verify trip belongs to this company
    if ($trip['company_id'] !== $user_company_id) {
        throw new Exception('You can only view your own company trips.');
    }

    // Format times for datetime-local input (remove seconds)
    if ($trip['departure_time']) {
        $dt = new DateTime($trip['departure_time']);
        $trip['departure_time'] = $dt->format('Y-m-d\TH:i');
    }
    
    if ($trip['arrival_time']) {
        $dt = new DateTime($trip['arrival_time']);
        $trip['arrival_time'] = $dt->format('Y-m-d\TH:i');
    }

    echo json_encode([
        'success' => true,
        'trip' => $trip
    ]);

} catch (Exception $e) {
    error_log("Get trip error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get trip: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}