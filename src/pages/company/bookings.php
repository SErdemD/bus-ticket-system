<?php
// src/pages/company/bookings.php

require_login();

if (!is_company()) {
    header('Location: /home');
    exit();
}

$user_id = $_SESSION['user_id'];
$company_id = null;
$bookings = [];
$error = null;

try {
    // Get company ID
    $company_stmt = $pdo->prepare("SELECT company_id FROM User WHERE id = ?");
    $company_stmt->execute([$user_id]);
    $company_data = $company_stmt->fetch(PDO::FETCH_ASSOC);
    $company_id = $company_data['company_id'] ?? null;
    
    if (!$company_id) {
        $error = "Company information not found.";
    } else {
        // Get all bookings for this company's trips
        $bookings_stmt = $pdo->prepare("
            SELECT 
                t.id as ticket_id,
                t.status,
                t.total_price,
                t.created_at as booking_date,
                tr.id as trip_id,
                tr.departure_city,
                tr.destination_city,
                tr.departure_time,
                tr.arrival_time,
                tr.bus_type,
                u.full_name as passenger_name,
                u.email as passenger_email,
                GROUP_CONCAT(bs.seat_number, ',') as seats
            FROM Tickets t
            JOIN Trips tr ON t.trip_id = tr.id
            JOIN User u ON t.user_id = u.id
            LEFT JOIN Booked_Seats bs ON bs.ticket_id = t.id
            WHERE tr.company_id = ?
            GROUP BY t.id
            ORDER BY tr.departure_time DESC, t.created_at DESC
        ");
        $bookings_stmt->execute([$company_id]);
        $bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process seats and check cancellation eligibility
        foreach ($bookings as &$booking) {
            $booking['seats'] = $booking['seats'] ? explode(',', $booking['seats']) : [];
            
            // Check if can cancel (within 1 hour of departure)
            $departure_time = strtotime($booking['departure_time']);
            $current_time = time();
            $time_until_departure = $departure_time - $current_time;
            $booking['can_cancel'] = ($booking['status'] === 'ACTIVE' && $time_until_departure >= 3600);
            
            // Mark as expired if departure time has passed
            if ($booking['status'] === 'ACTIVE' && $departure_time < $current_time) {
                $booking['status'] = 'EXPIRED';
            }
        }
    }
    
} catch (PDOException $e) {
    $error = "Error loading bookings.";
    error_log("Company bookings page error: " . $e->getMessage());
}
?>

<!-- Cancel Confirmation Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this booking?</p>
                <div id="cancel-booking-info" class="alert alert-info"></div>
                <p class="text-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i> 
                    The customer will be refunded automatically.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep It</button>
                <button type="button" class="btn btn-danger" id="confirm-cancel-btn">Yes, Cancel Booking</button>
            </div>
        </div>
    </div>
</div>

<!-- Standard Modals -->
<div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
                <h5>Processing...</h5>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                <h4 class="mt-3 text-success">Success!</h4>
                <p id="success-message"></p>
                <button type="button" class="btn btn-success mt-3" data-bs-dismiss="modal" onclick="location.reload()">OK</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <i class="bi bi-x-circle-fill text-danger" style="font-size: 4rem;"></i>
                <h4 class="mt-3 text-danger">Error</h4>
                <p id="error-message"></p>
                <button type="button" class="btn btn-primary mt-3" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<div class="company-bookings-container">
    <div class="page-header mb-4">
        <h2><i class="bi bi-ticket-perforated"></i> Manage Bookings</h2>
        <p class="text-muted mb-0">View and manage customer bookings for your trips</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>
        <!-- Filter Tabs -->
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <button class="nav-link active" data-filter="active" onclick="filterBookings('active')">
                    Active 
                    <span class="badge bg-success ms-1" id="active-count">
                        <?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'ACTIVE')); ?>
                    </span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-filter="cancelled" onclick="filterBookings('cancelled')">
                    Cancelled 
                    <span class="badge bg-danger ms-1" id="cancelled-count">
                        <?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'CANCELLED')); ?>
                    </span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-filter="expired" onclick="filterBookings('expired')">
                    Expired 
                    <span class="badge bg-secondary ms-1" id="expired-count">
                        <?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'EXPIRED')); ?>
                    </span>
                </button>
            </li>
        </ul>

        <!-- Bookings List -->
        <div id="bookings-list">
            <?php if (empty($bookings)): ?>
                <div class="empty-state">
                    <i class="bi bi-ticket-perforated"></i>
                    <h3>No Bookings Yet</h3>
                    <p>Bookings will appear here once customers book your trips</p>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $booking): 
                    $departure = new DateTime($booking['departure_time']);
                    $booking_date = new DateTime($booking['booking_date']);
                ?>
                    <div class="booking-card" data-status="<?php echo strtolower($booking['status']); ?>">
                        <div class="booking-card-header">
                            <div>
                                <h5 class="mb-1">
                                    <i class="bi bi-person-circle"></i>
                                    <?php echo htmlspecialchars($booking['passenger_name']); ?>
                                </h5>
                                <small class="text-muted"><?php echo htmlspecialchars($booking['passenger_email']); ?></small>
                            </div>
                            <span class="badge bg-<?php 
                                echo $booking['status'] === 'ACTIVE' ? 'success' : 
                                    ($booking['status'] === 'CANCELLED' ? 'danger' : 'secondary'); 
                            ?> fs-6">
                                <?php echo $booking['status']; ?>
                            </span>
                        </div>
                        
                        <div class="booking-card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="trip-route-display mb-3">
                                        <div class="route-point">
                                            <i class="bi bi-geo-alt-fill text-primary"></i>
                                            <div>
                                                <strong><?php echo htmlspecialchars($booking['departure_city']); ?></strong>
                                                <div class="text-muted small"><?php echo $departure->format('M d, Y - H:i'); ?></div>
                                            </div>
                                        </div>
                                        <div class="route-arrow">
                                            <i class="bi bi-arrow-right"></i>
                                        </div>
                                        <div class="route-point">
                                            <i class="bi bi-geo-alt-fill text-success"></i>
                                            <div>
                                                <strong><?php echo htmlspecialchars($booking['destination_city']); ?></strong>
                                                <div class="text-muted small">
                                                    <?php echo (new DateTime($booking['arrival_time']))->format('M d, Y - H:i'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="booking-details">
                                        <div class="detail-item">
                                            <i class="bi bi-bus-front"></i>
                                            <span>Bus Type: <strong><?php echo htmlspecialchars($booking['bus_type']); ?></strong></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="bi bi-calendar-check"></i>
                                            <span>Booked: <strong><?php echo $booking_date->format('M d, Y - H:i'); ?></strong></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="bi bi-receipt"></i>
                                            <span>Ticket ID: <strong><?php echo htmlspecialchars($booking['ticket_id']); ?></strong></span>
                                        </div>
                                    </div>
                                    
                                    <div class="seats-display mt-3">
                                        <strong>Seats:</strong>
                                        <?php foreach ($booking['seats'] as $seat): ?>
                                            <span class="seat-badge"><?php echo htmlspecialchars($seat); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 text-end">
                                    <div class="price-display mb-3">
                                        <small class="text-muted">Total Amount</small>
                                        <h3 class="text-success mb-0">$<?php echo number_format($booking['total_price'], 2); ?></h3>
                                    </div>
                                    
                                    <?php if ($booking['can_cancel']): ?>
                                        <button class="btn btn-danger w-100" 
                                                onclick="openCancelModal('<?php echo htmlspecialchars($booking['ticket_id']); ?>', 
                                                                        '<?php echo htmlspecialchars($booking['passenger_name']); ?>', 
                                                                        <?php echo $booking['total_price']; ?>)">
                                            <i class="bi bi-x-circle"></i> Cancel Booking
                                        </button>
                                        <small class="text-muted d-block mt-2">
                                            <i class="bi bi-clock"></i> Can cancel until 1 hour before departure
                                        </small>
                                    <?php elseif ($booking['status'] === 'ACTIVE'): ?>
                                        <div class="alert alert-warning small mb-0">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Cannot cancel within 1 hour of departure
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
let currentFilter = 'active';
let currentTicketToCancel = null;

