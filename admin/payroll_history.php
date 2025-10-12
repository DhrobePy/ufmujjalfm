<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// Get filter parameters from the URL
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Fetch all payroll records within the date range to be used by the page
$stmt = $pdo->prepare("
    SELECT p.*, e.first_name, e.last_name, pos.name as position_name
    FROM payrolls p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    WHERE p.pay_period_start >= ? AND p.pay_period_end <= ?
    ORDER BY p.pay_period_end DESC, e.last_name ASC
");
$stmt->execute([$start_date, $end_date]);
$payrolls = $stmt->fetchAll();

$page_title = 'Payroll History';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Payroll History & Status</h1>
<p class="text-muted">Cross-check the status of all payroll runs for any period.</p>

<div class="card mb-4">
    <div class="card-header">Filter Records</div>
    <div class="card-body">
        <form action="payroll_history.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="start_date" class="form-label">From</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">To</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Filter by Date</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Payroll Records</span>
        <div class="w-50">
            <input type="text" id="employeeSearchInput" class="form-control" placeholder="Type to search by employee name...">
        </div>
        </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm" id="payrollHistoryTable">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Position</th>
                        <th>Pay Period</th>
                        <th class="text-end">Net Salary</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payrolls)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No payroll records found for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payrolls as $payroll): ?>
                            <tr>
                                <td class="employee-name"><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($payroll['position_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payroll['pay_period_start'])) . ' - ' . date('M d, Y', strtotime($payroll['pay_period_end'])); ?></td>
                                <td class="text-end">Tk. <?php echo number_format($payroll['net_salary'], 2); ?></td>
                                <td class="text-center">
                                    <?php
                                        $status_classes = [
                                            'paid' => 'bg-success',
                                            'disbursed' => 'bg-info',
                                            'approved' => 'bg-primary',
                                            'pending_approval' => 'bg-warning text-dark',
                                            'rejected' => 'bg-danger'
                                        ];
                                        $status_class = $status_classes[$payroll['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo str_replace('_', ' ', ucfirst($payroll['status'])); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('employeeSearchInput');
    const table = document.getElementById('payrollHistoryTable');
    const tableRows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        
        for (let i = 0; i < tableRows.length; i++) {
            // Check if the row is a data row (and not a "no results" message)
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