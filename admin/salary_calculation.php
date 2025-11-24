<?php
// new_ufmhrm/admin/salary_calculation.php

ini_set('display_errors', 1);
error_reporting(E_ALL);


//die('reached here');


// --- 1. DATABASE CONNECTION ---
$db_host = 'localhost';
$db_name = 'ujjalfmc_hr';
$db_user = 'ujjalfmc_hr'; 
$db_pass = 'ujjalfmhr1234';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- 2. EXPORT CSV LOGIC (NEW) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_month = $_GET['month'] ?? date('m');
    $export_year = $_GET['year'] ?? date('Y');
    //$daysInExportMonth = cal_days_in_month(CAL_GREGORIAN, $export_month, $export_year);
    $daysInExportMonth = 30;

    // Clear output buffer to prevent corruption
    if (ob_get_length()) ob_end_clean();
    
    // Set Headers for Download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Salary_Report_' . date('F_Y', mktime(0,0,0,$export_month, 1, $export_year)) . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // Add BOM for Excel compatibility
    
    // CSV Headers
    fputcsv($output, [
    'Employee ID', 'Name', 'Basic Salary', 'Total Days', 
    'Present', 'On Leave (Paid)', 'Absent', 'Late', 
    'Absent Deduction', 'Loan Deduction', 'Advance Deduction', 
    'Total Deductions', 'Net Salary'
], ",", "\"", "\\");

    // Fetch all active employees
    $employees = $pdo->query("SELECT id, first_name, last_name, base_salary FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($employees as $emp) {
        $empId = $emp['id'];
        $basic = $emp['base_salary'];
        $daily = ($daysInExportMonth > 0) ? ($basic / $daysInExportMonth) : 0;

        // Calculate Attendance Counts
        $stats = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as p_count,
                SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as l_count,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as a_count,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
            FROM attendance 
            WHERE employee_id = ? AND MONTH(clock_in) = ? AND YEAR(clock_in) = ?
        ");
        $stats->execute([$empId, $export_month, $export_year]);
        $res = $stats->fetch(PDO::FETCH_ASSOC);
        
        $p_count = $res['p_count'] ?? 0;
        $l_count = $res['l_count'] ?? 0;
        $a_count = $res['a_count'] ?? 0;
        $late_count = $res['late_count'] ?? 0;

        // Calculate Financials
        $absentDed = $a_count * $daily;
        
        // Loan Deduction
        $loanDed = 0;
        $lQ = $pdo->prepare("SELECT monthly_payment FROM loans WHERE employee_id = ? AND status = 'active'");
        $lQ->execute([$empId]);
        if ($l = $lQ->fetch(PDO::FETCH_ASSOC)) $loanDed = $l['monthly_payment'];
        
        // Advance Deduction
        $advDed = 0;
        $aQ = $pdo->prepare("SELECT SUM(amount) as total FROM salary_advances WHERE employee_id = ? AND advance_month = ? AND advance_year = ? AND status = 'approved'");
        $aQ->execute([$empId, $export_month, $export_year]);
        if ($ad = $aQ->fetch(PDO::FETCH_ASSOC)) $advDed = $ad['total'] ?? 0;

        $totalDed = $absentDed + $loanDed + $advDed;
        $net = $basic - $totalDed;

        // Write Row
        // NEW LINE (Fixed)
fputcsv($output, [
    $empId,
    $emp['first_name'] . ' ' . $emp['last_name'],
    number_format($basic, 2, '.', ''),
    $daysInExportMonth,
    $p_count,
    $l_count,
    $a_count,
    $late_count,
    number_format($absentDed, 2, '.', ''),
    number_format($loanDed, 2, '.', ''),
    number_format($advDed, 2, '.', ''),
    number_format($totalDed, 2, '.', ''),
    number_format($net, 2, '.', '')
], ",", "\"", "\\");
    }
    fclose($output);
    exit();
}

// --- 3. INITIALIZE VARIABLES ---
$presentCount = 0;
$onLeaveCount = 0;
$lateCount = 0;
$absentCount = 0;
$daysInMonth = 0;
$calculation = null;
$attendanceCalendar = [];

// --- 4. DATA FETCHING ---
$allEmployees = $pdo->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();

$selected_employee_id = $_GET['employee_id'] ?? null;
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');

