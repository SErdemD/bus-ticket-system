<?php
// src/pages/add-funds.php

require_login();

// Only regular users can add funds (not admin or company)
if (is_admin() || is_company()) {
    header('Location: /home');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_data = null;
$error = null;

try {
    // Fetch user data
    $user_stmt = $pdo->prepare("SELECT id, full_name, email, balance FROM User WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        $error = "User data not found.";
    }
    
} catch (PDOException $e) {
    $error = "Error loading user data.";
    error_log("Add funds page error: " . $e->getMessage());
}

// Predefined amounts
$quick_amounts = [10, 25, 50, 100, 250, 500];
?>

<!-- Payment Method Modal -->
<div class="modal fade" id="paymentMethodModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Payment Method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="payment-amount-display mb-4">
                    <h3>Amount to Add</h3>
                    <h2 class="amount-value">$<span id="payment-amount-display">0.00</span></h2>
                </div>
                
                <div class="payment-methods">
                    <div class="payment-method-card" onclick="selectPaymentMethod('credit_card')">
                        <div class="payment-icon">
                            <i class="bi bi-credit-card"></i>
                        </div>
                        <div class="payment-info">
                            <h5>Credit/Debit Card</h5>
                            <p>Visa, Mastercard, American Express</p>
                        </div>
                        <i class="bi bi-chevron-right"></i>
                    </div>
                    
                    <div class="payment-method-card" onclick="selectPaymentMethod('paypal')">
                        <div class="payment-icon">
                            <i class="bi bi-paypal"></i>
                        </div>
                        <div class="payment-info">
                            <h5>PayPal</h5>
                            <p>Pay with your PayPal account</p>
                        </div>
                        <i class="bi bi-chevron-right"></i>
                    </div>
                    
                    <div class="payment-method-card" onclick="selectPaymentMethod('bank_transfer')">
                        <div class="payment-icon">
                            <i class="bi bi-bank"></i>
                        </div>
                        <div class="payment-info">
                            <h5>Bank Transfer</h5>
                            <p>Direct bank account transfer</p>
                        </div>
                        <i class="bi bi-chevron-right"></i>
                    </div>
                    
                    <div class="payment-method-card" onclick="selectPaymentMethod('crypto')">
                        <div class="payment-icon">
                            <i class="bi bi-currency-bitcoin"></i>
                        </div>
                        <div class="payment-info">
                            <h5>Cryptocurrency</h5>
                            <p>Bitcoin, Ethereum, and more</p>
                        </div>
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i>
                    <strong>Demo Mode:</strong> This is a demo payment system. Funds will be added instantly without actual payment processing.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Processing Modal -->
<div class="modal fade" id="processingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary mb-3" style="width: 4rem; height: 4rem;"></div>
                <h4>Processing Payment...</h4>
                <p class="text-muted">Please wait while we process your transaction</p>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                <h3 class="mt-3 text-success">Payment Successful!</h3>
                <p id="success-message" class="mb-3"></p>
                <div class="success-details">
                    <div class="detail-row">
                        <span>Amount Added:</span>
                        <strong id="added-amount">$0.00</strong>
                    </div>
                    <div class="detail-row">
                        <span>New Balance:</span>
                        <strong class="text-success" id="new-balance">$0.00</strong>
                    </div>
                </div>
                <button type="button" class="btn btn-success btn-lg w-100 mt-4" onclick="location.reload()">
                    <i class="bi bi-check-circle"></i> Done
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
                <i class="bi bi-x-circle-fill text-danger" style="font-size: 5rem;"></i>
                <h3 class="mt-3 text-danger">Payment Failed</h3>
                <p id="error-message"></p>
                <button type="button" class="btn btn-primary mt-3" data-bs-dismiss="modal">Try Again</button>
            </div>
        </div>
    </div>
</div>

<div class="add-funds-container">
    <div class="page-header mb-4">
        <div>
            <h2><i class="bi bi-wallet2"></i> Add Funds</h2>
            <p class="text-muted mb-0">Add money to your account balance</p>
        </div>
        <a href="/profile" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Profile
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Current Balance Card -->
            <div class="col-lg-4 mb-4">
                <div class="balance-card">
                    <div class="balance-card-header">
                        <i class="bi bi-wallet2"></i>
                        <span>Current Balance</span>
                    </div>
                    <div class="balance-amount">
                        $<?php echo number_format($user_data['balance'], 2); ?>
                    </div>
                    <div class="balance-info">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($user_data['full_name']); ?>
                    </div>
                </div>
            </div>

            <!-- Add Funds Form -->
            <div class="col-lg-8 mb-4">
                <div class="funds-card">
                    <h4 class="mb-4"><i class="bi bi-cash-stack"></i> Select Amount</h4>
                    
                    <!-- Quick Amount Buttons -->
                    <div class="quick-amounts mb-4">
                        <?php foreach ($quick_amounts as $amount): ?>
                            <button type="button" 
                                    class="quick-amount-btn" 
                                    onclick="selectQuickAmount(<?php echo $amount; ?>)">
                                $<?php echo $amount; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Custom Amount Input -->
                    <div class="custom-amount-section">
                        <label for="custom-amount" class="form-label">Or enter custom amount</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   class="form-control" 
                                   id="custom-amount" 
                                   placeholder="0.00" 
                                   min="1" 
                                   max="10000"
                                   step="0.01"
                                   oninput="updateSelectedAmount()">
                        </div>
                        <small class="text-muted">Minimum: $1.00 | Maximum: $10,000.00</small>
                    </div>
                    
                    <!-- Selected Amount Display -->
                    <div class="selected-amount-display" id="selected-amount-section" style="display: none;">
                        <div class="selected-label">Amount to Add:</div>
                        <div class="selected-value">$<span id="selected-amount">0.00</span></div>
                    </div>
                    
                    <!-- Proceed Button -->
                    <button type="button" 
                            class="btn btn-primary btn-lg w-100" 
                            id="proceed-btn"
                            onclick="proceedToPayment()"
                            disabled>
                        <i class="bi bi-credit-card"></i> Proceed to Payment
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="row">
            <div class="col-12">
                <div class="transactions-card">
                    <h4 class="mb-3"><i class="bi bi-clock-history"></i> Recent Transactions</h4>
                    <div class="table-responsive">
                        <table class="table transactions-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                        <p class="mb-0 mt-2">No recent transactions</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
let selectedAmount = 0;

function selectQuickAmount(amount) {
    selectedAmount = amount;
    document.getElementById('custom-amount').value = '';
    
    // Update UI
    document.querySelectorAll('.quick-amount-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    updateUI();
}

function updateSelectedAmount() {
    const customInput = document.getElementById('custom-amount');
    selectedAmount = parseFloat(customInput.value) || 0;
    
    // Remove active state from quick buttons
    document.querySelectorAll('.quick-amount-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    updateUI();
}

function updateUI() {
    const selectedSection = document.getElementById('selected-amount-section');
    const selectedAmountSpan = document.getElementById('selected-amount');
    const proceedBtn = document.getElementById('proceed-btn');
    
    if (selectedAmount >= 1 && selectedAmount <= 10000) {
        selectedSection.style.display = 'block';
        selectedAmountSpan.textContent = selectedAmount.toFixed(2);
        proceedBtn.disabled = false;
    } else {
        selectedSection.style.display = 'none';
        proceedBtn.disabled = true;
    }
}

function proceedToPayment() {
    if (selectedAmount < 1 || selectedAmount > 10000) {
        showError('Please select an amount between $1.00 and $10,000.00');
        return;
    }
    
    document.getElementById('payment-amount-display').textContent = selectedAmount.toFixed(2);
    const modal = new bootstrap.Modal(document.getElementById('paymentMethodModal'));
    modal.show();
}

async function selectPaymentMethod(method) {
    // Close payment method modal
    const paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentMethodModal'));
    paymentModal.hide();
    
    // Show processing modal
    const processingModal = new bootstrap.Modal(document.getElementById('processingModal'));
    processingModal.show();
    
    try {
        const response = await fetch('/api/add_funds.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                amount: selectedAmount,
                payment_method: method
            })
        });
        
        const data = await response.json();
        
        // Simulate processing delay for demo
        await new Promise(resolve => setTimeout(resolve, 1500));
        
        processingModal.hide();
        
        if (data.success) {
            document.getElementById('success-message').textContent = data.message;
            document.getElementById('added-amount').textContent = '$' + selectedAmount.toFixed(2);
            document.getElementById('new-balance').textContent = '$' + parseFloat(data.new_balance).toFixed(2);
            
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        } else {
            showError(data.message);
        }
    } catch (error) {
        processingModal.hide();
        console.error('Error:', error);
        showError('Network error. Please try again.');
    }
}

