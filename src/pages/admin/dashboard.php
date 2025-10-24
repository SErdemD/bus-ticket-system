<?php
// src/pages/admin-dashboard.php

require_login();

// Only admins can access this page
if (!is_admin()) {
    header('Location: /home');
    exit();
}

$stats = [];
$error = null;

try {
    // Get statistics
    $stats_queries = [
        'total_companies' => "SELECT COUNT(*) FROM Bus_Company",
        'total_users' => "SELECT COUNT(*) FROM User WHERE role = 'user'",
        'company_admins' => "SELECT COUNT(*) FROM User WHERE role = 'company'",
        'total_trips' => "SELECT COUNT(*) FROM Trips",
        'active_coupons' => "SELECT COUNT(*) FROM Coupons WHERE expire_date > datetime('now')",
        'total_bookings' => "SELECT COUNT(*) FROM Tickets WHERE status = 'ACTIVE'"
    ];
    
    foreach ($stats_queries as $key => $query) {
        $result = $pdo->query($query);
        $stats[$key] = $result->fetchColumn();
    }
    
} catch (PDOException $e) {
    $error = "Error loading dashboard statistics.";
    error_log("Admin dashboard error: " . $e->getMessage());
}
?>

<div class="admin-dashboard-container">
    <div class="page-header mb-4">
        <h2><i class="bi bi-speedometer2"></i> Admin Dashboard</h2>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="stat-card stat-primary">
                <div class="stat-icon">
                    <i class="bi bi-building"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_companies'] ?? 0; ?></h3>
                    <p>Bus Companies</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_users'] ?? 0; ?></h3>
                    <p>Regular Users</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="stat-card stat-warning">
                <div class="stat-icon">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['company_admins'] ?? 0; ?></h3>
                    <p>Company Admins</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="stat-card stat-info">
                <div class="stat-icon">
                    <i class="bi bi-bus-front"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_trips'] ?? 0; ?></h3>
                    <p>Total Trips</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="stat-card stat-purple">
                <div class="stat-icon">
                    <i class="bi bi-ticket-perforated"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['active_coupons'] ?? 0; ?></h3>
                    <p>Active Coupons</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="stat-card stat-danger">
                <div class="stat-icon">
                    <i class="bi bi-bookmark-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_bookings'] ?? 0; ?></h3>
                    <p>Active Bookings</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions-section">
        <h4 class="mb-3"><i class="bi bi-lightning-fill"></i> Quick Actions</h4>
        
        <div class="row">
            <div class="col-md-4 mb-3">
                <a href="/admin/companies" class="action-card">
                    <div class="action-icon">
                        <i class="bi bi-building"></i>
                    </div>
                    <div class="action-content">
                        <h5>Manage Companies</h5>
                        <p>Create, edit, and delete bus companies</p>
                    </div>
                    <i class="bi bi-arrow-right action-arrow"></i>
                </a>
            </div>
            
            <div class="col-md-4 mb-3">
                <a href="/admin/users" class="action-card">
                    <div class="action-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="action-content">
                        <h5>Manage Users</h5>
                        <p>View users and assign company roles</p>
                    </div>
                    <i class="bi bi-arrow-right action-arrow"></i>
                </a>
            </div>
            
            <div class="col-md-4 mb-3">
                <a href="/admin/coupons" class="action-card">
                    <div class="action-icon">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="action-content">
                        <h5>Manage Coupons</h5>
                        <p>Create and manage discount coupons</p>
                    </div>
                    <i class="bi bi-arrow-right action-arrow"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.admin-dashboard-container {
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
}

.stat-primary .stat-icon {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

.stat-success .stat-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.stat-warning .stat-icon {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.stat-info .stat-icon {
    background: rgba(14, 165, 233, 0.2);
    color: #38bdf8;
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
    align-items: center;
    gap: 20px;
    text-decoration: none;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
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
    transform: translateX(5px);
}

.action-card:hover::before {
    transform: scaleY(1);
}

.action-icon {
    width: 60px;
    height: 60px;
    background: rgba(59, 130, 246, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: #60a5fa;
    flex-shrink: 0;
}

.action-content {
    flex: 1;
}

.action-content h5 {
    color: #e2e8f0;
    margin: 0 0 5px 0;
    font-size: 1.1rem;
}

.action-content p {
    color: #a0aec0;
    margin: 0;
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
    
    .action-card {
        flex-direction: column;
        text-align: center;
    }
    
    .action-arrow {
        transform: rotate(90deg);
    }
    
    .action-card:hover .action-arrow {
        transform: rotate(90deg) translateX(5px);
    }
}
</style>