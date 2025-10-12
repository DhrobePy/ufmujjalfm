<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$employee_handler = new Employee($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_loan'])) {
        $employee_id = (int)$_POST['employee_id'];
        $amount = (float)$_POST['amount'];
        $installment_type = $_POST['installment_type'];
        
        if ($installment_type === 'fixed') {
            $installments = (int)$_POST['installments'];
            $monthly_payment = $amount / $installments;
        } else {
            $installments = 0; // For random EMI
            $monthly_payment = 0;
        }
        
        $stmt = $pdo->prepare('
            INSERT INTO loans (employee_id, loan_date, amount, installments, monthly_payment, installment_type, status) 
            VALUES (?, CURDATE(), ?, ?, ?, ?, \'active\')
        ');
        $stmt->execute([$employee_id, $amount, $installments, $monthly_payment, $installment_type]);
        
        header('Location: loan_enhanced.php?success=1');
        exit();
    }
    
    if (isset($_POST['add_installment'])) {
        $loan_id = (int)$_POST['loan_id'];
        $amount = (float)$_POST['installment_amount'];
        
        $stmt = $pdo->prepare('
            INSERT INTO loan_installments (loan_id, payment_date, amount) 
            VALUES (?, CURDATE(), ?)
        ');
        $stmt->execute([$loan_id, $amount]);
        
        // Check if loan is fully paid
        $stmt = $pdo->prepare('
            SELECT l.amount, SUM(li.amount) as total_paid
            FROM loans l
            LEFT JOIN loan_installments li ON l.id = li.loan_id
            WHERE l.id = ?
            GROUP BY l.id
        ');
        $stmt->execute([$loan_id]);
        $loan_status = $stmt->fetch();
        
        if ($loan_status && $loan_status['total_paid'] >= $loan_status['amount']) {
            $stmt = $pdo->prepare('UPDATE loans SET status = \'paid\' WHERE id = ?');
            $stmt->execute([$loan_id]);
        }
        
        header('Location: loan_enhanced.php?installment_added=1');
        exit();
    }
}

// Get all loans with employee details
$query = "
    SELECT l.*, e.first_name, e.last_name, p.name as position_name,
           COALESCE(SUM(li.amount), 0) as total_paid,
           (l.amount - COALESCE(SUM(li.amount), 0)) as remaining_balance
    FROM loans l
    JOIN employees e ON l.employee_id = e.id
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN loan_installments li ON l.id = li.loan_id
    GROUP BY l.id
    ORDER BY l.loan_date DESC
";

$stmt = $pdo->query($query);
$loans = $stmt->fetchAll();

$employees = $employee_handler->get_all_active();

$page_title = 'Enhanced Loan Management';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Enhanced Loan Management</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Loan created successfully!</div>
<?php endif; ?>

<?php if (isset($_GET['installment_added'])): ?>
    <div class="alert alert-success">Installment payment recorded successfully!</div>
<?php endif; ?>

<!-- Create New Loan -->
<div class="card mb-4">
    <div class="card-header">Create New Loan</div>
    <div class="card-body">
        <form action="loan_enhanced.php" method="POST" class="row g-3">
            <div class="col-md-4">
                <label for="employee_id" class="form-label">Employee</label>
                <select class="form-select employee-search" name="employee_id" id="employee_id" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['id']; ?>">
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . ($employee['position_name'] ?? 'No Position')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="amount" class="form-label">Loan Amount</label>
                <input type="number" step="0.01" class="form-control" name="amount" id="amount" required>
            </div>
            <div class="col-md-3">
                <label for="installment_type" class="form-label">Installment Type</label>
                <select class="form-select" name="installment_type" id="installment_type" required onchange="toggleInstallmentOptions()">
                    <option value="">Select Type</option>
                    <option value="fixed">Fixed EMI</option>
                    <option value="random">Random EMI</option>
                </select>
            </div>
            <div class="col-md-2" id="installments_field" style="display: none;">
                <label for="installments" class="form-label">Number of Installments</label>
                <input type="number" class="form-control" name="installments" id="installments" min="1">
            </div>
            <div class="col-md-12">
                <div id="installment_info" class="alert alert-info" style="display: none;"></div>
                <button type="submit" name="create_loan" class="btn btn-success">Create Loan</button>
            </div>
        </form>
    </div>
</div>

<!-- Loans List -->
<div class="card">
    <div class="card-header">All Loans</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Position</th>
                        <th>Loan Amount</th>
                        <th>Type</th>
                        <th>Total Paid</th>
                        <th>Remaining</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($loan['position_name'] ?? 'N/A'); ?></td>
                            <td><strong>$<?php echo number_format($loan['amount'], 2); ?></strong></td>
                            <td>
                                <span class="badge bg-<?php echo $loan['installment_type'] === 'fixed' ? 'primary' : 'warning'; ?>">
                                    <?php echo ucfirst($loan['installment_type']); ?> EMI
                                </span>
                                <?php if ($loan['installment_type'] === 'fixed'): ?>
                                    <br><small>$<?php echo number_format($loan['monthly_payment'], 2); ?>/month</small>
                                <?php endif; ?>
                            </td>
                            <td>$<?php echo number_format($loan['total_paid'], 2); ?></td>
                            <td>
                                <?php $remaining = $loan['remaining_balance']; ?>
                                <strong class="<?php echo $remaining <= 0 ? 'text-success' : 'text-danger'; ?>">
                                    $<?php echo number_format($remaining, 2); ?>
                                </strong>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $loan['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($loan['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($loan['status'] === 'active' && $remaining > 0): ?>
                                    <button type="button" class="btn btn-sm btn-primary" 
                                            onclick="showInstallmentModal(<?php echo $loan['id']; ?>, '<?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?>', <?php echo $remaining; ?>)">
                                        Add Payment
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-info" 
                                        onclick="showInstallmentHistory(<?php echo $loan['id']; ?>)">
                                    History
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Installment Modal -->
<div class="modal fade" id="installmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Loan Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="loan_enhanced.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="loan_id" id="modal_loan_id">
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <input type="text" class="form-control" id="modal_employee_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remaining Balance</label>
                        <input type="text" class="form-control" id="modal_remaining_balance" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="installment_amount" class="form-label">Payment Amount</label>
                        <input type="number" step="0.01" class="form-control" name="installment_amount" id="installment_amount" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_installment" class="btn btn-primary">Add Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Installment History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="history_content">Loading...</div>
            </div>
        </div>
    </div>
</div>

<style>
.employee-search {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6'/%3e%3c/svg%3e");
}
</style>

<script>
function toggleInstallmentOptions() {
    const type = document.getElementById('installment_type').value;
    const installmentsField = document.getElementById('installments_field');
    const installmentInfo = document.getElementById('installment_info');
    
    if (type === 'fixed') {
        installmentsField.style.display = 'block';
        installmentInfo.style.display = 'block';
        installmentInfo.innerHTML = '<strong>Fixed EMI:</strong> Equal monthly payments will be calculated automatically.';
        document.getElementById('installments').required = true;
    } else if (type === 'random') {
        installmentsField.style.display = 'none';
        installmentInfo.style.display = 'block';
        installmentInfo.innerHTML = '<strong>Random EMI:</strong> Employee can pay any amount at any time until loan is fully paid.';
        document.getElementById('installments').required = false;
    } else {
        installmentsField.style.display = 'none';
        installmentInfo.style.display = 'none';
    }
}

function showInstallmentModal(loanId, employeeName, remainingBalance) {
    document.getElementById('modal_loan_id').value = loanId;
    document.getElementById('modal_employee_name').value = employeeName;
    document.getElementById('modal_remaining_balance').value = '$' + remainingBalance.toFixed(2);
    document.getElementById('installment_amount').max = remainingBalance;
    
    const modal = new bootstrap.Modal(document.getElementById('installmentModal'));
    modal.show();
}

function showInstallmentHistory(loanId) {
    const modal = new bootstrap.Modal(document.getElementById('historyModal'));
    modal.show();
    
    // Fetch installment history via AJAX
    fetch('loan_history.php?loan_id=' + loanId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('history_content').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('history_content').innerHTML = 'Error loading history.';
        });
}
</script>

<?php
include __DIR__ . '/../templates/footer.php';
?>
