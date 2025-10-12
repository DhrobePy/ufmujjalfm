<?php
// new_ufmhrm/admin/index.php

// Force error reporting to ensure no blank screens
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load all core files and create the global $db variable
require_once '../core/init.php';

// Authentication Check
if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Dashboard - ' . APP_NAME;
include_once '../templates/header.php';

// --- Comprehensive Data Fetching for All Dashboard Components ---
$employee = new Employee($db);
$totalEmployees = $employee->count();
$today = date('Y-m-d');
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');
$daysInMonth = date('t');

// --- Data for Cards ---
$presentResult = $db->query("SELECT COUNT(*) as count FROM attendance WHERE DATE(clock_in) = ? AND status = 'present'", [$today]);
$presentCount = $presentResult ? $presentResult->first()->count : 0;

$pendingLeavesResult = $db->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
$onLeaveCount = $pendingLeavesResult ? $pendingLeavesResult->first()->count : 0;

$todaysExpenseSql = "SELECT SUM(e.base_salary / ?) as daily_expense FROM employees e JOIN attendance a ON e.id = a.employee_id WHERE a.status = 'present' AND DATE(a.clock_in) = ?";
$todaysExpenseResult = $db->query($todaysExpenseSql, [$daysInMonth, $today]);
$todaysSalaryExpense = $todaysExpenseResult->first()->daily_expense ?? 0;

$monthlyExpenseSql = "SELECT SUM(base_salary) as total_salary FROM employees WHERE status = 'active'";
$monthlyExpenseResult = $db->query($monthlyExpenseSql);
$estimatedMonthlySalary = $monthlyExpenseResult->first()->total_salary ?? 0;

$cumulativeExpenseSql = "SELECT SUM(e.base_salary / ?) as cumulative_expense FROM employees e JOIN attendance a ON e.id = a.employee_id WHERE a.status = 'present' AND DATE(a.clock_in) BETWEEN ? AND ?";
$cumulativeExpenseResult = $db->query($cumulativeExpenseSql, [$daysInMonth, $startOfMonth, $today]);
$cumulativeSalaryExpense = $cumulativeExpenseResult->first()->cumulative_expense ?? 0;

// (NEW) Salary Advance for This Month
$advancesSql = "SELECT SUM(amount) as total_advances FROM salary_advances WHERE advance_date BETWEEN ? AND ?";
$advancesResult = $db->query($advancesSql, [$startOfMonth, $endOfMonth]);
$monthlyAdvances = $advancesResult->first()->total_advances ?? 0;

// (NEW) Loans Taken This Month
$loansSql = "SELECT SUM(amount) as total_loans FROM loans WHERE loan_date BETWEEN ? AND ?";
$loansResult = $db->query($loansSql, [$startOfMonth, $endOfMonth]);
$monthlyLoans = $loansResult->first()->total_loans ?? 0;

// (NEW) Remaining Salary Expense
$remainingSalary = $estimatedMonthlySalary - $cumulativeSalaryExpense;

// --- Data for Charts & Lists ---
$absentCount = $totalEmployees > 0 ? ($totalEmployees - $presentCount - $onLeaveCount) : 0;
$deptEmpSql = "SELECT d.name AS department_name, COUNT(e.id) AS total_employees FROM departments d LEFT JOIN positions p ON d.id = p.department_id LEFT JOIN employees e ON p.id = e.position_id GROUP BY d.name HAVING total_employees > 0 ORDER BY d.name";
$deptEmpResult = $db->query($deptEmpSql);
$deptEmpStats = $deptEmpResult ? $deptEmpResult->results() : [];
$deptEmpLabels = []; $deptEmpData = [];
foreach ($deptEmpStats as $stat) { $deptEmpLabels[] = $stat->department_name; $deptEmpData[] = $stat->total_employees; }

$deptSalarySql = "SELECT d.name AS department_name, SUM(e.base_salary) AS total_salary FROM departments d LEFT JOIN positions p ON d.id = p.department_id LEFT JOIN employees e ON p.id = e.position_id WHERE e.status = 'active' GROUP BY d.name HAVING total_salary > 0 ORDER BY total_salary DESC";
$deptSalaryResult = $db->query($deptSalarySql);
$deptSalaryStats = $deptSalaryResult ? $deptSalaryResult->results() : [];
$deptSalaryLabels = []; $deptSalaryData = [];
foreach ($deptSalaryStats as $stat) { $deptSalaryLabels[] = $stat->department_name; $deptSalaryData[] = $stat->total_salary; }

