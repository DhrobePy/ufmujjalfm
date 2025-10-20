<?php
// new_ufmhrm/admin/loans.php (Final Loan Management Hub)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$currentUser = getCurrentUser();
$isAdmin = in_array($currentUser['role'], ['admin', 'superadmin']);

// --- LOGIC: Handle ALL form submissions before any HTML is sent ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Handle New Loan Application
    if (isset($_POST['apply_for_loan'])) {
        $loan_type = $_POST['loan_type'];
        $data = [
            'employee_id' => (int)$_POST['employee_id'],
            'loan_type'   => $loan_type,
            'amount'      => floatval($_POST['amount']),
            'installments'=> ($loan_type === 'fixed_emi' && !empty($_POST['installments'])) ? (int)$_POST['installments'] : NULL,
            'reason'      => $_POST['reason'],
            'advance_month' => ($loan_type === 'salary_advance') ? $_POST['advance_month'] : NULL,
            'advance_year'  => ($loan_type === 'salary_advance') ? $_POST['advance_year'] : NULL
        ];
        $db->insert('loan_applications', $data);
        $_SESSION['success_flash'] = 'Application submitted successfully.';
        header('Location: loans.php?tab=my_loans');
        exit();
    }

    // 2. Handle Admin Approval/Rejection Actions
    if (isset($_POST['update_loan_application']) && $isAdmin) {
        $application_id = (int)$_POST['application_id'];
        $action = $_POST['action'];
        $app = $db->query("SELECT * FROM loan_applications WHERE id = ?", [$application_id])->first();

        if ($app) {
            if ($action === 'approve') {
                $db->getPdo()->beginTransaction();
                try {
                    if ($app->loan_type === 'fixed_emi' || $app->loan_type === 'random_repayment') {
                        $monthly_payment = ($app->loan_type === 'fixed_emi' && $app->installments > 0) ? round($app->amount / $app->installments, 2) : 0;
                        $installment_type = ($app->loan_type === 'fixed_emi') ? 'fixed' : 'random';
                        
                        $db->insert('loans', [
                            'employee_id' => $app->employee_id, 'loan_date' => date('Y-m-d'),
                            'amount' => $app->amount, 'installments' => $app->installments ?? 0,
                            'monthly_payment' => $monthly_payment, 'status' => 'active',
                            'installment_type' => $installment_type
                        ]);
                    } else { // Salary Advance
                        $db->insert('salary_advances', ['employee_id' => $app->employee_id, 'advance_date' => date('Y-m-d'),'amount' => $app->amount, 'advance_month' => $app->advance_month,'advance_year' => $app->advance_year, 'reason' => $app->reason, 'status' => 'approved']);
                    }
                    $db->query("UPDATE loan_applications SET status = 'approved', approved_by = ?, approved_date = NOW() WHERE id = ?", [$currentUser['id'], $application_id]);
                    $db->getPdo()->commit();
                    $_SESSION['success_flash'] = 'Application has been approved.';
                } catch (Exception $e) {
                    $db->getPdo()->rollBack();
                    $_SESSION['error_flash'] = 'Error: ' . $e->getMessage();
                }
            } elseif ($action === 'reject') {
                $db->query("UPDATE loan_applications SET status = 'rejected', approved_by = ?, approved_date = NOW() WHERE id = ?", [$currentUser['id'], $application_id]);
                $_SESSION['error_flash'] = 'Application has been rejected.';
            }
        }
        header('Location: loans.php?tab=pending');
        exit();
    }
}

$pageTitle = 'Loan Management - ' . APP_NAME;
include_once '../templates/header.php';

