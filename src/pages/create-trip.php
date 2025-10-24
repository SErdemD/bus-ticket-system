<?php
// src/pages/create-trip.php

require_login();

// Only company admins can access this page
if (!is_company()) {
    header('Location: /home');
    exit();
}

$user_id = $_SESSION['user_id'];
$company_info = null;
$error = null;

try {
    // Fetch company information for the logged-in company admin
    // Company admins have role='company' and company_id set
    $user_stmt = $pdo->prepare("
        SELECT u.company_id, bc.id, bc.name, bc.logo_path 
        FROM User u
        JOIN Bus_Company bc ON u.company_id = bc.id
        WHERE u.id = ? AND u.role = 'company'
    ");
    $user_stmt->execute([$user_id]);
    $company_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company_info) {
        $error = "Company information not found.";
    }
    
} catch (PDOException $e) {
    $error = "Error loading company information.";
    error_log("Create trip page error: " . $e->getMessage());
}

// Get list of cities for dropdown (you can customize this list)
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

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="success-icon mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                </div>
                <h4 class="mb-3 text-success">Trip Created Successfully!</h4>
                <p class="mb-0" id="success-message"></p>
                <button type="button" class="btn btn-success w-100 mt-4" onclick="window.location.reload()">
                    Create Another Trip
                </button>
                <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="window.location.href='/manage-trips'">
                    View All Trips
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
                    Try Again
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 4rem; height: 4rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2">Creating Trip</h5>
                <p class="text-muted mb-0">Please wait...</p>
            </div>
        </div>
    </div>
</div>

<div class="create-trip-container">
    <div class="page-header mb-4">
        <div>
            <h2><i class="bi bi-bus-front-fill"></i> Create New Trip</h2>
            <?php if ($company_info): ?>
                <p class="company-name">
                    <img src="<?php echo htmlspecialchars($company_info['logo_path'] ?? '/assets/images/default-logo.png'); ?>" 
                         alt="<?php echo htmlspecialchars($company_info['name']); ?>" 
                         class="company-logo-small">
                    <?php echo htmlspecialchars($company_info['name']); ?>
                </p>
            <?php endif; ?>
        </div>
        <a href="/manage-trips" class="btn btn-outline-secondary">
            <i class="bi bi-list-ul"></i> View All Trips
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="trip-form-card">
                    <form id="create-trip-form">
                        <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($company_info['company_id']); ?>">
                        
                        <div class="form-section">
                            <h5><i class="bi bi-geo-alt-fill"></i> Route Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="departure_city" class="form-label">Departure City *</label>
                                    <select class="form-select" id="departure_city" name="departure_city" required>
                                        <option value="">Select departure city</option>
                                        <?php foreach ($cities as $city): ?>
                                            <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="destination_city" class="form-label">Destination City *</label>
                                    <select class="form-select" id="destination_city" name="destination_city" required>
                                        <option value="">Select destination city</option>
                                        <?php foreach ($cities as $city): ?>
                                            <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h5><i class="bi bi-clock-fill"></i> Schedule</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="departure_time" class="form-label">Departure Date & Time *</label>
                                    <input type="datetime-local" 
                                           class="form-control" 
                                           id="departure_time" 
                                           name="departure_time" 
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="arrival_time" class="form-label">Arrival Date & Time *</label>
                                    <input type="datetime-local" 
                                           class="form-control" 
                                           id="arrival_time" 
                                           name="arrival_time" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h5><i class="bi bi-bus-front"></i> Bus Details</h5>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="bus_type" class="form-label">Bus Type *</label>
                                    <select class="form-select" id="bus_type" name="bus_type" required>
                                        <option value="">Select bus type</option>
                                        <option value="2+2">2+2 (Standard)</option>
                                        <option value="2+1">2+1 (Premium)</option>
                                    </select>
                                    <small class="text-muted">2+2 = 40 seats, 2+1 = 30 seats</small>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="capacity" class="form-label">Capacity *</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="capacity" 
                                           name="capacity" 
                                           min="10" 
                                           max="60" 
                                           required
                                           readonly>
                                    <small class="text-muted">Auto-set based on bus type</small>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="price" class="form-label">Price per Seat ($) *</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="price" 
                                           name="price" 
                                           min="1" 
                                           step="0.01" 
                                           placeholder="25.00"
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-outline-secondary" onclick="window.history.back()">
                                <i class="bi bi-x-circle"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg" id="submit-btn">
                                <i class="bi bi-plus-circle"></i> Create Trip
                            </button>
                        </div>
                    </form>
                </div>

                <div class="info-card mt-4">
                    <h6><i class="bi bi-info-circle-fill"></i> Important Notes</h6>
                    <ul>
                        <li>Departure time must be in the future</li>
                        <li>Arrival time must be after departure time</li>
                        <li>Departure and destination cities must be different</li>
                        <li>Price must be a positive number</li>
                        <li>All fields marked with * are required</li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Set minimum datetime to current time
