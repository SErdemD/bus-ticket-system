<?php
// src/pages/profile.php

require_login();

// Block admin from accessing this page
if (is_admin()) {
    header('Location: /admin/dashboard');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_data = null;
$company_data = null;
$error = null;
$is_company_admin = is_company();

try {
    // Fetch user data
    $user_stmt = $pdo->prepare("
        SELECT id, full_name, email, role, balance, gender, company_id, created_at
        FROM User 
        WHERE id = ?
    ");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        $error = "User data not found.";
    }
    
    // If company admin, fetch company data
    if ($is_company_admin && $user_data['company_id']) {
        $company_stmt = $pdo->prepare("
            SELECT id, name, logo_path, created_at
            FROM Bus_Company 
            WHERE id = ?
        ");
        $company_stmt->execute([$user_data['company_id']]);
        $company_data = $company_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error = "Error loading profile.";
    error_log("Profile page error: " . $e->getMessage());
}
?>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="edit-profile-form">
                    <div class="mb-3">
                        <label for="full-name" class="form-label">Full Name *</label>
                        <input type="text" 
                               class="form-control" 
                               id="full-name" 
                               name="full_name" 
                               value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>"
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>"
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="gender" class="form-label">Gender</label>
                        <select class="form-select" id="gender" name="gender">
                            <option value="">Prefer not to say</option>
                            <option value="male" <?php echo ($user_data['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($user_data['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-profile-btn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="change-password-form">
                    <div class="mb-3">
                        <label for="current-password" class="form-label">Current Password *</label>
                        <input type="password" 
                               class="form-control" 
                               id="current-password" 
                               name="current_password" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new-password" class="form-label">New Password *</label>
                        <input type="password" 
                               class="form-control" 
                               id="new-password" 
                               name="new_password" 
                               required
                               minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm-password" class="form-label">Confirm New Password *</label>
                        <input type="password" 
                               class="form-control" 
                               id="confirm-password" 
                               name="confirm_password" 
                               required
                               minlength="6">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="change-password-btn">Change Password</button>
            </div>
        </div>
    </div>
</div>

<?php if ($is_company_admin): ?>
<!-- Edit Company Modal -->
<div class="modal fade" id="editCompanyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Company Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="edit-company-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="company-name" class="form-label">Company Name *</label>
                        <input type="text" 
                               class="form-control" 
                               id="company-name" 
                               name="company_name" 
                               value="<?php echo htmlspecialchars($company_data['name'] ?? ''); ?>"
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="company-logo" class="form-label">Company Logo</label>
                        <input type="file" 
                               class="form-control" 
                               id="company-logo" 
                               name="company_logo"
                               accept="image/*">
                        <small class="text-muted">Leave empty to keep current logo</small>
                    </div>
                    
                    <?php if ($company_data && $company_data['logo_path']): ?>
                    <div class="mb-3">
                        <label class="form-label">Current Logo</label>
                        <div>
                            <img src="<?php echo htmlspecialchars($company_data['logo_path']); ?>" 
                                 alt="Current logo" 
                                 style="max-width: 150px; border-radius: 8px; background: white; padding: 10px;">
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-company-btn">Save Changes</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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

<div class="profile-container">
    <div class="page-header mb-4">
        <h2><i class="bi bi-person-circle"></i> My Profile</h2>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- User Profile Card -->
            <div class="col-lg-6 mb-4">
                <div class="profile-card">
                    <div class="profile-card-header">
                        <h4><i class="bi bi-person-fill"></i> Personal Information</h4>
                        <button class="btn btn-sm btn-outline-primary" onclick="openEditProfileModal()">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                    </div>
                    
                    <div class="profile-card-body">
                        <div class="profile-avatar">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        
                        <div class="profile-info-group">
                            <label>Full Name</label>
                            <p><?php echo htmlspecialchars($user_data['full_name']); ?></p>
                        </div>
                        
                        <div class="profile-info-group">
                            <label>Email Address</label>
                            <p><?php echo htmlspecialchars($user_data['email']); ?></p>
                        </div>
                        
                        <div class="profile-info-group">
                            <label>Gender</label>
                            <p><?php echo $user_data['gender'] ? ucfirst($user_data['gender']) : 'Not specified'; ?></p>
                        </div>
                        
                        <div class="profile-info-group">
                            <label>Account Type</label>
                            <p>
                                <?php if ($user_data['role'] === 'company'): ?>
                                    <span class="badge bg-warning">Company Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Regular User</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="profile-info-group">
                            <label>Member Since</label>
                            <p><?php echo (new DateTime($user_data['created_at']))->format('F d, Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account Details Card -->
            <div class="col-lg-6 mb-4">
                <div class="profile-card">
                    <div class="profile-card-header">
                        <h4><i class="bi bi-shield-lock"></i> Account Security</h4>
                    </div>
                    
                    <div class="profile-card-body">
                        <div class="security-item">
                            <div class="security-info">
                                <i class="bi bi-lock-fill"></i>
                                <div>
                                    <strong>Password</strong>
                                    <small>Last changed: Not available</small>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="openChangePasswordModal()">
                                Change
                            </button>
                        </div>
                        
                        <div class="security-item">
                            <div class="security-info">
                                <i class="bi bi-envelope-fill"></i>
                                <div>
                                    <strong>Email Verification</strong>
                                    <small>Verified account</small>
                                </div>
                            </div>
                            <span class="badge bg-success">Verified</span>
                        </div>
                    </div>
                </div>
                
                <!-- Balance Card (Only for regular users) -->
                <?php if (!$is_company_admin): ?>
                <div class="profile-card mt-4">
                    <div class="profile-card-header">
                        <h4><i class="bi bi-wallet2"></i> Account Balance</h4>
                    </div>
                    
                    <div class="profile-card-body">
                        <div class="balance-display">
                            <span class="balance-label">Available Balance</span>
                            <h2 class="balance-amount">$<?php echo number_format($user_data['balance'], 2); ?></h2>
                        </div>
                        
                        <div class="balance-actions">
                            <a href="/add-funds" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle"></i> Add Funds
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Company Profile Card (Only for company admins) -->
        <?php if ($is_company_admin && $company_data): ?>
        <div class="row">
            <div class="col-12">
                <div class="profile-card">
                    <div class="profile-card-header">
                        <h4><i class="bi bi-building"></i> Company Profile</h4>
                        <button class="btn btn-sm btn-outline-primary" onclick="openEditCompanyModal()">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                    </div>
                    
                    <div class="profile-card-body">
                        <div class="company-profile-content">
                            <div class="company-logo-section">
                                <img src="<?php echo htmlspecialchars($company_data['logo_path'] ?? '/assets/images/default-logo.png'); ?>" 
                                     alt="Company Logo" 
                                     class="company-profile-logo">
                            </div>
                            
                            <div class="company-info-section">
                                <div class="profile-info-group">
                                    <label>Company Name</label>
                                    <h3><?php echo htmlspecialchars($company_data['name']); ?></h3>
                                </div>
                                
                                <div class="profile-info-group">
                                    <label>Company ID</label>
                                    <p class="text-muted"><?php echo htmlspecialchars(substr($company_data['id'], 0, 20)); ?></p>
                                </div>
                                
                                <div class="profile-info-group">
                                    <label>Established Since</label>
                                    <p><?php echo (new DateTime($company_data['created_at']))->format('F d, Y'); ?></p>
                                </div>
                                
                                <div class="profile-info-group">
                                    <label>Quick Actions</label>
                                    <div class="quick-links">
                                        <a href="/company/trips" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-bus-front"></i> Manage Trips
                                        </a>
                                        <a href="/company/bookings" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-ticket"></i> View Bookings
                                        </a>
                                        <a href="/company/coupons" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-ticket-perforated"></i> Coupons
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function openEditProfileModal() {
    const modal = new bootstrap.Modal(document.getElementById('editProfileModal'));
    modal.show();
}

function openChangePasswordModal() {
    document.getElementById('change-password-form').reset();
    const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
    modal.show();
}

<?php if ($is_company_admin): ?>
function openEditCompanyModal() {
    const modal = new bootstrap.Modal(document.getElementById('editCompanyModal'));
    modal.show();
}

document.getElementById('save-company-btn').addEventListener('click', async function() {
    const form = document.getElementById('edit-company-form');
    const formData = new FormData(form);
    
    const companyName = formData.get('company_name').trim();
    if (!companyName) {
        showError('Company name is required.');
        return;
    }
    
    const editModal = bootstrap.Modal.getInstance(document.getElementById('editCompanyModal'));
    editModal.hide();
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/company/update_profile.php', {
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
<?php endif; ?>

document.getElementById('save-profile-btn').addEventListener('click', async function() {
    const form = document.getElementById('edit-profile-form');
    const formData = new FormData(form);
    
    const fullName = formData.get('full_name').trim();
    const email = formData.get('email').trim();
    
    if (!fullName || !email) {
        showError('Full name and email are required.');
        return;
    }
    
    const editModal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
    editModal.hide();
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/update_profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                full_name: fullName,
                email: email,
                gender: formData.get('gender')
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
});

document.getElementById('change-password-btn').addEventListener('click', async function() {
    const form = document.getElementById('change-password-form');
    const formData = new FormData(form);
    
    const currentPassword = formData.get('current_password');
    const newPassword = formData.get('new_password');
    const confirmPassword = formData.get('confirm_password');
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        showError('All fields are required.');
        return;
    }
    
    if (newPassword.length < 6) {
        showError('New password must be at least 6 characters.');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showError('New passwords do not match.');
        return;
    }
    
    const passwordModal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
    passwordModal.hide();
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/change_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword
            })
        });
        
        const data = await response.json();
        
        loadingModal.hide();
        
        if (data.success) {
            showSuccess(data.message);
            form.reset();
        } else {
            showError(data.message);
        }
    } catch (error) {
        loadingModal.hide();
        console.error('Error:', error);
        showError('Network error. Please try again.');
    }
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
</script>

<style>
.profile-container {
    max-width: 1200px;
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

.profile-card {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 12px;
    overflow: hidden;
}

.profile-card-header {
    background: #1a202c;
    padding: 20px;
    border-bottom: 1px solid #4a5568;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.profile-card-header h4 {
    color: #e2e8f0;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-card-header h4 i {
    color: #60a5fa;
}

.profile-card-body {
    padding: 30px;
}

.profile-avatar {
    text-align: center;
    margin-bottom: 30px;
}

.profile-avatar i {
    font-size: 8rem;
    color: #60a5fa;
}

.profile-info-group {
    margin-bottom: 25px;
}

.profile-info-group label {
    color: #a0aec0;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    display: block;
    margin-bottom: 8px;
}

.profile-info-group p {
    color: #e2e8f0;
    font-size: 1.1rem;
    margin: 0;
}

.profile-info-group h3 {
    color: #e2e8f0;
    margin: 0;
}

.security-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #1a202c;
    border: 1px solid #4a5568;
    border-radius: 8px;
    margin-bottom: 15px;
}

.security-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.security-info i {
    font-size: 2rem;
    color: #60a5fa;
}

.security-info strong {
    color: #e2e8f0;
    display: block;
}

.security-info small {
    color: #a0aec0;
    display: block;
    margin-top: 3px;
}

.balance-display {
    text-align: center;
    padding: 30px 20px;
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    border-radius: 12px;
    margin-bottom: 20px;
}

.balance-label {
    color: #93c5fd;
    font-size: 0.875rem;
    display: block;
    margin-bottom: 10px;
}

.balance-amount {
    color: #fff;
    font-size: 3rem;
    font-weight: 700;
    margin: 0;
}

.company-profile-content {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 40px;
    align-items: start;
}

.company-logo-section {
    text-align: center;
}

.company-profile-logo {
    width: 200px;
    height: 200px;
    object-fit: contain;
    background: white;
    border-radius: 12px;
    padding: 20px;
    border: 2px solid #4a5568;
}

.quick-links {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
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
    .company-profile-content {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .company-logo-section {
        margin: 0 auto;
    }
    
    .quick-links {
        justify-content: center;
    }
}
</style>