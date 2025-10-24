<?php
// src/pages/company/dashboard.php

require_login();

if (!is_company()) {
    header('Location: /home');
    exit();
}

$user_id = $_SESSION['user_id'];
$company_info = null;
$stats = [];
$error = null;

try {
    // Get company info
    $company_stmt = $pdo->prepare("
        SELECT bc.id, bc.name, bc.logo_path, bc.created_at
        FROM User u
        JOIN Bus_Company bc ON u.company_id = bc.id
        WHERE u.id = ? AND u.role = 'company'
    ");
    $company_stmt->execute([$user_id]);
    $company_info = $company_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company_info) {
        $error = "Company information not found.";
    } else {
        // Get statistics
        $company_id = $company_info['id'];
        
        // Total trips
        $trips_stmt = $pdo->prepare("SELECT COUNT(*) FROM Trips WHERE company_id = ?");
        $trips_stmt->execute([$company_id]);
        $stats['total_trips'] = $trips_stmt->fetchColumn();
        
        // Upcoming trips
        $upcoming_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM Trips 
            WHERE company_id = ? AND departure_time > datetime('now')
        ");
        $upcoming_stmt->execute([$company_id]);
        $stats['upcoming_trips'] = $upcoming_stmt->fetchColumn();
        
        // Total bookings
        $bookings_stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT t.id) 
            FROM Tickets t
            JOIN Trips tr ON t.trip_id = tr.id
            WHERE tr.company_id = ? AND t.status = 'ACTIVE'
        ");
        $bookings_stmt->execute([$company_id]);
        $stats['total_bookings'] = $bookings_stmt->fetchColumn();
        
        // Active coupons
        $coupons_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM Coupons 
            WHERE company_id = ? AND expire_date > datetime('now')
        ");
        $coupons_stmt->execute([$company_id]);
        $stats['active_coupons'] = $coupons_stmt->fetchColumn();
        
        // Total revenue
        $revenue_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(t.total_price), 0)
            FROM Tickets t
            JOIN Trips tr ON t.trip_id = tr.id
            WHERE tr.company_id = ? AND t.status = 'ACTIVE'
        ");
        $revenue_stmt->execute([$company_id]);
        $stats['total_revenue'] = $revenue_stmt->fetchColumn();
        
        // Cancelled tickets
        $cancelled_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM Tickets t
            JOIN Trips tr ON t.trip_id = tr.id
            WHERE tr.company_id = ? AND t.status = 'CANCELLED'
        ");
        $cancelled_stmt->execute([$company_id]);
        $stats['cancelled_tickets'] = $cancelled_stmt->fetchColumn();
    }
    
} catch (PDOException $e) {
    $error = "Error loading dashboard.";
    error_log("Company dashboard error: " . $e->getMessage());
}
?>

<div class="company-dashboard-container">
    <div class="page-header mb-4">
        <div>
            <h2><i class="bi bi-speedometer2"></i> Company Dashboard</h2>
            <?php if ($company_info): ?>
                <div class="company-header-info">
                    <img src="<?php echo htmlspecialchars($company_info['logo_path'] ?? '/assets/images/default-logo.png'); ?>" 
                         alt="<?php echo htmlspecialchars($company_info['name']); ?>" 
                         class="company-logo-header">
                    <span class="company-name-header"><?php echo htmlspecialchars($company_info['name']); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <i class="bi bi-bus-front"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_trips']; ?></h3>
                        <p>Total Trips</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['upcoming_trips']; ?></h3>
                        <p>Upcoming Trips</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <i class="bi bi-ticket"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_bookings']; ?></h3>
                        <p>Active Bookings</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-purple">
                    <div class="stat-icon">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['active_coupons']; ?></h3>
                        <p>Active Coupons</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="stat-card stat-danger">
                    <div class="stat-icon">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['cancelled_tickets']; ?></h3>
                        <p>Cancelled Tickets</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
    <div class="quick-actions-section">
        <h4 class="mb-3"><i class="bi bi-lightning-fill"></i> Quick Actions</h4>

        <div class="row">
            <div class="col-md-4 mb-3">
                <a href="/company/trips" class="action-card large-card">
                    <div class="action-icon">
                        <i class="bi bi-bus-front"></i>
                    </div>
                    <div class="action-content">
                        <h5>Manage Trips</h5>
                        <p>Create, edit, and delete trips</p>
                    </div>
                    <i class="bi bi-arrow-right action-arrow"></i>
                </a>
            </div>

            <div class="col-md-4 mb-3">
                <a href="/company/bookings" class="action-card large-card">
                    <div class="action-icon">
                        <i class="bi bi-ticket"></i>
                    </div>
                    <div class="action-content">
                        <h5>View Bookings</h5>
                        <p>Manage customer bookings</p>
                    </div>
                    <i class="bi bi-arrow-right action-arrow"></i>
                </a>
            </div>

            <div class="col-md-4 mb-3">
                <a href="/company/coupons" class="action-card large-card">
                    <div class="action-icon">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="action-content">
                        <h5>Manage Coupons</h5>
                        <p>Create discount coupons</p>
                    </div>
                    <i class="bi bi-arrow-right action-arrow"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.company-dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    margin-bottom: 30px;
}

.page-header h2 {
    color: #e2e8f0;
    margin: 0 0 15px 0;
}

.page-header h2 i {
    color: #3b82f6;
    margin-right: 10px;
}

.company-header-info {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 20px;
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 10px;
    margin-top: 10px;
}

.company-logo-header {
    width: 50px;
    height: 50px;
    object-fit: contain;
    background: white;
    border-radius: 8px;
    padding: 5px;
}

.company-name-header {
    color: #e2e8f0;
    font-size: 1.25rem;
    font-weight: 600;
}

/* Statistics Cards */
.stat-card {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 12px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.2s, box-shadow 0.2s;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    flex-shrink: 0;
}

.stat-primary .stat-icon {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

.stat-success .stat-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.stat-info .stat-icon {
    background: rgba(14, 165, 233, 0.2);
    color: #38bdf8;
}

.stat-warning .stat-icon {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.stat-purple .stat-icon {
    background: rgba(168, 85, 247, 0.2);
    color: #a78bfa;
}

.stat-danger .stat-icon {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.stat-info h3 {
    color: #e2e8f0;
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
}

.stat-info p {
    color: #a0aec0;
    margin: 5px 0 0 0;
    font-size: 0.95rem;
}

/* Quick Actions */
.quick-actions-section {
    margin-top: 40px;
}

.quick-actions-section h4 {
    color: #e2e8f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.quick-actions-section h4 i {
    color: #fbbf24;
}

.action-card {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 12px;
    padding: 25px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 15px;
    text-decoration: none;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
    height: 100%;
}

.action-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
    background: #3b82f6;
    transform: scaleY(0);
    transition: transform 0.2s;
}

.action-card:hover {
    border-color: #3b82f6;
    transform: translateY(-5px);
}

.action-card:hover::before {
    transform: scaleY(1);
}

.action-icon {
    width: 60px;
    height: 60px;
    background: rgba(59, 130, 246, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: #60a5fa;
}

.action-content h5 {
    color: #e2e8f0;
    margin: 0;
    font-size: 1.1rem;
}

.action-content p {
    color: #a0aec0;
    margin: 5px 0 0 0;
    font-size: 0.9rem;
}

.action-arrow {
    color: #60a5fa;
    font-size: 1.5rem;
    transition: transform 0.2s;
}

.action-card:hover .action-arrow {
    transform: translateX(5px);
}

/* Responsive */
@media (max-width: 768px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .company-header-info {
        flex-direction: column;
        text-align: center;
    }
}
</style>