const now = new Date();
now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
document.getElementById('departure_time').min = now.toISOString().slice(0, 16);

// Auto-set capacity based on bus type
document.getElementById('bus_type').addEventListener('change', function() {
    const capacityInput = document.getElementById('capacity');
    if (this.value === '2+2') {
        capacityInput.value = 40;
    } else if (this.value === '2+1') {
        capacityInput.value = 30;
    } else {
        capacityInput.value = '';
    }
});

// Update arrival time minimum when departure time changes
document.getElementById('departure_time').addEventListener('change', function() {
    const arrivalInput = document.getElementById('arrival_time');
    if (this.value) {
        const departure = new Date(this.value);
        departure.setHours(departure.getHours() + 1); // Minimum 1 hour trip
        departure.setMinutes(departure.getMinutes() - departure.getTimezoneOffset());
        arrivalInput.min = departure.toISOString().slice(0, 16);
        
        // Reset arrival if it's now invalid
        if (arrivalInput.value && new Date(arrivalInput.value) <= new Date(this.value)) {
            arrivalInput.value = '';
        }
    }
});

// Form validation
document.getElementById('create-trip-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Validate form
    const formData = new FormData(this);
    const departureCity = formData.get('departure_city');
    const destinationCity = formData.get('destination_city');
    const departureTime = new Date(formData.get('departure_time'));
    const arrivalTime = new Date(formData.get('arrival_time'));
    const price = parseFloat(formData.get('price'));
    
    // Check if cities are different
    if (departureCity === destinationCity) {
        document.getElementById('error-message').textContent = 'Departure and destination cities must be different.';
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
        return;
    }
    
    // Check if departure is in the future
    if (departureTime <= new Date()) {
        document.getElementById('error-message').textContent = 'Departure time must be in the future.';
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
        return;
    }
    
    // Check if arrival is after departure
    if (arrivalTime <= departureTime) {
        document.getElementById('error-message').textContent = 'Arrival time must be after departure time.';
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
        return;
    }
    
    // Check price
    if (price <= 0) {
        document.getElementById('error-message').textContent = 'Price must be greater than zero.';
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
        return;
    }
    
    // Show loading modal
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/create_trip.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        loadingModal.hide();
        
        if (data.success) {
            document.getElementById('success-message').textContent = 
                `Trip from ${departureCity} to ${destinationCity} has been created successfully!`;
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        } else {
            document.getElementById('error-message').textContent = data.message || 'Failed to create trip.';
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            errorModal.show();
        }
    } catch (error) {
        loadingModal.hide();
        console.error('Create trip error:', error);
        document.getElementById('error-message').textContent = 'Network error. Please try again.';
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
    }
});
</script>

<style>
.create-trip-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
}

.page-header h2 {
    color: #e2e8f0;
    margin: 0 0 10px 0;
}

.page-header h2 i {
    color: #3b82f6;
    margin-right: 10px;
}

.company-name {
    color: #a0aec0;
    margin: 5px 0 0 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
}

.company-logo-small {
    width: 30px;
    height: 30px;
    object-fit: contain;
    background: white;
    border-radius: 6px;
    padding: 3px;
}

.trip-form-card {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 12px;
    padding: 30px;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 1px solid #4a5568;
}

.form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.form-section h5 {
    color: #e2e8f0;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section h5 i {
    color: #3b82f6;
}

.form-label {
    color: #e2e8f0;
    font-weight: 500;
    margin-bottom: 8px;
}

.form-control, .form-select {
    background: #1a202c;
    border: 1px solid #4a5568;
    color: #e2e8f0;
    padding: 10px 15px;
    border-radius: 6px;
}

.form-control:focus, .form-select:focus {
    background: #374151;
    border-color: #3b82f6;
    color: #e2e8f0;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

.form-control::placeholder {
    color: #6b7280;
}

.form-select option {
    background: #2d3748;
    color: #e2e8f0;
}

.form-control:read-only {
    background: #374151;
    cursor: not-allowed;
}

.text-muted {
    color: #a0aec0 !important;
    font-size: 0.875rem;
}

.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid #4a5568;
}

.info-card {
    background: #1a202c;
    border: 2px solid #3b82f6;
    border-radius: 10px;
    padding: 20px;
}

.info-card h6 {
    color: #60a5fa;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-card ul {
    color: #e2e8f0;
    margin: 0;
    padding-left: 20px;
}

.info-card li {
    margin-bottom: 8px;
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

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .form-actions {
        flex-direction: column-reverse;
        gap: 10px;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}
</style>