function showError(message) {
    document.getElementById('error-message').textContent = message;
    const modal = new bootstrap.Modal(document.getElementById('errorModal'));
    modal.show();
}
</script>

<style>
.add-funds-container {
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

/* Balance Card */
.balance-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    color: white;
    height: 100%;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.balance-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 20px;
}

.balance-card-header i {
    font-size: 1.5rem;
}

.balance-amount {
    font-size: 3.5rem;
    font-weight: 700;
    margin: 20px 0;
}

.balance-info {
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0.9;
    margin-top: 20px;
}

/* Funds Card */
.funds-card {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 15px;
    padding: 30px;
}

.funds-card h4 {
    color: #e2e8f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.funds-card h4 i {
    color: #60a5fa;
}

/* Quick Amount Buttons */
.quick-amounts {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.quick-amount-btn {
    background: #374151;
    border: 2px solid #4a5568;
    color: #e2e8f0;
    padding: 20px;
    font-size: 1.5rem;
    font-weight: 600;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.quick-amount-btn:hover {
    background: #4a5568;
    border-color: #60a5fa;
    transform: translateY(-2px);
}

.quick-amount-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    color: white;
}

/* Custom Amount */
.custom-amount-section {
    margin: 30px 0;
}

.custom-amount-section .form-label {
    color: #e2e8f0;
    font-weight: 500;
    margin-bottom: 10px;
}

.input-group-text {
    background: #374151;
    border: 2px solid #4a5568;
    color: #e2e8f0;
    font-size: 1.5rem;
    font-weight: 600;
}

.custom-amount-section .form-control {
    background: #374151;
    border: 2px solid #4a5568;
    color: #e2e8f0;
    font-size: 1.5rem;
    font-weight: 600;
    padding: 15px;
}

.custom-amount-section .form-control:focus {
    background: #374151;
    border-color: #667eea;
    color: #e2e8f0;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

/* Selected Amount Display */
.selected-amount-display {
    background: rgba(59, 130, 246, 0.1);
    border: 2px solid #3b82f6;
    border-radius: 12px;
    padding: 20px;
    margin: 30px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.selected-label {
    color: #a0aec0;
    font-size: 1rem;
}

.selected-value {
    color: #60a5fa;
    font-size: 2rem;
    font-weight: 700;
}

/* Payment Methods */
.payment-amount-display {
    text-align: center;
    padding: 20px;
    background: rgba(59, 130, 246, 0.1);
    border-radius: 12px;
}

.payment-amount-display h3 {
    color: #a0aec0;
    font-size: 1rem;
    margin: 0;
}

.payment-amount-display .amount-value {
    color: #60a5fa;
    font-size: 3rem;
    font-weight: 700;
    margin: 10px 0 0 0;
}

.payment-methods {
    display: grid;
    gap: 15px;
}

.payment-method-card {
    background: #374151;
    border: 2px solid #4a5568;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    cursor: pointer;
    transition: all 0.3s;
}

.payment-method-card:hover {
    background: #4a5568;
    border-color: #60a5fa;
    transform: translateX(5px);
}

.payment-icon {
    width: 60px;
    height: 60px;
    background: rgba(59, 130, 246, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #60a5fa;
}

.payment-info {
    flex: 1;
}

.payment-info h5 {
    color: #e2e8f0;
    margin: 0 0 5px 0;
}

.payment-info p {
    color: #a0aec0;
    margin: 0;
    font-size: 0.9rem;
}

.payment-method-card > .bi-chevron-right {
    color: #60a5fa;
    font-size: 1.5rem;
}

/* Transactions Card */
.transactions-card {
    background: #2d3748;
    border: 2px solid #4a5568;
    border-radius: 15px;
    padding: 30px;
}

.transactions-card h4 {
    color: #e2e8f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.transactions-card h4 i {
    color: #60a5fa;
}

.transactions-table {
    color: #e2e8f0;
    margin: 0;
}

.transactions-table thead th {
    background: #1a202c;
    color: #a0aec0;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    border: none;
}

.transactions-table tbody td {
    border-color: #4a5568;
}

/* Success Details */
.success-details {
    background: rgba(16, 185, 129, 0.1);
    border: 2px solid #10b981;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(16, 185, 129, 0.2);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row span {
    color: #a0aec0;
}

.detail-row strong {
    color: #e2e8f0;
    font-size: 1.2rem;
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

.modal-title {
    color: #e2e8f0;
}

.btn-close {
    filter: invert(1);
}

.text-muted {
    color: #a0aec0 !important;
}

/* Responsive */
@media (max-width: 768px) {
    .quick-amounts {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .page-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}
</style>