<?php
// new_ufmhrm/admin/salary_calculation.php (Final Corrected Version)

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- 1. DATABASE CONNECTION (Replace with your actual credentials) ---
$db_host = 'localhost';
$db_name = 'ujjalfmc_hr'; // Your database name
$db_user = 'ujjalfmc_hr'; // Your database user
$db_pass = 'ujjalfmhr1234';   // Your database password


try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- 2. DATA FETCHING & INITIALIZATION ---
$allEmployees = $pdo->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$calculation = null;
$attendanceCalendar = [];
$selected_employee_id = $_GET['employee_id'] ?? null;
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['employee_id'])) {
    // --- 3. CALCULATION LOGIC ---
    $employee_id = (int)$_GET['employee_id'];
    $month = str_pad((int)$_GET['month'], 2, '0', STR_PAD_LEFT);
    $year = (int)$_GET['year'];

    $payPeriodStart = "$year-$month-01";
    $payPeriodEnd = date('Y-m-t', strtotime($payPeriodStart));
    
    // a) Get total calendar days in the month
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);

    // b) Fetch Employee & Salary Data
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM salary_structures WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $salaryStructure = $stmt->fetch();
    
    $grossSalary = $salaryStructure->gross_salary ?? $employee->base_salary;
    $basicSalary = $salaryStructure->basic_salary ?? $employee->base_salary;
    
    // c) Fetch PRESENT days and calculate absences based on calendar days
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE employee_id = ? AND status = 'present' AND DATE(clock_in) BETWEEN ? AND ?");
    $stmt->execute([$employee_id, $payPeriodStart, $payPeriodEnd]);
    $presentDays = $stmt->fetch()->count;
    
    // DEFINITIVE LOGIC: Absence is the difference between calendar days and present days. No record = absent.
    $absentDays = $daysInMonth - $presentDays;
    if ($absentDays < 0) $absentDays = 0;

    // d) Calculate Absence Deduction using 30-day rate. NO GRACE PERIOD.
    $dailyRate = $basicSalary / 30;
    $absenceDeduction = $absentDays * $dailyRate;

    // e) Fetch Other Deductions
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM salary_advances WHERE employee_id = ? AND advance_month = ? AND advance_year = ? AND status = 'approved'");
    $stmt->execute([$employee_id, $month, $year]);
    $advanceDeduction = $stmt->fetch()->total ?? 0;

    $stmt = $pdo->prepare("SELECT COALESCE(monthly_payment, 0) as total FROM loans WHERE employee_id = ? AND status = 'active' AND installment_type = 'fixed' LIMIT 1");
    $stmt->execute([$employee_id]);
    $loanInstallment = $stmt->fetch()->total ?? 0;

    // f) Final Calculation
    $totalDeductions = $absenceDeduction + $advanceDeduction + $loanInstallment;
    $netSalary = $grossSalary - $totalDeductions;
    
    $calculation = [ 'employee' => $employee, 'payPeriodEnd' => $payPeriodEnd, 'grossSalary' => $grossSalary, 'basicSalary' => $basicSalary, 'daysInMonth' => $daysInMonth, 'presentDays' => $presentDays, 'absentDays' => $absentDays, 'dailyRate' => $dailyRate, 'absenceDeduction' => $absenceDeduction, 'advanceDeduction' => $advanceDeduction, 'loanInstallment' => $loanInstallment, 'totalDeductions' => $totalDeductions, 'netSalary' => $netSalary ];
    
    // --- 4. PREPARE ATTENDANCE CALENDAR ---
    $stmt = $pdo->prepare("SELECT DATE(clock_in) as date, status FROM attendance WHERE employee_id = ? AND DATE(clock_in) BETWEEN ? AND ?");
    $stmt->execute([$employee_id, $payPeriodStart, $payPeriodEnd]);
    $attendanceRecords = $stmt->fetchAll();
    $attendanceMap = [];
    foreach($attendanceRecords as $record) { $attendanceMap[$record->date] = $record->status; }

    $firstDayOfMonth = date('N', strtotime($payPeriodStart));
    if ($firstDayOfMonth == 7) $firstDayOfMonth = 0; // Adjust Sunday
    for ($i = 0; $i < $firstDayOfMonth; $i++) { $attendanceCalendar[] = ['day' => '', 'status' => 'empty']; }

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $currentDate = "$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
        $status = $attendanceMap[$currentDate] ?? 'unknown';
        $attendanceCalendar[] = ['day' => $day, 'status' => $status];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Calculation Checker (Final Logic)</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f4f5f7; color: #1f2937; margin: 2rem; }
        .container { max-width: 1200px; margin: auto; }
        .card { background: white; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .grid { display: grid; gap: 1.5rem; }
        .grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
        .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-cols-7 { grid-template-columns: repeat(7, 1fr); }
        .lg-cols-3 { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
        label { font-weight: 600; font-size: 0.875rem; margin-bottom: 0.5rem; display: block; }
        select, input, button { width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; box-sizing: border-box; }
        button { background-color: #0284c7; color: white; font-weight: bold; cursor: pointer; }
        h1, h2, h3 { margin: 0 0 1rem 0; }
        hr { border: 0; border-top: 1px solid #e2e8f0; margin: 1.5rem 0; }
        .float-right { float: right; }
        .font-bold { font-weight: bold; }
        .text-lg { font-size: 1.125rem; }
        .text-2xl { font-size: 1.5rem; }
        .text-4xl { font-size: 2.25rem; }
        .text-center { text-align: center; }
        .p-4 { padding: 1rem; }
        .bg-gray-50 { background-color: #f9fafb; }
        .bg-red-50 { background-color: #fef2f2; }
        .bg-green-50 { background-color: #f0fdf4; }
        .bg-blue-50 { background-color: #eff6ff; }
        .bg-indigo-50 { background-color: #eef2ff; }
        .rounded-lg { border-radius: 0.5rem; }
        .calendar-day { height: 3rem; display: flex; align-items: center; justify-content: center; border-radius: 0.5rem; font-weight: 600;}
        .bg-green-200 { background-color: #bbf7d0; color: #166534; }
        .bg-red-200 { background-color: #fecaca; color: #991b1b; }
        .bg-gray-200 { background-color: #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card"><h1>Salary Calculation Checker (Final Logic)</h1></div>
        <div class="card">
            <form method="GET" class="grid grid-cols-4">
                <div style="grid-column: span 2;"><label>Employee</label><select name="employee_id"><?php foreach($allEmployees as $emp): ?><option value="<?php echo $emp->id; ?>" <?php if($selected_employee_id == $emp->id) echo 'selected'; ?>><?php echo $emp->first_name . ' ' . $emp->last_name; ?></option><?php endforeach; ?></select></div>
                <div><label>Month</label><select name="month"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?php echo $m; ?>" <?php if($selected_month == $m) echo 'selected'; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option><?php endfor; ?></select></div>
                <div><label>Year</label><select name="year"><?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?><option value="<?php echo $y; ?>" <?php if($selected_year == $y) echo 'selected'; ?>><?php echo $y; ?></option><?php endfor; ?></select></div>
                <div style="align-self: end;"><button type="submit">Calculate</button></div>
            </form>
        </div>

        <?php if ($calculation): ?>
        <div class="lg-cols-3">
            <div class="card">
                <h2>Calculation for <?php echo htmlspecialchars($calculation['employee']->first_name . ' ' . $calculation['employee']->last_name); ?> - <?php echo date('F Y', strtotime($calculation['payPeriodEnd'])); ?></h2><hr>
                <div class="p-4 bg-gray-50 rounded-lg"><strong>Gross Salary:</strong> <span class="float-right font-bold text-lg">৳<?php echo number_format($calculation['grossSalary'], 2); ?></span></div>
                <div class="p-4 bg-gray-50 rounded-lg"><strong>Basic Salary:</strong> <span class="float-right font-bold text-lg">৳<?php echo number_format($calculation['basicSalary'], 2); ?></span></div>
                <hr>
                <h3>Absence Calculation</h3>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="p-3 bg-blue-50 rounded-lg"><div class="text-xs">Days in Month</div><div class="font-bold text-2xl"><?php echo $calculation['daysInMonth']; ?></div></div>
                    <div class="p-3 bg-green-50 rounded-lg"><div class="text-xs">Present Days</div><div class="font-bold text-2xl"><?php echo $calculation['presentDays']; ?></div></div>
                    <div class="p-3 bg-red-50 rounded-lg"><div class="text-xs">Calculated Absent Days</div><div class="font-bold text-2xl"><?php echo $calculation['absentDays']; ?></div></div>
                </div>
                 <div class="p-3 bg-indigo-50 rounded-lg text-center mt-4"><div class="text-xs">Daily Rate (Basic / 30)</div><div class="font-bold text-2xl">৳<?php echo number_format($calculation['dailyRate'], 2); ?></div></div>
                <div class="p-4 bg-red-50 rounded-lg mt-4"><strong>Absence Deduction (Absent Days * Rate):</strong> <span class="float-right font-bold text-lg">৳<?php echo number_format($calculation['absenceDeduction'], 2); ?></span></div>
                <hr>
                <h3>Other Deductions</h3>
                <div class="p-4 bg-red-50 rounded-lg"><strong>Salary Advance:</strong> <span class="float-right font-bold text-lg">৳<?php echo number_format($calculation['advanceDeduction'], 2); ?></span></div>
                <div class="p-4 bg-red-50 rounded-lg"><strong>Loan Installment (Fixed EMI):</strong> <span class="float-right font-bold text-lg">৳<?php echo number_format($calculation['loanInstallment'], 2); ?></span></div>
                <hr>
                <div class="p-4" style="background-color: #fee2e2; border-radius: 0.5rem;"><strong>TOTAL DEDUCTIONS:</strong> <span class="float-right font-bold text-2xl">৳<?php echo number_format($calculation['totalDeductions'], 2); ?></span></div>
                <div class="p-6 text-center" style="background-color: #dcfce7; border-radius: 0.5rem; margin-top: 1rem;"><h3 style="font-size: 1.25rem; font-weight: bold;">NET SALARY PAYABLE</h3><p style="font-size: 2.25rem; font-weight: 800; color: #166534; margin-top: 0.5rem;">৳<?php echo number_format($calculation['netSalary'], 2); ?></p></div>
            </div>
            <div class="card">
                <h2 class="text-center">Attendance Calendar</h2><hr>
                <div class="grid grid-cols-7 text-center font-bold"><div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div></div>
                <div class="grid grid-cols-7">
                    <?php foreach($attendanceCalendar as $day): ?>
                        <div class="calendar-day <?php if($day['status'] === 'present') echo 'bg-green-200'; if($day['status'] === 'unknown') echo 'bg-red-200'; ?>"><?php echo $day['day']; ?></div>
                    <?php endforeach; ?>
                </div>
                 <ul style="list-style: none; padding: 0; margin-top: 1rem;">
                    <li style="display: flex; align-items: center;"><span style="width: 1rem; height: 1rem; border-radius: 50%; background-color: #bbf7d0; margin-right: 0.5rem;"></span> Present</li>
                    <li style="display: flex; align-items: center;"><span style="width: 1rem; height: 1rem; border-radius: 50%; background-color: #fecaca; margin-right: 0.5rem;"></span> Absent / No Record</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>