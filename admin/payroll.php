<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$payroll_handler = new Payroll($pdo);
$employee_handler = new Employee($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_payroll'])) {
    $employee_id = (int)$_POST['employee_id'];
    $start_date = sanitize_input($_POST['start_date']);
    $end_date = sanitize_input($_POST['end_date']);

    $allowances = [];
    if (isset($_POST['allowance_name'])) {
        foreach ($_POST['allowance_name'] as $key => $name) {
            if (!empty($name)) {
                $allowances[] = ['name' => sanitize_input($name), 'amount' => (float)$_POST['allowance_amount'][$key]];
            }
        }
    }

    $deductions = [];
    if (isset($_POST['deduction_name'])) {
        foreach ($_POST['deduction_name'] as $key => $name) {
            if (!empty($name)) {
                $deductions[] = ['name' => sanitize_input($name), 'amount' => (float)$_POST['deduction_amount'][$key]];
            }
        }
    }

    $payroll_handler->run_payroll($employee_id, $start_date, $end_date, $allowances, $deductions);
    header('Location: payroll.php?success=1');
    exit();
}

$payroll_history = $payroll_handler->get_payroll_history();
$employees = $employee_handler->get_all();
$page_title = 'Payroll';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Payroll Management</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Payroll run successfully!</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Payroll History</span>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#runPayrollModal">
            Run New Payroll
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Pay Period</th>
                        <th>Gross Salary</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payroll_history as $payroll): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></td>
                            <td><?php echo format_date($payroll['pay_period_start'], 'M d, Y') . ' - ' . format_date($payroll['pay_period_end'], 'M d, Y'); ?></td>
                            <td><?php echo htmlspecialchars($payroll['gross_salary']); ?></td>
                            <td><?php echo htmlspecialchars($payroll['deductions']); ?></td>
                            <td><?php echo htmlspecialchars($payroll['net_salary']); ?></td>
                            <td><span class="badge bg-success"><?php echo ucfirst($payroll['status']); ?></span></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-info">View Payslip</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Run Payroll Modal -->
<div class="modal fade" id="runPayrollModal" tabindex="-1" aria-labelledby="runPayrollModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="runPayrollModalLabel">Run New Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="payroll.php" method="POST">
                    <input type="hidden" name="run_payroll" value="1">
                    <div class="mb-3">
                        <label for="employee_id" class="form-label">Employee</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Pay Period Start</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">Pay Period End</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>

                    <h5>Allowances</h5>
                    <div id="allowances-container">
                        <div class="row mb-2">
                            <div class="col-md-6"><input type="text" name="allowance_name[]" class="form-control" placeholder="Allowance Name"></div>
                            <div class="col-md-6"><input type="number" step="0.01" name="allowance_amount[]" class="form-control" placeholder="Amount"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-allowance">Add Allowance</button>

                    <h5 class="mt-4">Deductions</h5>
                    <div id="deductions-container">
                        <div class="row mb-2">
                            <div class="col-md-6"><input type="text" name="deduction_name[]" class="form-control" placeholder="Deduction Name"></div>
                            <div class="col-md-6"><input type="number" step="0.01" name="deduction_amount[]" class="form-control" placeholder="Amount"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-deduction">Add Deduction</button>

                    <div class="modal-footer mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Run Payroll</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('add-allowance').addEventListener('click', function() {
    const container = document.getElementById('allowances-container');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2';
    newRow.innerHTML = `
        <div class="col-md-6"><input type="text" name="allowance_name[]" class="form-control" placeholder="Allowance Name"></div>
        <div class="col-md-6"><input type="number" step="0.01" name="allowance_amount[]" class="form-control" placeholder="Amount"></div>
    `;
    container.appendChild(newRow);
});

document.getElementById('add-deduction').addEventListener('click', function() {
    const container = document.getElementById('deductions-container');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2';
    newRow.innerHTML = `
        <div class="col-md-6"><input type="text" name="deduction_name[]" class="form-control" placeholder="Deduction Name"></div>
        <div class="col-md-6"><input type="number" step="0.01" name="deduction_amount[]" class="form-control" placeholder="Amount"></div>
    `;
    container.appendChild(newRow);
});
</script>

<?php
include __DIR__ . '/../templates/footer.php';
?>