// --- DATA FETCHING for different tabs ---
$pendingApplications = $isAdmin ? $db->query("SELECT la.*, e.first_name, e.last_name FROM loan_applications la JOIN employees e ON la.employee_id = e.id WHERE la.status = 'pending' ORDER BY la.applied_date DESC")->results() : [];
$myLoans = (isset($currentUser['employee_id'])) ? $db->query("SELECT l.*, (l.amount - IFNULL((SELECT SUM(amount) FROM loan_installments WHERE loan_id = l.id), 0)) as outstanding_balance FROM loans l WHERE l.employee_id = ? ORDER BY l.loan_date DESC", [$currentUser['employee_id']])->results() : [];
$myAdvances = (isset($currentUser['employee_id'])) ? $db->query("SELECT * FROM salary_advances WHERE employee_id = ? AND status = 'approved' ORDER BY advance_date DESC", [$currentUser['employee_id']])->results() : [];
$allLoans = $isAdmin ? $db->query("SELECT l.*, e.first_name, e.last_name, (l.amount - IFNULL((SELECT SUM(amount) FROM loan_installments WHERE loan_id = l.id), 0)) as outstanding_balance FROM loans l JOIN employees e ON l.employee_id = e.id ORDER BY l.loan_date DESC")->results() : [];
$allEmployees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->results();
?>

