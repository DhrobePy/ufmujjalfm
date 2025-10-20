<?php
// new_ufmhrm/accounts/loans.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

// --- SECURITY & PERMISSIONS (MOVED TO TOP) ---
if (!is_admin_logged_in()) {
    redirect('../auth/login.php');
}

$currentUser = getCurrentUser();
$user_branch_id = $currentUser['branch_id'];

// Define which roles are branch-specific accountants
$branch_account_roles = ['Accounts- Srg', 'Accounts- Rampura'];
$is_branch_accountant = in_array($currentUser['role'], $branch_account_roles);

// Define who has general admin/management rights
$is_admin = in_array($currentUser['role'], ['superadmin', 'Admin', 'Accounts-HO', 'Admin-HO']);

// --- LOGIC: Handle ALL form submissions before any HTML is sent ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Handle New Loan Application
    if (isset($_POST['apply_for_loan'])) {
        $employee_id = (int)$_POST['employee_id'];
        $empCheck = $db->query("SELECT branch_id FROM employees WHERE id = ?", [$employee_id])->first();

        // Security: Can only apply for loans for employees in the same branch, unless admin
        if ($empCheck && ($empCheck->branch_id == $user_branch_id || $is_admin)) {
            $loan_type = $_POST['loan_type'];
            $data = [
                'employee_id' => $employee_id, 'loan_type' => $loan_type, 'amount' => floatval($_POST['amount']),
                'installments' => ($loan_type === 'fixed_emi' && !empty($_POST['installments'])) ? (int)$_POST['installments'] : NULL,
                'reason' => $_POST['reason'], 'advance_month' => ($loan_type === 'salary_advance') ? $_POST['advance_month'] : NULL,
                'advance_year' => ($loan_type === 'salary_advance') ? $_POST['advance_year'] : NULL
            ];
            $db->insert('loan_applications', $data);
            set_message('Application submitted successfully.', 'success');
        } else {
            set_message('Error: Invalid employee selection or permission denied.', 'error');
        }
        redirect('loans.php?tab=apply');
    }

    // 2. Handle Approval/Rejection Actions
    if (isset($_POST['update_loan_application']) && $is_admin) {
        $application_id = (int)$_POST['application_id'];
        $action = $_POST['action'];
        $app = $db->query("SELECT la.*, e.branch_id FROM loan_applications la JOIN employees e ON la.employee_id = e.id WHERE la.id = ?", [$application_id])->first();
        
        if ($app && (!$is_branch_accountant || $app->branch_id == $user_branch_id)) {
             if ($action === 'approve') {
                $db->getPdo()->beginTransaction();
                try {
                    if ($app->loan_type === 'fixed_emi' || $app->loan_type === 'random_repayment') {
                        $monthly_payment = ($app->loan_type === 'fixed_emi' && $app->installments > 0) ? round($app->amount / $app->installments, 2) : 0;
                        $db->insert('loans', ['employee_id' => $app->employee_id, 'loan_date' => date('Y-m-d'), 'amount' => $app->amount, 'installments' => $app->installments ?? 0, 'monthly_payment' => $monthly_payment, 'status' => 'active', 'installment_type' => ($app->loan_type === 'fixed_emi' ? 'fixed' : 'random'), 'branch_id' => $app->branch_id]);
                    } else {
                        $db->insert('salary_advances', ['employee_id' => $app->employee_id, 'advance_date' => date('Y-m-d'), 'amount' => $app->amount, 'advance_month' => $app->advance_month, 'advance_year' => $app->advance_year, 'reason' => $app->reason, 'status' => 'approved', 'branch_id' => $app->branch_id]);
                    }
                    $db->query("UPDATE loan_applications SET status = 'approved', approved_by = ?, approved_date = NOW() WHERE id = ?", [$currentUser['id'], $application_id]);
                    $db->getPdo()->commit();
                    set_message('Application has been approved.', 'success');
                } catch (Exception $e) {
                    $db->getPdo()->rollBack(); set_message('Error: ' . $e->getMessage(), 'error');
                }
            } elseif ($action === 'reject') {
                $db->query("UPDATE loan_applications SET status = 'rejected', approved_by = ?, approved_date = NOW() WHERE id = ?", [$currentUser['id'], $application_id]);
                set_message('Application has been rejected.', 'error');
            }
        } else {
            set_message('Application not found or access denied.', 'error');
        }
        redirect('loans.php?tab=pending');
    }

    // 3. Handle Loan Payment (NOW FULLY IMPLEMENTED)
    if (isset($_POST['make_payment'])) {
        $loan_id = (int)$_POST['loan_id'];
        $loan = $db->query("SELECT * FROM loans WHERE id = ?", [$loan_id])->first();
        
        if ($loan && ($loan->branch_id == $user_branch_id || $is_admin)) {
            $payment_amount = floatval($_POST['payment_amount']);
            $payment_date = $_POST['payment_date'];
            $paid = $db->query("SELECT IFNULL(SUM(amount), 0) as total_paid FROM loan_installments WHERE loan_id = ?", [$loan_id])->first();
            $outstanding = $loan->amount - ($paid->total_paid ?? 0);

            if ($payment_amount <= 0) {
                set_message('Payment amount must be greater than zero.', 'error');
            } elseif ($payment_amount > $outstanding) {
                set_message('Payment amount cannot exceed outstanding balance of ৳' . number_format($outstanding, 2), 'error');
            } else {
                $db->getPdo()->beginTransaction();
                try {
                    $db->insert('loan_installments', ['loan_id' => $loan_id, 'amount' => $payment_amount, 'payment_date' => $payment_date, 'payroll_id' => NULL]);
                    $new_paid = $db->query("SELECT IFNULL(SUM(amount), 0) as total_paid FROM loan_installments WHERE loan_id = ?", [$loan_id])->first();
                    if (($loan->amount - ($new_paid->total_paid ?? 0)) <= 0.01) {
                        $db->query("UPDATE loans SET status = 'paid' WHERE id = ?", [$loan_id]);
                    }
                    $db->getPdo()->commit();
                    set_message('Payment of ৳' . number_format($payment_amount, 2) . ' recorded successfully.', 'success');
                } catch (Exception $e) {
                    $db->getPdo()->rollBack();
                    set_message('Error processing payment: ' . $e->getMessage(), 'error');
                }
            }
        } else {
            set_message('Loan not found or access denied.', 'error');
        }
        redirect('loans.php?tab=' . ($_POST['redirect_tab'] ?? 'all_loans'));
    }
}


