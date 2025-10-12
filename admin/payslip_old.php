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
$employees = $employee_handler->get_all_active();

// Initialize variables for status and error handling
$payslip_data = null;
$payslip_error = null; // <-- NEW: Error message variable

// Handle payslip generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payslip'])) {
    $employee_id = (int)$_POST['employee_id'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    
    // 1. Get employee details
    $employee = $employee_handler->get_by_id($employee_id);
    if (!$employee) {
        $payslip_error = "Error: Employee not found with ID: {$employee_id}.";
        goto end_payslip_generation;
    }
    
    // 2. Get salary structure
    $stmt = $pdo->prepare('SELECT * FROM salary_structures WHERE employee_id = ?');
    $stmt->execute([$employee_id]);
    $salary_structure = $stmt->fetch();
    
    if (!$salary_structure) {
        $payslip_error = "Error: Salary structure not defined for " . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . ". Cannot generate payslip.";
        goto end_payslip_generation;
    }
    
    // 3. Get attendance for the month
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
    
    // 4. Get salary advance for this month
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
    
    // 5. Get loan installment for this month (Modified to check for payment date)
    // NOTE: If payment_date is not set for the installment, this query might fail to find installments.
    // If you log loan payments upon payroll generation, use the month/year of the payroll period.
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
    
    // 6. Calculate payslip (Only runs if $salary_structure is found)
    $basic_salary = $salary_structure['basic_salary'];
    $total_month_days = date('t', strtotime($start_date));
    $present_days = $attendance['present_days'] ?? 0;

    // Calculate earned basic based on days worked
    $daily_rate = $basic_salary / $total_month_days;
    $earned_basic = $daily_rate * $present_days;
    
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
        'present_days' => $present_days,
        'total_days' => $total_month_days,
        'basic_salary' => $basic_salary,
        'earned_basic' => $earned_basic,
        'allowances' => $allowances,
        'total_allowances' => $total_allowances,
        'gross_salary' => $gross_salary,
        'deductions' => $deductions,
        'total_deductions' => $total_deductions,
        'net_salary' => $net_salary
    ];

    end_payslip_generation: // <-- GOTO target
}

$page_title = 'Payslip Generation';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Payslip Generation</h1>

<?php if (isset($payslip_error)): // <-- NEW: Display error message ?>
    <div class="alert alert-danger mt-4">
        <strong>Payslip Error:</strong> <?php echo $payslip_error; ?>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Generate Employee Payslip</div>
    <div class="card-body">
        <form action="payslip.php" method="POST" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="employee_id" class="form-label">Employee</label>
                <select class="form-select employee-search" name="employee_id" id="employee_id" required>
                    <option value="">Select Employee</option>
                    <?php 
                    // Preserve selected employee after submission/reload
                    $selected_employee_id = $_POST['employee_id'] ?? '';
                    foreach ($employees as $employee): 
                    ?>
                        <option value="<?php echo $employee['id']; ?>" 
                                <?php echo ($selected_employee_id == $employee['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . ($employee['position_name'] ?? 'No Position')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="month" class="form-label">Month</label>
                <select class="form-select" name="month" id="month" required>
                    <?php 
                    // Preserve selected month after submission/reload
                    $selected_month = $_POST['month'] ?? date('m');
                    for ($i = 1; $i <= 12; $i++): 
                        $month_value = sprintf('%02d', $i);
                    ?>
                        <option value="<?php echo $month_value; ?>" 
                                <?php echo ($selected_month == $month_value) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" name="year" id="year" required>
                    <?php 
                    // Preserve selected year after submission/reload
                    $selected_year = $_POST['year'] ?? date('Y');
                    for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): 
                    ?>
                        <option value="<?php echo $y; ?>" 
                                <?php echo ($selected_year == $y) ? 'selected' : ''; ?>>
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
        
        <div class="row">
            <div class="col-md-6">
                <h5 class="text-success">EARNINGS</h5>
                <table class="table table-bordered">
                    <tr><td>Basic Salary (<?php echo $payslip_data['present_days']; ?> days)</td><td class="text-end">Tk<?php echo number_format($payslip_data['earned_basic'], 2); ?></td></tr>
                    <tr><td>House Allowance</td><td class="text-end">Tk<?php echo number_format($payslip_data['allowances']['house_allowance'], 2); ?></td></tr>
                    <tr><td>Transport Allowance</td><td class="text-end">Tk<?php echo number_format($payslip_data['allowances']['transport_allowance'], 2); ?></td></tr>
                    <tr><td>Medical Allowance</td><td class="text-end">Tk<?php echo number_format($payslip_data['allowances']['medical_allowance'], 2); ?></td></tr>
                    <tr><td>Other Allowances</td><td class="text-end">Tk<?php echo number_format($payslip_data['allowances']['other_allowances'], 2); ?></td></tr>
                    <tr class="table-success"><td><strong>GROSS SALARY</strong></td><td class="text-end"><strong>Tk<?php echo number_format($payslip_data['gross_salary'], 2); ?></strong></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h5 class="text-danger">DEDUCTIONS</h5>
                <table class="table table-bordered">
                    <tr><td>Provident Fund</td><td class="text-end">Tk<?php echo number_format($payslip_data['deductions']['provident_fund'], 2); ?></td></tr>
                    <tr><td>Tax Deduction</td><td class="text-end">Tk<?php echo number_format($payslip_data['deductions']['tax_deduction'], 2); ?></td></tr>
                    <tr><td>Other Deductions</td><td class="text-end">Tk<?php echo number_format($payslip_data['deductions']['other_deductions'], 2); ?></td></tr>
                    <tr><td>Salary Advance</td><td class="text-end">Tk<?php echo number_format($payslip_data['deductions']['salary_advance'], 2); ?></td></tr>
                    <tr><td>Loan Installment</td><td class="text-end">tk<?php echo number_format($payslip_data['deductions']['loan_installment'], 2); ?></td></tr>
                    <tr class="table-danger"><td><strong>TOTAL DEDUCTIONS</strong></td><td class="text-end"><strong>Tk<?php echo number_format($payslip_data['total_deductions'], 2); ?></strong></td></tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-primary text-center">
                    <h4><strong>NET SALARY: Tk<?php echo number_format($payslip_data['net_salary'], 2); ?></strong></h4>
                    <p class="mb-0">Amount in words: <?php echo ucwords(number_to_words($payslip_data['net_salary'])); ?></p>
                </div>
            </div>
        </div>
        
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
    /* The search icon styling remains, but note that the select element itself
       needs a JavaScript library (like Select2 or Bootstrap-Select) to become searchable.
       The 'data-live-search' attribute in the script block is for such libraries. */
}
</style>

<script>
function downloadPDF() {
    window.open('pdf_handler.php?type=payslip&employee_id=<?php echo $payslip_data['employee']['id'] ?? ''; ?>&month=<?php echo $payslip_data['month'] ?? ''; ?>&year=<?php echo $payslip_data['year'] ?? ''; ?>', '_blank');
}

// Make select searchable (This requires a JS library to work, but the attributes are set correctly)
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