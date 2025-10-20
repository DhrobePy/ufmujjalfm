<?php
// new_ufmhrm/admin/disburse_payroll.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// --- Fetch Approved/Disbursed Payroll Data with Full Breakdown Details ---
$payrollPeriodResult = $db->query("SELECT pay_period_start, pay_period_end FROM payrolls WHERE status IN ('approved', 'disbursed') LIMIT 1")->first();
if (!$payrollPeriodResult) {
    $_SESSION['info_flash'] = 'There is no approved payroll to disburse.';
    header('Location: payroll.php');
    exit();
}
$payPeriodStart = $payrollPeriodResult->pay_period_start;
$payPeriodEnd = $payrollPeriodResult->pay_period_end;
$daysInMonth = date('t', strtotime($payPeriodEnd));

$sql = "
    SELECT 
        p.*, 
        e.first_name, e.last_name, pos.name as position_name,
        ss.basic_salary, ss.house_allowance, ss.transport_allowance, ss.medical_allowance, ss.other_allowances,
        ss.provident_fund, ss.tax_deduction, ss.other_deductions,
        (SELECT COUNT(*) FROM attendance WHERE employee_id = e.id AND status = 'absent' AND clock_in BETWEEN ? AND ?) as absent_days,
        (SELECT amount FROM salary_advances WHERE employee_id = e.id AND advance_date BETWEEN ? AND ? LIMIT 1) as advance_amount,
        (SELECT monthly_payment FROM loans WHERE employee_id = e.id AND status = 'active' LIMIT 1) as loan_emi
    FROM payrolls p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    LEFT JOIN salary_structures ss ON e.id = ss.employee_id
    WHERE p.status IN ('approved', 'disbursed')
    GROUP BY p.id
    ORDER BY e.first_name
";
$payrollResult = $db->query($sql, [$payPeriodStart, $payPeriodEnd, $startOfMonth, $endOfMonth]); // Note: Adjusted placeholder count if needed
$payrollItems = $payrollResult ? $payrollResult->results() : [];


// --- Calculate Summaries ---
$totalToDisburse = 0; $totalDisbursed = 0;
foreach($payrollItems as $item) {
    $totalToDisburse += $item->net_salary;
    if ($item->status == 'disbursed' || $item->status == 'paid') {
        $totalDisbursed += $item->net_salary;
    }
}
$remaining = $totalToDisburse - $totalDisbursed;

$pageTitle = 'Disburse Payroll - ' . date('F Y', strtotime($payPeriodEnd));
include_once '../templates/header.php';
?>

