<?php
// src/pages/admin/coupons.php

require_login();

if (!is_admin()) {
    header('Location: /home');
    exit();
}

$coupons = [];
$companies = [];
$error = null;

try {
    // Get all coupons
    $coupons_stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.code,
            c.discount,
            c.usage_limit,
            c.expire_date,
            c.created_at,
            bc.name as company_name,
            bc.id as company_id,
            COUNT(uc.id) as times_used
        FROM Coupons c
        LEFT JOIN Bus_Company bc ON c.company_id = bc.id
        LEFT JOIN User_Coupons uc ON c.id = uc.coupon_id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $coupons_stmt->execute();
    $coupons = $coupons_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all companies for dropdown
    $companies_stmt = $pdo->prepare("SELECT id, name FROM Bus_Company ORDER BY name");
    $companies_stmt->execute();
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error loading coupons.";
    error_log("Admin coupons page error: " . $e->getMessage());
}
?>

<!-- Create/Edit Coupon Modal -->
<div class="modal fade" id="couponModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="couponModalTitle">Create New Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="coupon-form">
                    <input type="hidden" id="coupon-id" name="coupon_id">
                    <input type="hidden" id="form-action" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="coupon-code" class="form-label">Coupon Code *</label>
                        <input type="text" 
                               class="form-control text-uppercase" 
                               id="coupon-code" 
                               name="code" 
                               required
                               maxlength="20"
                               placeholder="e.g., SUMMER2025"
                               style="text-transform: uppercase;">
                        <small class="text-muted">Max 20 characters, letters and numbers only</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="coupon-discount" class="form-label">Discount Amount (%) *</label>
                        <input type="number" 
                               class="form-control" 
                               id="coupon-discount" 
                               name="discount" 
                               min="0.01"
                               step="0.01"
                               required
                               placeholder="10.00">
                        <small class="text-muted">Enter discount as a percentage (%)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="coupon-company" class="form-label">Company</label>
                        <select class="form-select" id="coupon-company" name="company_id">
                            <option value="">All Companies</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo htmlspecialchars($company['id']); ?>">
                                    <?php echo htmlspecialchars($company['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Leave empty for all companies</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="coupon-usage-limit" class="form-label">Usage Limit *</label>
                        <input type="number" 
                               class="form-control" 
                               id="coupon-usage-limit" 
                               name="usage_limit" 
                               min="1"
                               required
                               placeholder="100">
                        <small class="text-muted">Maximum number of times this coupon can be used</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="coupon-expire-date" class="form-label">Expiration Date *</label>
                        <input type="datetime-local" 
                               class="form-control" 
                               id="coupon-expire-date" 
                               name="expire_date" 
                               required>
                        <small class="text-muted">Date and time when this coupon expires</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-coupon-btn">Save Coupon</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this coupon?</p>
                <p id="delete-coupon-code" class="fw-bold"></p>
                <p class="text-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i> 
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-btn">Delete Coupon</button>
            </div>
        </div>
    </div>
</div>

<!-- Modals (Loading, Success, Error) -->
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

<div class="admin-coupons-container">
    <div class="page-header mb-4">
        <div>
            <h2><i class="bi bi-ticket-perforated"></i> Manage Coupons</h2>
            <p class="text-muted mb-0">Create, edit, and delete discount coupons</p>
        </div>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="bi bi-plus-circle"></i> Create New Coupon
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif (empty($coupons)): ?>
        <div class="empty-state">
            <i class="bi bi-ticket-perforated"></i>
            <h3>No Coupons Yet</h3>
            <p>Create your first discount coupon to get started</p>
            <button class="btn btn-primary btn-lg mt-3" onclick="openCreateModal()">
                <i class="bi bi-plus-circle"></i> Create Coupon
            </button>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table coupons-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Company</th>
                        <th>Usage</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $coupon): 
                        $expire = new DateTime($coupon['expire_date']);
                        $created = new DateTime($coupon['created_at']);
                        $is_expired = $expire <= new DateTime();
                        $is_used_up = $coupon['times_used'] >= $coupon['usage_limit'];
                    ?>
                        <tr>
                            <td>
                                <div class="coupon-code-cell">
                                    <strong class="coupon-code"><?php echo htmlspecialchars($coupon['code']); ?></strong>
                                    <small class="text-muted d-block">Created: <?php echo $created->format('M d, Y'); ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="discount-badge">
                                    %<?php echo number_format($coupon['discount'], 2); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($coupon['company_name']): ?>
                                    <span class="company-badge"><?php echo htmlspecialchars($coupon['company_name']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">All Companies</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="usage-info">
                                    <strong><?php echo $coupon['times_used']; ?></strong> / <?php echo $coupon['usage_limit']; ?>
                                    <div class="progress mt-1" style="height: 6px;">
                                        <?php 
                                            $percentage = ($coupon['usage_limit'] > 0) 
                                                ? min(100, ($coupon['times_used'] / $coupon['usage_limit']) * 100) 
                                                : 0;
                                        ?>
                                        <div class="progress-bar" 
                                             role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%"
                                             aria-valuenow="<?php echo $percentage; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="expire-info">
                                    <?php echo $expire->format('M d, Y'); ?>
                                    <small class="d-block text-muted"><?php echo $expire->format('H:i'); ?></small>
                                </div>
                            </td>
                            <td>
                                <?php if ($is_expired): ?>
                                    <span class="badge bg-danger">Expired</span>
                                <?php elseif ($is_used_up): ?>
                                    <span class="badge bg-warning">Used Up</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick='editCoupon(<?php echo json_encode($coupon); ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteCoupon('<?php echo $coupon['id']; ?>', '<?php echo htmlspecialchars($coupon['code']); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
let couponToDelete = null;

// Set minimum datetime to current time
const now = new Date();
now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
document.getElementById('coupon-expire-date').min = now.toISOString().slice(0, 16);

function openCreateModal() {
    document.getElementById('couponModalTitle').textContent = 'Create New Coupon';
    document.getElementById('coupon-form').reset();
    document.getElementById('coupon-id').value = '';
    document.getElementById('form-action').value = 'create';
    
    // Reset min date
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('coupon-expire-date').min = now.toISOString().slice(0, 16);
    
    const modal = new bootstrap.Modal(document.getElementById('couponModal'));
    modal.show();
}

function editCoupon(coupon) {
    document.getElementById('couponModalTitle').textContent = 'Edit Coupon';
    document.getElementById('coupon-id').value = coupon.id;
    document.getElementById('coupon-code').value = coupon.code;
    document.getElementById('coupon-discount').value = coupon.discount;
    document.getElementById('coupon-company').value = coupon.company_id || '';
    document.getElementById('coupon-usage-limit').value = coupon.usage_limit;
    
    // Format expire_date for datetime-local input
    const expireDate = new Date(coupon.expire_date);
    expireDate.setMinutes(expireDate.getMinutes() - expireDate.getTimezoneOffset());
    document.getElementById('coupon-expire-date').value = expireDate.toISOString().slice(0, 16);
    
    document.getElementById('form-action').value = 'edit';
    
    const modal = new bootstrap.Modal(document.getElementById('couponModal'));
    modal.show();
}

function deleteCoupon(couponId, couponCode) {
    couponToDelete = couponId;
    document.getElementById('delete-coupon-code').textContent = `Code: ${couponCode}`;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

document.getElementById('save-coupon-btn').addEventListener('click', async function() {
    const form = document.getElementById('coupon-form');
    const formData = new FormData(form);
    
    // Validation
    const code = formData.get('code').trim().toUpperCase();
    const discount = parseFloat(formData.get('discount'));
    const usageLimit = parseInt(formData.get('usage_limit'));
    const expireDate = formData.get('expire_date');
    
    if (!code || !/^[A-Z0-9]+$/.test(code)) {
        showError('Coupon code must contain only letters and numbers.');
        return;
    }
    
    if (discount <= 0) {
        showError('Discount must be greater than zero.');
        return;
    }
    
    if (usageLimit < 1) {
        showError('Usage limit must be at least 1.');
        return;
    }
    
    if (new Date(expireDate) <= new Date()) {
        showError('Expiration date must be in the future.');
        return;
    }
    
    // Update code to uppercase
    formData.set('code', code);
    
    // Hide coupon modal
    const couponModal = bootstrap.Modal.getInstance(document.getElementById('couponModal'));
    couponModal.hide();
    
    // Show loading
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/admin/manage_coupons.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        loadingModal.hide();
        
        if (data.success) {
            showSuccess(data.message);
        } else {
            showError(data.message);
        }
    } catch (error) {
        loadingModal.hide();
        console.error('Error:', error);
        showError('Network error. Please try again.');
    }
});

document.getElementById('confirm-delete-btn').addEventListener('click', async function() {
    if (!couponToDelete) return;
    
    const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
    deleteModal.hide();
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/admin/manage_coupons.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                coupon_id: couponToDelete
            })
        });
        
        const data = await response.json();
        
        loadingModal.hide();
        
        if (data.success) {
            showSuccess(data.message);
        } else {
            showError(data.message);
        }
    } catch (error) {
        loadingModal.hide();
        console.error('Error:', error);
        showError('Network error. Please try again.');
    }
    
    couponToDelete = null;
});

