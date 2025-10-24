<?php
// src/pages/company/trips.php

require_login();

if (!is_company()) {
    header('Location: /home');
    exit();
}

$user_id = $_SESSION['user_id'];
$company_id = null;
$trips = [];
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
        // Get all trips for this company
        $trips_stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.departure_city,
                t.destination_city,
                t.departure_time,
                t.arrival_time,
                t.bus_type,
                t.capacity,
                t.price,
                t.created_at,
                COUNT(DISTINCT tk.id) as total_bookings,
                COUNT(DISTINCT bs.id) as booked_seats
            FROM Trips t
            LEFT JOIN Tickets tk ON t.id = tk.trip_id AND tk.status = 'ACTIVE'
            LEFT JOIN Booked_Seats bs ON tk.id = bs.ticket_id
            WHERE t.company_id = ?
            GROUP BY t.id
            ORDER BY t.departure_time DESC
        ");
        $trips_stmt->execute([$company_id]);
        $trips = $trips_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error = "Error loading trips.";
    error_log("Company trips page error: " . $e->getMessage());
}

// Get list of cities
$cities = [
    'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix',
    'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose',
    'Austin', 'Jacksonville', 'Fort Worth', 'Columbus', 'Charlotte',
    'San Francisco', 'Indianapolis', 'Seattle', 'Denver', 'Washington',
    'Boston', 'Nashville', 'Detroit', 'Portland', 'Las Vegas',
    'Miami', 'Atlanta', 'Minneapolis', 'Tampa', 'Orlando'
];
sort($cities);
?>

<!-- Create/Edit Trip Modal -->
<div class="modal fade" id="tripModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tripModalTitle">Create New Trip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="trip-form">
                    <input type="hidden" id="trip-id" name="trip_id">
                    <input type="hidden" id="form-action" name="action" value="create">
                    <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($company_id); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departure-city" class="form-label">Departure City *</label>
                            <select class="form-select" id="departure-city" name="departure_city" required>
                                <option value="">Select city</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="destination-city" class="form-label">Destination City *</label>
                            <select class="form-select" id="destination-city" name="destination_city" required>
                                <option value="">Select city</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departure-time" class="form-label">Departure Date & Time *</label>
                            <input type="datetime-local" class="form-control" id="departure-time" name="departure_time" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="arrival-time" class="form-label">Arrival Date & Time *</label>
                            <input type="datetime-local" class="form-control" id="arrival-time" name="arrival_time" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="bus-type" class="form-label">Bus Type *</label>
                            <select class="form-select" id="bus-type" name="bus_type" required>
                                <option value="">Select type</option>
                                <option value="2+2">2+2 (Standard)</option>
                                <option value="2+1">2+1 (Premium)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="capacity" class="form-label">Capacity *</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" min="10" max="60" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="price" class="form-label">Price per Seat ($) *</label>
                            <input type="number" class="form-control" id="price" name="price" min="1" step="0.01" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-trip-btn">Save Trip</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Trip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this trip?</p>
                <p id="delete-trip-info" class="fw-bold"></p>
                <p class="text-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i> 
                    This will also cancel all bookings for this trip!
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-btn">Delete Trip</button>
            </div>
        </div>
    </div>
</div>

<!-- Standard Modals (Loading, Success, Error) -->
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

