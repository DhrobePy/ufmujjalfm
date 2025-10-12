<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) { 
    header('Location: ../auth/login.php');
    exit();
}

$payroll_handler = new Payroll($pdo);
$prepared_payrolls = null;
$results = null;
$error_message = null;

// Handle form submission to prepare a new payroll batch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prepare_salaries'])) {
    $pay_period_start = $_POST['pay_period_start'];
    $pay_period_end = $_POST['pay_period_end'];
    $results = $payroll_handler->prepare_bulk_payroll($pay_period_start, $pay_period_end);
    
    // --- FIX: Check for success or failure from the prepare function ---
    if (isset($results['success']) && $results['success'] === false) {
        // Handle the error case (e.g., duplicate payroll)
        $error_message = $results['message'];
    } else {
        // Handle the success case
        $_SESSION['last_prepared_results'] = $results;
        $_SESSION['last_period_heading'] = "Pay Period: " . date('F d, Y', strtotime($pay_period_start)) . " to " . date('F d, Y', strtotime($end_date));
        header('Location: prepare_payroll.php?prepared=true');
        exit();
    }
}

// Check if there are results from a recent successful submission in the session
if (isset($_GET['prepared']) && isset($_SESSION['last_prepared_results'])) {
    $results = $_SESSION['last_prepared_results'];
    
    if (!empty($results['prepared_ids'])) {
        $prepared_payrolls = $payroll_handler->get_pending_payroll_details();
    }
    
    unset($_SESSION['last_prepared_results']);
    unset($_SESSION['last_period_heading']);
}

$page_title = 'Prepare Salary List';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Prepare Salary Disbursement</h1>
<p class="text-muted">Step 1: Accounts team prepares the salary list and submits it for approval.</p>

<?php if ($error_message): ?>
    <div class="alert alert-danger">
        <strong>Action Failed:</strong> <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['prepared']) && $results && ($results['success_count'] > 0)): ?>
    <div class="alert alert-success">
        Successfully prepared payroll for <strong><?php echo $results['success_count']; ?> employees</strong>. The list below has been submitted for approval.
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Prepare Payroll for a Period</div>
    <div class="card-body">
        <form action="prepare_payroll.php" method="POST">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="pay_period_start" class="form-label">Pay Period Start</label>
                    <input type="date" class="form-control" name="pay_period_start" required>
                </div>
                <div class="col-md-5">
                    <label for="pay_period_end" class="form-label">Pay Period End</label>
                    <input type="date" class="form-control" name="pay_period_end" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="prepare_salaries" class="btn btn-primary w-100">Prepare & Submit</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($prepared_payrolls): ?>
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Newly Submitted for Approval</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Position</th>
                        <th class="text-end">Gross Salary</th>
                        <th class="text-center">Absent Days</th>
                        <th class="text-end text-danger">Absence Deduction</th>
                        <th class="text-end text-danger">Advance Deduction</th>
                        <th class="text-end text-danger">Loan (EMI)</th>
                        <th class="text-end fw-bold">Net Salary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prepared_payrolls as $payroll): ?>
                        <?php if (in_array($payroll['id'], $results['prepared_ids'])): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($payroll['position_name'] ?? 'N/A'); ?></td>
                                <td class="text-end">Tk. <?php echo number_format($payroll['gross_salary'], 2); ?></td>
                                <td class="text-center"><?php echo $payroll['absent_days']; ?></td>
                                <td class="text-end text-danger">(Tk. <?php echo number_format($payroll['salary_deducted_for_absent'], 2); ?>)</td>
                                <td class="text-end text-danger">(Tk. <?php echo number_format($payroll['advance_salary_deducted'], 2); ?>)</td>
                                <td class="text-end text-danger">(Tk. <?php echo number_format($payroll['loan_deduction'], 2); ?>)</td>
                                <td class="text-end fw-bold">Tk. <?php echo number_format($payroll['net_salary'], 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>