<div class="space-y-6" x-data="{ openRows: [] }">
    <div class="bg-white rounded-lg shadow-sm p-6">
        <a href="payroll.php" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 mb-2"><i class="fas fa-arrow-left mr-2"></i>Back to Payroll Hub</a>
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-hand-holding-usd text-primary-600 mr-3"></i>Disburse Payroll</h1>
        <p class="mt-1 text-sm text-gray-600">Manually update the payment status for <strong><?php echo date('F Y', strtotime($payPeriodEnd)); ?></strong>.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl p-6 text-white"><div><p class="font-semibold">Total Net Payroll</p><p class="text-3xl font-bold mt-2">৳<?php echo number_format($totalToDisburse, 2); ?></p></div></div>
        <div class="bg-gradient-to-br from-green-500 to-teal-600 rounded-xl p-6 text-white"><div><p class="font-semibold">Total Disbursed</p><p class="text-3xl font-bold mt-2">৳<?php echo number_format($totalDisbursed, 2); ?></p></div></div>
        <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl p-6 text-white"><div><p class="font-semibold">Remaining to Pay</p><p class="text-3xl font-bold mt-2">৳<?php echo number_format($remaining, 2); ?></p></div></div>
    </div>

    <div class="bg-white rounded-lg shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-12"></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Net Salary</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($payrollItems as $item): 
                        $dailyRate = $item->basic_salary / $daysInMonth;
                        $absenceDeduction = $item->absent_days * $dailyRate;
                    ?>
                        <tr class="hover:bg-gray-50 cursor-pointer" @click="openRows.includes(<?php echo $item->id; ?>) ? openRows = openRows.filter(id => id !== <?php echo $item->id; ?>) : openRows.push(<?php echo $item->id; ?>)">
                            <td class="px-4 py-4 text-center text-gray-400"><i class="fas" :class="{'fa-chevron-down': !openRows.includes(<?php echo $item->id; ?>), 'fa-chevron-up': openRows.includes(<?php echo $item->id; ?>)}"></i></td>
                            <td class="px-6 py-4"><div class="font-medium text-gray-900"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></div><div class="text-sm text-gray-500"><?php echo htmlspecialchars($item->position_name ?? 'N/A'); ?></div></td>
                            <td class="px-6 py-4 font-bold text-lg text-green-600">৳<?php echo number_format($item->net_salary, 2); ?></td>
                            <td class="px-6 py-4 text-center"><span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo ($item->status === 'paid') ? 'bg-green-100 text-green-800' : (($item->status === 'disbursed') ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'); ?>"><?php echo ucfirst(str_replace('_', ' ', $item->status)); ?></span></td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <?php if ($item->status == 'approved'): ?>
                                    <form action="update_payroll_status.php" method="POST" class="inline"><input type="hidden" name="payroll_id" value="<?php echo $item->id; ?>"><button type="submit" name="status" value="disbursed" class="text-blue-600 hover:text-blue-900">Mark as Disbursed</button></form>
                                <?php elseif ($item->status == 'disbursed'): ?>
                                    <form action="update_payroll_status.php" method="POST" class="inline"><input type="hidden" name="payroll_id" value="<?php echo $item->id; ?>"><button type="submit" name="status" value="paid" class="text-green-600 hover:text-green-900">Mark as Paid</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr x-show="openRows.includes(<?php echo $item->id; ?>)" x-cloak>
                            <td colspan="5" class="p-0">
                                <div class="bg-indigo-50 p-6 border-l-4 border-indigo-500">
                                    <h4 class="font-semibold text-indigo-800 mb-4">Salary Breakdown for <?php echo htmlspecialchars($item->first_name); ?></h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                                        <div class="space-y-2">
                                            <h5 class="font-bold text-green-700 border-b pb-1 mb-2">Earnings</h5>
                                            <div class="flex justify-between"><span>Basic Salary:</span><span class="font-medium">৳<?php echo number_format($item->basic_salary ?? 0, 2); ?></span></div>
                                            <div class="flex justify-between"><span>House Allowance:</span><span class="font-medium">৳<?php echo number_format($item->house_allowance ?? 0, 2); ?></span></div>
                                            <div class="flex justify-between"><span>Transport Allowance:</span><span class="font-medium">৳<?php echo number_format($item->transport_allowance ?? 0, 2); ?></span></div>
                                            <div class="flex justify-between"><span>Medical Allowance:</span><span class="font-medium">৳<?php echo number_format($item->medical_allowance ?? 0, 2); ?></span></div>
                                            <div class="flex justify-between border-t pt-2 mt-2"><strong>Gross Salary:</strong><strong class="text-green-800">৳<?php echo number_format($item->gross_salary, 2); ?></strong></div>
                                        </div>
                                        <div class="space-y-2">
                                            <h5 class="font-bold text-red-700 border-b pb-1 mb-2">Deductions</h5>
                                            <div class="flex justify-between"><span>Absence (<?php echo $item->absent_days; ?> days):</span><span class="font-medium">- ৳<?php echo number_format($absenceDeduction, 2); ?></span></div>
                                            <div class="flex justify-between"><span>Salary Advance:</span><span class="font-medium">- ৳<?php echo number_format($item->advance_amount ?? 0, 2); ?></span></div>
                                            <div class="flex justify-between"><span>Loan EMI:</span><span class="font-medium">- ৳<?php echo number_format($item->loan_emi ?? 0, 2); ?></span></div>
                                            <div class="flex justify-between"><span>Provident Fund:</span><span class="font-medium">- ৳<?php echo number_format($item->provident_fund ?? 0, 2); ?></span></div>
                                            <div class="flex justify-between"><span>Tax Deduction:</span><span class="font-medium">- ৳<?php echo number_format($item->tax_deduction ?? 0, 2); ?></span></div>
                                            <div class="flex justify-between border-t pt-2 mt-2"><strong>Total Deductions:</strong><strong class="text-red-800">- ৳<?php echo number_format($item->deductions, 2); ?></strong></div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>