<?php
// new_ufmhrm/admin/payroll.php (Final Refactored Payroll Hub)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// --- LOGIC MOVED TO TOP: Handle form submissions before any HTML output ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_paid'])) {
    $selectedIds = $_POST['selected_payrolls'] ?? [];
    if (!empty($selectedIds)) {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $db->query("UPDATE payrolls SET status = 'paid' WHERE id IN ($placeholders)", $selectedIds);
        $_SESSION['success_flash'] = count($selectedIds) . ' payroll(s) have been marked as paid.';
        
        // This redirect will now work correctly
        header('Location: payroll.php?tab=disbursement'); 
        exit();
    }
}

// --- Start Page Setup (after potential redirects) ---
$pageTitle = 'Payroll Management - ' . APP_NAME;
include_once '../templates/header.php';

// --- CORRECTED LOGIC: Check the status of the most recent payroll batch ---
$hasPendingApprovals = false;
$pendingPeriodEnd = null;

// 1. Find the most recent pay period that has entries not yet fully paid
$latestPeriodQuery = $db->query("SELECT pay_period_end FROM payrolls WHERE status != 'paid' ORDER BY pay_period_end DESC LIMIT 1");

if ($latestPeriodQuery->count()) {
    $latestPayPeriodEnd = $latestPeriodQuery->first()->pay_period_end;

    // 2. Check if ANY payrolls for that period are still 'pending_approval'
    $pendingCountQuery = $db->query(
        "SELECT COUNT(id) as count FROM payrolls WHERE pay_period_end = ? AND status = 'pending_approval'", 
        [$latestPayPeriodEnd]
    );
    
    if ($pendingCountQuery->first()->count > 0) {
        $hasPendingApprovals = true;
        $pendingPeriodEnd = $latestPayPeriodEnd;
    }
}

// --- Fetch 'approved' payrolls for the Disbursement tab ---
$disbursementSql = "
    SELECT p.id, e.first_name, e.last_name, pos.name as position_name, p.net_salary, p.pay_period_end
    FROM payrolls p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    WHERE p.status = 'approved'
    ORDER BY p.pay_period_end DESC, e.first_name
";
$disbursementItems = $db->query($disbursementSql)->results();

?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-money-check-alt text-indigo-600 mr-3"></i>Payroll Management Hub</h1>
        <p class="mt-1 text-sm text-gray-600">Generate, review, disburse, and report on employee salaries.</p>
    </div>

    <div x-data="{ activeTab: window.location.hash ? window.location.hash.substring(1) : 'run' }" x-init="()=>{
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab) { activeTab = tab; }
        window.history.replaceState({}, document.title, window.location.pathname); // Clean URL
    }">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <a href="#run" @click.prevent="activeTab = 'run'" :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'run', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'run' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-cogs"></i> Run Payroll</a>
                <a href="#disbursement" @click.prevent="activeTab = 'disbursement'" :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'disbursement', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'disbursement' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-hand-holding-usd"></i> Disbursement <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo count($disbursementItems); ?></span></a>
                <a href="payroll_history.php" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 flex items-center gap-2"><i class="fas fa-history"></i> Payroll History</a>
                <a href="payroll_reports.php" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 flex items-center gap-2"><i class="fas fa-chart-pie"></i> Reports</a>
            </nav>
        </div>

        <div class="mt-6">
            <div x-show="activeTab === 'run'" x-cloak>
                <?php if ($hasPendingApprovals): ?>
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-6 rounded-lg text-center">
                        <h2 class="text-xl font-bold text-amber-900">Action Required: Pending Approvals</h2>
                        <p class="text-amber-700 mt-2">You must review and approve all pending payroll entries for <strong><?php echo date('F Y', strtotime($pendingPeriodEnd)); ?></strong> before you can generate a new payroll.</p>
                        <a href="approve_payroll.php" class="mt-6 inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-amber-600 hover:bg-amber-700"><i class="fas fa-tasks mr-2"></i> Review Pending Payroll</a>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                        <i class="fas fa-play-circle text-indigo-500 text-5xl mb-4"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Generate New Payroll</h2>
                        <p class="text-gray-600 mt-2 max-w-xl mx-auto">Select a month and year to calculate salaries for all active employees.</p>
                        <form action="prepare_payroll.php" method="POST" class="mt-8 max-w-lg mx-auto flex items-center gap-4">
                            <select name="month" class="w-full px-4 py-3 rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all outline-none">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo (date('n') == $m) ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="year" class="w-full px-4 py-3 rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all outline-none">
                                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="w-auto px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transform hover:scale-105 transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-cogs"></i> Generate
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div x-show="activeTab === 'disbursement'" x-cloak>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6 border-b">
                        <h2 class="text-xl font-bold text-gray-900">Payroll Disbursement</h2>
                        <p class="text-sm text-gray-600 mt-1">These payrolls are approved and ready to be paid. Select entries and mark them as paid once the disbursement is complete.</p>
                    </div>
                    <?php if (!empty($disbursementItems)): ?>
                        <form method="POST">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net Salary</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach($disbursementItems as $item): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <input type="checkbox" name="selected_payrolls[]" value="<?php echo $item->id; ?>" class="h-4 w-4 text-indigo-600 border-gray-300 rounded mr-4">
                                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item->position_name ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-green-600">৳<?php echo number_format($item->net_salary, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-6 bg-gray-50 border-t flex justify-end">
                                <button type="submit" name="mark_as_paid" onclick="return confirm('Are you sure you want to mark the selected entries as paid? This action cannot be undone.')" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg font-semibold shadow-md hover:shadow-lg transition-all"><i class="fas fa-check-double mr-2"></i>Mark Selected as Paid</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900">All Clear!</h3>
                            <p class="text-sm text-gray-500 mt-1">There are no approved payrolls awaiting disbursement.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>