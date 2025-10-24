<?php
// src/api/company/manage_trips.php
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../scripts/auth_helper.php';
require_once __DIR__ . '/../../scripts/uuid_create.php';

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

    // Determine action
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        // Handle delete
        $trip_id = $_POST['trip_id'] ?? '';

        if (!$trip_id) {
            throw new Exception('Trip ID is required.');
        }

        $pdo->beginTransaction();

        // Verify trip belongs to this company
        $trip_check = $pdo->prepare("SELECT id, company_id FROM Trips WHERE id = ?");
        $trip_check->execute([$trip_id]);
        $trip = $trip_check->fetch(PDO::FETCH_ASSOC);

        if (!$trip) {
            throw new Exception('Trip not found.');
        }

        if ($trip['company_id'] !== $user_company_id) {
            throw new Exception('You can only delete your own company trips.');
        }

        // Check if trip has active bookings
        $bookings_check = $pdo->prepare("SELECT COUNT(*) FROM Tickets WHERE trip_id = ? AND status = 'ACTIVE'");
        $bookings_check->execute([$trip_id]);
        $active_bookings = $bookings_check->fetchColumn();

        if ($active_bookings > 0) {
            // Get tickets to refund
            $tickets_stmt = $pdo->prepare("
                SELECT id, user_id, total_price 
                FROM Tickets 
                WHERE trip_id = ? AND status = 'ACTIVE'
            ");
            $tickets_stmt->execute([$trip_id]);
            $tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cancel all active bookings
            $cancel_stmt = $pdo->prepare("UPDATE Tickets SET status = 'CANCELLED' WHERE trip_id = ? AND status = 'ACTIVE'");
            $cancel_stmt->execute([$trip_id]);

            // Refund users
            foreach ($tickets as $ticket) {
                $refund_stmt = $pdo->prepare("UPDATE User SET balance = balance + ? WHERE id = ?");
                $refund_stmt->execute([$ticket['total_price'], $ticket['user_id']]);
            }
        }

        // Delete trip
        $delete_stmt = $pdo->prepare("DELETE FROM Trips WHERE id = ?");
        $delete_stmt->execute([$trip_id]);

        $pdo->commit();

        $message = $active_bookings > 0 
            ? "Trip deleted successfully. {$active_bookings} booking(s) were cancelled and refunded."
            : "Trip deleted successfully.";

        echo json_encode([
            'success' => true,
            'message' => $message
        ]);

    } else {
        // Handle create/edit
        $trip_id = $_POST['trip_id'] ?? '';
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

        // Verify company_id matches user's company
        if ($company_id !== $user_company_id) {
            throw new Exception('You can only manage your own company trips.');
        }

        // Validate cities are different
        if ($departure_city === $destination_city) {
            throw new Exception('Departure and destination cities must be different.');
        }

        // Validate bus type and capacity
        if (!in_array($bus_type, ['2+2', '2+1'])) {
            throw new Exception('Invalid bus type.');
        }

        if (($bus_type === '2+2' && $capacity != 40) || ($bus_type === '2+1' && $capacity != 30)) {
            throw new Exception('Capacity does not match bus type.');
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

        // Validate trip duration
        $duration = $arrival_dt->getTimestamp() - $departure_dt->getTimestamp();
        if ($duration < 1800) {
            throw new Exception('Trip duration must be at least 30 minutes.');
        }
        if ($duration > 86400) {
            throw new Exception('Trip duration cannot exceed 24 hours.');
        }

        $pdo->beginTransaction();

        if ($action === 'edit' && $trip_id) {
            // Edit existing trip
            $check_stmt = $pdo->prepare("SELECT id, company_id, capacity FROM Trips WHERE id = ?");
            $check_stmt->execute([$trip_id]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                throw new Exception('Trip not found.');
            }

            if ($existing['company_id'] !== $user_company_id) {
                throw new Exception('You can only edit your own company trips.');
            }

            // Check if trip has active bookings
            $bookings_check = $pdo->prepare("SELECT COUNT(*) FROM Tickets WHERE trip_id = ? AND status = 'ACTIVE'");
            $bookings_check->execute([$trip_id]);
            $has_bookings = $bookings_check->fetchColumn() > 0;

            if ($has_bookings && $capacity < $existing['capacity']) {
                throw new Exception('Cannot decrease capacity for trips with active bookings.');
            }

            // Update trip
            $update_stmt = $pdo->prepare("
                UPDATE Trips 
                SET departure_city = ?,
                    destination_city = ?,
                    departure_time = ?,
                    arrival_time = ?,
                    bus_type = ?,
                    capacity = ?,
                    price = ?
                WHERE id = ?
            ");
            $update_stmt->execute([
                $departure_city,
                $destination_city,
                $departure_formatted,
                $arrival_formatted,
                $bus_type,
                $capacity,
                $price,
                $trip_id
            ]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Trip updated successfully!'
            ]);

        } else {
            // Create new trip
            $new_trip_id = uuidv4();
            $created_at = date('Y-m-d H:i:s');

            $insert_stmt = $pdo->prepare("
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
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_stmt->execute([
                $new_trip_id,
                $company_id,
                $bus_type,
                $destination_city,
                $arrival_formatted,
                $departure_formatted,
                $departure_city,
                $price,
                $capacity,
                $created_at
            ]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Trip created successfully!',
                'data' => [
                    'trip_id' => $new_trip_id
                ]
            ]);
        }
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Company manage trips error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in company manage trips: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}