$currentYear = date('Y');
$monthlyExpenseHistorySql = "SELECT MONTH(pay_period_end) as month, SUM(net_salary) as total_paid FROM payrolls WHERE YEAR(pay_period_end) = ? AND status = 'paid' GROUP BY MONTH(pay_period_end) ORDER BY month ASC";
$monthlyExpenseResult = $db->query($monthlyExpenseHistorySql, [$currentYear]);
$monthlyExpenses = $monthlyExpenseResult ? $monthlyExpenseResult->results() : [];
$monthData = array_fill(1, 12, 0);
foreach ($monthlyExpenses as $expense) { $monthData[(int)$expense->month] = $expense->total_paid; }
$monthLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
$monthData = array_values($monthData);

$topEarnersSql = "SELECT e.first_name, e.last_name, e.base_salary, p.name AS position_name FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE e.status = 'active' ORDER BY e.base_salary DESC LIMIT 10";
$topEarnersResult = $db->query($topEarnersSql);
$topEarners = $topEarnersResult ? $topEarnersResult->results() : [];
?>

<div class="space-y-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-tachometer-alt text-primary-600 mr-3"></i>Admin Dashboard</h1>
        <p class="mt-1 text-sm text-gray-600">Welcome back, <?php echo $_SESSION['admin_name']; ?>! Here's your HRM overview for <?php echo date("l, F j, Y"); ?>.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Total Employees Card -->
        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-blue-100">Total Employees</p>
                    <p class="text-4xl font-bold mt-2"><?php echo $totalEmployees; ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4">
                    <i class="fas fa-users text-4xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Present Today Card -->
        <div class="bg-gradient-to-br from-green-500 to-teal-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-green-100">Present Today</p>
                    <p class="text-4xl font-bold mt-2"><?php echo $presentCount; ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4">
                    <i class="fas fa-user-check text-4xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Pending Leave Card -->
        <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-amber-100">Pending Leave</p>
                    <p class="text-4xl font-bold mt-2"><?php echo $onLeaveCount; ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4">
                    <i class="fas fa-calendar-times text-4xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Today's Salary Expense Card -->
        <div class="bg-gradient-to-br from-rose-500 to-pink-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-rose-100">Today's Salary Expense</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($todaysSalaryExpense, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4">
                    <i class="fas fa-calendar-day text-4xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Cumulative Expense Card -->
        <div class="bg-gradient-to-br from-cyan-500 to-sky-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-cyan-100">Cumulative Expense (MTD)</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($cumulativeSalaryExpense, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4">
                    <i class="fas fa-chart-line text-4xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Est. Monthly Salary Card -->
        <div class="bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-purple-100">Est. Monthly Salary</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($estimatedMonthlySalary, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4">
                    <i class="fas fa-money-bill-wave text-4xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Salary Advance Card -->
        <div class="bg-gradient-to-br from-red-500 to-red-700 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-red-100">Salary Advance (This Month)</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($monthlyAdvances, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4">
                    <i class="fas fa-hand-holding-usd text-4xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Loans Taken Card -->
        <div class="bg-gradient-to-br from-fuchsia-500 to-purple-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-fuchsia-100">Loans Taken (This Month)</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($monthlyLoans, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4">
                    <i class="fas fa-file-invoice-dollar text-4xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Remaining Salary Expense Card -->
        <div class="bg-gradient-to-br from-lime-500 to-green-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-lime-100">Remaining Salary Expense</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($remainingSalary, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4">
                    <i class="fas fa-wallet text-4xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"><h2 class="text-lg font-semibold text-gray-800 mb-4">Today's Employee Status</h2><div class="h-80"><canvas id="attendancePieChart"></canvas></div></div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"><h2 class="text-lg font-semibold text-gray-800 mb-4">Employee Distribution</h2><div class="h-80"><canvas id="departmentPieChart"></canvas></div></div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"><h2 class="text-lg font-semibold text-gray-800 mb-4">Monthly Salary Expense by Department</h2><div style="height: 400px;"><canvas id="departmentSalaryPieChart"></canvas></div></div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"><h2 class="text-lg font-semibold text-gray-800 mb-4">Paid Salary Expense for <?php echo date('Y'); ?></h2><div style="height: 400px;"><canvas id="monthlyExpenseBarChart"></canvas></div></div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Top 10 Highest Earning Employees</h2>
        <div class="space-y-4">
            <?php foreach($topEarners as $index => $earner): ?>
            <div class="flex items-center bg-gray-50 rounded-lg p-3 transition-shadow duration-200 hover:shadow-md">
                <span class="text-lg font-bold text-gray-400 w-8 text-center"><?php echo $index + 1; ?></span>
                <div class="flex-shrink-0 h-10 w-10 bg-primary-100 rounded-full flex items-center justify-center ml-4"><i class="fas fa-user text-primary-600"></i></div>
                <div class="ml-4 flex-grow">
                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($earner->first_name . ' ' . $earner->last_name); ?></p>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($earner->position_name ?? 'N/A'); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-md font-bold text-green-600">৳<?php echo number_format($earner->base_salary); ?></p>
                    <p class="text-xs text-gray-400">Monthly</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const observerOptions = { root: null, rootMargin: '0px', threshold: 0.4 };
        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const canvas = entry.target;
                    const chartId = canvas.id;
                    if (chartId === 'attendancePieChart') { new Chart(canvas.getContext('2d'), { type: 'pie', data: { labels: [`Present: <?php echo $presentCount; ?>`, `Absent: <?php echo $absentCount; ?>`, `On Leave: <?php echo $onLeaveCount; ?>`], datasets: [{ data: [<?php echo $presentCount; ?>, <?php echo $absentCount; ?>, <?php echo $onLeaveCount; ?>], backgroundColor: ['#22C55E', '#EF4444', '#F59E0B'], hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' }, title: { display: true, text: 'Total Employees: <?php echo $totalEmployees; ?>' } } } }); }
                    else if (chartId === 'departmentPieChart') { new Chart(canvas.getContext('2d'), { type: 'pie', data: { labels: <?php echo json_encode($deptEmpLabels); ?>, datasets: [{ label: 'Employees', data: <?php echo json_encode($deptEmpData); ?>, backgroundColor: <?php echo json_encode($deptEmpData); ?>.map(() => `rgba(${Math.floor(Math.random() * 200)}, ${Math.floor(Math.random() * 200)}, ${Math.floor(Math.random() * 200)}, 0.7)`), hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' }, title: { display: true, text: 'Employee Count per Department' } } } }); }
                    else if (chartId === 'departmentSalaryPieChart') { new Chart(canvas.getContext('2d'), { type: 'pie', data: { labels: <?php echo json_encode($deptSalaryLabels); ?>, datasets: [{ label: 'Monthly Salary (৳)', data: <?php echo json_encode($deptSalaryData); ?>, backgroundColor: <?php echo json_encode($deptSalaryData); ?>.map(() => `rgba(${Math.floor(Math.random() * 200)}, ${Math.floor(Math.random() * 200)}, ${Math.floor(Math.random() * 200)}, 0.8)`), hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' }, title: { display: true, text: 'Total Est. Salary: ৳<?php echo number_format($estimatedMonthlySalary, 2); ?>' } } } }); }
                    else if (chartId === 'monthlyExpenseBarChart') { new Chart(canvas.getContext('2d'), { type: 'bar', data: { labels: <?php echo json_encode($monthLabels); ?>, datasets: [{ label: 'Total Paid Salary (৳)', data: <?php echo json_encode($monthData); ?>, backgroundColor: 'rgba(59, 130, 246, 0.6)', borderColor: 'rgba(59, 130, 246, 1)', borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } } }); }
                    observer.unobserve(canvas);
                }
            });
        }, observerOptions);
        document.querySelectorAll('canvas').forEach(canvas => observer.observe(canvas));
    });
</script>

<?php include_once '../templates/footer.php'; ?>