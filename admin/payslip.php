<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$employee_handler = new Employee($pdo);
$loan_handler = new Loan($pdo);
$employees = $employee_handler->get_all_active();

$payslip_data = null;
$payslip_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payslip'])) {
    $employee_id = (int)$_POST['employee_id'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));

    // 1. Find the specific, processed payroll record for this employee and period.
    $stmt_payroll = $pdo->prepare("
        SELECT * FROM payrolls 
        WHERE employee_id = ? 
        AND pay_period_start = ? 
        AND pay_period_end = ? 
        AND status IN ('disbursed', 'paid') 
        LIMIT 1
    ");
    $stmt_payroll->execute([$employee_id, $start_date, $end_date]);
    $payroll_record = $stmt_payroll->fetch();

    if (!$payroll_record) {
        $payslip_error = "No paid or disbursed payroll record found for the selected employee and period. Please ensure payroll has been fully processed.";
        goto end_payslip_generation;
    }

    $employee = $employee_handler->get_by_id($employee_id);
    
    $stmt_structure = $pdo->prepare('SELECT * FROM salary_structures WHERE employee_id = ?');
    $stmt_structure->execute([$employee_id]);
    $salary_structure = $stmt_structure->fetch();

    // 2. Fetch the actual loan installment that was recorded for THIS payroll run.
    $stmt_loan = $pdo->prepare("SELECT SUM(amount) as total_repayment FROM loan_installments WHERE payroll_id = ?");
    $stmt_loan->execute([$payroll_record['id']]);
    $loan_repayment = $stmt_loan->fetch();
    $loan_amount_deducted = $loan_repayment['total_repayment'] ?? 0;

    // 3. Get other details for breakdown.
    $stmt_att = $pdo->prepare("SELECT COUNT(*) as present_days FROM attendance WHERE employee_id = ? AND DATE(clock_in) BETWEEN ? AND ? AND status = 'present'");
    $stmt_att->execute([$employee_id, $start_date, $end_date]);
    $attendance = $stmt_att->fetch();
    $present_days = $attendance['present_days'] ?? 0;
    
    $absent_days = 30 - $present_days;
    $daily_rate = ($payroll_record['gross_salary'] > 0) ? $payroll_record['gross_salary'] / 30 : 0;
    $absence_deduction = ($absent_days > 0) ? $daily_rate * $absent_days : 0;
    
    $stmt_adv = $pdo->prepare("SELECT SUM(amount) as advance_amount FROM salary_advances WHERE employee_id = ? AND advance_month = ? AND advance_year = ? AND status = 'approved'");
    $stmt_adv->execute([$employee_id, $month, $year]);
    $advance = $stmt_adv->fetch();
    $advance_amount = $advance['amount'] ?? 0;

    // 4. Assemble the final payslip data from the fetched records.
    $earnings = [
        'basic_salary' => $salary_structure['basic_salary'] ?? $payroll_record['gross_salary'],
        'house_allowance' => $salary_structure['house_allowance'] ?? 0,
        'transport_allowance' => $salary_structure['transport_allowance'] ?? 0,
        'medical_allowance' => $salary_structure['medical_allowance'] ?? 0,
        'other_allowances' => $salary_structure['other_allowances'] ?? 0,
    ];

    $deductions = [
        'absence_deduction' => $absence_deduction,
        'provident_fund' => $salary_structure['provident_fund'] ?? 0,
        'tax_deduction' => $salary_structure['tax_deduction'] ?? 0,
        'other_deductions' => $salary_structure['other_deductions'] ?? 0,
        'salary_advance' => $advance_amount,
        'loan_installment' => $loan_amount_deducted
    ];
    
    // --- *** THE FIX IS HERE *** ---
    // The total deductions must be the sum of the array we just built for display.
    $total_deductions = array_sum($deductions);
    
    $payslip_data = [
        'employee' => $employee,
        'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
        'year' => $year,
        'present_days' => $present_days,
        'total_days' => 30,
        'gross_salary' => $payroll_record['gross_salary'],
        'earnings' => $earnings,
        'deductions' => $deductions,
        'total_deductions' => $total_deductions, // Use the correctly summed variable
        'net_salary' => $payroll_record['net_salary']
    ];

    end_payslip_generation:
}

$page_title = 'Payslip Generation';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Payslip Generation</h1>

<?php if (isset($payslip_error)): ?>
    <div class="alert alert-danger mt-4"><strong>Error:</strong> <?php echo $payslip_error; ?></div>
<?php endif; ?>

