<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$employee_handler = new Employee($pdo);

// Get report parameters
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Calculate salary requirements
$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

// Get all active employees with their salary structures
$query = "
    SELECT e.*, p.name as position_name, d.name as department_name,
           ss.basic_salary, ss.house_allowance, ss.transport_allowance, 
           ss.medical_allowance, ss.other_allowances, ss.provident_fund,
           ss.tax_deduction, ss.other_deductions, ss.gross_salary, ss.net_salary
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN salary_structures ss ON e.id = ss.employee_id
    WHERE e.status = 'active'
    ORDER BY e.last_name ASC
";

$stmt = $pdo->query($query);
$employees = $stmt->fetchAll();

$salary_data = [];
$totals = [
    'employees' => 0,
    'basic_salary' => 0,
    'allowances' => 0,
    'gross_salary' => 0,
    'deductions' => 0,
    'advances' => 0,
    'loans' => 0,
    'net_salary' => 0,
    'attendance_days' => 0
];

foreach ($employees as $employee) {
    $employee_id = $employee['id'];
    
    // Get attendance for the month
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as present_days 
        FROM attendance 
        WHERE employee_id = ? 
        AND DATE(clock_in) BETWEEN ? AND ? 
        AND status = 'present'
    ");
    $stmt->execute([$employee_id, $start_date, $end_date]);
    $attendance = $stmt->fetch();
    $present_days = $attendance['present_days'];
    
    // Get salary advance for this month
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as advance_amount 
        FROM salary_advances 
        WHERE employee_id = ? 
        AND advance_month = ? 
        AND advance_year = ? 
        AND status = 'approved'
    ");
    $stmt->execute([$employee_id, $month, $year]);
    $advance = $stmt->fetch();
    $advance_amount = $advance['advance_amount'] ?? 0;
    
    // Get loan installment for this month
    $stmt = $pdo->prepare("
        SELECT SUM(li.amount) as loan_installment
        FROM loan_installments li
        JOIN loans l ON li.loan_id = l.id
        WHERE l.employee_id = ?
        AND MONTH(li.payment_date) = ?
        AND YEAR(li.payment_date) = ?
    ");
    $stmt->execute([$employee_id, $month, $year]);
    $loan_installment = $stmt->fetch();
    $loan_amount = $loan_installment['loan_installment'] ?? 0;
    
    // Calculate salary
    $basic_salary = $employee['basic_salary'] ?? $employee['base_salary'];
    $daily_rate = $basic_salary / 30;
    $earned_basic = $daily_rate * $present_days;
    
    $allowances = ($employee['house_allowance'] ?? 0) + 
                  ($employee['transport_allowance'] ?? 0) + 
                  ($employee['medical_allowance'] ?? 0) + 
                  ($employee['other_allowances'] ?? 0);
    
    $gross_salary = $earned_basic + $allowances;
    
    $standard_deductions = ($employee['provident_fund'] ?? 0) + 
                          ($employee['tax_deduction'] ?? 0) + 
                          ($employee['other_deductions'] ?? 0);
    
    $total_deductions = $standard_deductions + $advance_amount + $loan_amount;
    $net_salary = $gross_salary - $total_deductions;
    
    $salary_data[] = [
        'employee' => $employee,
        'present_days' => $present_days,
        'basic_salary' => $basic_salary,
        'earned_basic' => $earned_basic,
        'allowances' => $allowances,
        'gross_salary' => $gross_salary,
        'standard_deductions' => $standard_deductions,
        'advance_amount' => $advance_amount,
        'loan_amount' => $loan_amount,
        'total_deductions' => $total_deductions,
        'net_salary' => $net_salary
    ];
    
    // Add to totals
    $totals['employees']++;
    $totals['basic_salary'] += $earned_basic;
    $totals['allowances'] += $allowances;
    $totals['gross_salary'] += $gross_salary;
    $totals['deductions'] += $standard_deductions;
    $totals['advances'] += $advance_amount;
    $totals['loans'] += $loan_amount;
    $totals['net_salary'] += $net_salary;
    $totals['attendance_days'] += $present_days;
}

$page_title = 'Monthly Salary Report';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Monthly Salary Requirement Report</h1>

<!-- Month Selection -->
<div class="card mb-4">
    <div class="card-header">Select Month for Report</div>
    <div class="card-body">
        <form action="monthly_salary_report.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="month" class="form-label">Month</label>
                <select class="form-select" name="month" id="month" required>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo sprintf('%02d', $i); ?>" 
                                <?php echo sprintf('%02d', $i) == $month ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" name="year" id="year" required>
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Generate Report</button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5>Total Employees</h5>
                <h2><?php echo $totals['employees']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5>Gross Salary Required</h5>
                <h2>$<?php echo number_format($totals['gross_salary'], 0); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5>Total Deductions</h5>
                <h2>$<?php echo number_format($totals['deductions'] + $totals['advances'] + $totals['loans'], 0); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5>Net Salary Required</h5>
                <h2>$<?php echo number_format($totals['net_salary'], 0); ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Breakdown -->
<div class="card mb-4">
    <div class="card-header">
        <h5>Salary Breakdown - <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-success">EARNINGS</h6>
                <table class="table table-sm">
                    <tr><td>Basic Salary (Attendance Based)</td><td class="text-end"><strong>$<?php echo number_format($totals['basic_salary'], 2); ?></strong></td></tr>
                    <tr><td>Total Allowances</td><td class="text-end"><strong>$<?php echo number_format($totals['allowances'], 2); ?></strong></td></tr>
                    <tr class="table-success"><td><strong>TOTAL GROSS</strong></td><td class="text-end"><strong>$<?php echo number_format($totals['gross_salary'], 2); ?></strong></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-danger">DEDUCTIONS</h6>
                <table class="table table-sm">
                    <tr><td>Standard Deductions (PF, Tax, etc.)</td><td class="text-end"><strong>$<?php echo number_format($totals['deductions'], 2); ?></strong></td></tr>
                    <tr><td>Salary Advances</td><td class="text-end"><strong>$<?php echo number_format($totals['advances'], 2); ?></strong></td></tr>
                    <tr><td>Loan Installments</td><td class="text-end"><strong>$<?php echo number_format($totals['loans'], 2); ?></strong></td></tr>
                    <tr class="table-danger"><td><strong>TOTAL DEDUCTIONS</strong></td><td class="text-end"><strong>$<?php echo number_format($totals['deductions'] + $totals['advances'] + $totals['loans'], 2); ?></strong></td></tr>
                </table>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-primary text-center">
                    <h4><strong>NET SALARY REQUIREMENT: $<?php echo number_format($totals['net_salary'], 2); ?></strong></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Employee-wise Details -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Employee-wise Salary Details</span>
        <button class="btn btn-sm btn-secondary" onclick="window.print()">Print Report</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Position</th>
                        <th>Days</th>
                        <th>Basic</th>
                        <th>Allowances</th>
                        <th>Gross</th>
                        <th>Deductions</th>
                        <th>Advance</th>
                        <th>Loan</th>
                        <th>Net Salary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salary_data as $data): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($data['employee']['first_name'] . ' ' . $data['employee']['last_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($data['employee']['position_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $data['present_days']; ?></td>
                            <td>$<?php echo number_format($data['earned_basic'], 0); ?></td>
                            <td>$<?php echo number_format($data['allowances'], 0); ?></td>
                            <td><strong>$<?php echo number_format($data['gross_salary'], 0); ?></strong></td>
                            <td>$<?php echo number_format($data['standard_deductions'], 0); ?></td>
                            <td>$<?php echo number_format($data['advance_amount'], 0); ?></td>
                            <td>$<?php echo number_format($data['loan_amount'], 0); ?></td>
                            <td><strong>$<?php echo number_format($data['net_salary'], 0); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <th colspan="2">TOTALS</th>
                        <th><?php echo $totals['attendance_days']; ?></th>
                        <th>$<?php echo number_format($totals['basic_salary'], 0); ?></th>
                        <th>$<?php echo number_format($totals['allowances'], 0); ?></th>
                        <th>$<?php echo number_format($totals['gross_salary'], 0); ?></th>
                        <th>$<?php echo number_format($totals['deductions'], 0); ?></th>
                        <th>$<?php echo number_format($totals['advances'], 0); ?></th>
                        <th>$<?php echo number_format($totals['loans'], 0); ?></th>
                        <th>$<?php echo number_format($totals['net_salary'], 0); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
}
</style>

<?php
include __DIR__ . '/../templates/footer.php';
?>
