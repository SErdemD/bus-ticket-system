<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../db/db.php';

$trip_id = $_GET['trip_id'] ?? null;

if (!$trip_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Trip ID is required.']);
    exit();
}

// Validate that trip_id is a string (your IDs are TEXT/UUID)
if (empty(trim($trip_id))) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Trip ID.']);
    exit();
}

try {
    // 1. Get trip details (bus_type and capacity)
    $trip_stmt = $pdo->prepare("SELECT bus_type, capacity FROM Trips WHERE id = ?");
    $trip_stmt->execute([$trip_id]);
    $trip = $trip_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        http_response_code(404);
        echo json_encode(['error' => 'Trip not found.']);
        exit();
    }

    // 2. Get booked seats and passenger gender
    // FIXED: Join properly through Tickets table to get the User who booked
    $seats_sql = "
        SELECT 
            bs.seat_number,
            u.gender
        FROM Booked_Seats bs
        INNER JOIN Tickets t ON bs.ticket_id = t.id
        INNER JOIN User u ON t.user_id = u.id
        WHERE t.trip_id = ? AND t.status = 'ACTIVE'
        ORDER BY bs.seat_number ASC
    ";
    $seats_stmt = $pdo->prepare($seats_sql);
    $seats_stmt->execute([$trip_id]);
    $booked_seats = $seats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine all data into a single response
    $response = [
        'bus_type' => $trip['bus_type'],
        'capacity' => (int)$trip['capacity'],
        'booked_seats' => $booked_seats
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    // Log the error for debugging
    error_log("Database error in get_seats.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred.']);
}