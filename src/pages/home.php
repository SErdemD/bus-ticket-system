<?php
// src/pages/home.php

// --- 1. Fetch data for the search form ---
try {
    $departure_cities_stmt = $pdo->query("SELECT DISTINCT departure_city FROM Trips ORDER BY departure_city");
    $departure_cities = $departure_cities_stmt->fetchAll(PDO::FETCH_COLUMN);
    $destination_cities_stmt = $pdo->query("SELECT DISTINCT destination_city FROM Trips ORDER BY destination_city");
    $destination_cities = $destination_cities_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $departure_cities = [];
    $destination_cities = [];
}

// --- 2. Handle the search form submission ---
// --- 2. Handle the search form submission ---
$departure_trips = [];
$return_trips = [];
$show_results = false;
$search_params = [];
$show_error = false;
$error_message = '';
$now = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $show_results = true;
    $search_params = [
        'from' => $_GET['from'] ?? '',
        'to' => $_GET['to'] ?? '',
        'departure_date' => $_GET['departure_date'] ?? '',
        'trip_type' => $_GET['trip_type'] ?? 'one_way',
        'return_date' => $_GET['return_date'] ?? '',
    ];

    if (!empty($search_params['from']) && !empty($search_params['to']) && !empty($search_params['departure_date'])) {
        try {
            // --- Prepare departure time filter ---
            $departure_time_filter = ($search_params['departure_date'] === date('Y-m-d'))
                ? $now
                : $search_params['departure_date'] . ' 00:00:00';

            // --- SQL for trips ---
            $sql = "
                SELECT
                    t.id, t.bus_type, t.departure_city, t.destination_city, t.departure_time,
                    t.arrival_time, t.price, t.capacity,
                    bc.name as company_name, bc.logo_path as company_logo
                FROM Trips t
                JOIN Bus_Company bc ON t.company_id = bc.id
                WHERE t.departure_city = :from
                  AND t.destination_city = :to
                  AND DATE(t.departure_time) = :departure_date
                  AND t.departure_time >= :departure_time
                ORDER BY t.departure_time ASC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':from' => $search_params['from'],
                ':to' => $search_params['to'],
                ':departure_date' => $search_params['departure_date'],
                ':departure_time' => $departure_time_filter
            ]);
            $departure_trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Handle errors for one-way ---
            if (empty($departure_trips) && $search_params['trip_type'] === 'one_way') {
                $show_error = true;
                $error_message = "No trips found from <strong>{$search_params['from']}</strong> to <strong>{$search_params['to']}</strong> on <strong>{$search_params['departure_date']}</strong>.";
            }

            // --- Fetch return trips if round trip ---
            if ($search_params['trip_type'] === 'round_trip' && !empty($search_params['return_date'])) {
                if ($search_params['return_date'] < $search_params['departure_date']) {
                    $show_error = true;
                    $error_message = "Return date cannot be earlier than departure date.";
                    $return_trips = [];
                }
                else{
                    $return_time_filter = ($search_params['return_date'] === date('Y-m-d'))
                        ? $now
                        : $search_params['return_date'] . ' 00:00:00';

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':from' => $search_params['to'],
                        ':to' => $search_params['from'],
                        ':departure_date' => $search_params['return_date'],
                        ':departure_time' => $return_time_filter
                    ]);
                    $return_trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // --- Handle errors for round trip ---
                    if (empty($departure_trips) && empty($return_trips)) {
                        $show_error = true;
                        $error_message = "No trips found for your selected dates. Please try different dates.";
                    } elseif (empty($departure_trips)) {
                        $show_error = true;
                        $error_message = "No departure trips found from <strong>{$search_params['from']}</strong> to <strong>{$search_params['to']}</strong> on <strong>{$search_params['departure_date']}</strong>.";
                    } elseif (empty($return_trips)) {
                        $show_error = true;
                        $error_message = "No return trips found from <strong>{$search_params['to']}</strong> to <strong>{$search_params['from']}</strong> on <strong>{$search_params['return_date']}</strong>.";
                    }
                    }
                }
        } catch (PDOException $e) {
            $departure_trips = [];
            $return_trips = [];
            $show_error = true;
            $error_message = "An error occurred while searching for trips.";
        }
    }
}

?>