<div class="company-trips-container">
    <div class="page-header mb-4">
        <div>
            <h2><i class="bi bi-bus-front"></i> Manage Trips</h2>
            <p class="text-muted mb-0">Create, edit, and delete your company trips</p>
        </div>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="bi bi-plus-circle"></i> Create New Trip
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif (empty($trips)): ?>
        <div class="empty-state">
            <i class="bi bi-bus-front"></i>
            <h3>No Trips Yet</h3>
            <p>Create your first trip to start accepting bookings</p>
            <button class="btn btn-primary btn-lg mt-3" onclick="openCreateModal()">
                <i class="bi bi-plus-circle"></i> Create Trip
            </button>
        </div>
    <?php else: ?>
        <!-- Filter Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-filter="all" onclick="filterTrips('all')">
                    All Trips <span class="badge bg-primary ms-2"><?php echo count($trips); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-filter="upcoming" onclick="filterTrips('upcoming')">
                    Upcoming
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-filter="past" onclick="filterTrips('past')">
                    Past
                </button>
            </li>
        </ul>

        <!-- Trips Grid -->
        <div class="trips-grid">
            <?php foreach ($trips as $trip): 
                $departure = new DateTime($trip['departure_time']);
                $arrival = new DateTime($trip['arrival_time']);
                $created = new DateTime($trip['created_at']);
                $is_past = $departure <= new DateTime();
                $available_seats = $trip['capacity'] - $trip['booked_seats'];
            ?>
                <div class="trip-card" data-filter="<?php echo $is_past ? 'past' : 'upcoming'; ?>">
                    <div class="trip-card-header">
                        <div class="trip-route">
                            <h4><?php echo htmlspecialchars($trip['departure_city']); ?></h4>
                            <i class="bi bi-arrow-right"></i>
                            <h4><?php echo htmlspecialchars($trip['destination_city']); ?></h4>
                        </div>
                        <?php if ($is_past): ?>
                            <span class="badge bg-secondary">Past</span>
                        <?php else: ?>
                            <span class="badge bg-success">Upcoming</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="trip-card-body">
                        <div class="trip-info-row">
                            <div class="trip-info-item">
                                <i class="bi bi-calendar-event"></i>
                                <div>
                                    <small>Departure</small>
                                    <strong><?php echo $departure->format('M d, Y - H:i'); ?></strong>
                                </div>
                            </div>
                            <div class="trip-info-item">
                                <i class="bi bi-calendar-check"></i>
                                <div>
                                    <small>Arrival</small>
                                    <strong><?php echo $arrival->format('M d, Y - H:i'); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="trip-info-row">
                            <div class="trip-info-item">
                                <i class="bi bi-bus-front"></i>
                                <div>
                                    <small>Bus Type</small>
                                    <strong><?php echo htmlspecialchars($trip['bus_type']); ?></strong>
                                </div>
                            </div>
                            <div class="trip-info-item">
                                <i class="bi bi-cash"></i>
                                <div>
                                    <small>Price per Seat</small>
                                    <strong>$<?php echo number_format($trip['price'], 2); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="trip-info-row">
                            <div class="trip-info-item">
                                <i class="bi bi-people"></i>
                                <div>
                                    <small>Capacity</small>
                                    <strong><?php echo $trip['capacity']; ?> seats</strong>
                                </div>
                            </div>
                            <div class="trip-info-item">
                                <i class="bi bi-check-circle"></i>
                                <div>
                                    <small>Booked</small>
                                    <strong class="<?php echo $available_seats > 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $trip['booked_seats']; ?> / <?php echo $trip['capacity']; ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="trip-stats mt-3">
                            <span class="stat-badge">
                                <i class="bi bi-ticket"></i> <?php echo $trip['total_bookings']; ?> Bookings
                            </span>
                            <span class="stat-badge <?php echo $available_seats > 0 ? 'available' : 'full'; ?>">
                                <i class="bi bi-seat"></i> <?php echo $available_seats; ?> Available
                            </span>
                        </div>
                    </div>
                    
                    <div class="trip-card-footer">
                        <button class="btn btn-sm btn-warning" onclick="editTrip('<?php echo htmlspecialchars($trip['id']); ?>')">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-danger" 
                                onclick="deleteTrip('<?php echo htmlspecialchars($trip['id']); ?>', '<?php echo htmlspecialchars($trip['departure_city'] . ' → ' . $trip['destination_city']); ?>')">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
let currentTripToDelete = null;

// Auto-set capacity based on bus type
document.getElementById('bus-type')?.addEventListener('change', function() {
    const capacity = this.value === '2+2' ? 40 : 30;
    document.getElementById('capacity').value = capacity;
});

// Open create modal
function openCreateModal() {
    document.getElementById('trip-form').reset();
    document.getElementById('trip-id').value = '';
    document.getElementById('form-action').value = 'create';
    document.getElementById('tripModalTitle').textContent = 'Create New Trip';
    document.getElementById('capacity').value = '';
    
    // Remove readonly from all fields
    document.querySelectorAll('#trip-form input, #trip-form select').forEach(el => {
        el.removeAttribute('readonly');
        el.removeAttribute('disabled');
    });
    
    new bootstrap.Modal(document.getElementById('tripModal')).show();
}