function showSuccess(message) {
    document.getElementById('success-message').textContent = message;
    const modal = new bootstrap.Modal(document.getElementById('successModal'));
    modal.show();
}

function showError(message) {
    document.getElementById('error-message').textContent = message;
    const modal = new bootstrap.Modal(document.getElementById('errorModal'));
    modal.show();
}

// Auto-uppercase coupon code as user types
document.getElementById('coupon-code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>

<style>
.admin-coupons-container {
    max-width: 1400px;
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
    margin: 0;
}

.page-header h2 i {
    color: #3b82f6;
    margin-right: 10px;
}

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
}

.empty-state h3 {
    color: #e2e8f0;
    margin: 20px 0 10px;
}

.empty-state p {
    color: #a0aec0;
}

/* Table */
.table-responsive {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 12px;
    overflow: hidden;
}

.coupons-table {
    margin: 0;
    color: #e2e8f0;
}

.coupons-table thead {
    background: #1a202c;
}

.coupons-table thead th {
    color: #a0aec0;
    font-weight: 600;
    border-bottom: 2px solid #4a5568;
    padding: 15px;
    text-transform: uppercase;
    font-size: 0.85rem;
}

.coupons-table tbody tr {
    border-bottom: 1px solid #4a5568;
    transition: background 0.2s;
}

.coupons-table tbody tr:hover {
    background: #374151;
}

.coupons-table td {
    padding: 15px;
    vertical-align: middle;
}

.coupon-code {
    color: #60a5fa;
    font-family: 'Courier New', monospace;
    font-size: 1.1rem;
}

.discount-badge {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 1rem;
}

.company-badge {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 500;
}

.usage-info {
    color: #e2e8f0;
}

.progress {
    background: #1a202c;
}

.progress-bar {
    background: #3b82f6;
}

.expire-info {
    color: #e2e8f0;
}

.action-buttons {
    display: flex;
    gap: 8px;
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

.form-label {
    color: #e2e8f0;
}

.form-control, .form-select {
    background: #1a202c;
    border: 1px solid #4a5568;
    color: #e2e8f0;
}

.form-control:focus, .form-select:focus {
    background: #374151;
    border-color: #3b82f6;
    color: #e2e8f0;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

.form-select option {
    background: #2d3748;
}

.text-muted {
    color: #a0aec0 !important;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .coupons-table td, .coupons-table th {
        padding: 10px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>