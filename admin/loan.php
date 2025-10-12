<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$loan_handler = new Loan($pdo);
$employee_handler = new Employee($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_loan'])) {
    $amount = (float)$_POST['amount'];
    $installments = (int)$_POST['installments'];
    $monthly_payment = $installments > 0 ? $amount / $installments : 0;

    $data = [
        'employee_id'     => (int)$_POST['employee_id'],
        'amount'          => $amount,
        'installments'    => $installments,
        'monthly_payment' => $monthly_payment
    ];
    $loan_handler->create_loan($data);
    header('Location: loan.php?success=1');
    exit();
}

$loans = $loan_handler->get_all_loans();
$employees = $employee_handler->get_all();
$page_title = 'Loan Management';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Loan Management</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Loan created successfully!</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>All Loans</span>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createLoanModal">
            Create New Loan
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Loan Date</th>
                        <th>Amount</th>
                        <th>Installments</th>
                        <th>Monthly Payment</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></td>
                            <td><?php echo format_date($loan['loan_date'], 'M d, Y'); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($loan['amount'], 2)); ?></td>
                            <td><?php echo $loan['installments']; ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($loan['monthly_payment'], 2)); ?></td>
                            <td><span class="badge bg-<?php echo $loan['status'] == 'active' ? 'primary' : 'success'; ?>"><?php echo ucfirst($loan['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Loan Modal -->
<div class="modal fade" id="createLoanModal" tabindex="-1" aria-labelledby="createLoanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createLoanModalLabel">Create New Loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="loan.php" method="POST">
                    <input type="hidden" name="create_loan" value="1">
                    <div class="mb-3">
                        <label for="employee_id" class="form-label">Employee</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Loan Amount</label>
                        <input type="number" step="0.01" class="form-control" name="amount" required>
                    </div>
                    <div class="mb-3">
                        <label for="installments" class="form-label">Number of Installments</label>
                        <input type="number" class="form-control" name="installments" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Create Loan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../templates/footer.php';
?>
