<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$employee_handler = new Employee($pdo);
$employees = $employee_handler->get_all_active();

// Handle payslip generation
$payslip_data = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payslip'])) {
    $employee_id = (int)$_POST['employee_id'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    
    // Get employee details
    $employee = $employee_handler->get_by_id($employee_id);
    
    // Get salary structure
    $stmt = $pdo->prepare('SELECT * FROM salary_structures WHERE employee_id = ?');
    $stmt->execute([$employee_id]);
    $salary_structure = $stmt->fetch();
    
    // Get attendance for the month
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as present_days 
        FROM attendance 
        WHERE employee_id = ? 
        AND DATE(clock_in) BETWEEN ? AND ? 
        AND status = 'present'
    ");
    $stmt->execute([$employee_id, $start_date, $end_date]);
    $attendance = $stmt->fetch();
    
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
    
    // Calculate payslip
    if ($salary_structure) {
        $basic_salary = $salary_structure['basic_salary'];
        $daily_rate = $basic_salary / 30;
        $earned_basic = $daily_rate * $attendance['present_days'];
        
        $allowances = [
            'house_allowance' => $salary_structure['house_allowance'],
            'transport_allowance' => $salary_structure['transport_allowance'],
            'medical_allowance' => $salary_structure['medical_allowance'],
            'other_allowances' => $salary_structure['other_allowances']
        ];
        
        $total_allowances = array_sum($allowances);
        $gross_salary = $earned_basic + $total_allowances;
        
        $deductions = [
            'provident_fund' => $salary_structure['provident_fund'],
            'tax_deduction' => $salary_structure['tax_deduction'],
            'other_deductions' => $salary_structure['other_deductions'],
            'salary_advance' => $advance_amount,
            'loan_installment' => $loan_amount
        ];
        
        $total_deductions = array_sum($deductions);
        $net_salary = $gross_salary - $total_deductions;
        
        $payslip_data = [
            'employee' => $employee,
            'month' => $month,
            'year' => $year,
            'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
            'present_days' => $attendance['present_days'],
            'total_days' => date('t', strtotime($start_date)),
            'basic_salary' => $basic_salary,
            'earned_basic' => $earned_basic,
            'allowances' => $allowances,
            'total_allowances' => $total_allowances,
            'gross_salary' => $gross_salary,
            'deductions' => $deductions,
            'total_deductions' => $total_deductions,
            'net_salary' => $net_salary
        ];
    }
}

$page_title = 'Payslip Generation';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Payslip Generation</h1>