<div class="card mb-4 no-print">
    <div class="card-header">Generate Employee Payslip</div>
    <div class="card-body">
        <form action="payslip.php" method="POST" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="employee_id" class="form-label">Employee</label>
                <select class="form-select employee-search" name="employee_id" id="employee_id" required>
                    <option value="">Select Employee</option>
                    <?php 
                    $selected_employee_id = $_POST['employee_id'] ?? '';
                    foreach ($employees as $employee): 
                    ?>
                        <option value="<?php echo $employee['id']; ?>" <?php echo ($selected_employee_id == $employee['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . ($employee['position_name'] ?? 'No Position')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="month" class="form-label">Month</label>
                <select class="form-select" name="month" id="month" required>
                    <?php 
                    $selected_month = $_POST['month'] ?? date('m');
                    for ($i = 1; $i <= 12; $i++): 
                        $month_value = sprintf('%02d', $i);
                    ?>
                        <option value="<?php echo $month_value; ?>" <?php echo ($selected_month == $month_value) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" name="year" id="year" required>
                    <?php 
                    $selected_year = $_POST['year'] ?? date('Y');
                    for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): 
                    ?>
                        <option value="<?php echo $y; ?>" <?php echo ($selected_year == $y) ? 'selected' : ''; ?>>
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
        <div class="text-center mb-4">
            <h2 class="company-name">উজ্জ্বল ফ্লাওয়ার মিলস </h2>
            <p class="text-muted">১৭, নুরাইবাগ , ডেমরা , ঢাকা </p>
            <h4 class="text-primary">SALARY SLIP</h4>
        </div>
        
        <div class="row mb-4">
            <div class="col-6">
                <table class="table table-borderless table-sm">
                    <tr><td><strong>Employee Name:</strong></td><td><?php echo htmlspecialchars($payslip_data['employee']['first_name'] . ' ' . $payslip_data['employee']['last_name']); ?></td></tr>
                    <tr><td><strong>Designation:</strong></td><td><?php echo htmlspecialchars($payslip_data['employee']['position_name'] ?? 'N/A'); ?></td></tr>
                </table>
            </div>
            <div class="col-6">
                <table class="table table-borderless table-sm">
                    <tr><td><strong>Pay Period:</strong></td><td><?php echo $payslip_data['month_name'] . ' ' . $payslip_data['year']; ?></td></tr>
                    <tr><td><strong>Days Present:</strong></td><td><?php echo $payslip_data['present_days']; ?> / <?php echo $payslip_data['total_days']; ?></td></tr>
                </table>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <h5 class="text-success">EARNINGS</h5>
                <table class="table table-bordered">
                    <tr><td>Basic Salary</td><td class="text-end">Tk. <?php echo number_format($payslip_data['earnings']['basic_salary'], 2); ?></td></tr>
                    <tr><td>House Allowance</td><td class="text-end">Tk. <?php echo number_format($payslip_data['earnings']['house_allowance'], 2); ?></td></tr>
                    <tr><td>Transport Allowance</td><td class="text-end">Tk. <?php echo number_format($payslip_data['earnings']['transport_allowance'], 2); ?></td></tr>
                    <tr><td>Medical Allowance</td><td class="text-end">Tk. <?php echo number_format($payslip_data['earnings']['medical_allowance'], 2); ?></td></tr>
                    <tr><td>Other Allowances</td><td class="text-end">Tk. <?php echo number_format($payslip_data['earnings']['other_allowances'], 2); ?></td></tr>
                    <tr class="table-success"><td><strong>GROSS SALARY</strong></td><td class="text-end"><strong>Tk. <?php echo number_format($payslip_data['gross_salary'], 2); ?></strong></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h5 class="text-danger">DEDUCTIONS</h5>
                <table class="table table-bordered">
                    <tr><td>Absence Deduction</td><td class="text-end">Tk. <?php echo number_format($payslip_data['deductions']['absence_deduction'], 2); ?></td></tr>
                    <tr><td>Provident Fund</td><td class="text-end">Tk. <?php echo number_format($payslip_data['deductions']['provident_fund'], 2); ?></td></tr>
                    <tr><td>Tax Deduction</td><td class="text-end">Tk. <?php echo number_format($payslip_data['deductions']['tax_deduction'], 2); ?></td></tr>
                    <tr><td>Other Deductions</td><td class="text-end">Tk. <?php echo number_format($payslip_data['deductions']['other_deductions'], 2); ?></td></tr>
                    <tr><td>Salary Advance</td><td class="text-end">Tk. <?php echo number_format($payslip_data['deductions']['salary_advance'], 2); ?></td></tr>
                    <tr><td>Loan Installment</td><td class="text-end">Tk. <?php echo number_format($payslip_data['deductions']['loan_installment'], 2); ?></td></tr>
                    <tr class="table-danger"><td><strong>TOTAL DEDUCTIONS</strong></td><td class="text-end"><strong>Tk. <?php echo number_format($payslip_data['total_deductions'], 2); ?></strong></td></tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-primary text-center">
                    <h4><strong>NET SALARY: Tk. <?php echo number_format($payslip_data['net_salary'], 2); ?></strong></h4>
                    <p class="mb-0">Amount in words: <?php echo ucwords(number_to_words($payslip_data['net_salary'])); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-3 no-print">
    <button class="btn btn-success" onclick="window.print()">Print Payslip</button>
    <a href="payslip.php" class="btn btn-primary">Generate Another</a>
</div>
<?php endif; ?>

<style>
@media print {
    .no-print { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    body { -webkit-print-color-adjust: exact; }
}
</style>

<?php include __DIR__ . '/../templates/footer.php'; ?>