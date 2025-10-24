<?php
// src/pages/admin/users.php

require_login();

if (!is_admin()) {
    header('Location: /home');
    exit();
}

$users = [];
$companies = [];
$error = null;

try {
    // Get all users
    $users_stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.role,
            u.balance,
            u.company_id,
            u.created_at,
            bc.name as company_name
        FROM User u
        LEFT JOIN Bus_Company bc ON u.company_id = bc.id
        ORDER BY u.created_at DESC
    ");
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all companies for dropdown
    $companies_stmt = $pdo->prepare("SELECT id, name FROM Bus_Company ORDER BY name");
    $companies_stmt->execute();
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error loading users.";
    error_log("Admin users page error: " . $e->getMessage());
}
?>

<!-- Add Company Admin Modal -->
<div class="modal fade" id="addCompanyAdminModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Company Admin Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="add-company-admin-form">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new-full-name" class="form-label">Full Name *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="new-full-name" 
                                   name="full_name" 
                                   required
                                   minlength="2"
                                   maxlength="100">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="new-email" class="form-label">Email Address *</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="new-email" 
                                   name="email" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new-password" class="form-label">Password *</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="new-password" 
                                   name="password" 
                                   required
                                   minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="new-gender" class="form-label">Gender</label>
                            <select class="form-select" id="new-gender" name="gender">
                                <option value="" disabled selected>Select the account owner’s gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new-company-select" class="form-label">Assign to Company *</label>
                        <select class="form-select" id="new-company-select" name="company_id" required>
                            <option value="">Choose a company...</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo htmlspecialchars($company['id']); ?>">
                                    <?php echo htmlspecialchars($company['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> This will create a new user account with "Company Admin" role and link them to the selected company. The user will be able to manage trips, bookings, and coupons for their company.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-add-admin-btn">
                    <i class="bi bi-plus-circle"></i> Create Company Admin
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Company Modal -->
<div class="modal fade" id="assignCompanyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign User to Company</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">User</label>
                    <input type="text" class="form-control" id="assign-user-name" readonly>
                    <input type="hidden" id="assign-user-id">
                </div>
                
                <div class="mb-3">
                    <label for="assign-company-select" class="form-label">Select Company *</label>
                    <select class="form-select" id="assign-company-select" required>
                        <option value="">Choose a company...</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo htmlspecialchars($company['id']); ?>">
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    This will change the user's role to "company" and link them to the selected company.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-assign-btn">Assign to Company</button>
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

<div class="admin-users-container">
    <div class="page-header mb-4">
        <div>
            <h2><i class="bi bi-people"></i> Manage Users</h2>
            <p class="text-muted mb-0">View users and manage company roles</p>
        </div>
        <button class="btn btn-primary" onclick="openAddCompanyAdminModal()">
            <i class="bi bi-plus-circle"></i> Add Company Admin
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>
        <!-- Filter Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-filter="all" onclick="filterUsers('all')">
                    All Users <span class="badge bg-primary ms-2"><?php echo count($users); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-filter="user" onclick="filterUsers('user')">
                    Regular Users <span class="badge bg-success ms-2">
                        <?php echo count(array_filter($users, fn($u) => $u['role'] === 'user')); ?>
                    </span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-filter="company" onclick="filterUsers('company')">
                    Company Admins <span class="badge bg-warning ms-2">
                        <?php echo count(array_filter($users, fn($u) => $u['role'] === 'company')); ?>
                    </span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-filter="admin" onclick="filterUsers('admin')">
                    Admins <span class="badge bg-danger ms-2">
                        <?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?>
                    </span>
                </button>
            </li>
        </ul>

        <!-- Users Table -->
        <div class="table-responsive">
            <table class="table users-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Company</th>
                        <th>Balance</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    <?php foreach ($users as $user): 
                        $created = new DateTime($user['created_at']);
                    ?>
                        <tr data-role="<?php echo $user['role']; ?>">
                            <td>
                                <div class="user-info">
                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                    <small class="text-muted d-block">ID: <?php echo htmlspecialchars(substr($user['id'], 0, 12)); ?></small>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="badge bg-danger">Admin</span>
                                <?php elseif ($user['role'] === 'company'): ?>
                                    <span class="badge bg-warning">Company Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-success">User</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'company' && $user['company_name']): ?>
                                    <span class="company-badge"><?php echo htmlspecialchars($user['company_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>$<?php echo number_format($user['balance'], 2); ?></td>
                            <td><?php echo $created->format('M d, Y'); ?></td>
                            <td>
                                <?php if ($user['role'] === 'user'): ?>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="openAssignModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                        <i class="bi bi-building"></i> Assign to Company
                                    </button>
                                <?php elseif ($user['role'] === 'company'): ?>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="removeFromCompany('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                        <i class="bi bi-x-circle"></i> Remove from Company
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function filterUsers(role) {
    const rows = document.querySelectorAll('#users-table-body tr');
    const buttons = document.querySelectorAll('.nav-link');
    
    // Update active button
    buttons.forEach(btn => {
        if (btn.getAttribute('data-filter') === role) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    // Filter rows
    rows.forEach(row => {
        if (role === 'all' || row.getAttribute('data-role') === role) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function openAddCompanyAdminModal() {
    document.getElementById('add-company-admin-form').reset();
    const modal = new bootstrap.Modal(document.getElementById('addCompanyAdminModal'));
    modal.show();
}

document.getElementById('confirm-add-admin-btn').addEventListener('click', async function() {
    const form = document.getElementById('add-company-admin-form');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    const data = {
        action: 'create_company_admin',
        full_name: formData.get('full_name'),
        email: formData.get('email'),
        password: formData.get('password'),
        gender: formData.get('gender'),
        company_id: formData.get('company_id')
    };
    
    const addModal = bootstrap.Modal.getInstance(document.getElementById('addCompanyAdminModal'));
    addModal.hide();
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/admin/manage_users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        loadingModal.hide();
        
        if (result.success) {
            showSuccess(result.message);
        } else {
            showError(result.message);
        }
    } catch (error) {
        loadingModal.hide();
        console.error('Error:', error);
        showError('Network error. Please try again.');
    }
});

function openAssignModal(userId, userName) {
    document.getElementById('assign-user-id').value = userId;
    document.getElementById('assign-user-name').value = userName;
    document.getElementById('assign-company-select').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('assignCompanyModal'));
    modal.show();
}

document.getElementById('confirm-assign-btn').addEventListener('click', async function() {
    const userId = document.getElementById('assign-user-id').value;
    const companyId = document.getElementById('assign-company-select').value;
    
    if (!companyId) {
        showError('Please select a company.');
        return;
    }
    
    const assignModal = bootstrap.Modal.getInstance(document.getElementById('assignCompanyModal'));
    assignModal.hide();
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/admin/manage_users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'assign_company',
                user_id: userId,
                company_id: companyId
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

async function removeFromCompany(userId, userName) {
    if (!confirm(`Are you sure you want to remove ${userName} from their company? Their role will be changed back to regular user.`)) {
        return;
    }
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/admin/manage_users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'remove_company',
                user_id: userId
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
}

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
.admin-users-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header h2 {
    color: #e2e8f0;
    margin: 0;
}

.page-header h2 i {
    color: #3b82f6;
    margin-right: 10px;
}

/* Nav Tabs */
.nav-tabs {
    border-bottom: 2px solid #4a5568;
}

.nav-tabs .nav-link {
    color: #a0aec0;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 12px 20px;
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

/* Table */
.table-responsive {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 12px;
    overflow: hidden;
}

.users-table {
    margin: 0;
    color: #e2e8f0;
}

.users-table thead {
    background: #1a202c;
}

.users-table thead th {
    color: #a0aec0;
    font-weight: 600;
    border-bottom: 2px solid #4a5568;
    padding: 15px;
    text-transform: uppercase;
    font-size: 0.85rem;
}

.users-table tbody tr {
    border-bottom: 1px solid #4a5568;
    transition: background 0.2s;
}

.users-table tbody tr:hover {
    background: #374151;
}

.users-table td {
    padding: 15px;
    vertical-align: middle;
}

.user-info strong {
    color: #e2e8f0;
    display: block;
}

.company-badge {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 500;
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

.form-control:read-only {
    background: #374151;
    cursor: not-allowed;
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
        align-items: flex-start;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .users-table td, .users-table th {
        padding: 10px;
    }
}
</style>