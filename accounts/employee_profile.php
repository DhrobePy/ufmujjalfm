<?php
// new_ufmhrm/accounts/employee_profile.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

// --- SECURITY & PERMISSIONS ---
if (!is_admin_logged_in()) {
    redirect('../auth/login.php');
}

$currentUser = getCurrentUser();
$user_branch_id = $currentUser['branch_id'];
$branch_account_roles = ['Accounts- Srg', 'Accounts- Rampura'];
$is_branch_accountant = in_array($currentUser['role'], $branch_account_roles);

// Get employee ID from URL and ensure it's valid
$employeeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$employeeId) {
    redirect('employees.php');
}

// --- SECURE EMPLOYEE FETCHING (BRANCH-SCOPED) ---
$params = [$employeeId];
$branch_check_sql = "";
if ($is_branch_accountant && !empty($user_branch_id)) {
    $branch_check_sql = " AND e.branch_id = ? ";
    $params[] = $user_branch_id;
}

$sql = "
    SELECT 
        e.*, 
        p.name as position_name, 
        d.name as department_name,
        d.id as department_id
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    WHERE e.id = ? $branch_check_sql
";

$result = $db->query($sql, $params);
$employee = $result ? $result->first() : null;

// If employee is not found OR doesn't belong to the accountant's branch, redirect.
if (!$employee) {
    set_message('Employee not found or you do not have permission to view this profile.', 'error');
    redirect('employees.php');
}

// --- DATA FETCHING FOR PROFILE WIDGETS ---
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');