$pageTitle = 'Loan Management - ' . APP_NAME;

// --- DYNAMIC FILTERS FOR DATA FETCHING ---
$branch_filter_sql = "";
$branch_params = [];
if ($is_branch_accountant && !$is_admin) {
    $branch_filter_sql = " AND e.branch_id = ? ";
    $branch_params[] = $user_branch_id;
}

// --- DATA FETCHING for different tabs ---
$pendingApplications = $db->query("SELECT la.*, e.first_name, e.last_name FROM loan_applications la JOIN employees e ON la.employee_id = e.id WHERE la.status = 'pending' $branch_filter_sql ORDER BY la.applied_date DESC", $branch_params)->results();
$allLoans = $db->query("SELECT l.*, e.first_name, e.last_name, (l.amount - IFNULL((SELECT SUM(amount) FROM loan_installments WHERE loan_id = l.id), 0)) as outstanding_balance FROM loans l JOIN employees e ON l.employee_id = e.id WHERE 1=1 $branch_filter_sql ORDER BY l.loan_date DESC", $branch_params)->results();
// CORRECTED QUERY: Added alias 'e' to match the filter
$allEmployees = $db->query("SELECT e.id, e.first_name, e.last_name FROM employees e WHERE e.status = 'active' $branch_filter_sql ORDER BY e.first_name", $branch_params)->results();

$adv_filter_month = $_GET['adv_month'] ?? date('m');
$adv_filter_year = $_GET['adv_year'] ?? date('Y');
$adv_params = [$adv_filter_month, $adv_filter_year];
$adv_branch_sql = "";
if ($is_branch_accountant && !$is_admin) {
    $adv_branch_sql = " AND sa.branch_id = ? ";
    $adv_params[] = $user_branch_id;
}
$myAdvancesList = $db->query("SELECT sa.*, e.first_name, e.last_name, d.name as department_name FROM salary_advances sa JOIN employees e ON sa.employee_id = e.id LEFT JOIN positions p ON e.position_id = p.id LEFT JOIN departments d ON p.department_id = d.id WHERE sa.advance_month = ? AND sa.advance_year = ? AND sa.status = 'approved' $adv_branch_sql ORDER BY sa.advance_date DESC", $adv_params)->results();

