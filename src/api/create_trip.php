<?php
// src/api/create_trip.php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../scripts/auth_helper.php';

require_login();

// Only company admins can create trips
if (!is_company()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Only company admins can create trips.']);
    exit();
}

header('Content-Type: application/json');

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $user_id = $_SESSION['user_id'];
    
    // Get form data
    $company_id = trim($_POST['company_id'] ?? '');
    $departure_city = trim($_POST['departure_city'] ?? '');
    $destination_city = trim($_POST['destination_city'] ?? '');
    $departure_time = trim($_POST['departure_time'] ?? '');
    $arrival_time = trim($_POST['arrival_time'] ?? '');
    $bus_type = trim($_POST['bus_type'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);

    // Validate required fields
    if (!$company_id || !$departure_city || !$destination_city || 
        !$departure_time || !$arrival_time || !$bus_type || !$capacity || !$price) {
        throw new Exception('All fields are required.');
    }

    // CRITICAL: Verify that the company_id belongs to the logged-in user
    // This prevents a company admin from creating trips for another company
    $user_check = $pdo->prepare("SELECT company_id FROM User WHERE id = ? AND role = 'company'");
    $user_check->execute([$user_id]);
    $user_data = $user_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data || $user_data['company_id'] !== $company_id) {
        throw new Exception('You can only create trips for your own company.');
    }

    // Verify company exists
    $company_check = $pdo->prepare("SELECT id FROM Bus_Company WHERE id = ?");
    $company_check->execute([$company_id]);
    if (!$company_check->fetch()) {
        throw new Exception('Invalid company.');
    }

    // Validate cities are different
    if ($departure_city === $destination_city) {
        throw new Exception('Departure and destination cities must be different.');
    }

    // Validate bus type
    if (!in_array($bus_type, ['2+2', '2+1'])) {
        throw new Exception('Invalid bus type. Must be 2+2 or 2+1.');
    }

    // Validate capacity matches bus type
    if (($bus_type === '2+2' && $capacity !== 40) || ($bus_type === '2+1' && $capacity !== 30)) {
        throw new Exception('Capacity does not match bus type.');
    }

    // Validate capacity range
    if ($capacity < 10 || $capacity > 60) {
        throw new Exception('Capacity must be between 10 and 60.');
    }

    // Validate price
    if ($price <= 0) {
        throw new Exception('Price must be greater than zero.');
    }

    // Validate datetime formats
    $departure_dt = DateTime::createFromFormat('Y-m-d\TH:i', $departure_time);
    $arrival_dt = DateTime::createFromFormat('Y-m-d\TH:i', $arrival_time);
    
    if (!$departure_dt || !$arrival_dt) {
        throw new Exception('Invalid date/time format.');
    }

    // Convert to database format (SQLite datetime)
    $departure_formatted = $departure_dt->format('Y-m-d H:i:s');
    $arrival_formatted = $arrival_dt->format('Y-m-d H:i:s');

    // Validate departure is in the future
    if ($departure_dt <= new DateTime()) {
        throw new Exception('Departure time must be in the future.');
    }

    // Validate arrival is after departure
    if ($arrival_dt <= $departure_dt) {
        throw new Exception('Arrival time must be after departure time.');
    }

    // Validate trip duration is reasonable (at least 30 minutes, max 24 hours)
    $duration = $arrival_dt->getTimestamp() - $departure_dt->getTimestamp();
    if ($duration < 1800) { // 30 minutes
        throw new Exception('Trip duration must be at least 30 minutes.');
    }
    if ($duration > 86400) { // 24 hours
        throw new Exception('Trip duration cannot exceed 24 hours.');
    }

    $pdo->beginTransaction();

    // Generate unique trip ID
    $trip_id = uniqid('trip_', true);

    // Insert trip
    $insert_trip = $pdo->prepare("
        INSERT INTO Trips (
            id, 
            company_id, 
            bus_type, 
            destination_city, 
            arrival_time, 
            departure_time, 
            departure_city, 
            price, 
            capacity,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");

    $insert_trip->execute([
        $trip_id,
        $company_id,
        $bus_type,
        $destination_city,
        $arrival_formatted,
        $departure_formatted,
        $departure_city,
        $price,
        $capacity
    ]);

    $pdo->commit();

    // Calculate trip duration for response
    $hours = floor($duration / 3600);
    $minutes = floor(($duration % 3600) / 60);
    $duration_text = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";

    echo json_encode([
        'success' => true,
        'message' => 'Trip created successfully!',
        'data' => [
            'trip_id' => $trip_id,
            'route' => "{$departure_city} → {$destination_city}",
            'departure' => $departure_dt->format('M d, Y H:i'),
            'arrival' => $arrival_dt->format('M d, Y H:i'),
            'duration' => $duration_text,
            'price' => $price,
            'capacity' => $capacity,
            'bus_type' => $bus_type
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Create trip error [User: {$user_id}]: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in create trip: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}