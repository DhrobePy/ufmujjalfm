<?php
// new_ufmhrm/accounts/index.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

// --- SECURITY CHECK ---
if (!is_admin_logged_in()) {
    redirect('../auth/login.php');
    exit();
}

// --- BRANCH & ROLE LOGIC ---
$currentUser = getCurrentUser();
$branch_id = $currentUser['branch_id'];

// Define which roles get the special branch-limited view and header
$branch_account_roles = ['Accounts- Srg', 'Accounts- Rampura'];
$is_branch_accountant = in_array($currentUser['role'], $branch_account_roles);

// --- DYNAMIC SQL FILTERING ---
// This is the core of the branch-scoping logic.
// We create SQL snippets that will be added to our queries.
$branch_sql_filter_e = ""; // For queries using alias 'e' for employees table
$branch_sql_filter_direct = ""; // For queries directly on 'employees'
$branch_params = [];

// If the user is a branch accountant AND has a branch_id, create the filter.
// A superadmin or HO user will not trigger this, so they will see all data.
if ($is_branch_accountant && !empty($branch_id)) {
    $branch_sql_filter_e = " AND e.branch_id = ? ";
    $branch_sql_filter_direct = " AND branch_id = ? ";
    $branch_params = [$branch_id];
}

$pageTitle = 'Accounts Dashboard - ' . APP_NAME;

// --- CONDITIONAL HEADER LOADING ---
// Load the correct header based on the user's role.
if ($is_branch_accountant) {
    include_once '../templates/accounts_header.php';
} else {
    // Superadmins and HO Accounts see the default full header
    include_once '../templates/header.php';
}

// --- ACCOUNTS-SPECIFIC DATA FETCHING (NOW BRANCH-FILTERED) ---
$today = date('Y-m-d');
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');
$daysInMonth = date('t');

// Estimated Total Salary (Filtered)
$monthlyExpenseSql = "SELECT SUM(base_salary) as total_salary FROM employees WHERE status = 'active' $branch_sql_filter_direct";
$monthlyExpenseResult = $db->query($monthlyExpenseSql, $branch_params);
$estimatedMonthlySalary = $monthlyExpenseResult->first()->total_salary ?? 0;

// Cumulative salary expense (Filtered)
$cumulativeExpenseSql = "SELECT SUM(e.base_salary / ?) as cumulative_expense 
                         FROM employees e 
                         JOIN attendance a ON e.id = a.employee_id 
                         WHERE a.status = 'present' AND DATE(a.clock_in) BETWEEN ? AND ? $branch_sql_filter_e";
$cumulativeParams = array_merge([$daysInMonth, $startOfMonth, $today], $branch_params);
$cumulativeExpenseResult = $db->query($cumulativeExpenseSql, $cumulativeParams);
$cumulativeSalaryExpense = $cumulativeExpenseResult->first()->cumulative_expense ?? 0;

// Salary Advances (Filtered)
$advancesSql = "SELECT SUM(sa.amount) as total_advances 
                FROM salary_advances sa
                JOIN employees e ON sa.employee_id = e.id
                WHERE sa.advance_date BETWEEN ? AND ? $branch_sql_filter_e";
$advancesParams = array_merge([$startOfMonth, $endOfMonth], $branch_params);
$advancesResult = $db->query($advancesSql, $advancesParams);
$monthlyAdvances = $advancesResult->first()->total_advances ?? 0;

// Loans given (Filtered)
$loansSql = "SELECT SUM(l.amount) as total_loans 
             FROM loans l
             JOIN employees e ON l.employee_id = e.id
             WHERE l.loan_date BETWEEN ? AND ? $branch_sql_filter_e";
$loansParams = array_merge([$startOfMonth, $endOfMonth], $branch_params);
$loansResult = $db->query($loansSql, $loansParams);
$monthlyLoans = $loansResult->first()->total_loans ?? 0;

// Remaining salary
$remainingSalary = $estimatedMonthlySalary - $cumulativeSalaryExpense;