<!-- Search Form -->
<div class="card mb-4">
    <div class="card-header">
        <h4>Find Your Trip</h4>
    </div>
    <div class="card-body">
        <form action="/home" method="GET">
            <!-- Trip Type Selection -->
            <div class="trip-type-toggle">
                <input type="radio" id="one_way" name="trip_type" value="one_way" 
                       <?php echo ($search_params['trip_type'] ?? 'one_way') === 'one_way' ? 'checked' : ''; ?>>
                <label for="one_way">One Way</label>
                
                <input type="radio" id="round_trip" name="trip_type" value="round_trip"
                       <?php echo ($search_params['trip_type'] ?? '') === 'round_trip' ? 'checked' : ''; ?>>
                <label for="round_trip">Round Trip</label>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="from" class="form-label">From</label>
                    <select id="from" name="from" class="form-select" required>
                        <option value="" disabled selected>Select origin</option>
                        <?php foreach ($departure_cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>"
                                <?php echo ($search_params['from'] ?? '') === $city ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($city); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="to" class="form-label">To</label>
                    <select id="to" name="to" class="form-select" required>
                        <option value="" disabled selected>Select destination</option>
                        <?php foreach ($destination_cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>"
                                <?php echo ($search_params['to'] ?? '') === $city ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($city); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="departure_date" class="form-label">Departure Date</label>
                    <input type="date" 
                        id="departure_date" 
                        name="departure_date" 
                        class="form-control"
                        value="<?php echo htmlspecialchars($search_params['departure_date'] ?? date('Y-m-d')); ?>" 
                    required>
                </div>
                <div class="col-md-6" id="return_date_group">
                    <label for="return_date" class="form-label">Return Date</label>
                    <input type="date" id="return_date" name="return_date" class="form-control"
                           value="<?php echo htmlspecialchars($search_params['return_date'] ?? ''); ?>">
                </div>
            </div>
            <div class="mt-4 text-end">
                <button type="submit" name="search" class="btn btn-primary btn-lg">Search Trips</button>
            </div>
        </form>
    </div>
</div>

<!-- Error Message -->
<?php if ($show_error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Trip Results -->
<?php if ($show_results && !$show_error): ?>
    <!-- Departure Trips -->
    <h3 class="mb-3">
        <?php echo $search_params['trip_type'] === 'round_trip' ? 'Departure Trips' : 'Available Trips'; ?>
    </h3>
    <div class="accordion mb-5" id="departure-trips-accordion">
        <?php foreach ($departure_trips as $trip):
            $departure = new DateTime($trip['departure_time']);
            $arrival = new DateTime($trip['arrival_time']);
            $interval = $departure->diff($arrival);
        ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-dep-<?php echo $trip['id']; ?>">
                    <button class="accordion-button collapsed trip-details-toggle" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#collapse-dep-<?php echo $trip['id']; ?>" 
                            aria-expanded="false" aria-controls="collapse-dep-<?php echo $trip['id']; ?>"
                            data-trip-id="<?php echo $trip['id']; ?>"
                            data-trip-price="<?php echo $trip['price']; ?>"
                            data-trip-name="<?php echo htmlspecialchars($trip['company_name']); ?>"
                            data-trip-time="<?php echo $departure->format('H:i'); ?>">
                        
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <img src="<?php echo htmlspecialchars($trip['company_logo'] ?? '/assets/images/default-logo.png'); ?>" 
                                 alt="<?php echo htmlspecialchars($trip['company_name']); ?>" 
                                 style="width: 40px; height: 40px; margin-right: 15px; object-fit: contain;">
                            <strong class="flex-grow-1"><?php echo htmlspecialchars($trip['company_name']); ?></strong>
                            <span class="badge bg-info mx-3"><?php echo htmlspecialchars($trip['bus_type']); ?></span>
                            <span class="mx-3 fs-5"><?php echo $departure->format('H:i'); ?> &rarr; <?php echo $arrival->format('H:i'); ?></span>
                            <span class="badge bg-secondary mx-3"><?php echo $interval->format('%h h %i m'); ?></span>
                            <strong class="text-primary fs-4">$<?php echo htmlspecialchars(number_format($trip['price'], 2)); ?></strong>
                        </div>
                    </button>
                </h2>
                <div id="collapse-dep-<?php echo $trip['id']; ?>" class="accordion-collapse collapse" 
                     aria-labelledby="heading-dep-<?php echo $trip['id']; ?>">
                    <div class="accordion-body">
                        <div class="seat-map-container text-center" data-trip-id="<?php echo $trip['id']; ?>" data-trip-type="departure">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading seats...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Return Trips (only for round trip) -->
    <?php if ($search_params['trip_type'] === 'round_trip' && !empty($return_trips)): ?>
        <h3 class="mb-3">Return Trips</h3>
        <div class="accordion mb-5" id="return-trips-accordion">
            <?php foreach ($return_trips as $trip):
                $departure = new DateTime($trip['departure_time']);
                $arrival = new DateTime($trip['arrival_time']);
                $interval = $departure->diff($arrival);
            ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-ret-<?php echo $trip['id']; ?>">
                        <button class="accordion-button collapsed trip-details-toggle" type="button" 
                                data-bs-toggle="collapse" data-bs-target="#collapse-ret-<?php echo $trip['id']; ?>" 
                                aria-expanded="false" aria-controls="collapse-ret-<?php echo $trip['id']; ?>"
                                data-trip-id="<?php echo $trip['id']; ?>"
                                data-trip-price="<?php echo $trip['price']; ?>"
                                data-trip-name="<?php echo htmlspecialchars($trip['company_name']); ?>"
                                data-trip-time="<?php echo $departure->format('H:i'); ?>">
                            
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <img src="<?php echo htmlspecialchars($trip['company_logo'] ?? '/assets/images/default-logo.png'); ?>" 
                                     alt="<?php echo htmlspecialchars($trip['company_name']); ?>" 
                                     style="width: 40px; height: 40px; margin-right: 15px; object-fit: contain;">
                                <strong class="flex-grow-1"><?php echo htmlspecialchars($trip['company_name']); ?></strong>
                                <span class="badge bg-info mx-3"><?php echo htmlspecialchars($trip['bus_type']); ?></span>
                                <span class="mx-3 fs-5"><?php echo $departure->format('H:i'); ?> &rarr; <?php echo $arrival->format('H:i'); ?></span>
                                <span class="badge bg-secondary mx-3"><?php echo $interval->format('%h h %i m'); ?></span>
                                <strong class="text-primary fs-4">$<?php echo htmlspecialchars(number_format($trip['price'], 2)); ?></strong>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse-ret-<?php echo $trip['id']; ?>" class="accordion-collapse collapse" 
                         aria-labelledby="heading-ret-<?php echo $trip['id']; ?>">
                        <div class="accordion-body">
                            <div class="seat-map-container text-center" data-trip-id="<?php echo $trip['id']; ?>" data-trip-type="return">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading seats...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Round Trip Booking Bar (Fixed Bottom) -->
<?php if ($show_results && !$show_error && $search_params['trip_type'] === 'round_trip' && $_SESSION['user_role'] == 'user'): ?>
<div class="round-trip-booking-bar" id="round-trip-bar">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-3">
                <div class="booking-summary-item">
                    <strong>Departure</strong>
                    <small id="departure-summary">No seats selected</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="booking-summary-item">
                    <strong>Return</strong>
                    <small id="return-summary">No seats selected</small>
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="booking-summary-item">
                    <strong>Total Price</strong>
                    <div class="text-primary fs-4" id="total-price">$0.00</div>
                </div>
            </div>
            <div class="col-md-3 text-end">
                <button type="button" class="btn btn-success btn-lg w-100" id="book-round-trip" disabled>
                    Book Both Trips
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toast notification function
    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container') || createToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <span class="toast-icon">${getToastIcon(type)}</span>
                <span class="toast-message">${message}</span>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Remove toast after 4 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
    
    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
        return container;
    }
    
    function getToastIcon(type) {
        const icons = {
            'success': '✓',
            'warning': '⚠',
            'error': '✕',
            'info': 'ℹ'
        };
        return icons[type] || icons.info;
    }
    
    // Make showToast available globally for other functions
    window.showToast = showToast;

    const departureDateInput = document.getElementById('departure_date');
    const returnDateInput = document.getElementById('return_date');
    const today = new Date().toISOString().split("T")[0];
    
    if (departureDateInput) {
        departureDateInput.min = today;
    }

    // Trip type toggle logic
    const oneWayRadio = document.getElementById('one_way');
    const roundTripRadio = document.getElementById('round_trip');
    const returnDateGroup = document.getElementById('return_date_group');

    function updateReturnDateField() {
        if (roundTripRadio.checked) {
            returnDateGroup.classList.remove('disabled');
            returnDateInput.required = true;
        } else {
            returnDateGroup.classList.add('disabled');
            returnDateInput.required = false;
            returnDateInput.value = '';
        }
    }

    oneWayRadio.addEventListener('change', updateReturnDateField);
    roundTripRadio.addEventListener('change', updateReturnDateField);
    updateReturnDateField();

    departureDateInput.addEventListener('change', function() {
        if (returnDateInput) {
            returnDateInput.min = this.value;
            if (returnDateInput.value && returnDateInput.value < this.value) {
                returnDateInput.value = this.value;
            }
        }
    });

    // --- SEAT MAP LOGIC ---
    const isRoundTrip = <?php echo ($search_params['trip_type'] ?? 'one_way') === 'round_trip' ? 'true' : 'false'; ?>;
    let selectedSeats = {
        departure: { trip_id: null, seats: [], price: 0, name: '', time: '' },
        return: { trip_id: null, seats: [], price: 0, name: '', time: '' }
    };

    const accordions = document.querySelectorAll('.accordion');

    accordions.forEach(accordion => {
        // Use 'show.bs.collapse' event to load seats when accordion opens
        accordion.addEventListener('show.bs.collapse', async function(event) {
            const collapseElement = event.target;
            const button = document.querySelector(`[data-bs-target="#${collapseElement.id}"]`);
            
            if (button && button.classList.contains('trip-details-toggle')) {
                const seatMapContainer = collapseElement.querySelector('.seat-map-container');
                const tripId = button.dataset.tripId;

                // Load seats if not loaded yet
                if (seatMapContainer.querySelector('.spinner-border')) {
                    try {
                        const response = await fetch(`/api/get_seats.php?trip_id=${tripId}`);
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        const data = await response.json();
                        renderSeatMap(seatMapContainer, data, button);
                    } catch (error) {
                        seatMapContainer.innerHTML = '<div class="alert alert-danger">Could not load seat map. Please try again.</div>';
                        console.error('Fetch error:', error);
                    }
                }
            }
        });

        // Handle seat clicks and booking button
        accordion.addEventListener('click', function(event) {
            const seat = event.target.closest('.seat.available');
            const bookBtn = event.target.closest('.book-trip-btn');

            if (seat) {
                handleSeatClick(seat);
            }
            if (bookBtn) {
                handleBooking(bookBtn);
            }
        });
    });

    async function handleAccordionToggle(button, event) {
        const tripId = button.dataset.tripId;
        const targetCollapse = document.querySelector(button.dataset.bsTarget);
        const seatMapContainer = targetCollapse.querySelector('.seat-map-container');
        const tripType = seatMapContainer.dataset.tripType;

        // Check if user already has seats selected for a different bus
        if (selectedSeats[tripType].trip_id && selectedSeats[tripType].trip_id !== tripId) {
            const tripTypeName = tripType === 'departure' ? 'departure' : 'return';
            showToast(`You have already selected seats for a ${tripTypeName} trip. Please deselect them first.`, 'warning');
            
            // Prevent accordion from opening
            event.preventDefault();
            event.stopPropagation();
            return false;
        }

        if (seatMapContainer.querySelector('.spinner-border')) {
            try {
                const response = await fetch(`/api/get_seats.php?trip_id=${tripId}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();
                renderSeatMap(seatMapContainer, data, button);
            } catch (error) {
                seatMapContainer.innerHTML = '<div class="alert alert-danger">Could not load seat map. Please try again.</div>';
                console.error('Fetch error:', error);
            }
        }
    }

    function handleSeatClick(seatElement) {
        const seatNum = parseInt(seatElement.dataset.seatNumber, 10);
        const container = seatElement.closest('.seat-map-container');
        const tripId = container.dataset.tripId;
        const tripType = container.dataset.tripType;

        // Check if trying to select a seat from a different bus
        if (selectedSeats[tripType].trip_id && selectedSeats[tripType].trip_id !== tripId) {
            const tripTypeName = tripType === 'departure' ? 'departure' : 'return';
            showToast(`You have already selected seats from another ${tripTypeName} bus. Please deselect them first.`, 'error');
            return;
        }

        seatElement.classList.toggle('selected');

        if (seatElement.classList.contains('selected')) {
            // Set trip_id and trip info on first seat selection
            if (!selectedSeats[tripType].trip_id) {
                selectedSeats[tripType].trip_id = tripId;
                selectedSeats[tripType].price = parseFloat(container.dataset.tripPrice);
                selectedSeats[tripType].name = container.dataset.tripName;
                selectedSeats[tripType].time = container.dataset.tripTime;
            }
            
            if (!selectedSeats[tripType].seats.includes(seatNum)) {
                selectedSeats[tripType].seats.push(seatNum);
            }
        } else {
            selectedSeats[tripType].seats = selectedSeats[tripType].seats.filter(s => s !== seatNum);
            
            // Reset trip info if no seats are selected
            if (selectedSeats[tripType].seats.length === 0) {
                selectedSeats[tripType].trip_id = null;
                selectedSeats[tripType].price = 0;
                selectedSeats[tripType].name = '';
                selectedSeats[tripType].time = '';
            }
        }

        if (isRoundTrip) {
            updateRoundTripBar();
        } else {
            const bookBtn = container.querySelector('.book-trip-btn');
            if (bookBtn) {
                bookBtn.disabled = selectedSeats[tripType].seats.length === 0;
            }
        }
    }

    function handleBooking(bookBtn) {
        const tripId = bookBtn.dataset.tripId;
        const tripType = bookBtn.dataset.tripType || 'departure';
        const seats = selectedSeats[tripType].seats;

        if (seats.length > 0) {
            const bookingData = {
                trip_type: 'one_way',
                trip_id: tripId,
                seats: seats
            };

            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/booking';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'booking_data';
            input.value = JSON.stringify(bookingData);
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function updateRoundTripBar() {
        const bar = document.getElementById('round-trip-bar');
        const departureSeats = selectedSeats.departure.seats.length;
        const returnSeats = selectedSeats.return.seats.length;
        const depPrice = selectedSeats.departure.price * departureSeats;
        const retPrice = selectedSeats.return.price * returnSeats;
        const totalPrice = depPrice + retPrice;

        // Update summaries
        document.getElementById('departure-summary').textContent = departureSeats > 0 
            ? `${selectedSeats.departure.name} - ${departureSeats} seat(s) - $${depPrice.toFixed(2)}`
            : 'No seats selected';
        
        document.getElementById('return-summary').textContent = returnSeats > 0 
            ? `${selectedSeats.return.name} - ${returnSeats} seat(s) - $${retPrice.toFixed(2)}`
            : 'No seats selected';

        document.getElementById('total-price').textContent = `$${totalPrice.toFixed(2)}`;

        // Enable/disable button
        const bookBtn = document.getElementById('book-round-trip');
        bookBtn.disabled = departureSeats === 0 || returnSeats === 0;

        // Show/hide bar
        if (departureSeats > 0 || returnSeats > 0) {
            bar.classList.add('show');
        } else {
            bar.classList.remove('show');
        }
    }

    // Round trip booking button
    if (isRoundTrip) {
        document.getElementById('book-round-trip').addEventListener('click', function() {
            const depSeats = selectedSeats.departure.seats;
            const retSeats = selectedSeats.return.seats;
            const depTripId = selectedSeats.departure.trip_id;
            const retTripId = selectedSeats.return.trip_id;

            if (depSeats.length > 0 && retSeats.length > 0 && depTripId && retTripId) {
                const bookingData = {
                    trip_type: 'round',
                    departure_trip: depTripId,
                    return_trip: retTripId,
                    departure_seats: depSeats,
                    return_seats: retSeats
                };

                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/booking';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'booking_data';
                input.value = JSON.stringify(bookingData);
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function renderSeatMap(container, data, button) {
        const tripId = container.dataset.tripId;
        const tripType = container.dataset.tripType;
        const userRole = "<?= $_SESSION['user_role'] ?? '' ?>";
        const { bus_type, capacity, booked_seats } = data;
        
        // Store trip info for later use (but don't set trip_id yet - only when seat is selected)
        container.dataset.tripPrice = button.dataset.tripPrice;
        container.dataset.tripName = button.dataset.tripName;
        container.dataset.tripTime = button.dataset.tripTime;
        
        let seatMapHtml = `<div class="bus-container">`;
        seatMapHtml += `<div class="bus-front">🚗 Driver</div>`;
        seatMapHtml += `<div class="bus-body">`;

        if (bus_type === '2+2') {
            // Calculate how many columns we need
            const totalCols = Math.ceil(capacity / 4);
            
            // First row (seats 1, 5, 9, 13, ...)
            seatMapHtml += `<div class="bus-row">`;
            for (let col = 0; col < totalCols; col++) {
                const seatNum = 1 + (col * 4);
                seatMapHtml += renderSeat(seatNum, capacity, booked_seats);
            }
            seatMapHtml += `</div>`;
            
            // Second row (seats 2, 6, 10, 14, ...)
            seatMapHtml += `<div class="bus-row">`;
            for (let col = 0; col < totalCols; col++) {
                const seatNum = 2 + (col * 4);
                seatMapHtml += renderSeat(seatNum, capacity, booked_seats);
            }
            seatMapHtml += `</div>`;
            
            // Aisle
            seatMapHtml += `<div class="bus-aisle"></div>`;
            
            // Third row (seats 3, 7, 11, 15, ...)
            seatMapHtml += `<div class="bus-row">`;
            for (let col = 0; col < totalCols; col++) {
                const seatNum = 3 + (col * 4);
                seatMapHtml += renderSeat(seatNum, capacity, booked_seats);
            }
            seatMapHtml += `</div>`;
            
            // Fourth row (seats 4, 8, 12, 16, ...)
            seatMapHtml += `<div class="bus-row">`;
            for (let col = 0; col < totalCols; col++) {
                const seatNum = 4 + (col * 4);
                seatMapHtml += renderSeat(seatNum, capacity, booked_seats);
            }
            seatMapHtml += `</div>`;
            
        } else if (bus_type === '2+1') {
            // Calculate how many columns we need
            const totalCols = Math.ceil(capacity / 3);
            
            // First row (seats 1, 4, 7, 10, ...)
            seatMapHtml += `<div class="bus-row">`;
            for (let col = 0; col < totalCols; col++) {
                const seatNum = 1 + (col * 3);
                seatMapHtml += renderSeat(seatNum, capacity, booked_seats);
            }
            seatMapHtml += `</div>`;
            
            // Second row (seats 2, 5, 8, 11, ...)
            seatMapHtml += `<div class="bus-row">`;
            for (let col = 0; col < totalCols; col++) {
                const seatNum = 2 + (col * 3);
                seatMapHtml += renderSeat(seatNum, capacity, booked_seats);
            }
            seatMapHtml += `</div>`;
            
            // Aisle
            seatMapHtml += `<div class="bus-aisle"></div>`;
            
            // Third row (seats 3, 6, 9, 12, ...)
            seatMapHtml += `<div class="bus-row">`;
            for (let col = 0; col < totalCols; col++) {
                const seatNum = 3 + (col * 3);
                seatMapHtml += renderSeat(seatNum, capacity, booked_seats);
            }
            seatMapHtml += `</div>`;
        }
        
        seatMapHtml += '</div>'; // Close bus-body
        seatMapHtml += '</div>'; // Close bus-container
        
        seatMapHtml += `
            <div class="d-flex justify-content-center align-items-center mt-4 flex-wrap">
                <div class="me-3 mb-2"><span class="seat-legend available"></span> Available</div>
                <div class="me-3 mb-2"><span class="seat-legend selected"></span> Selected</div>
                <div class="me-3 mb-2"><span class="seat-legend female"></span> Female</div>
                <div class="me-3 mb-2"><span class="seat-legend male"></span> Male</div>`;
        
        if (!isRoundTrip && userRole === 'user') {
            seatMapHtml += `<button class="btn btn-success ms-4 mb-2 book-trip-btn" data-trip-id="${tripId}" data-trip-type="${tripType}" disabled>Proceed to Book</button>`;
        }
        
        seatMapHtml += `</div>`;

        container.innerHTML = seatMapHtml;
    }

    function renderSeat(seatNum, maxCapacity, booked_seats) {
        if (seatNum > maxCapacity) {
            return '<div class="seat-placeholder"></div>';
        }

        const bookedSeat = booked_seats.find(s => s.seat_number == seatNum);
        let seatClass = 'available';
        
        if (bookedSeat) {
            seatClass = bookedSeat.gender === 'female' ? 'female' : 'male';
        }
        
        return `<div class="seat ${seatClass}" data-seat-number="${seatNum}" title="Seat ${seatNum}">${seatNum}</div>`;
    }
});
</script>

<style>
    /* Bus Seat Map Styles - Horizontal Layout */
    .bus-container {
        display: inline-flex;
        background: #f8f9fa;
        border: 3px solid #333;
        border-radius: 40px 20px 20px 40px;
        padding: 20px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        align-items: stretch;
    }

    .bus-front {
        background: #6c757d;
        color: white;
        padding: 15px 10px;
        text-align: center;
        border-radius: 10px;
        margin-right: 20px;
        font-weight: bold;
        writing-mode: vertical-rl;
        text-orientation: mixed;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 60px;
    }

    .bus-body {
        display: flex;
        flex-direction: column;
        gap: 10px;
        justify-content: center;
    }

    .bus-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .bus-aisle {
        height: 20px;
        width: 100%;
    }

    .seat {
        width: 45px;
        height: 45px;
        border: 2px solid #333;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
        transition: all 0.3s ease;
        cursor: pointer;
        user-select: none;
    }

    .seat.available {
        background: #28a745;
        color: white;
        cursor: pointer;
    }

    .seat.available:hover {
        background: #218838;
        transform: scale(1.1);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    .seat.selected {
        background: #ffc107;
        color: #000;
        border-color: #ff9800;
        transform: scale(1.05);
    }

    .seat.male {
        background: #007bff;
        color: white;
        cursor: not-allowed;
        opacity: 0.7;
    }

    .seat.female {
        background: #e83e8c;
        color: white;
        cursor: not-allowed;
        opacity: 0.7;
    }

    .seat-placeholder {
        width: 45px;
        height: 45px;
        visibility: hidden;
    }

    .seat-legend {
        display: inline-block;
        width: 30px;
        height: 30px;
        border: 2px solid #333;
        border-radius: 5px;
        margin-right: 5px;
        vertical-align: middle;
    }

    .seat-legend.available {
        background: #28a745;
    }

    .seat-legend.selected {
        background: #ffc107;
    }

    .seat-legend.male {
        background: #007bff;
    }

    .seat-legend.female {
        background: #e83e8c;
    }

    /* Trip Type Toggle */
    .trip-type-toggle {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .trip-type-toggle input[type="radio"] {
        display: none;
    }

    .trip-type-toggle label {
        flex: 1;
        padding: 12px 24px;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .trip-type-toggle input[type="radio"]:checked + label {
        background: #0d6efd;
        color: white;
        border-color: #0d6efd;
    }

    .trip-type-toggle label:hover {
        border-color: #0d6efd;
    }

    #return_date_group {
        transition: opacity 0.3s ease;
    }

    #return_date_group.disabled {
        opacity: 0.5;
        pointer-events: none;
    }

    /* Round Trip Booking Bar */
    .round-trip-booking-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: white;
        border-top: 3px solid #0d6efd;
        padding: 15px 20px;
        box-shadow: 0 -4px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        display: none;
    }

    .round-trip-booking-bar.show {
        display: block;
        background: #2a2b2cff;
    }

    .booking-summary-item {
        background: #2a2b2cff;
        padding: 10px 15px;
        border-radius: 6px;
        border: 1px solid #dee2e6;
    }

    .booking-summary-item strong {
        display: block;
        margin-bottom: 3px;
    }

    .booking-summary-item small {
        color: #d6d6d6ff;
    }

    /* Toast Notifications */
    #toast-container {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    }

    .toast-notification {
        background: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        min-width: 300px;
        max-width: 500px;
        opacity: 0;
        transform: translateY(-20px);
        transition: all 0.3s ease;
        pointer-events: auto;
    }

    .toast-notification.show {
        opacity: 1;
        transform: translateY(0);
    }

    .toast-content {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
    }

    .toast-icon {
        font-size: 20px;
        font-weight: bold;
        flex-shrink: 0;
    }

    .toast-message {
        flex: 1;
        color: #333;
        font-size: 14px;
    }

    .toast-success {
        border-left: 4px solid #28a745;
    }

    .toast-success .toast-icon {
        color: #28a745;
    }

    .toast-warning {
        border-left: 4px solid #ffc107;
    }

    .toast-warning .toast-icon {
        color: #ffc107;
    }

    .toast-error {
        border-left: 4px solid #dc3545;
    }

    .toast-error .toast-icon {
        color: #dc3545;
    }

    .toast-info {
        border-left: 4px solid #0d6efd;
    }

    .toast-info .toast-icon {
        color: #0d6efd;
    }
</style>