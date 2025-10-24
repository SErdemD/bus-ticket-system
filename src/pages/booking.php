<?php
// src/pages/booking.php

require_login();
// Check if user is admin or company admin
if (is_admin() || is_company()){
    header('Location: /home');
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_data = null;
$error = null;
$trips_info = [];

// Get booking data from POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_data'])) {
    $booking_data = json_decode($_POST['booking_data'], true);
    
    if (!$booking_data) {
        $error = "Invalid booking data.";
    } else {
        try {
            // Fetch user info
            $user_stmt = $pdo->prepare("SELECT full_name, email, balance FROM User WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

            if ($booking_data['trip_type'] === 'one_way') {
                // Fetch trip details
                $trip_stmt = $pdo->prepare("
                    SELECT t.id, t.company_id, t.bus_type, t.destination_city, t.arrival_time, 
                           t.departure_time, t.departure_city, t.price, t.capacity, t.created_at,
                           bc.name as company_name, bc.logo_path as company_logo
                    FROM Trips t
                    JOIN Bus_Company bc ON t.company_id = bc.id
                    WHERE t.id = ?
                ");
                $trip_stmt->execute([$booking_data['trip_id']]);
                $trip = $trip_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$trip) {
                    $error = "Trip not found.";
                } else {
                    // Check if seats are still available
                    $seats_check_stmt = $pdo->prepare("
                        SELECT bs.seat_number
                        FROM Booked_Seats bs
                        JOIN Tickets t ON bs.ticket_id = t.id
                        WHERE t.trip_id = ? AND t.status = 'ACTIVE'
                    ");
                    $seats_check_stmt->execute([$booking_data['trip_id']]);
                    $booked_seats = $seats_check_stmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($booking_data['seats'] as $seat) {
                        if (in_array($seat, $booked_seats)) {
                            $error = "Seat {$seat} is no longer available.";
                            break;
                        }
                    }

                    if (!$error) {
                        $trips_info[] = [
                            'type' => 'departure',
                            'trip' => $trip,
                            'seats' => $booking_data['seats'],
                            'total_price' => $trip['price'] * count($booking_data['seats'])
                        ];
                    }
                }

            } else if ($booking_data['trip_type'] === 'round') {
                // Fetch both trip details
                $trip_stmt = $pdo->prepare("
                    SELECT t.id, t.company_id, t.bus_type, t.destination_city, t.arrival_time, 
                           t.departure_time, t.departure_city, t.price, t.capacity, t.created_at,
                           bc.name as company_name, bc.logo_path as company_logo
                    FROM Trips t
                    JOIN Bus_Company bc ON t.company_id = bc.id
                    WHERE t.id = ?
                ");

                // Departure trip
                $trip_stmt->execute([$booking_data['departure_trip']]);
                $departure_trip = $trip_stmt->fetch(PDO::FETCH_ASSOC);

                // Return trip
                $trip_stmt->execute([$booking_data['return_trip']]);
                $return_trip = $trip_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$departure_trip || !$return_trip) {
                    $error = "One or more trips not found.";
                } else {
                    // Check departure seats
                    $seats_check_stmt = $pdo->prepare("
                        SELECT bs.seat_number
                        FROM Booked_Seats bs
                        JOIN Tickets t ON bs.ticket_id = t.id
                        WHERE t.trip_id = ? AND t.status = 'ACTIVE'
                    ");
                    $seats_check_stmt->execute([$booking_data['departure_trip']]);
                    $booked_dep_seats = $seats_check_stmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($booking_data['departure_seats'] as $seat) {
                        if (in_array($seat, $booked_dep_seats)) {
                            $error = "Departure seat {$seat} is no longer available.";
                            break;
                        }
                    }

                    // Check return seats
                    if (!$error) {
                        $seats_check_stmt->execute([$booking_data['return_trip']]);
                        $booked_ret_seats = $seats_check_stmt->fetchAll(PDO::FETCH_COLUMN);

                        foreach ($booking_data['return_seats'] as $seat) {
                            if (in_array($seat, $booked_ret_seats)) {
                                $error = "Return seat {$seat} is no longer available.";
                                break;
                            }
                        }
                    }

                    if (!$error) {
                        $trips_info[] = [
                            'type' => 'departure',
                            'trip' => $departure_trip,
                            'seats' => $booking_data['departure_seats'],
                            'total_price' => $departure_trip['price'] * count($booking_data['departure_seats'])
                        ];
                        $trips_info[] = [
                            'type' => 'return',
                            'trip' => $return_trip,
                            'seats' => $booking_data['return_seats'],
                            'total_price' => $return_trip['price'] * count($booking_data['return_seats'])
                        ];
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error occurred.";
            error_log("Booking page error: " . $e->getMessage());
        }
    }
} else {
    $error = "No booking data provided.";
}

// Calculate total
$grand_total = 0;
foreach ($trips_info as $info) {
    $grand_total += $info['total_price'];
}
?>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 4rem; height: 4rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2">Processing Your Booking</h5>
                <p class="text-muted mb-0">Please wait while we confirm your reservation...</p>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="success-icon mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                </div>
                <h4 class="mb-3 text-success">Booking Successful!</h4>
                <p class="mb-2" id="success-message">Your tickets have been confirmed.</p>
                <div class="alert alert-info mt-3" id="booking-details"></div>
                <button type="button" class="btn btn-success w-100 mt-3" onclick="window.location.href='/my-tickets'">
                    View My Tickets
                </button>
                <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="window.location.href='/home'">
                    Back to Home
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="error-icon mb-3">
                    <i class="bi bi-x-circle-fill text-danger" style="font-size: 5rem;"></i>
                </div>
                <h4 class="mb-3 text-danger">Booking Failed</h4>
                <p class="mb-0" id="error-message">An error occurred while processing your booking.</p>
                <button type="button" class="btn btn-primary w-100 mt-4" data-bs-dismiss="modal">
                    Try Again
                </button>
            </div>
        </div>
    </div>
</div>

<div class="booking-container">
    <h2 class="mb-4">Complete Your Booking</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <a href="/home" class="btn btn-primary">Back to Search</a>
    <?php else: ?>
        <div class="row">
            <!-- Left Column - Trip Details -->
            <div class="col-lg-7">
                <?php foreach ($trips_info as $info): 
                    $trip = $info['trip'];
                    $departure = new DateTime($trip['departure_time']);
                    $arrival = new DateTime($trip['arrival_time']);
                ?>
                    <div class="trip-card <?php echo $info['type']; ?>">
                        <div class="d-flex align-items-center mb-3">
                            <img src="<?php echo htmlspecialchars($trip['company_logo'] ?? '/assets/images/default-logo.png'); ?>" 
                                 alt="<?php echo htmlspecialchars($trip['company_name']); ?>" 
                                 style="width: 50px; height: 50px; margin-right: 15px; object-fit: contain;">
                            <div>
                                <h5 class="mb-0"><?php echo htmlspecialchars($trip['company_name']); ?></h5>
                                <span class="badge bg-<?php echo $info['type'] === 'departure' ? 'primary' : 'secondary'; ?>">
                                    <?php echo ucfirst($info['type']); ?> Trip
                                </span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">From</small>
                                <div><strong><?php echo htmlspecialchars($trip['departure_city']); ?></strong></div>
                                <div class="text-primary"><?php echo $departure->format('M d, Y - H:i'); ?></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">To</small>
                                <div><strong><?php echo htmlspecialchars($trip['destination_city']); ?></strong></div>
                                <div class="text-primary"><?php echo $arrival->format('M d, Y - H:i'); ?></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted">Selected Seats</small>
                            <div>
                                <?php foreach ($info['seats'] as $seat): ?>
                                    <span class="seat-badge">Seat <?php echo $seat; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="price-breakdown">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Price per seat:</span>
                                <strong>$<?php echo number_format($trip['price'], 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Number of seats:</span>
                                <strong><?php echo count($info['seats']); ?></strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Subtotal:</strong>
                                <strong class="text-primary fs-5">$<?php echo number_format($info['total_price'], 2); ?></strong>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Right Column - Summary & Payment -->
            <div class="col-lg-5">
                <div class="summary-card">
                    <h5 class="mb-3">Booking Summary</h5>

                    <div class="mb-3">
                        <small class="text-muted">Passenger</small>
                        <div><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <?php foreach ($trips_info as $info): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo ucfirst($info['type']); ?> (<?php echo count($info['seats']); ?> seats)</span>
                                <span>$<?php echo number_format($info['total_price'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="coupon-applied-display" style="display: none;">
                        <div class="coupon-applied">
                            <div>
                                <span>Coupon: <strong id="applied-coupon-code"></strong></span>
                                <div class="coupon-discount" id="applied-coupon-discount"></div>
                            </div>
                            <button type="button" onclick="removeCoupon()" title="Remove coupon">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-1">
                        <span>Subtotal:</span>
                        <span id="subtotal-amount">$<?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3" id="discount-row" style="display: none;">
                        <span class="text-success">Discount:</span>
                        <span class="text-success" id="discount-amount">-$0.00</span>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <strong class="fs-5">Total Amount:</strong>
                        <strong class="text-primary fs-4" id="final-total">$<?php echo number_format($grand_total, 2); ?></strong>
                    </div>

                    <!-- Coupon Section -->
                    <div class="coupon-section" id="coupon-input-section">
                        <label for="coupon-code">Have a coupon code?</label>
                        <div class="coupon-input-group">
                            <input type="text" 
                                   id="coupon-code" 
                                   placeholder="Enter coupon code" 
                                   maxlength="20">
                            <button type="button" 
                                    class="btn btn-primary" 
                                    onclick="applyCoupon()"
                                    id="apply-coupon-btn">
                                Apply
                            </button>
                        </div>
                        <div id="coupon-message"></div>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted">Your Balance</small>
                        <div class="fs-5 <?php echo $user['balance'] >= $grand_total ? 'text-success' : 'text-danger'; ?>" id="balance-status">
                            $<?php echo number_format($user['balance'], 2); ?>
                        </div>
                    </div>

                    <div id="insufficient-balance-msg" style="display: <?php echo $user['balance'] < $grand_total ? 'block' : 'none'; ?>;">
                        <div class="insufficient-balance">
                            <strong>⚠️ Insufficient Balance</strong>
                            <p class="mb-0 small">You need $<span id="needed-amount"><?php echo number_format($grand_total - $user['balance'], 2); ?></span> more to complete this booking.</p>
                        </div>
                    </div>

                    <form method="POST" action="/api/process_booking.php" id="booking-form">
                        <input type="hidden" name="booking_data" value='<?php echo json_encode($booking_data, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                        <input type="hidden" name="coupon_code" id="coupon-code-input" value="">
                        <input type="hidden" name="final_amount" id="final-amount-input" value="<?php echo $grand_total; ?>">
                        <button type="submit" 
                                class="btn btn-success w-100 btn-lg mb-2" 
                                id="confirm-booking"
                                <?php echo $user['balance'] < $grand_total ? 'disabled' : ''; ?>>
                            <i class="bi bi-check-circle"></i> Confirm Booking
                        </button>
                    </form>

                    <a href="/home" class="btn btn-outline-secondary w-100">Cancel</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
// Fixed JavaScript for booking.php
const originalTotal = <?php echo json_encode($grand_total); ?>;
const userBalance = <?php echo json_encode($user['balance']); ?>;
const bookingData = <?php echo json_encode($booking_data); ?>;
let appliedCoupon = null;

// Handle form submission
document.getElementById('booking-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();

    const formData = new FormData(this);

    try {
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        loadingModal.hide();

        if (data?.success) {
            document.getElementById('success-message').textContent = data.message || 'Booking successful';

            let detailsHTML = `<strong>Booking Details:</strong><br>
                               Amount Paid: $${(data.data?.amount_paid || 0).toFixed(2)}<br>
                               New Balance: $${(data.data?.new_balance || 0).toFixed(2)}`;

            if (data.data?.coupon_used) {
                detailsHTML += `<br>Coupon Applied: ${data.data.coupon_used}<br>
                                Discount: $${(data.data?.discount_applied || 0).toFixed(2)}`;
            }

            document.getElementById('booking-details').innerHTML = detailsHTML;
            new bootstrap.Modal(document.getElementById('successModal')).show();
        } else {
            document.getElementById('error-message').textContent = data?.message || 'An unexpected error occurred.';
            new bootstrap.Modal(document.getElementById('errorModal')).show();
        }
    } catch (err) {
        loadingModal.hide();
        console.error('Booking error:', err);
        document.getElementById('error-message').textContent = 'Network error. Please check your connection and try again.';
        new bootstrap.Modal(document.getElementById('errorModal')).show();
    }
});

// Apply coupon
async function applyCoupon() {
    const couponCode = document.getElementById('coupon-code')?.value.trim();
    const applyBtn = document.getElementById('apply-coupon-btn');

    if (!couponCode) {
        showCouponMessage('Please enter a coupon code', 'error');
        return;
    }

    applyBtn.disabled = true;
    applyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking...';

    try {
        const response = await fetch('/api/validate_coupon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_data: bookingData,
                coupon_code: couponCode,
                total_amount: originalTotal
            })
        });

        const data = await response.json();

        if (data?.success) {
            appliedCoupon = {
                code: couponCode,
                discount_percentage: data.discount_percentage || 0,
                discount_amount: data.discount_amount || 0,
                discount_type: 'percentage'
            };

            updatePricing();
            showCouponApplied();
            showCouponMessage(data.message || 'Coupon applied successfully', 'success');
        } else {
            showCouponMessage(data?.message || 'Invalid coupon code', 'error');
        }
    } catch (err) {
        console.error('Coupon error:', err);
        showCouponMessage('Error validating coupon. Please try again.', 'error');
    } finally {
        applyBtn.disabled = false;
        applyBtn.innerHTML = 'Apply';
    }
}

// Remove applied coupon
function removeCoupon() {
    appliedCoupon = null;
    updatePricing();
    document.getElementById('coupon-applied-display').style.display = 'none';
    document.getElementById('coupon-input-section').style.display = 'block';
    document.getElementById('coupon-code').value = '';
    document.getElementById('coupon-message').style.display = 'none';
}

// Show coupon applied
function showCouponApplied() {
    if (!appliedCoupon) return;

    const display = document.getElementById('coupon-applied-display');
    const inputSection = document.getElementById('coupon-input-section');

    document.getElementById('applied-coupon-code').textContent = appliedCoupon.code;
    document.getElementById('applied-coupon-discount').textContent = `-${appliedCoupon.discount_percentage}%`;

    display.style.display = 'block';
    inputSection.style.display = 'none';
}

// Update pricing & balance
function updatePricing() {
    let finalTotal = originalTotal;
    let discountAmount = 0;

    if (appliedCoupon) {
        discountAmount = appliedCoupon.discount_amount;
        finalTotal = Math.max(0, originalTotal - discountAmount);
        
        document.getElementById('discount-row').style.display = 'flex';
        document.getElementById('discount-amount').textContent = `-${discountAmount.toFixed(2)}`;
        document.getElementById('coupon-code-input').value = appliedCoupon.code;
    } else {
        document.getElementById('discount-row').style.display = 'none';
        document.getElementById('discount-amount').textContent = '-$0.00';
        document.getElementById('coupon-code-input').value = '';
    }

    document.getElementById('final-total').textContent = `${finalTotal.toFixed(2)}`;

    const balanceStatus = document.getElementById('balance-status');
    const confirmBtn = document.getElementById('confirm-booking');
    const insufficientMsg = document.getElementById('insufficient-balance-msg');

    if (userBalance >= finalTotal) {
        balanceStatus.className = 'fs-5 text-success';
        confirmBtn.disabled = false;
        insufficientMsg.style.display = 'none';
    } else {
        balanceStatus.className = 'fs-5 text-danger';
        confirmBtn.disabled = true;
        insufficientMsg.style.display = 'block';
        document.getElementById('needed-amount').textContent = (finalTotal - userBalance).toFixed(2);
    }
}

// Show coupon message
function showCouponMessage(message, type) {
    const messageDiv = document.getElementById('coupon-message');
    if (!messageDiv) return;
    messageDiv.textContent = message;
    messageDiv.className = type;
    messageDiv.style.display = 'block';
}

// Enter key applies coupon
document.getElementById('coupon-code')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        applyCoupon();
    }
});
</script>


<style>
.booking-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.trip-card {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    color: #e2e8f0;
}

.trip-card.departure {
    border-left: 5px solid #3b82f6;
}

.trip-card.return {
    border-left: 5px solid #8b5cf6;
}

.trip-card small.text-muted {
    color: #a0aec0 !important;
    font-size: 0.875rem;
}

.trip-card .text-primary {
    color: #60a5fa !important;
}

.trip-card h5, .trip-card strong, .trip-card div {
    color: #e2e8f0;
}

.seat-badge {
    display: inline-block;
    background: #1e3a8a;
    color: #93c5fd;
    padding: 5px 12px;
    border-radius: 15px;
    margin: 3px;
    font-weight: 500;
    font-size: 0.875rem;
}

.price-breakdown {
    background: #1a202c;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.price-breakdown span, .price-breakdown strong {
    color: #e2e8f0;
}

.price-breakdown hr {
    margin: 10px 0;
    border-color: #4a5568;
}

.price-breakdown .text-primary {
    color: #60a5fa !important;
}

.summary-card {
    background: #2d3748;
    border: 2px solid #3b82f6;
    border-radius: 10px;
    padding: 20px;
    position: sticky;
    top: 20px;
    color: #e2e8f0;
}

.summary-card h5 {
    color: #e2e8f0;
}

.summary-card small.text-muted {
    color: #a0aec0 !important;
}

.summary-card .text-muted {
    color: #a0aec0 !important;
}

.summary-card hr {
    margin: 15px 0;
    border-color: #4a5568;
}

.summary-card .text-success {
    color: #34d399 !important;
}

.summary-card .text-danger {
    color: #f87171 !important;
}

.summary-card strong, .summary-card div, .summary-card span {
    color: #e2e8f0;
}

.insufficient-balance {
    background: #78350f;
    border: 2px solid #fbbf24;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    color: #fde68a;
}

.insufficient-balance strong {
    color: #fde68a;
}

.btn-outline-secondary {
    color: #e2e8f0;
    border-color: #4a5568;
}

.btn-outline-secondary:hover {
    background-color: #4a5568;
    color: #fff;
}

.coupon-section {
    background: #1a202c;
    border: 2px solid #4a5568;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.coupon-section label {
    color: #e2e8f0;
    font-weight: 500;
    margin-bottom: 8px;
}

.coupon-input-group {
    display: flex;
    align-items: center;
    gap: 6px;
}

.coupon-input-group input {
    flex: 1;
    height: 34px;
    padding: 6px 10px;
    font-size: 14px;
    border-radius: 5px;
    border: 1px solid #4a5568;
    background: #2d3748;
    color: #e2e8f0;
}

.coupon-input-group button {
    height: 34px;
    padding: 0 12px;
    font-size: 14px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.coupon-input-group input:focus {
    outline: none;
    border-color: #3b82f6;
    background: #374151;
}

.coupon-input-group input::placeholder {
    color: #6b7280;
}

.coupon-applied {
    background: #065f46;
    border: 2px solid #10b981;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.coupon-applied span {
    color: #d1fae5;
    font-weight: 500;
}

.coupon-applied .coupon-discount {
    color: #6ee7b7;
    font-size: 1.1rem;
    font-weight: bold;
}

.coupon-applied button {
    background: transparent;
    border: none;
    color: #fca5a5;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.2s;
}

.coupon-applied button:hover {
    background: rgba(239, 68, 68, 0.1);
}

#coupon-message {
    margin-top: 8px;
    font-size: 0.875rem;
    padding: 8px;
    border-radius: 4px;
    display: none;
}

#coupon-message.success {
    display: block;
    background: #065f46;
    color: #d1fae5;
    border: 1px solid #10b981;
}

#coupon-message.error {
    display: block;
    background: #7f1d1d;
    color: #fecaca;
    border: 1px solid #ef4444;
}

/* Modal Styles */
.modal-content {
    background: #2d3748;
    color: #e2e8f0;
    border: 2px solid #4a5568;
}

.modal-body {
    background: #2d3748;
}

.success-icon, .error-icon {
    animation: scaleIn 0.3s ease-out;
}

@keyframes scaleIn {
    from {
        transform: scale(0);
    }
    to {
        transform: scale(1);
    }
}

.spinner-border {
    animation: spinner-border 0.75s linear infinite;
}

@keyframes spinner-border {
    to {
        transform: rotate(360deg);
    }
}
</style>