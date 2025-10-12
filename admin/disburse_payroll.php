<?php
require_once __DIR__ . '/../core/init.php';

// This page would typically be for an "Accounts" role.
if (!is_logged_in()) { 
    header('Location: ../auth/login.php');
    exit();
}

$payroll_handler = new Payroll($pdo);

// Handle POST requests for disbursing or marking as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payroll_id = $_POST['payroll_id'] ?? null;

    if ($payroll_id) {
        if (isset($_POST['disburse'])) {
            // Process loan installments and update status to 'disbursed'
            $payroll_handler->process_disbursement([$payroll_id]);
            header('Location: disburse_payroll.php?disbursed=1');
            exit();
        }
        
        if (isset($_POST['mark_paid'])) {
            // Final step: update status to 'paid'
            $payroll_handler->update_payroll_status([$payroll_id], 'paid');
            header('Location: disburse_payroll.php?paid=1');
            exit();
        }
    }
}

// Fetch payrolls that are 'approved' (ready to be disbursed)
$approved_stmt = $pdo->query("
    SELECT p.*, e.first_name, e.last_name 
    FROM payrolls p 
    JOIN employees e ON p.employee_id = e.id 
    WHERE p.status = 'approved'
    ORDER BY p.pay_period_end DESC
");
$approved_payrolls = $approved_stmt->fetchAll();

// Fetch payrolls that are 'disbursed' (awaiting final confirmation)
$disbursed_stmt = $pdo->query("
    SELECT p.*, e.first_name, e.last_name 
    FROM payrolls p 
    JOIN employees e ON p.employee_id = e.id 
    WHERE p.status = 'disbursed'
    ORDER BY p.pay_period_end DESC
");
$disbursed_payrolls = $disbursed_stmt->fetchAll();


$page_title = 'Disburse Salaries';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Disburse and Finalize Payroll</h1>
<p class="text-muted">Step 3 & 4: Process approved payments and mark them as paid after bank transfer.</p>

<?php if (isset($_GET['disbursed'])): ?>
    <div class="alert alert-success">Payroll marked as disbursed and loan installments have been processed.</div>
<?php endif; ?>
<?php if (isset($_GET['paid'])): ?>
    <div class="alert alert-info">Payroll has been marked as paid. The process is complete.</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Step 3: Approved for Disbursement</h5>
        <div class="w-50">
            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search for an employee...">
        </div>
        </div>
    <div class="card-body">
        <?php if (empty($approved_payrolls)): ?>
            <p class="text-muted text-center">There are no payrolls currently approved for disbursement.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="approvedTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Pay Period</th>
                            <th class="text-end">Net Salary</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approved_payrolls as $payroll): ?>
                            <tr>
                                <td class="employee-name"><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payroll['pay_period_start'])) . ' to ' . date('M d, Y', strtotime($payroll['pay_period_end'])); ?></td>
                                <td class="text-end fw-bold">Tk.<?php echo number_format($payroll['net_salary'], 2); ?></td>
                                <td class="text-center">
                                    <form action="disburse_payroll.php" method="POST" class="d-inline">
                                        <input type="hidden" name="payroll_id" value="<?php echo $payroll['id']; ?>">
                                        <button type="submit" name="disburse" class="btn btn-sm btn-success" onclick="return confirm('This will process loan deductions and mark the salary as disbursed. Are you sure?')">
                                            Disburse
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Step 4: Disbursed - Awaiting Final Confirmation</h5>
    </div>
    <div class="card-body">
         <?php if (empty($disbursed_payrolls)): ?>
            <p class="text-muted text-center">There are no payrolls currently awaiting final payment confirmation.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Pay Period</th>
                            <th class="text-end">Net Salary</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disbursed_payrolls as $payroll): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payroll['pay_period_start'])) . ' to ' . date('M d, Y', strtotime($payroll['pay_period_end'])); ?></td>
                                <td class="text-end fw-bold">Tk.<?php echo number_format($payroll['net_salary'], 2); ?></td>
                                <td class="text-center">
                                     <form action="disburse_payroll.php" method="POST" class="d-inline">
                                        <input type="hidden" name="payroll_id" value="<?php echo $payroll['id']; ?>">
                                        <button type="submit" name="mark_paid" class="btn btn-sm btn-info" onclick="return confirm('Confirm that this salary has been successfully paid to the employee?')">
                                            Mark as Paid
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('approvedTable');
    const tableRows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        
        for (let i = 0; i < tableRows.length; i++) {
            const employeeNameCell = tableRows[i].querySelector('.employee-name');
            if (employeeNameCell) {
                const employeeName = employeeNameCell.textContent || employeeNameCell.innerText;
                if (employeeName.toLowerCase().indexOf(filter) > -1) {
                    tableRows[i].style.display = "";
                } else {
                    tableRows[i].style.display = "none";
                }
            }
        }
    });
});
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>