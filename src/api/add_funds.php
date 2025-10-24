<?php
// src/api/add_funds.php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../scripts/auth_helper.php';

require_login();

// Only regular users can add funds (not admin or company)
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
    
    $amount = floatval($input['amount'] ?? 0);
    $payment_method = $input['payment_method'] ?? '';

    // Validate amount
    if ($amount < 1) {
        throw new Exception('Minimum amount is $1.00');
    }

    if ($amount > 10000) {
        throw new Exception('Maximum amount is $10,000.00');
    }

    // Validate payment method
    $valid_methods = ['credit_card', 'paypal', 'bank_transfer', 'crypto'];
    if (!in_array($payment_method, $valid_methods)) {
        throw new Exception('Invalid payment method.');
    }

    $pdo->beginTransaction();

    // Fetch current user balance
    $user_stmt = $pdo->prepare("SELECT id, balance, full_name FROM User WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found.');
    }

    // Calculate new balance
    $new_balance = $user['balance'] + $amount;

    // Update user balance
    $update_stmt = $pdo->prepare("UPDATE User SET balance = ? WHERE id = ?");
    $update_stmt->execute([$new_balance, $user_id]);


    
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Successfully added $" . number_format($amount, 2) . " to your account!",
        'data' => [
            'amount_added' => $amount,
            'previous_balance' => $user['balance'],
            'new_balance' => $new_balance,
            'payment_method' => $payment_method,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Add funds error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in add funds: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}