<div class="card mb-4">
    <div class="card-header">Generate Employee Payslip</div>
    <div class="card-body">
        <form action="payslip.php" method="POST" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="employee_id" class="form-label">Employee</label>
                <select class="form-select employee-search" name="employee_id" id="employee_id" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['id']; ?>" 
                                <?php echo (isset($_POST['employee_id']) && $_POST['employee_id'] == $employee['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . ($employee['position_name'] ?? 'No Position')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="month" class="form-label">Month</label>
                <select class="form-select" name="month" id="month" required>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo sprintf('%02d', $i); ?>" 
                                <?php echo (isset($_POST['month']) && $_POST['month'] == sprintf('%02d', $i)) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" name="year" id="year" required>
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" 
                                <?php echo (isset($_POST['year']) && $_POST['year'] == $y) || (!isset($_POST['year']) && $y == date('Y')) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" name="generate_payslip" class="btn btn-primary w-100">Generate</button>
            </div>
        </form>
    </div>
</div>

<?php if ($payslip_data): ?>
<div class="card" id="payslip">
    <div class="card-body">
        <!-- Company Header -->
        <div class="text-center mb-4">
            <h2 class="company-name">Company Name</h2>
            <p class="text-muted">Company Address, City, State, ZIP</p>
            <h4 class="text-primary">SALARY SLIP</h4>
        </div>
        
        <!-- Employee Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Employee Name:</strong></td><td><?php echo htmlspecialchars($payslip_data['employee']['first_name'] . ' ' . $payslip_data['employee']['last_name']); ?></td></tr>
                    <tr><td><strong>Employee ID:</strong></td><td><?php echo $payslip_data['employee']['id']; ?></td></tr>
                    <tr><td><strong>Designation:</strong></td><td><?php echo htmlspecialchars($payslip_data['employee']['position_name'] ?? 'N/A'); ?></td></tr>
                    <tr><td><strong>Department:</strong></td><td><?php echo htmlspecialchars($payslip_data['employee']['department_name'] ?? 'N/A'); ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Pay Period:</strong></td><td><?php echo $payslip_data['month_name'] . ' ' . $payslip_data['year']; ?></td></tr>
                    <tr><td><strong>Days Worked:</strong></td><td><?php echo $payslip_data['present_days']; ?> / <?php echo $payslip_data['total_days']; ?></td></tr>
                    <tr><td><strong>Join Date:</strong></td><td><?php echo date('M d, Y', strtotime($payslip_data['employee']['hire_date'])); ?></td></tr>
                    <tr><td><strong>Generated On:</strong></td><td><?php echo date('M d, Y'); ?></td></tr>
                </table>
            </div>
        </div>
        
        <!-- Salary Details -->
        <div class="row">
            <div class="col-md-6">
                <h5 class="text-success">EARNINGS</h5>
                <table class="table table-bordered">
                    <tr><td>Basic Salary (<?php echo $payslip_data['present_days']; ?> days)</td><td class="text-end">$<?php echo number_format($payslip_data['earned_basic'], 2); ?></td></tr>
                    <tr><td>House Allowance</td><td class="text-end">$<?php echo number_format($payslip_data['allowances']['house_allowance'], 2); ?></td></tr>
                    <tr><td>Transport Allowance</td><td class="text-end">$<?php echo number_format($payslip_data['allowances']['transport_allowance'], 2); ?></td></tr>
                    <tr><td>Medical Allowance</td><td class="text-end">$<?php echo number_format($payslip_data['allowances']['medical_allowance'], 2); ?></td></tr>
                    <tr><td>Other Allowances</td><td class="text-end">$<?php echo number_format($payslip_data['allowances']['other_allowances'], 2); ?></td></tr>
                    <tr class="table-success"><td><strong>GROSS SALARY</strong></td><td class="text-end"><strong>$<?php echo number_format($payslip_data['gross_salary'], 2); ?></strong></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h5 class="text-danger">DEDUCTIONS</h5>
                <table class="table table-bordered">
                    <tr><td>Provident Fund</td><td class="text-end">$<?php echo number_format($payslip_data['deductions']['provident_fund'], 2); ?></td></tr>
                    <tr><td>Tax Deduction</td><td class="text-end">$<?php echo number_format($payslip_data['deductions']['tax_deduction'], 2); ?></td></tr>
                    <tr><td>Other Deductions</td><td class="text-end">$<?php echo number_format($payslip_data['deductions']['other_deductions'], 2); ?></td></tr>
                    <tr><td>Salary Advance</td><td class="text-end">$<?php echo number_format($payslip_data['deductions']['salary_advance'], 2); ?></td></tr>
                    <tr><td>Loan Installment</td><td class="text-end">$<?php echo number_format($payslip_data['deductions']['loan_installment'], 2); ?></td></tr>
                    <tr class="table-danger"><td><strong>TOTAL DEDUCTIONS</strong></td><td class="text-end"><strong>$<?php echo number_format($payslip_data['total_deductions'], 2); ?></strong></td></tr>
                </table>
            </div>
        </div>
        
        <!-- Net Salary -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-primary text-center">
                    <h4><strong>NET SALARY: $<?php echo number_format($payslip_data['net_salary'], 2); ?></strong></h4>
                    <p class="mb-0">Amount in words: <?php echo ucwords(number_to_words($payslip_data['net_salary'])); ?> Dollars Only</p>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="row mt-4">
            <div class="col-md-6">
                <p><small>This is a computer-generated payslip and does not require a signature.</small></p>
            </div>
            <div class="col-md-6 text-end">
                <p><strong>HR Department</strong><br>
                <small>Generated on <?php echo date('M d, Y H:i:s'); ?></small></p>
            </div>
        </div>
    </div>
</div>

<div class="mt-3 no-print">
    <button class="btn btn-success" onclick="window.print()">Print Payslip</button>
    <button class="btn btn-secondary" onclick="downloadPDF()">Download PDF</button>
    <a href="payslip.php" class="btn btn-primary">Generate Another</a>
</div>
<?php endif; ?>

<style>
@media print {
    .no-print { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .company-name { color: #000 !important; }
}

.employee-search {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6'/%3e%3c/svg%3e");
}
</style>

<script>
function downloadPDF() {
    window.open('pdf_handler.php?type=payslip&employee_id=<?php echo $payslip_data['employee']['id'] ?? ''; ?>&month=<?php echo $payslip_data['month'] ?? ''; ?>&year=<?php echo $payslip_data['year'] ?? ''; ?>', '_blank');
}

// Make select searchable
document.addEventListener('DOMContentLoaded', function() {
    const select = document.querySelector('.employee-search');
    if (select) {
        select.setAttribute('data-live-search', 'true');
    }
});
</script>

<?php
include __DIR__ . '/../templates/footer.php';
?>