// Edit trip
async function editTrip(tripId) {
    try {
        const response = await fetch(`/api/company/get_trip.php?id=${tripId}`);
        const data = await response.json();
        
        if (data.success) {
            const trip = data.trip;
            
            document.getElementById('trip-id').value = trip.id;
            document.getElementById('form-action').value = 'edit';
            document.getElementById('departure-city').value = trip.departure_city;
            document.getElementById('destination-city').value = trip.destination_city;
            document.getElementById('departure-time').value = trip.departure_time;
            document.getElementById('arrival-time').value = trip.arrival_time;
            document.getElementById('bus-type').value = trip.bus_type;
            document.getElementById('capacity').value = trip.capacity;
            document.getElementById('price').value = trip.price;
            
            document.getElementById('tripModalTitle').textContent = 'Edit Trip';
            new bootstrap.Modal(document.getElementById('tripModal')).show();
        } else {
            alert(data.message || 'Error loading trip');
        }
    } catch (error) {
        console.error('Error loading trip:', error);
        alert('Error loading trip details');
    }
}

// Delete trip
function deleteTrip(tripId, route) {
    currentTripToDelete = tripId;
    document.getElementById('delete-trip-info').textContent = route;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Save trip (create or edit)
document.getElementById('save-trip-btn')?.addEventListener('click', async function() {
    const form = document.getElementById('trip-form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    
    const tripModal = bootstrap.Modal.getInstance(document.getElementById('tripModal'));
    tripModal.hide();
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/company/manage_trips.php', {
            method: 'POST',
            body: formData
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

// Confirm delete
document.getElementById('confirm-delete-btn')?.addEventListener('click', async function() {
    if (!currentTripToDelete) return;
    
    const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
    deleteModal.hide();
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('trip_id', currentTripToDelete);
    
    try {
        const response = await fetch('/api/company/manage_trips.php', {
            method: 'POST',
            body: formData
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

// Filter trips
function filterTrips(filter) {
    document.querySelectorAll('.nav-tabs button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-filter="${filter}"]`)?.classList.add('active');
    
    document.querySelectorAll('.trip-card').forEach(card => {
        const cardFilter = card.getAttribute('data-filter');
        if (filter === 'all' || cardFilter === filter) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<style>
.company-trips-container {
    max-width: 1400px;
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

.nav-tabs {
    border-bottom: 2px solid #4a5568;
}

.nav-tabs button {
    color: #a0aec0;
    border: none;
    padding: 12px 20px;
    background: transparent;
    transition: all 0.3s;
}

.nav-tabs button:hover {
    color: #e2e8f0;
    background: #2d3748;
}

.nav-tabs button.active {
    color: #fff;
    background: #667eea;
    border-radius: 8px 8px 0 0;
}

.trips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
}

.trip-card {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 15px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.trip-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
    border-color: #667eea;
}

.trip-card-header {
    background: #374151;
    padding: 20px;
    border-bottom: 2px solid #4a5568;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.trip-route {
    display: flex;
    align-items: center;
    gap: 15px;
}

.trip-route h4 {
    color: #e2e8f0;
    margin: 0;
    font-size: 1.1rem;
}

.trip-route i {
    color: #60a5fa;
    font-size: 1.2rem;
}

.trip-card-body {
    padding: 20px;
}

.trip-info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.trip-info-item {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.trip-info-item i {
    color: #60a5fa;
    font-size: 1.3rem;
}

.trip-info-item small {
    color: #a0aec0;
    display: block;
    font-size: 0.8rem;
}

.trip-info-item strong {
    color: #e2e8f0;
    display: block;
}

.trip-stats {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.stat-badge {
    background: #1a202c;
    color: #e2e8f0;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.stat-badge.available {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.stat-badge.full {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.trip-card-footer {
    background: #1a202c;
    padding: 15px 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
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

.form-control, .form-select {
    background: #1a202c;
    border: 2px solid #4a5568;
    color: #e2e8f0;
}

.form-control:focus, .form-select:focus {
    background: #1a202c;
    border-color: #667eea;
    color: #e2e8f0;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.form-control[readonly] {
    background: #374151;
    opacity: 0.7;
}

.form-label {
    font-weight: 500;
    color: #e2e8f0;
}

.btn-close {
    filter: invert(1);
}

.text-muted {
    color: #a0aec0 !important;
}

@media (max-width: 768px) {
    .trips-grid {
        grid-template-columns: 1fr;
    }
    
    .trip-info-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .page-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}
</style>