function filterBookings(filter) {
    currentFilter = filter;
    
    // Update active button
    document.querySelectorAll('[data-filter]').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
    
    // Filter cards
    document.querySelectorAll('.booking-card').forEach(card => {
        const status = card.getAttribute('data-status');
        if (filter === 'all' || status === filter) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function openCancelModal(ticketId, passengerName, amount) {
    currentTicketToCancel = ticketId;
    document.getElementById('cancel-booking-info').innerHTML = `
        <strong>Passenger:</strong> ${passengerName}<br>
        <strong>Refund Amount:</strong> $${amount.toFixed(2)}
    `;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}

document.getElementById('confirm-cancel-btn')?.addEventListener('click', async function() {
    if (!currentTicketToCancel) return;
    
    const cancelModal = bootstrap.Modal.getInstance(document.getElementById('cancelModal'));
    cancelModal.hide();
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/company/cancel_booking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_id: currentTicketToCancel })
        });
        
        const data = await response.json();
        loadingModal.hide();
        
        if (data.success) {
            document.getElementById('success-message').textContent = data.message;
            new bootstrap.Modal(document.getElementById('successModal')).show();
        } else {
            document.getElementById('error-message').textContent = data.message;
            new bootstrap.Modal(document.getElementById('errorModal')).show();
        }
    } catch (error) {
        loadingModal.hide();
        console.error('Error:', error);
        document.getElementById('error-message').textContent = 'Network error occurred.';
        new bootstrap.Modal(document.getElementById('errorModal')).show();
    }
});