// --- 5. SINGLE EMPLOYEE CALCULATION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['employee_id']) && !empty($_GET['employee_id'])) {
    
    $employee_id = $_GET['employee_id'];
    $month = $_GET['month'];
    $year = $_GET['year'];

    // Fetch Basic Salary
    $stmt = $pdo->prepare("SELECT base_salary FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch();
    
    if ($emp) {
        $basicSalary = $emp->base_salary;
        
        // Calculate days in month
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $dailySalary = ($daysInMonth > 0) ? ($basicSalary / 30) : 0;

        // Fetch Attendance Records
        $stmt = $pdo->prepare("SELECT DAY(clock_in) as day, status FROM attendance WHERE employee_id = ? AND MONTH(clock_in) = ? AND YEAR(clock_in) = ?");
        $stmt->execute([$employee_id, $month, $year]);
        $attendanceRecords = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Process Attendance
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $status = $attendanceRecords[$i] ?? 'unknown';
            
            if ($status === 'present') {
                $presentCount++;
                $calendarData[] = ['day' => $i, 'status' => 'present'];
            } elseif ($status === 'on_leave') {
                $onLeaveCount++;
                $calendarData[] = ['day' => $i, 'status' => 'leave'];
            } elseif ($status === 'late') {
                $lateCount++;
                $calendarData[] = ['day' => $i, 'status' => 'late'];
            } elseif ($status === 'absent') {
                $absentCount++;
                $calendarData[] = ['day' => $i, 'status' => 'absent'];
            } else {
                 $calendarData[] = ['day' => $i, 'status' => 'unknown'];
            }
        }
        $attendanceCalendar = $calendarData;

        // Calculate Salary Components
        $grossSalary = $basicSalary;
        $absentDeduction = $absentCount * $dailySalary;
        
        // Loans
        $loanDeduction = 0;
        $stmt = $pdo->prepare("SELECT monthly_payment FROM loans WHERE employee_id = ? AND status = 'active'");
        $stmt->execute([$employee_id]);
        $loan = $stmt->fetch();
        if ($loan) {
            $loanDeduction = $loan->monthly_payment ?? 0;
        }

        // Salary Advance
        $advanceDeduction = 0;
        $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM salary_advances WHERE employee_id = ? AND advance_month = ? AND advance_year = ? AND status = 'approved'");
        $stmt->execute([$employee_id, $month, $year]);
        $advance = $stmt->fetch();
        if ($advance) {
            $advanceDeduction = $advance->total ?? 0;
        }

        $totalDeductions = $absentDeduction + $loanDeduction + $advanceDeduction;
        $netSalary = $grossSalary - $totalDeductions;

        $calculation = [
            'basicSalary' => $basicSalary,
            'grossSalary' => $grossSalary,
            'dailySalary' => $dailySalary,
            'absentCount' => $absentCount,
            'absentDeduction' => $absentDeduction,
            'loanDeduction' => $loanDeduction,
            'advanceDeduction' => $advanceDeduction,
            'totalDeductions' => $totalDeductions,
            'netSalary' => $netSalary
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Calculation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-2xl font-bold text-gray-800">Salary Calculator</h1>
                <a href="index.php" class="text-blue-600 hover:underline">Back to Dashboard</a>
            </div>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Employee</label>
                    <select name="employee_id" class="w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        <option value="">-- Select (Optional for Export) --</option>
                        <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?php echo $emp->id; ?>" <?php echo ($selected_employee_id == $emp->id) ? 'selected' : ''; ?>>
                                <?php echo $emp->first_name . ' ' . $emp->last_name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                    <select name="month" class="w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo ($selected_month == $m) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                    <select name="year" class="w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        <?php for ($y = date('Y') + 5; $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($selected_year == $y) ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 shadow-sm transition">
                        Calculate
                    </button>
                    <button type="submit" name="export" value="csv" class="flex-1 bg-green-600 text-white py-2 rounded-md hover:bg-green-700 shadow-sm transition flex items-center justify-center gap-2" title="Download salary report for all employees">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                </div>
            </form>
        </div>

        <?php if ($calculation): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-2 space-y-6">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="p-4 bg-white rounded-lg shadow-sm text-center">
                        <p class="text-sm text-gray-500">Total Days</p>
                        <p class="text-xl font-bold text-gray-800"><?php echo $daysInMonth; ?></p>
                    </div>
                    <div class="p-4 bg-green-50 rounded-lg shadow-sm text-center">
                        <p class="text-sm text-green-600">Present</p>
                        <p class="text-xl font-bold text-green-800"><?php echo $presentCount; ?></p>
                    </div>
                    <div class="p-4 bg-blue-50 rounded-lg shadow-sm text-center">
                        <p class="text-sm text-blue-600">On Leave (Paid)</p>
                        <p class="text-xl font-bold text-blue-800"><?php echo $onLeaveCount; ?></p>
                    </div>
                    <div class="p-4 bg-amber-50 rounded-lg shadow-sm text-center">
                        <p class="text-sm text-amber-600">Late</p>
                        <p class="text-xl font-bold text-amber-800"><?php echo $lateCount; ?></p>
                    </div>
                    <div class="p-4 bg-red-50 rounded-lg shadow-sm text-center">
                        <p class="text-sm text-red-600">Absent (Unpaid)</p>
                        <p class="text-xl font-bold text-red-800"><?php echo $absentCount; ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Attendance Calendar</h3>
                    <div class="grid grid-cols-7 gap-1 text-center font-medium text-gray-500 mb-2">
                        <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                    </div>
                    <div class="grid grid-cols-7 gap-1">
                        <?php foreach ($attendanceCalendar as $day): ?>
                            <div class="calendar-day rounded-md text-sm 
                                <?php 
                                    if ($day['status'] === 'present') echo 'bg-green-100 text-green-700 border-green-200';
                                    elseif ($day['status'] === 'leave') echo 'bg-blue-100 text-blue-700 border-blue-200';
                                    elseif ($day['status'] === 'late') echo 'bg-amber-100 text-amber-700 border-amber-200';
                                    elseif ($day['status'] === 'absent') echo 'bg-red-100 text-red-700 border-red-200';
                                    else echo 'bg-gray-50 text-gray-400';
                                ?>">
                                <?php echo $day['day']; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex flex-wrap gap-4 mt-4 text-sm text-gray-600">
                        <div class="flex items-center"><span class="w-3 h-3 bg-green-100 border border-green-200 rounded-full mr-2"></span>Present</div>
                        <div class="flex items-center"><span class="w-3 h-3 bg-blue-100 border border-blue-200 rounded-full mr-2"></span>On Leave</div>
                        <div class="flex items-center"><span class="w-3 h-3 bg-amber-100 border border-amber-200 rounded-full mr-2"></span>Late</div>
                        <div class="flex items-center"><span class="w-3 h-3 bg-red-100 border border-red-200 rounded-full mr-2"></span>Absent</div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900">Salary Breakdown</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Earnings</p>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-600">Basic Salary</span>
                                <span class="font-medium">৳<?php echo number_format($calculation['basicSalary'], 2); ?></span>
                            </div>
                            <div class="flex justify-between border-t pt-2 mt-2">
                                <span class="font-bold text-gray-800">Gross Salary</span>
                                <span class="font-bold text-green-600">৳<?php echo number_format($calculation['grossSalary'], 2); ?></span>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-2 mt-4">Deductions</p>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-600">Absence (<?php echo $absentCount; ?> days)</span>
                                <span class="text-red-500">- ৳<?php echo number_format($calculation['absentDeduction'], 2); ?></span>
                            </div>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-600">Salary Advance</span>
                                <span class="text-red-500">- ৳<?php echo number_format($calculation['advanceDeduction'], 2); ?></span>
                            </div>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-600">Loan Installment</span>
                                <span class="text-red-500">- ৳<?php echo number_format($calculation['loanDeduction'], 2); ?></span>
                            </div>
                            <div class="flex justify-between border-t pt-2 mt-2">
                                <span class="font-bold text-gray-800">Total Deductions</span>
                                <span class="font-bold text-red-600">- ৳<?php echo number_format($calculation['totalDeductions'], 2); ?></span>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg mt-6 border border-gray-200">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-gray-900">NET PAY</span>
                                <span class="text-2xl font-bold text-indigo-600">৳<?php echo number_format($calculation['netSalary'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <?php endif; ?>

    </div>
</body>
</html>