// Attendance statistics for the current month
$attendanceStats = $db->query("
    SELECT 
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
    FROM attendance 
    WHERE employee_id = ? AND `date` BETWEEN ? AND ?
", [$employeeId, $startOfMonth, $endOfMonth])->first();

// Fetch latest salary structure
$salaryStructure = $db->query("SELECT * FROM salary_structures WHERE employee_id = ? ORDER BY created_date DESC LIMIT 1", [$employeeId])->first();

// Fetch full month attendance for calendar
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$attendanceCalendar = $db->query("SELECT DATE(`date`) as attendance_date, status FROM attendance WHERE employee_id = ? AND MONTH(`date`) = ? AND YEAR(`date`) = ? ORDER BY `date`", [$employeeId, $currentMonth, $currentYear])->results();
$attendanceMap = [];
foreach ($attendanceCalendar as $record) {
    $attendanceMap[$record->attendance_date] = $record->status;
}

// Fetch recent history
$recentAttendance = $db->query("SELECT * FROM attendance WHERE employee_id = ? ORDER BY `date` DESC LIMIT 5", [$employeeId])->results();
$leaveRequests = $db->query("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY start_date DESC LIMIT 5", [$employeeId])->results();
$salaryAdvances = $db->query("SELECT * FROM salary_advances WHERE employee_id = ? ORDER BY advance_date DESC", [$employeeId])->results();
$loans = $db->query("SELECT * FROM loans WHERE employee_id = ? ORDER BY loan_date DESC", [$employeeId])->results();

// For each active loan, get its installment history
foreach ($loans as $loan) {
    if ($loan->status === 'active') {
        $loan->paid_installments = $db->query("SELECT * FROM loan_installments WHERE loan_id = ? ORDER BY payment_date ASC", [$loan->id])->results();
    } else {
        $loan->paid_installments = [];
    }
}

// Calculate tenure
$hireDate = new DateTime($employee->hire_date);
$today = new DateTime();
$tenure = $hireDate->diff($today);

// Fetch payroll history (last 12 paid records)
$payrollHistory = $db->query("
    SELECT pay_period_end, net_salary, status 
    FROM payrolls 
    WHERE employee_id = ? AND status = 'paid' 
    ORDER BY pay_period_end DESC 
    LIMIT 12
", [$employeeId])->results();

$pageTitle = $employee->first_name . ' ' . $employee->last_name . ' - Profile';

// --- CONDITIONAL HEADER ---
if ($is_branch_accountant) {
    include_once '../templates/accounts_header.php';
} else {
    include_once '../templates/header.php';
}
?>

<!-- Google Font Import & Styles -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style> body { font-family: 'Poppins', sans-serif; } </style>

<div class="space-y-6">
    <!-- Back Button -->
    <div>
        <a href="employees.php" class="inline-flex items-center text-sm text-gray-600 hover:text-primary-700 font-semibold">
            <i class="fas fa-arrow-left mr-2"></i>Back to Employees
        </a>
    </div>

    <!-- Profile Header Card -->
    <div class="bg-gradient-to-r from-primary-600 to-primary-800 rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-8 sm:p-10">
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
                <div class="relative flex-shrink-0">
                    <div class="h-32 w-32 rounded-full bg-white flex items-center justify-center shadow-xl ring-4 ring-white ring-opacity-50 overflow-hidden">
                        <?php if (!empty($employee->profile_picture) && file_exists('../' . $employee->profile_picture)): ?>
                            <img class="h-full w-full object-cover" src="../<?php echo htmlspecialchars($employee->profile_picture); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <span class="text-5xl font-bold text-primary-600"><?php echo strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="absolute bottom-1 right-1 h-8 w-8 bg-<?php echo $employee->status === 'active' ? 'green' : 'gray'; ?>-500 rounded-full border-4 border-primary-700" title="Status: <?php echo ucfirst($employee->status); ?>"></div>
                </div>
                <div class="flex-1 text-center sm:text-left">
                    <h1 class="text-3xl font-bold text-white mb-2"><?php echo htmlspecialchars($employee->first_name . ' ' . $employee->last_name); ?></h1>
                    <p class="text-xl text-primary-100 mb-4"><?php echo htmlspecialchars($employee->position_name ?? 'N/A'); ?></p>
                    <div class="flex flex-wrap gap-3 justify-center sm:justify-start">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white bg-opacity-20 text-white"><i class="fas fa-building mr-2"></i><?php echo htmlspecialchars($employee->department_name ?? 'N/A'); ?></span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white bg-opacity-20 text-white"><i class="fas fa-id-badge mr-2"></i>EMP-<?php echo str_pad($employee->id, 4, '0', STR_PAD_LEFT); ?></span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-<?php echo $employee->status === 'active' ? 'green' : 'red'; ?>-500 text-white"><i class="fas fa-circle mr-2 text-xs"></i><?php echo ucfirst(str_replace('_', ' ', $employee->status)); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Present Days</p>
                    <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $attendanceStats->present_days ?? 0; ?></p>
                    <p class="text-xs text-gray-500 mt-1">This month</p>
                </div>
                <div class="h-12 w-12 bg-green-100 rounded-lg flex items-center justify-center"><i class="fas fa-calendar-check text-2xl text-green-600"></i></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
             <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Absent Days</p>
                    <p class="text-3xl font-bold text-red-600 mt-2"><?php echo $attendanceStats->absent_days ?? 0; ?></p>
                    <p class="text-xs text-gray-500 mt-1">This month</p>
                </div>
                <div class="h-12 w-12 bg-red-100 rounded-lg flex items-center justify-center"><i class="fas fa-calendar-times text-2xl text-red-600"></i></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
             <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Tenure</p>
                    <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $tenure->y; ?><span class="text-lg">y</span> <?php echo $tenure->m; ?><span class="text-lg">m</span></p>
                    <p class="text-xs text-gray-500 mt-1">Since <?php echo date('M Y', strtotime($employee->hire_date)); ?></p>
                </div>
                <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center"><i class="fas fa-briefcase text-2xl text-blue-600"></i></div>
            </div>
        </div>
         <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Base Salary</p>
                    <p class="text-3xl font-bold text-indigo-600 mt-2">৳<?php echo number_format($employee->base_salary, 0); ?></p>
                    <p class="text-xs text-gray-500 mt-1">Per month</p>
                </div>
                <div class="h-12 w-12 bg-indigo-100 rounded-lg flex items-center justify-center"><i class="fas fa-money-bill-wave text-2xl text-indigo-600"></i></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Salary Breakdown Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900"><i class="fas fa-wallet text-primary-600 mr-2"></i>Salary Breakdown</h2></div>
                <div class="p-6">
                    <?php if ($salaryStructure): ?>
                        <div class="space-y-4 text-sm">
                            <h3 class="font-semibold text-green-600 text-base">Earnings</h3>
                            <div class="space-y-2 pl-4 border-l-2 border-green-200">
                                <p class="flex justify-between"><span><i class="fas fa-money-bill-wave w-5 mr-2 text-gray-400"></i>Basic Salary</span> <span class="font-medium text-gray-800">৳<?php echo number_format($salaryStructure->basic_salary, 2); ?></span></p>
                                <p class="flex justify-between"><span><i class="fas fa-home w-5 mr-2 text-gray-400"></i>House Allowance</span> <span class="font-medium text-gray-800">৳<?php echo number_format($salaryStructure->house_allowance, 2); ?></span></p>
                                <p class="flex justify-between"><span><i class="fas fa-bus w-5 mr-2 text-gray-400"></i>Transport</span> <span class="font-medium text-gray-800">৳<?php echo number_format($salaryStructure->transport_allowance, 2); ?></span></p>
                                <p class="flex justify-between"><span><i class="fas fa-briefcase-medical w-5 mr-2 text-gray-400"></i>Medical</span> <span class="font-medium text-gray-800">৳<?php echo number_format($salaryStructure->medical_allowance, 2); ?></span></p>
                                <p class="flex justify-between"><span><i class="fas fa-plus-circle w-5 mr-2 text-gray-400"></i>Other</span> <span class="font-medium text-gray-800">৳<?php echo number_format($salaryStructure->other_allowances, 2); ?></span></p>
                            </div>
                            <p class="flex justify-between font-bold text-base border-t pt-3 mt-3"><span class="text-green-700">Gross Salary</span> <span class="text-green-700">৳<?php echo number_format($salaryStructure->gross_salary, 2); ?></span></p>
                            <h3 class="font-semibold text-red-600 text-base pt-4">Deductions</h3>
                            <div class="space-y-2 pl-4 border-l-2 border-red-200">
                                <p class="flex justify-between"><span><i class="fas fa-landmark w-5 mr-2 text-gray-400"></i>Provident Fund</span> <span class="font-medium text-gray-800">৳<?php echo number_format($salaryStructure->provident_fund, 2); ?></span></p>
                                <p class="flex justify-between"><span><i class="fas fa-file-invoice-dollar w-5 mr-2 text-gray-400"></i>Tax</span> <span class="font-medium text-gray-800">৳<?php echo number_format($salaryStructure->tax_deduction, 2); ?></span></p>
                            </div>
                            <p class="flex justify-between font-bold text-base border-t pt-3 mt-3"><span class="text-primary-700">Net Salary</span> <span class="text-primary-700">৳<?php echo number_format($salaryStructure->net_salary, 2); ?></span></p>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-8">No salary structure found.</p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Personal and Employment Details Cards -->
            <!-- ... (These can be added back here from your original template) ... -->
        </div>
        
        <!-- Right Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Attendance Calendar (YOUR UNCHANGED DESIGN) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900"><i class="fas fa-calendar text-primary-600 mr-2"></i>Attendance Calendar</h2>
                    <div class="flex gap-2">
                        <a href="?id=<?php echo $employeeId; ?>&month=<?php echo ($currentMonth == 1 ? 12 : $currentMonth - 1); ?>&year=<?php echo ($currentMonth == 1 ? $currentYear - 1 : $currentYear); ?>" class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-200 rounded transition-colors"><i class="fas fa-chevron-left"></i></a>
                        <span class="px-4 py-1 text-sm font-semibold text-gray-900 bg-white border border-gray-200 rounded"><?php echo date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)); ?></span>
                        <a href="?id=<?php echo $employeeId; ?>&month=<?php echo ($currentMonth == 12 ? 1 : $currentMonth + 1); ?>&year=<?php echo ($currentMonth == 12 ? $currentYear + 1 : $currentYear); ?>" class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-200 rounded transition-colors"><i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-7 gap-2">
                        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day): ?><div class="text-center font-semibold text-sm text-gray-600 py-2"><?php echo $day; ?></div><?php endforeach; ?>
                        <?php
                        $firstDay = date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
                        $daysInMonth = date('t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
                        for ($i = 0; $i < $firstDay; $i++) { echo '<div></div>'; }
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            $date = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                            $status = $attendanceMap[$date] ?? null;
                            $today = date('Y-m-d');
                            $isToday = $date === $today;
                            
                            $statusClass = ''; $statusLabel = '';
                            if ($status === 'present') { $statusClass = 'bg-green-100 text-green-800 border-green-300'; $statusLabel = '✓'; }
                            elseif ($status === 'absent') { $statusClass = 'bg-red-100 text-red-800 border-red-300'; $statusLabel = '✕'; }
                            elseif (isset($attendanceMap[$date]) && $attendanceMap[$date] != '') { $statusClass = 'bg-blue-100 text-blue-800 border-blue-300'; $statusLabel = 'L'; }
                            else { $statusClass = 'bg-gray-100 text-gray-400'; $statusLabel = ''; }
                            
                            echo '<div class="relative"><div class="h-16 rounded-lg ' . $statusClass . ' border-2 flex items-center justify-center cursor-pointer hover:shadow-md transition-shadow group" title="' . ($status ? ucfirst($status) : 'No record') . '">';
                            echo '<div class="text-center"><div class="text-xs font-bold">' . $day . '</div>';
                            if ($statusLabel) { echo '<div class="text-lg">' . $statusLabel . '</div>'; }
                            echo '</div>';
                            if ($isToday) { echo '<div class="absolute top-1 right-1 w-2 h-2 bg-primary-500 rounded-full"></div>'; }
                            echo '</div></div>';
                        }
                        ?>
                    </div>
                     <div class="mt-6 pt-6 border-t border-gray-200 flex flex-wrap gap-4 text-sm">
                        <div class="flex items-center gap-2"><div class="w-4 h-4 bg-green-100 border-2 border-green-300 rounded"></div><span class="text-gray-600">Present</span></div>
                        <div class="flex items-center gap-2"><div class="w-4 h-4 bg-red-100 border-2 border-red-300 rounded"></div><span class="text-gray-600">Absent</span></div>
                        <div class="flex items-center gap-2"><div class="w-4 h-4 bg-blue-100 border-2 border-blue-300 rounded"></div><span class="text-gray-600">Leave</span></div>
                        <div class="flex items-center gap-2"><div class="w-4 h-4 bg-gray-100 border-2 border-gray-300 rounded"></div><span class="text-gray-600">No Record</span></div>
                    </div>
                </div>
            </div>

            <!-- Salary Advance History -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900"><i class="fas fa-hand-holding-usd text-orange-600 mr-2"></i>Salary Advance History</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($salaryAdvances): ?>
                                <?php foreach ($salaryAdvances as $advance): ?>
                                    <tr class="hover:bg-gray-50"><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('M d, Y', strtotime($advance->advance_date)); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-medium">৳<?php echo number_format($advance->amount, 2); ?></td><td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $advance->status === 'paid' ? 'bg-green-100 text-green-800' : ($advance->status === 'approved' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'); ?>"><?php echo ucfirst($advance->status); ?></span></td></tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-8 text-gray-500">No salary advance records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- NEW: Past Paid Salary Widget -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">
            <i class="fas fa-piggy-bank text-teal-600 mr-2"></i>Paid Salary History
        </h2>
    </div>
    <div class="p-6">
        <?php if ($payrollHistory): ?>
            <div class="space-y-4">
                <?php foreach ($payrollHistory as $payroll): ?>
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 last:border-b-0">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-8 w-8 bg-teal-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-check-circle text-teal-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Paid for <?php echo date('F Y', strtotime($payroll->pay_period_end)); ?></p>
                                <p class="text-xs text-gray-500">Status: <?php echo ucfirst($payroll->status); ?></p>
                            </div>
                        </div>
                        <div class="text-sm font-bold text-teal-700">
                            ৳<?php echo number_format($payroll->net_salary, 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-500 py-8">No paid payroll history found.</p>
        <?php endif; ?>
    </div>
</div>


            <!-- Loan History & EMI Schedules -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900"><i class="fas fa-file-invoice-dollar text-indigo-600 mr-2"></i>Loan History & Schedules</h2></div>
                <div class="p-6 space-y-6">
                    <?php if ($loans): ?>
                        <?php foreach ($loans as $loan): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-gray-800">Loan of ৳<?php echo number_format($loan->amount, 2); ?></p>
                                        <p class="text-xs text-gray-500">Taken on <?php echo date('M d, Y', strtotime($loan->loan_date)); ?></p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $loan->status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-indigo-100 text-indigo-800'; ?>"><?php echo ucfirst($loan->status); ?></span>
                                </div>
                                <?php if ($loan->status === 'active' && $loan->installments > 0): ?>
                                    <div class="mt-4">
                                        <h4 class="text-sm font-semibold mb-2">Installment Schedule (৳<?php echo number_format($loan->monthly_payment); ?> /mo)</h4>
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                                            <?php
                                            $paid_dates = array_map(function($inst) { return date('Y-m', strtotime($inst->payment_date)); }, $loan->paid_installments);
                                            for ($i = 1; $i <= $loan->installments; $i++):
                                                $dueDate = date('Y-m-d', strtotime("+$i month", strtotime($loan->loan_date)));
                                                $dueMonthYear = date('Y-m', strtotime($dueDate));
                                                $isPaid = in_array($dueMonthYear, $paid_dates);
                                            ?>
                                                <div class="flex items-center p-2 rounded <?php echo $isPaid ? 'bg-green-50' : 'bg-gray-50'; ?>">
                                                    <?php if ($isPaid): ?>
                                                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                                        <span class="font-semibold text-gray-700"><?php echo date('M Y', strtotime($dueDate)); ?>: <span class="text-green-600">Paid</span></span>
                                                    <?php else: ?>
                                                         <i class="far fa-circle text-gray-400 mr-2"></i>
                                                         <span class="text-gray-500"><?php echo date('M Y', strtotime($dueDate)); ?>: Due</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-8">No loan records found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>

