<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$payroll_handler = new Payroll($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disburse_salaries'])) {
    $pay_period_start = $_POST['pay_period_start'];
    $pay_period_end = $_POST['pay_period_end'];

    $results = $payroll_handler->run_bulk_payroll($pay_period_start, $pay_period_end);
    
    // Store results in session to display after redirect
    $_SESSION['disbursement_results'] = $results;
    header('Location: bulk_disbursement.php?disbursed=1');
    exit();
}

$results = $_SESSION['disbursement_results'] ?? null;
if ($results) {
    unset($_SESSION['disbursement_results']);
}

$page_title = 'Bulk Salary Disbursement';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Bulk Salary Disbursement</h1>

<?php if (isset($_GET['disbursed']) && $results): ?>
    <div class="alert alert-success">
        Salaries disbursed for <?php echo $results['success_count']; ?> employees. <?php echo $results['fail_count']; ?> failed.
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Run Bulk Payroll</div>
    <div class="card-body">
        <form action="bulk_disbursement.php" method="POST">
            <input type="hidden" name="disburse_salaries" value="1">
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
                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to disburse salaries for all active employees?')">Disburse Now</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($results): ?>
<div class="card mb-4">
    <div class="card-header">Disbursement Results</div>
    <div class="card-body">
        <h5>Successful Disbursements:</h5>
        <ul class="list-group mb-3">
            <?php foreach ($results['success_details'] as $detail): ?>
                <li class="list-group-item"><?php echo $detail; ?></li>
            <?php endforeach; ?>
        </ul>
        
        <?php if (!empty($results['fail_details'])): ?>
            <h5>Failed Disbursements:</h5>
            <ul class="list-group">
                <?php foreach ($results['fail_details'] as $detail): ?>
                    <li class="list-group-item list-group-item-danger"><?php echo $detail; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
include __DIR__ . '/../templates/footer.php';
?>