// Initialize - show active bookings
filterBookings('active');
</script>

<style>
.company-bookings-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header h2 {
    color: #e2e8f0;
    margin: 0;
}

.page-header h2 i {
    color: #3b82f6;
    margin-right: 10px;
}

.nav-pills .nav-link {
    color: #a0aec0;
    background: #2d3748;
    border: 2px solid #4a5568;
    margin-right: 10px;
    transition: all 0.3s;
}

.nav-pills .nav-link:hover {
    background: #374151;
    color: #e2e8f0;
}

.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    color: white;
}

.booking-card {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 15px;
    margin-bottom: 20px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.booking-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    border-color: #667eea;
}

.booking-card-header {
    background: #374151;
    padding: 20px;
    border-bottom: 2px solid #4a5568;
    border-radius: 13px 13px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.booking-card-header h5 {
    color: #e2e8f0;
    margin: 0;
}

.booking-card-header i {
    color: #60a5fa;
    margin-right: 8px;
}

.booking-card-body {
    padding: 25px;
}

.trip-route-display {
    display: flex;
    align-items: center;
    gap: 20px;
    background: #1a202c;
    padding: 15px;
    border-radius: 10px;
}

.route-point {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.route-point i {
    font-size: 1.5rem;
}

.route-point strong {
    color: #e2e8f0;
    display: block;
}

.route-arrow {
    color: #4a5568;
    font-size: 1.5rem;
}

.booking-details {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 15px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #a0aec0;
}

.detail-item i {
    color: #60a5fa;
}

.detail-item strong {
    color: #e2e8f0;
}

.seats-display {
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

.price-display h3 {
    color: #34d399;
    font-weight: bold;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #a0aec0;
}

.empty-state i {
    font-size: 5rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    color: #e2e8f0;
}

.modal-content {
    background: #2d3748;
    border: 2px solid #4a5568;
    color: #e2e8f0;
}

.modal-header {
    border-bottom: 2px solid #4a5568;
}

.modal-footer {
    border-top: 2px solid #4a5568;
}

.btn-close {
    filter: invert(1);
}

@media (max-width: 768px) {
    .trip-route-display {
        flex-direction: column;
        gap: 10px;
    }
    
    .route-arrow {
        transform: rotate(90deg);
    }
    
    .booking-details {
        flex-direction: column;
        gap: 10px;
    }
}
</style>