<?php
// src/pages/admin/companies.php

require_login();

if (!is_admin()) {
    header('Location: /home');
    exit();
}

$companies = [];
$error = null;

try {
    $companies_stmt = $pdo->prepare("
        SELECT 
            bc.id,
            bc.name,
            bc.logo_path,
            bc.created_at,
            COUNT(DISTINCT t.id) as total_trips,
            COUNT(DISTINCT tk.id) as total_bookings
        FROM Bus_Company bc
        LEFT JOIN Trips t ON bc.id = t.company_id
        LEFT JOIN Tickets tk ON t.id = tk.trip_id AND tk.status = 'ACTIVE'
        GROUP BY bc.id
        ORDER BY bc.created_at DESC
    ");
    $companies_stmt->execute();
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error loading companies.";
    error_log("Admin companies page error: " . $e->getMessage());
}
?>

<!-- Create/Edit Company Modal -->
<div class="modal fade" id="companyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="companyModalTitle">Create New Company</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="company-form" enctype="multipart/form-data">
                    <input type="hidden" id="company-id" name="company_id">
                    <input type="hidden" id="form-action" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="company-name" class="form-label">Company Name *</label>
                        <input type="text" 
                               class="form-control" 
                               id="company-name" 
                               name="company_name" 
                               required
                               placeholder="Enter company name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="company-logo" class="form-label">Company Logo</label>
                        <input type="file" 
                               class="form-control" 
                               id="company-logo" 
                               name="company_logo"
                               accept="image/*">
                        <small class="text-muted">Leave empty to keep current logo (when editing)</small>
                    </div>
                    
                    <div id="current-logo-preview" style="display: none;" class="mb-3">
                        <label class="form-label">Current Logo</label>
                        <div>
                            <img id="current-logo-img" src="" alt="Current logo" style="max-width: 100px; border-radius: 8px;">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-company-btn">Save Company</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Company</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this company?</p>
                <p class="text-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i> 
                    <strong>Warning:</strong> This will also delete all trips and bookings associated with this company!
                </p>
                <p id="delete-company-name" class="fw-bold"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-btn">Delete Company</button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
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

<!-- Success/Error Modals -->
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

<div class="admin-companies-container">
    <div class="page-header mb-4">
        <div>
            <h2><i class="bi bi-building"></i> Manage Companies</h2>
            <p class="text-muted mb-0">Create, edit, and delete bus companies</p>
        </div>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="bi bi-plus-circle"></i> Create New Company
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif (empty($companies)): ?>
        <div class="empty-state">
            <i class="bi bi-building"></i>
            <h3>No Companies Yet</h3>
            <p>Create your first bus company to get started</p>
            <button class="btn btn-primary btn-lg mt-3" onclick="openCreateModal()">
                <i class="bi bi-plus-circle"></i> Create Company
            </button>
        </div>
    <?php else: ?>
        <div class="companies-grid">
            <?php foreach ($companies as $company): 
                $created = new DateTime($company['created_at']);
            ?>
                <div class="company-card">
                    <div class="company-header">
                        <img src="<?php echo htmlspecialchars($company['logo_path'] ?? '/assets/images/default-logo.png'); ?>" 
                             alt="<?php echo htmlspecialchars($company['name']); ?>" 
                             class="company-logo">
                        <div class="company-actions">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick='editCompany(<?php echo json_encode($company); ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteCompany('<?php echo $company['id']; ?>', '<?php echo htmlspecialchars($company['name']); ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="company-body">
                        <h4><?php echo htmlspecialchars($company['name']); ?></h4>
                        <p class="company-id">ID: <?php echo htmlspecialchars(substr($company['id'], 0, 12)); ?></p>
                        
                        <div class="company-stats">
                            <div class="stat-item">
                                <i class="bi bi-bus-front"></i>
                                <span><?php echo $company['total_trips']; ?> Trips</span>
                            </div>
                            <div class="stat-item">
                                <i class="bi bi-ticket"></i>
                                <span><?php echo $company['total_bookings']; ?> Bookings</span>
                            </div>
                        </div>
                        
                        <div class="company-footer">
                            <small class="text-muted">
                                <i class="bi bi-calendar"></i>
                                Created: <?php echo $created->format('M d, Y'); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
let companyToDelete = null;

function openCreateModal() {
    document.getElementById('companyModalTitle').textContent = 'Create New Company';
    document.getElementById('company-form').reset();
    document.getElementById('company-id').value = '';
    document.getElementById('form-action').value = 'create';
    document.getElementById('current-logo-preview').style.display = 'none';
    
    const modal = new bootstrap.Modal(document.getElementById('companyModal'));
    modal.show();
}

function editCompany(company) {
    document.getElementById('companyModalTitle').textContent = 'Edit Company';
    document.getElementById('company-id').value = company.id;
    document.getElementById('company-name').value = company.name;
    document.getElementById('form-action').value = 'edit';
    
    if (company.logo_path) {
        document.getElementById('current-logo-preview').style.display = 'block';
        document.getElementById('current-logo-img').src = company.logo_path;
    } else {
        document.getElementById('current-logo-preview').style.display = 'none';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('companyModal'));
    modal.show();
}

function deleteCompany(companyId, companyName) {
    companyToDelete = companyId;
    document.getElementById('delete-company-name').textContent = companyName;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

document.getElementById('save-company-btn').addEventListener('click', async function() {
    const form = document.getElementById('company-form');
    const formData = new FormData(form);
    
    // Validation
    const companyName = formData.get('company_name').trim();
    if (!companyName) {
        showError('Company name is required.');
        return;
    }
    
    // Hide company modal
    const companyModal = bootstrap.Modal.getInstance(document.getElementById('companyModal'));
    companyModal.hide();
    
    // Show loading
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/admin/manage_company.php', {
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
    if (!companyToDelete) return;
    
    const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
    deleteModal.hide();
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        const response = await fetch('/api/admin/manage_company.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                company_id: companyToDelete
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
    
    companyToDelete = null;
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
.admin-companies-container {
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

.companies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.company-card {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.company-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.company-header {
    background: #1a202c;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.company-logo {
    width: 60px;
    height: 60px;
    object-fit: contain;
    background: white;
    border-radius: 8px;
    padding: 8px;
}

.company-actions {
    display: flex;
    gap: 8px;
}

.company-body {
    padding: 20px;
}

.company-body h4 {
    color: #e2e8f0;
    margin: 0 0 5px 0;
}

.company-id {
    color: #a0aec0;
    font-size: 0.85rem;
    margin-bottom: 15px;
}

.company-stats {
    display: flex;
    gap: 20px;
    padding: 15px 0;
    border-top: 1px solid #4a5568;
    border-bottom: 1px solid #4a5568;
    margin-bottom: 15px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #e2e8f0;
}

.stat-item i {
    color: #60a5fa;
}

.company-footer small {
    color: #a0aec0;
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

.form-control {
    background: #1a202c;
    border: 1px solid #4a5568;
    color: #e2e8f0;
}

.form-control:focus {
    background: #374151;
    border-color: #3b82f6;
    color: #e2e8f0;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
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
    
    .companies-grid {
        grid-template-columns: 1fr;
    }
}
</style>