<div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-xl border p-6">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3"><div class="h-12 w-12 bg-primary-100 rounded-xl flex items-center justify-center"><i class="fas fa-landmark text-primary-600 text-xl"></i></div>Loan Management</h1>
    </div>

    <div x-data="{ activeTab: '<?php echo $isAdmin ? 'pending' : 'apply'; ?>' }" x-init="()=>{ const params = new URLSearchParams(window.location.search); if (params.get('tab')) { activeTab = params.get('tab'); } }">
        <div class="border-b border-gray-200"><nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <?php if ($isAdmin): ?><a href="#pending" @click.prevent="activeTab = 'pending'" :class="{'border-primary-500 text-primary-600': activeTab === 'pending'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-clock"></i> Pending Approval <span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full text-xs"><?php echo count($pendingApplications); ?></span></a><?php endif; ?>
            <a href="#apply" @click.prevent="activeTab = 'apply'" :class="{'border-primary-500 text-primary-600': activeTab === 'apply'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-paper-plane"></i> Apply</a>
            <a href="#my_loans" @click.prevent="activeTab = 'my_loans'" :class="{'border-primary-500 text-primary-600': activeTab === 'my_loans'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-history"></i> My History</a>
            <?php if ($isAdmin): ?><a href="#all_loans" @click.prevent="activeTab = 'all_loans'" :class="{'border-primary-500 text-primary-600': activeTab === 'all_loans'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-list"></i> All Loans</a><?php endif; ?>
            <?php if ($currentUser['role'] === 'superadmin'): ?><a href="#reports" @click.prevent="activeTab = 'reports'" :class="{'border-primary-500 text-primary-600': activeTab === 'reports'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-chart-pie"></i> Reports</a><?php endif; ?>
        </nav></div>

        <div class="mt-6">
            <div x-show="activeTab === 'pending'" x-cloak><?php if ($isAdmin): ?>
                <div class="bg-white rounded-2xl shadow-xl border overflow-hidden">
                    <div class="p-6 border-b"><h2 class="text-xl font-bold">Pending Loan Applications</h2></div>
                    <?php if (!empty($pendingApplications)): ?>
                    <div class="overflow-x-auto"><table class="min-w-full divide-y">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Employee</th><th class="px-6 py-3 text-left">Type</th><th class="px-6 py-3 text-right">Amount</th><th class="px-6 py-3 text-center">Installments</th><th class="px-6 py-3 text-left">Reason</th><th class="px-6 py-3 text-center">Actions</th></tr></thead>
                        <tbody class="divide-y"><?php foreach ($pendingApplications as $app): ?><tr>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($app->first_name . ' ' . $app->last_name); ?></td><td class="px-6 py-4"><?php if($app->loan_type == 'fixed_emi') echo 'Fixed EMI'; elseif($app->loan_type == 'random_repayment') echo 'Random Repayment'; else echo 'Salary Advance'; ?></td><td class="px-6 py-4 text-right">৳<?php echo number_format($app->amount, 2); ?></td><td class="px-6 py-4 text-center"><?php echo $app->installments ?? 'N/A'; ?></td><td class="px-6 py-4 max-w-xs truncate"><?php echo htmlspecialchars($app->reason); ?></td><td class="px-6 py-4 text-center"><form method="POST" class="inline-flex gap-2"><input type="hidden" name="application_id" value="<?php echo $app->id; ?>"><button type="submit" name="action" value="approve" class="px-3 py-1 text-sm bg-green-500 text-white rounded-md">Approve</button><button type="submit" name="action" value="reject" class="px-3 py-1 text-sm bg-red-500 text-white rounded-md">Reject</button><input type="hidden" name="update_loan_application" value="1"></form></td>
                        </tr><?php endforeach; ?></tbody>
                    </table></div>
                    <?php else: ?><div class="text-center py-12"><i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i><h3 class="text-lg font-medium">No pending applications.</h3></div><?php endif; ?>
                </div>
            <?php endif; ?></div>

            <div x-show="activeTab === 'apply'" x-cloak><div class="bg-white rounded-2xl shadow-xl border p-8 max-w-2xl mx-auto">
                <h2 class="text-2xl font-bold mb-6">New Loan/Advance Application</h2>
                <form method="POST" class="space-y-6" x-data="{ loanType: 'fixed_emi' }">
                    <input type="hidden" name="apply_for_loan" value="1">
                    <?php if ($isAdmin): ?><div><label class="block text-sm font-medium">Employee</label><select name="employee_id" required class="mt-1 w-full rounded-md border-gray-300"><option value="">Select Employee</option><?php foreach($allEmployees as $emp): ?><option value="<?php echo $emp->id; ?>"><?php echo $emp->first_name . ' ' . $emp->last_name; ?></option><?php endforeach; ?></select></div>
                    <?php else: ?><input type="hidden" name="employee_id" value="<?php echo $currentUser['employee_id']; ?>"><?php endif; ?>
                    <div><label class="block text-sm font-medium">Type</label><select name="loan_type" x-model="loanType" class="mt-1 w-full rounded-md border-gray-300"><option value="fixed_emi">Fixed EMI Loan</option><option value="random_repayment">Random Repayment Loan</option><option value="salary_advance">Salary Advance</option></select></div>
                    <div><label class="block text-sm font-medium">Amount Requested</label><input type="number" name="amount" required class="mt-1 w-full rounded-md border-gray-300" placeholder="e.g., 50000"></div>
                    <div x-show="loanType === 'fixed_emi'" x-transition><label class="block text-sm font-medium">Number of Installments (Months)</label><input type="number" name="installments" x-bind:required="loanType === 'fixed_emi'" class="mt-1 w-full rounded-md border-gray-300" placeholder="e.g., 12"></div>
                    <div x-show="loanType === 'salary_advance'" x-transition class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium">Deduction Month</label><select name="advance_month" x-bind:required="loanType === 'salary_advance'" class="mt-1 w-full rounded-md border-gray-300"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php if(date('n') == $m) echo 'selected'; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option><?php endfor; ?></select></div>
                        <div><label class="block text-sm font-medium">Deduction Year</label><select name="advance_year" x-bind:required="loanType === 'salary_advance'" class="mt-1 w-full rounded-md border-gray-300"><?php for ($y = date('Y'); $y <= date('Y') + 1; $y++): ?><option value="<?php echo $y; ?>"><?php echo $y; ?></option><?php endfor; ?></select></div>
                    </div>
                    <div><label class="block text-sm font-medium">Reason</label><textarea name="reason" rows="4" required class="mt-1 w-full rounded-md border-gray-300"></textarea></div>
                    <div class="flex justify-end"><button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg font-bold">Submit Application</button></div>
                </form>
            </div></div>

            <div x-show="activeTab === 'my_loans'" x-cloak><div class="space-y-6">
                <div class="bg-white rounded-2xl shadow-xl border"><div class="p-6 border-b"><h2 class="text-xl font-bold">My Active Loans</h2></div><div class="overflow-x-auto"><table class="min-w-full divide-y">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Date</th><th class="px-6 py-3 text-right">Total Amount</th><th class="px-6 py-3 text-right">Outstanding</th><th class="px-6 py-3 text-center">Type</th><th class="px-6 py-3 text-right">EMI</th></tr></thead>
                    <tbody class="divide-y"><?php foreach ($myLoans as $loan): ?><tr><td class="px-6 py-4"><?php echo date('d M, Y', strtotime($loan->loan_date)); ?></td><td class="px-6 py-4 text-right">৳<?php echo number_format($loan->amount, 2); ?></td><td class="px-6 py-4 text-right text-red-600 font-semibold">৳<?php echo number_format($loan->outstanding_balance, 2); ?></td><td class="px-6 py-4 text-center"><?php echo ucfirst($loan->installment_type); ?></td><td class="px-6 py-4 text-right">৳<?php echo number_format($loan->monthly_payment, 2); ?></td></tr><?php endforeach; ?></tbody>
                </table></div></div>
                <div class="bg-white rounded-2xl shadow-xl border"><div class="p-6 border-b"><h2 class="text-xl font-bold">My Approved Salary Advances</h2></div><div class="overflow-x-auto"><table class="min-w-full divide-y">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Date</th><th class="px-6 py-3 text-right">Amount</th><th class="px-6 py-3 text-left">Reason</th></tr></thead>
                    <tbody class="divide-y"><?php foreach ($myAdvances as $adv): ?><tr><td class="px-6 py-4"><?php echo date('d M, Y', strtotime($adv->advance_date)); ?></td><td class="px-6 py-4 text-right">৳<?php echo number_format($adv->amount, 2); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($adv->reason); ?></td></tr><?php endforeach; ?></tbody>
                </table></div></div>
            </div></div>

            <div x-show="activeTab === 'all_loans'" x-cloak><?php if ($isAdmin): ?>
                <div class="bg-white rounded-2xl shadow-xl border"><div class="p-6 border-b"><h2 class="text-xl font-bold">All Employee Loans</h2></div><div class="overflow-x-auto"><table class="min-w-full divide-y">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Employee</th><th class="px-6 py-3 text-left">Date</th><th class="px-6 py-3 text-right">Total</th><th class="px-6 py-3 text-right">Outstanding</th><th class="px-6 py-3 text-center">Status</th></tr></thead>
                    <tbody class="divide-y"><?php foreach ($allLoans as $loan): ?><tr><td class="px-6 py-4"><?php echo htmlspecialchars($loan->first_name . ' ' . $loan->last_name); ?></td><td class="px-6 py-4"><?php echo date('d M, Y', strtotime($loan->loan_date)); ?></td><td class="px-6 py-4 text-right">৳<?php echo number_format($loan->amount, 2); ?></td><td class="px-6 py-4 text-right text-red-600 font-semibold">৳<?php echo number_format($loan->outstanding_balance, 2); ?></td><td class="px-6 py-4 text-center"><?php echo ucfirst($loan->status); ?></td></tr><?php endforeach; ?></tbody>
                </table></div></div>
            <?php endif; ?></div>
            
            <div x-show="activeTab === 'reports'" x-cloak><?php if ($currentUser['role'] === 'superadmin'): ?>
                <div class="bg-white rounded-2xl shadow-xl border p-12 text-center"><i class="fas fa-chart-line text-primary-300 text-6xl mb-6"></i><h2 class="text-2xl font-bold">Loan Reports</h2><p class="text-gray-500 mt-2">This feature is coming soon.</p></div>
            <?php endif; ?></div>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>