// Recent Financial Activity (Filtered)
$recentActivitySql = "
    (SELECT 
        e.first_name, e.last_name, 'Salary Advance' as type, sa.amount, sa.advance_date as date
    FROM salary_advances sa
    JOIN employees e ON sa.employee_id = e.id
    WHERE sa.status = 'approved' $branch_sql_filter_e
    ORDER BY sa.advance_date DESC
    LIMIT 5)
    UNION ALL
    (SELECT 
        e.first_name, e.last_name, 'Loan' as type, l.amount, l.loan_date as date
    FROM loans l
    JOIN employees e ON l.employee_id = e.id
    WHERE l.status = 'active' $branch_sql_filter_e
    ORDER BY l.loan_date DESC
    LIMIT 5)
    ORDER BY date DESC
    LIMIT 10
";
// We need to pass the branch params for each part of the UNION query
$recentActivityParams = array_merge($branch_params, $branch_params);
$recentActivityResult = $db->query($recentActivitySql, $recentActivityParams);
$recentActivities = $recentActivityResult ? $recentActivityResult->results() : [];

// Chart Data (Already filtered by the queries above)
$chartData = [
    'paid' => round($cumulativeSalaryExpense, 2),
    'advances' => round($monthlyAdvances, 2),
    'remaining' => round($remainingSalary > 0 ? $remainingSalary : 0, 2)
];

?>

<div class="space-y-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-file-invoice-dollar text-primary-600 mr-3"></i>Accounts Dashboard</h1>
        <p class="mt-1 text-sm text-gray-600">
            Financial overview for <?php echo date("F Y"); ?>. 
            <?php if ($is_branch_accountant): ?>
                <span class="font-semibold text-primary-700">(Branch: Sirajgonj Mills)</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-purple-100">Est. Monthly Salary</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($estimatedMonthlySalary, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4"><i class="fas fa-money-bill-wave text-4xl"></i></div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-cyan-500 to-sky-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-cyan-100">Paid Salary (MTD)</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($cumulativeSalaryExpense, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4"><i class="fas fa-chart-line text-4xl"></i></div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-amber-100">Advances (This Month)</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($monthlyAdvances, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4"><i class="fas fa-hand-holding-usd text-4xl"></i></div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-rose-500 to-pink-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-rose-100">Loans (This Month)</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($monthlyLoans, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4"><i class="fas fa-landmark text-4xl"></i></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
        <div class="lg:col-span-3 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Monthly Salary Expense Breakdown</h2>
            <div class="h-96"><canvas id="expenseDonutChart"></canvas></div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Financial Activity</h2>
            <div class="space-y-4">
                <?php if($recentActivities): ?>
                    <?php foreach($recentActivities as $activity): ?>
                    <div class="flex items-center bg-gray-50 rounded-lg p-3">
                        <div class="flex-shrink-0 h-10 w-10 <?php echo $activity->type === 'Loan' ? 'bg-rose-100' : 'bg-amber-100'; ?> rounded-lg flex items-center justify-center">
                            <i class="fas <?php echo $activity->type === 'Loan' ? 'fa-landmark text-rose-600' : 'fa-hand-holding-usd text-amber-600'; ?>"></i>
                        </div>
                        <div class="ml-4 flex-grow">
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($activity->first_name . ' ' . $activity->last_name); ?></p>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($activity->type); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-md font-bold <?php echo $activity->type === 'Loan' ? 'text-rose-600' : 'text-amber-600'; ?>">৳<?php echo number_format($activity->amount, 2); ?></p>
                            <p class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($activity->date)); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-gray-500 py-8">No recent loans or advances found for this branch.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const expenseCtx = document.getElementById('expenseDonutChart').getContext('2d');
    if (expenseCtx) {
        new Chart(expenseCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    `Paid Salary (৳<?php echo number_format($chartData['paid']); ?>)`,
                    `Advances (৳<?php echo number_format($chartData['advances']); ?>)`,
                    `Remaining (৳<?php echo number_format($chartData['remaining']); ?>)`
                ],
                datasets: [{
                    label: 'Monthly Expense',
                    data: [
                        <?php echo $chartData['paid']; ?>,
                        <?php echo $chartData['advances']; ?>,
                        <?php echo $chartData['remaining']; ?>
                    ],
                    backgroundColor: [
                        '#38bdf8', // sky-400
                        '#f59e0b', // amber-500
                        '#4ade80'  // green-400
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: { size: 14 }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Total Estimated Salary: ৳<?php echo number_format($estimatedMonthlySalary, 2); ?>',
                        font: { size: 16 },
                        padding: { bottom: 20 }
                    }
                }
            }
        });
    }
});
</script>

<?php include_once '../templates/footer.php'; ?>