// Conditionally load the correct header
if ($is_branch_accountant && !$is_admin) {
    include_once '../templates/accounts_header.php';
} else {
    include_once '../templates/header.php';
}
?>

<!-- The HTML structure is the same as your admin template. The PHP variables above are now correctly filtered by branch. -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

<div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-xl border p-6">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
            <div class="h-12 w-12 bg-primary-100 rounded-xl flex items-center justify-center"><i class="fas fa-landmark text-primary-600 text-xl"></i></div>
            Loan Management
        </h1>
    </div>

    <div x-data="{ activeTab: 'pending', paymentModal: false, selectedLoan: null, paymentAmount: '', paymentDate: '<?php echo date('Y-m-d'); ?>', redirectTab: 'all_loans' }" x-init="()=>{ const params = new URLSearchParams(window.location.search); if (params.get('tab')) { activeTab = params.get('tab'); } }">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <a href="#pending" @click.prevent="activeTab = 'pending'" :class="{'border-primary-500 text-primary-600': activeTab === 'pending'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-clock"></i> Pending Approval <span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full text-xs"><?php echo count($pendingApplications); ?></span></a>
                <a href="#apply" @click.prevent="activeTab = 'apply'" :class="{'border-primary-500 text-primary-600': activeTab === 'apply'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-paper-plane"></i> Apply</a>
                <a href="#salary_advances" @click.prevent="activeTab = 'salary_advances'" :class="{'border-primary-500 text-primary-600': activeTab === 'salary_advances'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-hand-holding-usd"></i> Salary Advances</a>
                <a href="#all_loans" @click.prevent="activeTab = 'all_loans'" :class="{'border-primary-500 text-primary-600': activeTab === 'all_loans'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-list"></i> All Loans</a>
            </nav>
        </div>

        <div class="mt-6">
            <!-- PENDING APPROVAL TAB -->
            <div x-show="activeTab === 'pending'" x-cloak>
                 <div class="bg-white rounded-2xl shadow-xl border overflow-hidden">
                    <div class="p-6 border-b"><h2 class="text-xl font-bold">Pending Loan Applications</h2></div>
                    <?php if (!empty($pendingApplications)): ?>
                        <div class="overflow-x-auto"><table class="min-w-full divide-y">
                            <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Employee</th><th class="px-6 py-3 text-left">Type</th><th class="px-6 py-3 text-right">Amount</th><th class="px-6 py-3 text-center">Installments</th><th class="px-6 py-3 text-left">Reason</th><th class="px-6 py-3 text-center">Actions</th></tr></thead>
                            <tbody class="divide-y"><?php foreach ($pendingApplications as $app): ?><tr><td class="px-6 py-4"><?php echo htmlspecialchars($app->first_name . ' ' . $app->last_name); ?></td><td class="px-6 py-4"><?php if($app->loan_type == 'fixed_emi') echo 'Fixed EMI'; elseif($app->loan_type == 'random_repayment') echo 'Random Repayment'; else echo 'Salary Advance'; ?></td><td class="px-6 py-4 text-right">৳<?php echo number_format($app->amount, 2); ?></td><td class="px-6 py-4 text-center"><?php echo $app->installments ?? 'N/A'; ?></td><td class="px-6 py-4 max-w-xs truncate"><?php echo htmlspecialchars($app->reason); ?></td><td class="px-6 py-4 text-center"><form method="POST" class="inline-flex gap-2"><input type="hidden" name="application_id" value="<?php echo $app->id; ?>"><button type="submit" name="action" value="approve" class="px-3 py-1 text-sm bg-green-500 text-white rounded-md">Approve</button><button type="submit" name="action" value="reject" class="px-3 py-1 text-sm bg-red-500 text-white rounded-md">Reject</button><input type="hidden" name="update_loan_application" value="1"></form></td></tr><?php endforeach; ?></tbody>
                        </table></div>
                    <?php else: ?><div class="text-center py-12"><i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i><h3 class="text-lg font-medium">No pending applications for your branch.</h3></div><?php endif; ?>
                </div>
            </div>

            <!-- APPLY TAB -->
            <div x-show="activeTab === 'apply'" x-cloak>
                 <div class="bg-white rounded-2xl shadow-xl border p-8 max-w-2xl mx-auto">
                    <h2 class="text-2xl font-bold mb-6">New Loan/Advance Application</h2>
                    <form method="POST" class="space-y-6" x-data="{ loanType: 'fixed_emi' }">
                        <input type="hidden" name="apply_for_loan" value="1">
                        <div><label class="block text-sm font-medium">Employee</label><select name="employee_id" required class="mt-1 w-full rounded-md border-gray-300"><option value="">Select Employee</option><?php foreach($allEmployees as $emp): ?><option value="<?php echo $emp->id; ?>"><?php echo htmlspecialchars($emp->first_name . ' ' . $emp->last_name); ?></option><?php endforeach; ?></select></div>
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
                </div>
            </div>
            
            <!-- ALL LOANS TAB -->
             <div x-show="activeTab === 'all_loans'" x-cloak>
                 <div class="bg-white rounded-2xl shadow-xl border"><div class="p-6 border-b"><h2 class="text-xl font-bold">All Employee Loans</h2></div><div class="overflow-x-auto"><table class="min-w-full divide-y">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Employee</th><th class="px-6 py-3 text-left">Date</th><th class="px-6 py-3 text-right">Total</th><th class="px-6 py-3 text-right">Outstanding</th><th class="px-6 py-3 text-center">Status</th><th class="px-6 py-3 text-center">Actions</th></tr></thead>
                    <tbody class="divide-y"><?php if(empty($allLoans)): ?><tr><td colspan="6" class="text-center py-10 text-gray-500">No loans found for this branch.</td></tr><?php else: ?><?php foreach ($allLoans as $loan): ?><tr><td class="px-6 py-4"><?php echo htmlspecialchars($loan->first_name . ' ' . $loan->last_name); ?></td><td class="px-6 py-4"><?php echo date('d M, Y', strtotime($loan->loan_date)); ?></td><td class="px-6 py-4 text-right">৳<?php echo number_format($loan->amount, 2); ?></td><td class="px-6 py-4 text-right text-red-600 font-semibold">৳<?php echo number_format($loan->outstanding_balance, 2); ?></td><td class="px-6 py-4 text-center"><?php echo ucfirst($loan->status); ?></td><td class="px-6 py-4 text-center"><?php if ($loan->outstanding_balance > 0.01 && $loan->status === 'active'): ?><button @click="paymentModal = true; selectedLoan = { id: <?php echo $loan->id; ?>, amount: <?php echo $loan->amount; ?>, outstanding: <?php echo $loan->outstanding_balance; ?>, date: '<?php echo date('d M, Y', strtotime($loan->loan_date)); ?>', employee: '<?php echo htmlspecialchars(addslashes($loan->first_name . ' ' . $loan->last_name)); ?>' }; paymentAmount = ''; redirectTab = 'all_loans';" class="px-3 py-1 text-sm bg-blue-500 text-white rounded-md hover:bg-blue-600">Add Payment</button><?php else: ?><span class="text-green-600 font-semibold">Paid</span><?php endif; ?></td></tr><?php endforeach; ?><?php endif; ?></tbody>
                </table></div></div>
            </div>

            <!-- SALARY ADVANCES TAB -->
            <div x-show="activeTab === 'salary_advances'" x-cloak>
                 <div class="bg-white rounded-2xl shadow-xl border">
                    <div class="p-6 border-b"><h2 class="text-xl font-bold">Salary Advance History</h2></div>
                    <div class="p-6 bg-gray-50 border-b">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <input type="hidden" name="tab" value="salary_advances">
                            <div><label class="block text-sm font-medium">Month</label><select name="adv_month" class="mt-1 w-full rounded-md border-gray-300"><?php for ($m=1; $m<=12; $m++): ?><option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php if($adv_filter_month == $m) echo 'selected'; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option><?php endfor; ?></select></div>
                            <div><label class="block text-sm font-medium">Year</label><select name="adv_year" class="mt-1 w-full rounded-md border-gray-300"><?php for ($y = date('Y'); $y >= date('Y')-2; $y--): ?><option value="<?php echo $y; ?>" <?php if($adv_filter_year == $y) echo 'selected'; ?>><?php echo $y; ?></option><?php endfor; ?></select></div>
                            <div class="md:col-start-4 flex justify-end"><button type="submit" class="px-6 py-3 bg-primary-600 text-white rounded-lg font-bold">Filter</button></div>
                        </form>
                    </div>
                    <div class="overflow-x-auto"><table class="min-w-full divide-y">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Employee</th><th class="px-6 py-3 text-left">Department</th><th class="px-6 py-3 text-left">Date</th><th class="px-6 py-3 text-right">Amount</th><th class="px-6 py-3 text-left">Deduction Period</th><th class="px-6 py-3 text-left">Reason</th><th class="px-6 py-3 text-center">Status</th></tr></thead>
                        <tbody class="divide-y"><?php if(empty($myAdvancesList)): ?><tr><td colspan="7" class="text-center py-10 text-gray-500">No salary advances found for this period and branch.</td></tr><?php else: ?><?php foreach ($myAdvancesList as $adv): ?><tr><td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($adv->first_name . ' ' . $adv->last_name); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($adv->department_name ?? 'N/A'); ?></td><td class="px-6 py-4"><?php echo date('d M, Y', strtotime($adv->advance_date)); ?></td><td class="px-6 py-4 text-right font-semibold text-red-600">৳<?php echo number_format($adv->amount, 2); ?></td><td class="px-6 py-4"><?php echo date('F Y', mktime(0,0,0,$adv->advance_month,1,$adv->advance_year)); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($adv->reason); ?></td><td class="px-6 py-4 text-center"><span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800"><?php echo ucfirst($adv->status); ?></span></td></tr><?php endforeach; ?><?php endif; ?></tbody>
                    </table></div>
                </div>
            </div>

            <!-- PAYMENT MODAL -->
             <div x-show="paymentModal" x-cloak class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click.self="paymentModal = false">
                <div class="relative top-20 mx-auto p-8 border w-full max-w-md shadow-2xl rounded-2xl bg-white">
                    <div class="flex justify-between items-center mb-6"><h3 class="text-2xl font-bold text-gray-900">Make Loan Payment</h3><button @click="paymentModal = false" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button></div>
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <div class="text-sm text-gray-600 mb-2" x-show="selectedLoan && selectedLoan.employee"><strong>Employee:</strong> <span x-text="selectedLoan.employee"></span></div>
                        <div class="text-sm text-gray-600 mb-2"><strong>Loan Date:</strong> <span x-text="selectedLoan ? selectedLoan.date : ''"></span></div>
                        <div class="text-sm text-gray-600 mb-2"><strong>Total Amount:</strong> ৳<span x-text="selectedLoan ? selectedLoan.amount.toFixed(2) : '0.00'"></span></div>
                        <div class="text-sm text-red-600 font-semibold"><strong>Outstanding:</strong> ৳<span x-text="selectedLoan ? selectedLoan.outstanding.toFixed(2) : '0.00'"></span></div>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="make_payment" value="1"><input type="hidden" name="loan_id" :value="selectedLoan ? selectedLoan.id : ''"><input type="hidden" name="redirect_tab" x-model="redirectTab">
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Payment Amount</label><input type="number" name="payment_amount" x-model="paymentAmount" step="0.01" min="0.01" :max="selectedLoan ? selectedLoan.outstanding : 0" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Enter amount"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Payment Date</label><input type="date" name="payment_date" x-model="paymentDate" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"></div>
                        <div class="flex gap-3 pt-4"><button type="button" @click="paymentModal = false" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300">Cancel</button><button type="submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg font-bold hover:from-primary-700 hover:to-primary-800">Submit Payment</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSelectAll(source, className) { document.querySelectorAll('.' + className).forEach(checkbox => checkbox.checked = source.checked); }
    function filterBulkTable() { const searchTerm = document.getElementById('bulkSearchInput').value.toLowerCase(); document.querySelectorAll('#bulkTableBody .bulk-employee-row').forEach(row => { const name = row.dataset.name || ''; const department = row.dataset.department || ''; row.style.display = (name.includes(searchTerm) || department.includes(searchTerm)) ? '' : 'none'; }); }
    function filterSheetTable() { const searchTerm = document.getElementById('sheetSearchInput').value.toLowerCase(); document.querySelectorAll('#sheetTableBody .employee-row').forEach(row => { const name = row.dataset.name || ''; const department = row.dataset.department || ''; row.style.display = (name.includes(searchTerm) || department.includes(searchTerm)) ? '' : 'none'; }); }
</script>

<?php include_once '../templates/footer.php'; ?>

