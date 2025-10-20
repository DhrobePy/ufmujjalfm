<?php
// new_ufmhrm/admin/payroll.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Payroll Management - ' . APP_NAME;
include_once '../templates/header.php';

// --- Check for any payroll currently in a non-paid state ---
$pendingPayrollResult = $db->query("SELECT * FROM payrolls WHERE status IN ('pending_approval', 'approved', 'disbursed') ORDER BY pay_period_end DESC LIMIT 1");
$pendingPayroll = $pendingPayrollResult->count() ? $pendingPayrollResult->first() : null;


// --- Fetch Payroll History (status = 'paid') ---
$historySql = "
    SELECT 
        DATE_FORMAT(pay_period_end, '%Y-%m') as payroll_month,
        COUNT(id) as employee_count,
        SUM(net_salary) as total_disbursed,
        status
    FROM payrolls
    WHERE status = 'paid'
    GROUP BY payroll_month, status
    ORDER BY payroll_month DESC
";
$historyResult = $db->query($historySql);
$payrollHistory = $historyResult ? $historyResult->results() : [];

?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-money-check-alt text-primary-600 mr-3"></i>Payroll Management</h1>
        <p class="mt-1 text-sm text-gray-600">Generate, review, and disburse monthly employee salaries.</p>
    </div>

    <div x-data="{ activeTab: 'run' }">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <a href="#" @click.prevent="activeTab = 'run'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'run', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'run' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Run Payroll</a>
                <a href="#" @click.prevent="activeTab = 'history'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'history', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'history' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Payroll History</a>
            </nav>
        </div>

        <div class="mt-6">
            <div x-show="activeTab === 'run'" x-cloak>
                <?php if ($pendingPayroll): ?>
                    <?php if ($pendingPayroll->status === 'pending_approval'): ?>
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-lg text-center">
                            <h2 class="text-xl font-bold text-blue-900">Payroll in Progress</h2>
                            <p class="text-blue-700 mt-2">Payroll for <strong><?php echo date('F Y', strtotime($pendingPayroll->pay_period_end)); ?></strong> is awaiting approval.</p>
                            <a href="approve_payroll.php" class="mt-6 inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700"><i class="fas fa-tasks mr-2"></i> Review & Approve Now</a>
                        </div>
                    <?php elseif ($pendingPayroll->status === 'approved' || $pendingPayroll->status === 'disbursed'): ?>
                         <div class="bg-green-50 border-l-4 border-green-500 p-6 rounded-lg text-center">
                            <h2 class="text-xl font-bold text-green-900">Payroll Ready for Disbursement</h2>
                            <p class="text-green-700 mt-2">Payroll for <strong><?php echo date('F Y', strtotime($pendingPayroll->pay_period_end)); ?></strong> has been approved.</p>
                            <a href="disburse_payroll.php" class="mt-6 inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-green-600 hover:bg-green-700"><i class="fas fa-hand-holding-usd mr-2"></i> Manage Disbursement</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                        <i class="fas fa-play-circle text-primary-500 text-5xl mb-4"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Generate New Payroll</h2>
                        <p class="text-gray-600 mt-2 max-w-xl mx-auto">Select a month and year to calculate salaries for all active employees.</p>
                        <form action="prepare_payroll.php" method="POST" class="mt-8 max-w-lg mx-auto flex items-center gap-4">
                            <select name="month" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo (date('n') == $m) ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="year" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none">
                                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-primary-600 to-indigo-600 text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transform hover:scale-105 transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-cogs"></i> Generate
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div x-show="activeTab === 'history'" x-cloak>
                </div>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>