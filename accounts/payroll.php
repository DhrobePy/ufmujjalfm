<?php
// new_ufmhrm/accounts/payroll.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

// --- SECURITY & PERMISSIONS ---
if (!is_admin_logged_in()) {
    redirect('../auth/login.php');
}

$currentUser = getCurrentUser();
$user_branch_id = $currentUser['branch_id'];
$is_admin = in_array($currentUser['role'], ['superadmin', 'Admin', 'Accounts-HO', 'Admin-HO']);
$is_branch_accountant = in_array($currentUser['role'], ['Accounts- Srg', 'Accounts- Rampura']);

// --- DYNAMIC FILTERS FOR BRANCH-SCOPING ---
$branch_filter_sql = "";
$branch_params = [];
if ($is_branch_accountant && !$is_admin) {
    $branch_filter_sql = " AND e.branch_id = ? ";
    $branch_params[] = $user_branch_id;
}

// --- LOGIC: Handle form submissions before any HTML is sent ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_paid'])) {
    $selectedIds = $_POST['selected_payrolls'] ?? [];
    if (!empty($selectedIds)) {
        // Security: Ensure the user can only mark payrolls from their own branch
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $update_params = $selectedIds;
        $security_sql = "";
        if ($is_branch_accountant && !$is_admin) {
            $security_sql = " AND branch_id = ? ";
            $update_params[] = $user_branch_id;
        }
        
        $db->query("UPDATE payrolls SET status = 'paid' WHERE id IN ($placeholders) $security_sql", $update_params);
        set_message(count($selectedIds) . ' payroll(s) have been marked as paid.', 'success');
        
        redirect('payroll.php?tab=disbursement');
    }
}

// --- Start Page Setup (after potential redirects) ---
$pageTitle = 'Payroll Management - ' . APP_NAME;

// --- Fetch 'approved' payrolls for the Disbursement tab WITHIN THE BRANCH ---
$disbursementSql = "
    SELECT p.id, e.first_name, e.last_name, pos.name as position_name, p.net_salary, p.pay_period_end
    FROM payrolls p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    WHERE p.status = 'approved' $branch_filter_sql
    ORDER BY p.pay_period_end DESC, e.first_name
";
$disbursementItems = $db->query($disbursementSql, $branch_params)->results();

// --- CONDITIONAL HEADER ---
if ($is_branch_accountant && !$is_admin) {
    include_once '../templates/accounts_header.php';
} else {
    include_once '../templates/header.php';
}
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-money-check-alt text-indigo-600 mr-3"></i>Payroll Management Hub</h1>
        <p class="mt-1 text-sm text-gray-600">Review, disburse, and report on employee salaries for your branch.</p>
    </div>

    <div x-data="{ activeTab: 'disbursement' }" x-init="()=>{
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab) { activeTab = tab; }
        window.history.replaceState({}, document.title, window.location.pathname); // Clean URL
    }">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <a href="#disbursement" @click.prevent="activeTab = 'disbursement'" :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'disbursement' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-hand-holding-usd"></i> Disbursement <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo count($disbursementItems); ?></span></a>
                <a href="payroll_history.php" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 flex items-center gap-2"><i class="fas fa-history"></i> Payroll History</a>
                <a href="payroll_reports.php" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 flex items-center gap-2"><i class="fas fa-chart-pie"></i> Reports</a>
            </nav>
        </div>

        <div class="mt-6">
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
                                            <th class="px-6 py-3 text-left"><input type="checkbox" onclick="var a=this.checked; document.querySelectorAll('input[name*=\'selected_payrolls\']').forEach(function(c){c.checked=a;});"></th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pay Period</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net Salary</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach($disbursementItems as $item): ?>
                                            <tr>
                                                <td class="px-6 py-4"><input type="checkbox" name="selected_payrolls[]" value="<?php echo $item->id; ?>" class="h-4 w-4 text-indigo-600 border-gray-300 rounded"></td>
                                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item->position_name ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('F Y', strtotime($item->pay_period_end)); ?></td>
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
                            <p class="text-sm text-gray-500 mt-1">There are no approved payrolls awaiting disbursement for your branch.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>

