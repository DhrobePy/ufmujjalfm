<?php
require_once __DIR__ . '/../core/init.php';

// Ensure only an admin can access this page
if (!is_superadmin()) { 
    exit('Access Denied: You do not have permission to view this page.'); 
}

$payroll_handler = new Payroll($pdo);

// Handle form submission for approving or rejecting payrolls
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payroll_ids'])) {
    $payroll_ids = $_POST['payroll_ids'];
    $new_status = isset($_POST['approve']) ? 'approved' : 'rejected';
    
    if (!empty($payroll_ids)) {
        $payroll_handler->update_payroll_status($payroll_ids, $new_status);
    }
    
    header('Location: approve_payroll.php?status_updated=1');
    exit();
}

// Fetch the detailed payroll data using our new function
$pending_payrolls = $payroll_handler->get_pending_payroll_details();

// Group the payrolls by their pay period for display
$grouped_payrolls = [];
foreach ($pending_payrolls as $payroll) {
    $period_key = $payroll['pay_period_start'] . '_' . $payroll['pay_period_end'];
    $grouped_payrolls[$period_key][] = $payroll;
}

$page_title = 'Approve Payroll';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Approve Payroll Disbursement</h1>
<p class="text-muted">Step 2: Admin reviews the prepared salary list and approves it for disbursement.</p>

<?php if (isset($_GET['status_updated'])): ?>
    <div class="alert alert-success">Payroll statuses updated successfully!</div>
<?php endif; ?>

<?php if (empty($grouped_payrolls)): ?>
    <div class="alert alert-info">There are no payrolls currently pending approval.</div>
<?php else: ?>
    <form action="approve_payroll.php" method="POST">
        <?php foreach ($grouped_payrolls as $period => $payrolls): ?>
            <?php
                [$start_date, $end_date] = explode('_', $period);
                $period_heading = "Pay Period: " . date('F d, Y', strtotime($start_date)) . " to " . date('F d, Y', strtotime($end_date));
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo $period_heading; ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th><input type="checkbox" onclick="toggleGroup(this, '<?php echo $period; ?>');"></th>
                                    <th>Employee</th>
                                    <th>Position</th>
                                    <th>Address</th>
                                    <th class="text-end">Gross Salary</th>
                                    <th class="text-center">Absent Days</th>
                                    <th class="text-end text-danger">Absence Deduction</th>
                                    <th class="text-end text-danger">Advance Deduction</th>
                                    <th class="text-end text-danger">Loan (EMI)</th>
                                    <th class="text-end fw-bold">Net Salary</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payrolls as $payroll): ?>
                                    <tr>
                                        <td><input type="checkbox" class="payroll-checkbox-<?php echo $period; ?>" name="payroll_ids[]" value="<?php echo $payroll['id']; ?>"></td>
                                        <td><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payroll['position_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($payroll['address']); ?></td>
                                        <td class="text-end">Tk.<?php echo number_format($payroll['gross_salary'], 2); ?></td>
                                        <td class="text-center"><?php echo $payroll['absent_days']; ?></td>
                                        <td class="text-end text-danger">(Tk.<?php echo number_format($payroll['salary_deducted_for_absent'], 2); ?>)</td>
                                        <td class="text-end text-danger">(Tk.<?php echo number_format($payroll['advance_salary_deducted'], 2); ?>)</td>
                                        <td class="text-end text-danger">(Tk.<?php echo number_format($payroll['loan_deduction'], 2); ?>)</td>
                                        <td class="text-end fw-bold">Tk.<?php echo number_format($payroll['net_salary'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="card-footer text-end">
            <button type="submit" name="reject" class="btn btn-danger btn-lg">Reject Selected</button>
            <button type="submit" name="approve" class="btn btn-success btn-lg">Approve Selected for Disbursement</button>
        </div>
    </form>
<?php endif; ?>

<script>
function toggleGroup(source, groupKey) {
    const checkboxes = document.querySelectorAll('.payroll-checkbox-' + groupKey);
    for(let i=0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>