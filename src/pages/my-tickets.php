<?php
// src/pages/my-tickets.php

require_login();

// Check if user is admin or company admin
if (is_admin() || is_company()){
    header('Location: /home');
    exit();
}

$user_id = $_SESSION['user_id'];
$tickets = [];
$error = null;

try {
    // Fetch all user tickets with trip and company details
    $tickets_stmt = $pdo->prepare("
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
            bc.name as company_name,
            bc.logo_path as company_logo,
            GROUP_CONCAT(bs.seat_number, ',') as seats
        FROM Tickets t
        JOIN Trips tr ON t.trip_id = tr.id
        JOIN Bus_Company bc ON tr.company_id = bc.id
        LEFT JOIN Booked_Seats bs ON bs.ticket_id = t.id
        WHERE t.user_id = ?
        GROUP BY t.id
        ORDER BY tr.departure_time DESC
    ");
    $tickets_stmt->execute([$user_id]);
    $tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process seats from comma-separated string to array
    foreach ($tickets as &$ticket) {
        $ticket['seats'] = $ticket['seats'] ? explode(',', $ticket['seats']) : [];
        sort($ticket['seats'], SORT_NUMERIC);
    }

} catch (PDOException $e) {
    $error = "Error loading your tickets.";
    error_log("My Tickets page error: " . $e->getMessage());
}

// Separate tickets by status
$active_tickets = array_filter($tickets, function($t) { 
    return $t['status'] === 'ACTIVE' && strtotime($t['departure_time']) > time(); 
});
$past_tickets = array_filter($tickets, function($t) { 
    return $t['status'] === 'ACTIVE' && strtotime($t['departure_time']) <= time(); 
});
$cancelled_tickets = array_filter($tickets, function($t) { 
    return $t['status'] === 'CANCELLED'; 
});
?>

<!-- Cancel Confirmation Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this ticket?</p>
                <p class="text-warning mb-0">
                    <i class="bi bi-exclamation-triangle"></i> 
                    Refund will be processed to your account balance.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Ticket</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">Cancel Ticket</button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-0">Processing...</h5>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="success-icon mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                </div>
                <h4 class="mb-3 text-success">Success!</h4>
                <p class="mb-0" id="success-message"></p>
                <button type="button" class="btn btn-success w-100 mt-4" data-bs-dismiss="modal" onclick="location.reload()">
                    OK
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
                    <i class="bi bi-x-circle-fill text-danger" style="font-size: 4rem;"></i>
                </div>
                <h4 class="mb-3 text-danger">Error</h4>
                <p class="mb-0" id="error-message"></p>
                <button type="button" class="btn btn-primary w-100 mt-4" data-bs-dismiss="modal">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<div class="tickets-container">
    <div class="page-header mb-4">
        <h2><i class="bi bi-ticket-perforated"></i> My Tickets</h2>
        <a href="/home" class="btn btn-primary">
            <i class="bi bi-search"></i> Book New Trip
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif (empty($tickets)): ?>
        <div class="empty-state">
            <i class="bi bi-ticket-perforated"></i>
            <h3>No Tickets Yet</h3>
            <p>You haven't booked any trips yet. Start exploring destinations!</p>
            <a href="/home" class="btn btn-primary btn-lg mt-3">
                <i class="bi bi-search"></i> Search Trips
            </a>
        </div>
    <?php else: ?>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button">
                    Upcoming <span class="badge bg-primary"><?php echo count($active_tickets); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button">
                    Past <span class="badge bg-secondary"><?php echo count($past_tickets); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" data-bs-target="#cancelled" type="button">
                    Cancelled <span class="badge bg-danger"><?php echo count($cancelled_tickets); ?></span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Active Tickets -->
            <div class="tab-pane fade show active" id="active" role="tabpanel">
                <?php if (empty($active_tickets)): ?>
                    <div class="empty-tab-state">
                        <i class="bi bi-calendar-check"></i>
                        <p>No upcoming trips</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($active_tickets as $ticket): 
                        $departure = new DateTime($ticket['departure_time']);
                        $arrival = new DateTime($ticket['arrival_time']);
                        $booking_date = new DateTime($ticket['booking_date']);
                        $is_cancellable = strtotime($ticket['departure_time']) > (time() + 3600); // 1 hour before
                    ?>
                        <div class="ticket-card active">
                            <div class="ticket-header">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($ticket['company_logo'] ?? '/assets/images/default-logo.png'); ?>" 
                                         alt="<?php echo htmlspecialchars($ticket['company_name']); ?>" 
                                         class="company-logo">
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($ticket['company_name']); ?></h5>
                                        <small class="text-muted">Ticket ID: <?php echo htmlspecialchars(substr($ticket['ticket_id'], 0, 12)); ?></small>
                                    </div>
                                </div>
                                <span class="status-badge status-active">
                                    <i class="bi bi-check-circle-fill"></i> Active
                                </span>
                            </div>

                            <div class="ticket-body">
                                <div class="row mb-3">
                                    <div class="col-md-5">
                                        <div class="trip-point">
                                            <small class="text-muted">From</small>
                                            <h4><?php echo htmlspecialchars($ticket['departure_city']); ?></h4>
                                            <div class="trip-time">
                                                <i class="bi bi-calendar3"></i>
                                                <?php echo $departure->format('M d, Y'); ?>
                                            </div>
                                            <div class="trip-time">
                                                <i class="bi bi-clock"></i>
                                                <?php echo $departure->format('H:i'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
                                        <i class="bi bi-arrow-right trip-arrow"></i>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="trip-point">
                                            <small class="text-muted">To</small>
                                            <h4><?php echo htmlspecialchars($ticket['destination_city']); ?></h4>
                                            <div class="trip-time">
                                                <i class="bi bi-calendar3"></i>
                                                <?php echo $arrival->format('M d, Y'); ?>
                                            </div>
                                            <div class="trip-time">
                                                <i class="bi bi-clock"></i>
                                                <?php echo $arrival->format('H:i'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="ticket-details">
                                    <div class="detail-item">
                                        <i class="bi bi-bus-front"></i>
                                        <span>Bus Type: <strong><?php echo htmlspecialchars($ticket['bus_type']); ?></strong></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="bi bi-person-fill"></i>
                                        <span>Seats: 
                                            <?php foreach ($ticket['seats'] as $seat): ?>
                                                <span class="seat-badge-small"><?php echo $seat; ?></span>
                                            <?php endforeach; ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="bi bi-cash"></i>
                                        <span>Total: <strong class="text-primary">$<?php echo number_format($ticket['total_price'], 2); ?></strong></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="bi bi-calendar-check"></i>
                                        <span>Booked: <?php echo $booking_date->format('M d, Y H:i'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="ticket-footer">
                                <div class="footer-actions">
                                    <button class="btn btn-outline-primary" onclick="downloadTicket('<?php echo $ticket['ticket_id']; ?>')">
                                        <i class="bi bi-download"></i> Download Ticket
                                    </button>
                                    <?php if ($is_cancellable): ?>
                                        <button class="btn btn-outline-danger" onclick="showCancelModal('<?php echo $ticket['ticket_id']; ?>')">
                                            <i class="bi bi-x-circle"></i> Cancel Ticket
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-outline-danger" disabled 
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="top" 
                                                title="Cannot cancel within 1 hour of departure">
                                            <i class="bi bi-x-circle"></i> Cancel Ticket
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$is_cancellable): ?>
                                    <span class="text-warning small">
                                        <i class="bi bi-exclamation-triangle-fill"></i> Cancellation not available (less than 1 hour to departure)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Past Tickets -->
            <div class="tab-pane fade" id="past" role="tabpanel">
                <?php if (empty($past_tickets)): ?>
                    <div class="empty-tab-state">
                        <i class="bi bi-clock-history"></i>
                        <p>No past trips</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($past_tickets as $ticket): 
                        $departure = new DateTime($ticket['departure_time']);
                        $arrival = new DateTime($ticket['arrival_time']);
                    ?>
                        <div class="ticket-card past">
                            <div class="ticket-header">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($ticket['company_logo'] ?? '/assets/images/default-logo.png'); ?>" 
                                         alt="<?php echo htmlspecialchars($ticket['company_name']); ?>" 
                                         class="company-logo">
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($ticket['company_name']); ?></h5>
                                        <small class="text-muted">Ticket ID: <?php echo htmlspecialchars(substr($ticket['ticket_id'], 0, 12)); ?></small>
                                    </div>
                                </div>
                                <span class="status-badge status-past">
                                    <i class="bi bi-check2-all"></i> Completed
                                </span>
                            </div>

                            <div class="ticket-body">
                                <div class="row mb-3">
                                    <div class="col-md-5">
                                        <div class="trip-point">
                                            <small class="text-muted">From</small>
                                            <h5><?php echo htmlspecialchars($ticket['departure_city']); ?></h5>
                                            <div class="trip-time">
                                                <?php echo $departure->format('M d, Y - H:i'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
                                        <i class="bi bi-arrow-right trip-arrow"></i>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="trip-point">
                                            <small class="text-muted">To</small>
                                            <h5><?php echo htmlspecialchars($ticket['destination_city']); ?></h5>
                                            <div class="trip-time">
                                                <?php echo $arrival->format('M d, Y - H:i'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="ticket-details">
                                    <div class="detail-item">
                                        <span>Seats: 
                                            <?php foreach ($ticket['seats'] as $seat): ?>
                                                <span class="seat-badge-small"><?php echo $seat; ?></span>
                                            <?php endforeach; ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span>Paid: <strong>$<?php echo number_format($ticket['total_price'], 2); ?></strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Cancelled Tickets -->
            <div class="tab-pane fade" id="cancelled" role="tabpanel">
                <?php if (empty($cancelled_tickets)): ?>
                    <div class="empty-tab-state">
                        <i class="bi bi-x-circle"></i>
                        <p>No cancelled tickets</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($cancelled_tickets as $ticket): 
                        $departure = new DateTime($ticket['departure_time']);
                        $arrival = new DateTime($ticket['arrival_time']);
                    ?>
                        <div class="ticket-card cancelled">
                            <div class="ticket-header">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($ticket['company_logo'] ?? '/assets/images/default-logo.png'); ?>" 
                                         alt="<?php echo htmlspecialchars($ticket['company_name']); ?>" 
                                         class="company-logo">
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($ticket['company_name']); ?></h5>
                                        <small class="text-muted">Ticket ID: <?php echo htmlspecialchars(substr($ticket['ticket_id'], 0, 12)); ?></small>
                                    </div>
                                </div>
                                <span class="status-badge status-cancelled">
                                    <i class="bi bi-x-circle-fill"></i> Cancelled
                                </span>
                            </div>

                            <div class="ticket-body">
                                <div class="row mb-3">
                                    <div class="col-md-5">
                                        <div class="trip-point">
                                            <small class="text-muted">From</small>
                                            <h5><?php echo htmlspecialchars($ticket['departure_city']); ?></h5>
                                            <div class="trip-time">
                                                <?php echo $departure->format('M d, Y - H:i'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
                                        <i class="bi bi-arrow-right trip-arrow"></i>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="trip-point">
                                            <small class="text-muted">To</small>
                                            <h5><?php echo htmlspecialchars($ticket['destination_city']); ?></h5>
                                            <div class="trip-time">
                                                <?php echo $arrival->format('M d, Y - H:i'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="ticket-details">
                                    <div class="detail-item">
                                        <span>Seats: 
                                            <?php foreach ($ticket['seats'] as $seat): ?>
                                                <span class="seat-badge-small"><?php echo $seat; ?></span>
                                            <?php endforeach; ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span>Refunded: <strong class="text-success">$<?php echo number_format($ticket['total_price'], 2); ?></strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
let ticketToCancel = null;

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function showCancelModal(ticketId) {
    ticketToCancel = ticketId;
    const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
    cancelModal.show();
}

document.getElementById('confirmCancelBtn')?.addEventListener('click', async function() {
    if (!ticketToCancel) return;
    
    // Hide cancel modal
    const cancelModal = bootstrap.Modal.getInstance(document.getElementById('cancelModal'));
    cancelModal.hide();
    
    // Show loading modal
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/cancel_ticket.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                ticket_id: ticketToCancel
            })
        });
        
        const data = await response.json();
        
        loadingModal.hide();
        
        if (data.success) {
            document.getElementById('success-message').textContent = data.message;
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        } else {
            document.getElementById('error-message').textContent = data.message || 'Failed to cancel ticket.';
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            errorModal.show();
        }
    } catch (error) {
        loadingModal.hide();
        console.error('Cancel error:', error);
        document.getElementById('error-message').textContent = 'Network error. Please try again.';
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
    }
    
    ticketToCancel = null;
});

    async function downloadTicket(ticketId) {
    const response = await fetch('/../api/download_ticket.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticket_id: ticketId }),
    });

    if (!response.ok) {
        alert('Failed to download ticket.');
        return;
    }

    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `ticket_${ticketId}.pdf`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
    }

</script>

<style>
.tickets-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.page-header h2 {
    color: #e2e8f0;
    margin: 0;
}

.page-header h2 i {
    color: #3b82f6;
    margin-right: 10px;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: #2d3748;
    border: 2px dashed #4a5568;
    border-radius: 12px;
}

.empty-state i {
    font-size: 5rem;
    color: #4a5568;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #e2e8f0;
    margin-bottom: 10px;
}

.empty-state p {
    color: #a0aec0;
    font-size: 1.1rem;
}

.empty-tab-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-tab-state i {
    font-size: 4rem;
    color: #4a5568;
    margin-bottom: 15px;
}

.empty-tab-state p {
    color: #a0aec0;
    font-size: 1rem;
}

/* Tabs */
.nav-tabs {
    border-bottom: 2px solid #4a5568;
}

.nav-tabs .nav-link {
    color: #a0aec0;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 12px 20px;
    font-weight: 500;
}

.nav-tabs .nav-link:hover {
    color: #e2e8f0;
    border-bottom-color: #4a5568;
}

.nav-tabs .nav-link.active {
    color: #3b82f6;
    background: transparent;
    border-bottom-color: #3b82f6;
}

.nav-tabs .badge {
    margin-left: 8px;
}

/* Ticket Cards */
.ticket-card {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.ticket-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.ticket-card.active {
    border-left: 5px solid #10b981;
}

.ticket-card.past {
    border-left: 5px solid #6b7280;
    opacity: 0.9;
}

.ticket-card.cancelled {
    border-left: 5px solid #ef4444;
    opacity: 0.85;
}

.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #1a202c;
    border-bottom: 1px solid #4a5568;
}

.company-logo {
    width: 50px;
    height: 50px;
    object-fit: contain;
    margin-right: 15px;
    background: white;
    border-radius: 8px;
    padding: 5px;
}

.ticket-header h5 {
    color: #e2e8f0;
    margin: 0;
}

.ticket-header small {
    color: #a0aec0;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.status-active {
    background: #065f46;
    color: #d1fae5;
}

.status-past {
    background: #374151;
    color: #d1d5db;
}

.status-cancelled {
    background: #7f1d1d;
    color: #fecaca;
}

.ticket-body {
    padding: 25px;
}

.trip-point h4, .trip-point h5 {
    color: #e2e8f0;
    margin: 5px 0;
}

.trip-point small {
    color: #a0aec0;
    text-transform: uppercase;
    font-size: 0.75rem;
    font-weight: 600;
}

.trip-time {
    color: #60a5fa;
    font-size: 0.95rem;
    margin-top: 5px;
}

.trip-time i {
    margin-right: 6px;
}

.trip-arrow {
    font-size: 2rem;
    color: #4a5568;
}

.ticket-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #4a5568;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #e2e8f0;
}

.detail-item i {
    color: #60a5fa;
    font-size: 1.2rem;
}

.seat-badge-small {
    display: inline-block;
    background: #1e3a8a;
    color: #93c5fd;
    padding: 3px 10px;
    border-radius: 12px;
    margin: 2px;
    font-size: 0.8rem;
    font-weight: 500;
}

.ticket-footer {
    padding: 15px 25px;
    background: #1a202c;
    border-top: 1px solid #4a5568;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.ticket-footer .btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.ticket-footer .text-warning {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.ticket-footer .text-warning i {
    color: #fbbf24;
}

/* Modal Styles */
.modal-content {
    background: #2d3748;
    color: #e2e8f0;
    border: 2px solid #4a5568;
}

.modal-header {
    border-bottom: 1px solid #4a5568;
}

.modal-footer {
    border-top: 1px solid #4a5568;
}

.modal-title {
    color: #e2e8f0;
}

.btn-close {
    filter: invert(1);
}

/* Animations */
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

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .ticket-details {
        grid-template-columns: 1fr;
    }
    
    .trip-arrow {
        transform: rotate(90deg);
    }
}
</style>