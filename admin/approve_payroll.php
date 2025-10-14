<?php
// new_ufmhrm/admin/approve_payroll.php (Fully Corrected & Integrated)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// --- Handle Form Actions (Approve/Reject/Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_selected'])) {
        $selectedIds = $_POST['selected_payrolls'] ?? [];
        if (!empty($selectedIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $db->query("UPDATE payrolls SET status = 'approved' WHERE id IN ($placeholders)", $selectedIds);
            $_SESSION['success_flash'] = count($selectedIds) . ' payroll(s) have been approved successfully.';
        }
    } elseif (isset($_POST['reject_selected'])) {
        $selectedIds = $_POST['selected_payrolls'] ?? [];
        if (!empty($selectedIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $db->query("UPDATE payrolls SET status = 'rejected' WHERE id IN ($placeholders)", $selectedIds);
            $_SESSION['error_flash'] = count($selectedIds) . ' payroll(s) have been rejected.';
        }
    } elseif (isset($_POST['update_payroll'])) {
        // --- ROBUST UPDATE LOGIC ---
        $payrollId = intval($_POST['payroll_id']);

        // Get all editable values from the form
        $basic_salary = floatval($_POST['basic_salary']);
        $house_allowance = floatval($_POST['house_allowance']);
        $transport_allowance = floatval($_POST['transport_allowance']);
        $medical_allowance = floatval($_POST['medical_allowance']);
        $other_allowances = floatval($_POST['other_allowances']);
        $provident_fund = floatval($_POST['provident_fund']);
        $tax_deduction = floatval($_POST['tax_deduction']);
        $other_deductions = floatval($_POST['other_deductions']);
        
        // Get fixed deductions that were sent via hidden inputs
        $absence_deduction = floatval($_POST['absence_deduction']);
        $salary_advance_deduction = floatval($_POST['salary_advance_deduction']);
        $loan_installment_deduction = floatval($_POST['loan_installment_deduction']);

        // Calculate totals
        $grossSalary = $basic_salary + $house_allowance + $transport_allowance + $medical_allowance + $other_allowances;
        $totalDeductions = $absence_deduction + $salary_advance_deduction + $loan_installment_deduction + 
                          $provident_fund + $tax_deduction + $other_deductions;
        $netSalary = $grossSalary - $totalDeductions;
        
        $db->getPdo()->beginTransaction();
        try {
            // Check if payroll_details record exists
            $existingDetails = $db->query("SELECT id FROM payroll_details WHERE payroll_id = ?", [$payrollId])->first();
            
            if ($existingDetails) {
                // Update existing record
                $db->query(
                    "UPDATE payroll_details SET 
                        basic_salary = ?, house_allowance = ?, transport_allowance = ?, medical_allowance = ?, other_allowances = ?,
                        provident_fund = ?, tax_deduction = ?, other_deductions = ?,
                        gross_salary = ?, total_deductions = ?, net_salary = ?
                    WHERE payroll_id = ?",
                    [
                        $basic_salary, $house_allowance, $transport_allowance, $medical_allowance, $other_allowances,
                        $provident_fund, $tax_deduction, $other_deductions,
                        round($grossSalary, 2), round($totalDeductions, 2), round($netSalary, 2), $payrollId
                    ]
                );
            } else {
                // Insert new record if it doesn't exist (backward compatibility)
                $db->query(
                    "INSERT INTO payroll_details (
                        payroll_id, basic_salary, house_allowance, transport_allowance, medical_allowance, other_allowances,
                        absence_deduction, salary_advance_deduction, loan_installment_deduction,
                        provident_fund, tax_deduction, other_deductions,
                        gross_salary, total_deductions, net_salary
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $payrollId, $basic_salary, $house_allowance, $transport_allowance, $medical_allowance, $other_allowances,
                        $absence_deduction, $salary_advance_deduction, $loan_installment_deduction,
                        $provident_fund, $tax_deduction, $other_deductions,
                        round($grossSalary, 2), round($totalDeductions, 2), round($netSalary, 2)
                    ]
                );
            }

            // Update the main payrolls table with the final calculated summaries
            $db->query(
                "UPDATE payrolls SET gross_salary = ?, deductions = ?, net_salary = ? WHERE id = ?",
                [round($grossSalary, 2), round($totalDeductions, 2), round($netSalary, 2), $payrollId]
            );

            $db->getPdo()->commit();
            $_SESSION['success_flash'] = 'Payroll record has been updated successfully.';

        } catch (Exception $e) {
            $db->getPdo()->rollBack();
            $_SESSION['error_flash'] = 'Error updating payroll: ' . $e->getMessage();
        }
    }
    header('Location: approve_payroll.php');
    exit();
}

// --- ROBUST QUERY: Fetch data with LEFT JOIN to handle older payroll records ---
$sql = "
    SELECT 
        p.id, p.employee_id, p.pay_period_start, p.pay_period_end, p.gross_salary, p.deductions, p.net_salary,
        e.first_name, e.last_name, 
        pos.name as position_name, d.name as department_name,
        pd.basic_salary, pd.house_allowance, pd.transport_allowance, pd.medical_allowance, pd.other_allowances,
        pd.absence_deduction, pd.salary_advance_deduction, pd.loan_installment_deduction,
        pd.provident_fund, pd.tax_deduction, pd.other_deductions, pd.total_deductions,
        ss.basic_salary as ss_basic_salary, ss.house_allowance as ss_house_allowance, 
        ss.transport_allowance as ss_transport_allowance, ss.medical_allowance as ss_medical_allowance, 
        ss.other_allowances as ss_other_allowances, ss.provident_fund as ss_provident_fund, 
        ss.tax_deduction as ss_tax_deduction, ss.other_deductions as ss_other_deductions
    FROM payrolls p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN payroll_details pd ON p.id = pd.payroll_id
    LEFT JOIN salary_structures ss ON e.id = ss.employee_id
    LEFT JOIN positions pos ON e.position_id = pos.id
    LEFT JOIN departments d ON pos.department_id = d.id
    WHERE p.status = 'pending_approval'
    ORDER BY e.first_name, e.last_name
";
$payrollResult = $db->query($sql);
$payrollItems = $payrollResult ? $payrollResult->results() : [];

if (empty($payrollItems)) {
    $_SESSION['info_message'] = 'There is no pending payroll to review.';
    header('Location: payroll.php');
    exit();
}

// Process items to create a consistent data structure, using salary_structures as a fallback
foreach ($payrollItems as $item) {
    $item->basic_salary = $item->basic_salary ?? $item->ss_basic_salary ?? 0;
    $item->house_allowance = $item->house_allowance ?? $item->ss_house_allowance ?? 0;
    $item->transport_allowance = $item->transport_allowance ?? $item->ss_transport_allowance ?? 0;
    $item->medical_allowance = $item->medical_allowance ?? $item->ss_medical_allowance ?? 0;
    $item->other_allowances = $item->other_allowances ?? $item->ss_other_allowances ?? 0;
    $item->provident_fund = $item->provident_fund ?? $item->ss_provident_fund ?? 0;
    $item->tax_deduction = $item->tax_deduction ?? $item->ss_tax_deduction ?? 0;
    $item->other_deductions = $item->other_deductions ?? 0;
    
    // For records without a 'payroll_details' entry, we must calculate fixed deductions from the summary 'deductions' field.
    // This is an estimation, but ensures backward compatibility.
    if (!isset($item->absence_deduction)) {
        // A simple assumption for older records if you need to display something.
        $item->absence_deduction = $item->deductions; 
        $item->salary_advance_deduction = 0;
        $item->loan_installment_deduction = 0;
    }
    
    $item->total_deductions = $item->total_deductions ?? $item->deductions;
}

// --- Calculate Financial Summaries ---
$totalGross = array_sum(array_column($payrollItems, 'gross_salary'));
$totalDeductions = array_sum(array_column($payrollItems, 'total_deductions'));
$totalNet = array_sum(array_column($payrollItems, 'net_salary'));
$employeeCount = count($payrollItems);

$payPeriodStart = $payrollItems[0]->pay_period_start;
$payPeriodEnd = $payrollItems[0]->pay_period_end;
$pageTitle = 'Review Payroll - ' . date('F Y', strtotime($payPeriodEnd));
include_once '../templates/header.php';
?>

<style>
    .detail-row { display: none; }
    .detail-row.active { display: table-row; }
    .chevron-icon { transition: transform 0.3s ease; }
    .chevron-icon.rotated { transform: rotate(180deg); }
    .highlight-row { background-color: #fef3c7 !important; }
    .edit-field { width: 9rem; padding: 0.5rem; border-radius: 0.5rem; text-align: right; font-weight: 600; border: 2px solid #e5e7eb; }
    .edit-field:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
</style>

<div class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 py-8">
    <div class="max-w-7xl mx-auto px-4 space-y-6">
        
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
             <a href="payroll.php" class="inline-flex items-center text-sm text-gray-600 hover:text-indigo-600 mb-4 transition-colors group"><i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i>Back to Payroll Hub</a>
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3"><div class="h-12 w-12 bg-indigo-100 rounded-xl flex items-center justify-center"><i class="fas fa-tasks text-indigo-600 text-xl"></i></div>Review & Approve Payroll</h1>
                    <p class="mt-2 text-gray-600">Review the generated payroll for <strong class="text-indigo-600"><?php echo date('F Y', strtotime($payPeriodEnd)); ?></strong></p>
                </div>
                <div class="bg-indigo-50 px-6 py-3 rounded-xl border-2 border-indigo-200"><p class="text-sm text-indigo-600 font-semibold">Total Employees</p><p class="text-3xl font-bold text-indigo-900"><?php echo $employeeCount; ?></p></div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-6 text-white shadow-xl transform hover:scale-105 transition-all"><div class="h-14 w-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-calendar-alt text-3xl"></i></div><p class="font-semibold text-blue-100 mb-2">Pay Period</p><p class="text-2xl font-bold"><?php echo date('M d', strtotime($payPeriodStart)) . ' - ' . date('M d, Y', strtotime($payPeriodEnd)); ?></p></div>
            <div class="bg-gradient-to-br from-green-500 to-teal-600 rounded-2xl p-6 text-white shadow-xl transform hover:scale-105 transition-all"><div class="h-14 w-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-money-bill-wave text-3xl"></i></div><p class="font-semibold text-green-100 mb-2">Total Gross Salary</p><p class="text-3xl font-bold" id="summary-gross">৳<?php echo number_format($totalGross, 2); ?></p></div>
            <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-6 text-white shadow-xl transform hover:scale-105 transition-all"><div class="h-14 w-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-minus-circle text-3xl"></i></div><p class="font-semibold text-amber-100 mb-2">Total Deductions</p><p class="text-3xl font-bold" id="summary-deductions">৳<?php echo number_format($totalDeductions, 2); ?></p></div>
            <div class="bg-gradient-to-br from-purple-500 to-violet-600 rounded-2xl p-6 text-white shadow-xl transform hover:scale-105 transition-all"><div class="h-14 w-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-hand-holding-usd text-3xl"></i></div><p class="font-semibold text-purple-100 mb-2">Total Net Pay</p><p class="text-3xl font-bold" id="summary-net">৳<?php echo number_format($totalNet, 2); ?></p></div>
        </div>
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6"><div class="flex flex-col md:flex-row gap-4 items-center justify-between"><div class="flex-1 w-full md:max-w-md"><div class="relative"><input type="text" id="searchInput" placeholder="Search by name, position, or department..." class="w-full pl-12 pr-4 py-3 rounded-xl border-2 border-gray-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all outline-none" onkeyup="searchPayroll()"><i class="fas fa-search absolute left-4 top-4 text-gray-400 text-lg"></i></div></div><div id="selectedCount" class="text-sm text-gray-600 font-semibold py-3 px-4 bg-gray-100 rounded-lg"><i class="fas fa-check-square mr-2"></i>0 selected</div></div></div>

        <form id="payrollForm" method="POST">
            <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
                <div class="p-6 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200 flex items-center justify-between">
                    <div><h2 class="text-xl font-bold text-gray-900 flex items-center gap-3"><i class="fas fa-list-alt text-indigo-600"></i>Payroll Details</h2><p class="text-sm text-gray-600 mt-1">Click the arrow on any row to view/edit the detailed breakdown</p></div>
                    <label class="flex items-center gap-2 cursor-pointer bg-white px-4 py-2 rounded-lg shadow-sm hover:shadow-md transition-all"><input type="checkbox" id="selectAll" class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" onchange="toggleSelectAll()"><span class="font-semibold text-gray-700">Select All</span></label>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-12 px-4 py-4"><i class="fas fa-check-circle text-gray-400"></i></th>
                                <th class="w-12 px-4 py-4"></th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Position</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Gross Salary</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Deductions</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Net Salary</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="payrollTableBody">
                            <?php foreach ($payrollItems as $item): ?>
                                <tr class="hover:bg-indigo-50 transition-colors payroll-row" data-employee="<?php echo strtolower(htmlspecialchars($item->first_name . ' ' . $item->last_name)); ?>" data-position="<?php echo strtolower(htmlspecialchars($item->position_name ?? '')); ?>" data-department="<?php echo strtolower(htmlspecialchars($item->department_name ?? '')); ?>">
                                    <td class="px-4 py-4 text-center"><input type="checkbox" name="selected_payrolls[]" value="<?php echo $item->id; ?>" class="payroll-checkbox w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"></td>
                                    <td class="px-4 py-4 text-center cursor-pointer" onclick="toggleDetail(<?php echo $item->id; ?>)"><i class="fas fa-chevron-down text-gray-400 chevron-icon" id="chevron-<?php echo $item->id; ?>"></i></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="flex items-center gap-3"><div class="h-10 w-10 bg-indigo-100 rounded-full flex-shrink-0 flex items-center justify-center"><span class="text-indigo-600 font-bold text-sm"><?php echo strtoupper(substr($item->first_name, 0, 1) . substr($item->last_name, 0, 1)); ?></span></div><div><div class="font-semibold text-gray-900"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></div><div class="text-xs text-gray-500">EMP-<?php echo str_pad($item->employee_id, 4, '0', STR_PAD_LEFT); ?></div></div></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div><span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item->position_name ?? 'N/A'); ?></span><div class="text-xs text-gray-500"><?php echo htmlspecialchars($item->department_name ?? 'N/A'); ?></div></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right"><span class="text-sm font-semibold text-gray-900" id="gross-<?php echo $item->id; ?>">৳<?php echo number_format($item->gross_salary, 2); ?></span></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right"><span class="text-sm font-semibold text-red-600" id="deduction-<?php echo $item->id; ?>">৳<?php echo number_format($item->total_deductions, 2); ?></span></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right"><span class="text-sm font-bold text-green-600" id="net-<?php echo $item->id; ?>">৳<?php echo number_format($item->net_salary, 2); ?></span></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center"><button type="button" onclick="toggleDetail(<?php echo $item->id; ?>)" class="text-indigo-600 hover:text-indigo-800 font-semibold text-sm"><i class="fas fa-edit mr-1"></i>Edit</button></td>
                                </tr>
                                
                                <tr class="detail-row" id="detail-<?php echo $item->id; ?>">
                                    <td colspan="8" class="p-0">
                                        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 p-6 border-l-4 border-indigo-500">
                                            <h4 class="font-bold text-indigo-900 text-lg mb-4">Salary Breakdown for <?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></h4>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                                <div class="bg-white rounded-xl p-5 shadow-sm border-2 border-green-200"><h5 class="font-bold text-green-700 mb-3">Earnings (Editable)</h5><div class="space-y-2 text-sm">
                                                    <div class="flex justify-between items-center py-1"><label for="basic-<?php echo $item->id; ?>">Basic Salary</label><input oninput="calculateTotal(<?php echo $item->id; ?>)" id="basic-<?php echo $item->id; ?>" type="number" step="0.01" value="<?php echo $item->basic_salary; ?>" class="edit-field"></div>
                                                    <div class="flex justify-between items-center py-1"><label for="house-<?php echo $item->id; ?>">House Allowance</label><input oninput="calculateTotal(<?php echo $item->id; ?>)" id="house-<?php echo $item->id; ?>" type="number" step="0.01" value="<?php echo $item->house_allowance; ?>" class="edit-field"></div>
                                                    <div class="flex justify-between items-center py-1"><label for="transport-<?php echo $item->id; ?>">Transport Allowance</label><input oninput="calculateTotal(<?php echo $item->id; ?>)" id="transport-<?php echo $item->id; ?>" type="number" step="0.01" value="<?php echo $item->transport_allowance; ?>" class="edit-field"></div>
                                                    <div class="flex justify-between items-center py-1"><label for="medical-<?php echo $item->id; ?>">Medical Allowance</label><input oninput="calculateTotal(<?php echo $item->id; ?>)" id="medical-<?php echo $item->id; ?>" type="number" step="0.01" value="<?php echo $item->medical_allowance; ?>" class="edit-field"></div>
                                                    <div class="flex justify-between items-center py-1"><label for="other-allowance-<?php echo $item->id; ?>">Other Allowances</label><input oninput="calculateTotal(<?php echo $item->id; ?>)" id="other-allowance-<?php echo $item->id; ?>" type="number" step="0.01" value="<?php echo $item->other_allowances; ?>" class="edit-field"></div>
                                                    <div class="flex justify-between items-center font-bold text-green-700 mt-2 pt-2 border-t"><span>Total Earnings</span><span id="total-earnings-<?php echo $item->id; ?>">৳<?php echo number_format($item->gross_salary, 2); ?></span></div>
                                                </div></div>
                                                <div class="bg-white rounded-xl p-5 shadow-sm border-2 border-red-200"><h5 class="font-bold text-red-700 mb-3">Deductions</h5><div class="space-y-2 text-sm">
                                                    <div class="bg-gray-50 p-3 rounded-lg border "><p class="text-xs font-semibold text-gray-500 mb-2">FIXED DEDUCTIONS</p><div class="space-y-1">
                                                        <div class="flex justify-between items-center"><span class="text-gray-600">Absence</span><span class="font-semibold text-gray-800">৳<?php echo number_format($item->absence_deduction, 2); ?></span><input type="hidden" id="absence-<?php echo $item->id; ?>" value="<?php echo $item->absence_deduction; ?>"></div>
                                                        <div class="flex justify-between items-center"><span class="text-gray-600">Advance</span><span class="font-semibold text-gray-800">৳<?php echo number_format($item->salary_advance_deduction, 2); ?></span><input type="hidden" id="advance-<?php echo $item->id; ?>" value="<?php echo $item->salary_advance_deduction; ?>"></div>
                                                        <div class="flex justify-between items-center"><span class="text-gray-600">Loan</span><span class="font-semibold text-gray-800">৳<?php echo number_format($item->loan_installment_deduction, 2); ?></span><input type="hidden" id="loan-<?php echo $item->id; ?>" value="<?php echo $item->loan_installment_deduction; ?>"></div>
                                                    </div></div>
                                                    <div class="flex justify-between items-center py-1"><label for="provident-<?php echo $item->id; ?>">Provident Fund</label><input oninput="calculateTotal(<?php echo $item->id; ?>)" id="provident-<?php echo $item->id; ?>" type="number" step="0.01" value="<?php echo $item->provident_fund; ?>" class="edit-field"></div>
                                                    <div class="flex justify-between items-center py-1"><label for="tax-<?php echo $item->id; ?>">Tax Deduction</label><input oninput="calculateTotal(<?php echo $item->id; ?>)" id="tax-<?php echo $item->id; ?>" type="number" step="0.01" value="<?php echo $item->tax_deduction; ?>" class="edit-field"></div>
                                                    <div class="flex justify-between items-center py-1"><label for="other-deduction-<?php echo $item->id; ?>">Other Deductions</label><input oninput="calculateTotal(<?php echo $item->id; ?>)" id="other-deduction-<?php echo $item->id; ?>" type="number" step="0.01" value="<?php echo $item->other_deductions; ?>" class="edit-field"></div>
                                                    <div class="flex justify-between items-center font-bold text-red-700 mt-2 pt-2 border-t"><span>Total Deductions</span><span id="total-deductions-<?php echo $item->id; ?>">৳<?php echo number_format($item->total_deductions, 2); ?></span></div>
                                                </div></div>
                                                <div class="bg-white rounded-xl p-5 shadow-sm"><h5 class="font-bold text-indigo-700 mb-3">Summary</h5><div class="space-y-4">
                                                    <div class="bg-green-50 p-4 rounded-lg border-2 border-green-200"><p class="text-xs font-semibold text-green-600">Gross Salary</p><p class="text-2xl font-bold text-green-700" id="summary-gross-<?php echo $item->id; ?>">৳<?php echo number_format($item->gross_salary, 2); ?></p></div>
                                                    <div class="bg-red-50 p-4 rounded-lg border-2 border-red-200"><p class="text-xs font-semibold text-red-600">Total Deductions</p><p class="text-2xl font-bold text-red-700" id="summary-deductions-<?php echo $item->id; ?>">৳<?php echo number_format($item->total_deductions, 2); ?></p></div>
                                                    <div class="bg-gradient-to-r from-purple-500 to-violet-600 p-5 rounded-lg shadow-lg"><p class="text-sm font-semibold text-white">NET SALARY</p><p class="text-3xl font-bold text-white" id="net-salary-<?php echo $item->id; ?>">৳<?php echo number_format($item->net_salary, 2); ?></p></div>
                                                    <div class="mt-4 flex flex-col gap-3">
                                                        <button type="button" onclick="savePayrollEdit(<?php echo $item->id; ?>)" class="w-full px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg font-bold hover:from-green-600 hover:to-emerald-700 transition-all shadow-lg"><i class="fas fa-save mr-2"></i>Save Changes</button>
                                                        <button type="button" onclick="toggleDetail(<?php echo $item->id; ?>)" class="w-full px-6 py-3 bg-gray-500 text-white rounded-lg font-semibold hover:bg-gray-600">Cancel</button>
                                                    </div>
                                                </div></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-200 mt-6">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-gray-600"><i class="fas fa-exclamation-triangle text-amber-500 mr-2"></i>Once approved, selected payroll entries cannot be edited or deleted.</div>
                    <div class="flex gap-4">
                        <button type="submit" name="reject_selected" onclick="return confirmRejectSelected()" class="px-8 py-4 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl font-bold hover:from-red-600 hover:to-red-700 transition-all shadow-lg hover:shadow-xl transform hover:scale-105 flex items-center gap-3"><i class="fas fa-times-circle text-xl"></i>Reject Selected</button>
                        <button type="submit" name="approve_selected" onclick="return confirmApproveSelected()" class="px-8 py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl font-bold hover:from-green-600 hover:to-emerald-700 transition-all shadow-lg hover:shadow-xl transform hover:scale-105 flex items-center gap-3"><i class="fas fa-check-circle text-xl"></i>Approve Selected</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDetail(payrollId) {
    const detailRow = document.getElementById('detail-' + payrollId);
    if (!detailRow) return;
    detailRow.classList.toggle('active');
    
    const chevron = document.getElementById('chevron-' + payrollId);
    if (chevron) {
        chevron.classList.toggle('rotated');
    }
}

function searchPayroll() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#payrollTableBody .payroll-row').forEach(row => {
        const payrollId = row.querySelector('.payroll-checkbox').value;
        const detailRow = document.getElementById('detail-' + payrollId);
        
        const isVisible = row.dataset.employee.includes(searchTerm) || row.dataset.position.includes(searchTerm) || row.dataset.department.includes(searchTerm);
        row.style.display = isVisible ? '' : 'none';
        
        if (detailRow && !isVisible && detailRow.classList.contains('active')) {
            toggleDetail(payrollId);
        }
    });
}

function calculateTotal(payrollId) {
    const getFloat = (id) => parseFloat(document.getElementById(id).value) || 0;

    const basic = getFloat(`basic-${payrollId}`);
    const house = getFloat(`house-${payrollId}`);
    const transport = getFloat(`transport-${payrollId}`);
    const medical = getFloat(`medical-${payrollId}`);
    const otherAllowance = getFloat(`other-allowance-${payrollId}`);
    const provident = getFloat(`provident-${payrollId}`);
    const tax = getFloat(`tax-${payrollId}`);
    const otherDeduction = getFloat(`other-deduction-${payrollId}`);
    const absence = getFloat(`absence-${payrollId}`);
    const advance = getFloat(`advance-${payrollId}`);
    const loan = getFloat(`loan-${payrollId}`);

    const totalEarnings = basic + house + transport + medical + otherAllowance;
    const totalDeductions = absence + advance + loan + provident + tax + otherDeduction;
    const netSalary = totalEarnings - totalDeductions;
    
    const formatCurrency = (num) => '৳' + (num || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');

    document.getElementById(`total-earnings-${payrollId}`).textContent = formatCurrency(totalEarnings);
    document.getElementById(`summary-gross-${payrollId}`).textContent = formatCurrency(totalEarnings);
    document.getElementById(`total-deductions-${payrollId}`).textContent = formatCurrency(totalDeductions);
    document.getElementById(`summary-deductions-${payrollId}`).textContent = formatCurrency(totalDeductions);
    document.getElementById(`net-salary-${payrollId}`).textContent = formatCurrency(netSalary);
    
    // Also update the main row display
    document.getElementById(`gross-${payrollId}`).textContent = formatCurrency(totalEarnings);
    document.getElementById(`deduction-${payrollId}`).textContent = formatCurrency(totalDeductions);
    document.getElementById(`net-${payrollId}`).textContent = formatCurrency(netSalary);
}

function savePayrollEdit(payrollId) {
    const getFloat = (id) => parseFloat(document.getElementById(id).value) || 0;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'approve_payroll.php';

    const fields = {
        'update_payroll': '1', 'payroll_id': payrollId,
        'basic_salary': getFloat(`basic-${payrollId}`), 'house_allowance': getFloat(`house-${payrollId}`),
        'transport_allowance': getFloat(`transport-${payrollId}`), 'medical_allowance': getFloat(`medical-${payrollId}`),
        'other_allowances': getFloat(`other-allowance-${payrollId}`), 'provident_fund': getFloat(`provident-${payrollId}`),
        'tax_deduction': getFloat(`tax-${payrollId}`), 'other_deductions': getFloat(`other-deduction-${payrollId}`),
        'absence_deduction': getFloat(`absence-${payrollId}`), 'salary_advance_deduction': getFloat(`advance-${payrollId}`),
        'loan_installment_deduction': getFloat(`loan-${payrollId}`)
    };
    
    for (const key in fields) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
}

function toggleSelectAll() {
    const isChecked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.payroll-row').forEach(row => {
        if (row.style.display !== 'none') {
            const checkbox = row.querySelector('.payroll-checkbox');
            if (checkbox) {
                checkbox.checked = isChecked;
                row.classList.toggle('highlight-row', isChecked);
            }
        }
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.payroll-checkbox:checked').length;
    document.getElementById('selectedCount').innerHTML = `<i class="fas fa-check-square mr-2"></i>${count} selected`;
}

function confirmApproveSelected() {
    const count = document.querySelectorAll('.payroll-checkbox:checked').length;
    if (count === 0) { alert('Please select at least one payroll entry to approve.'); return false; }
    return confirm(`✓ Approve ${count} selected payroll entries?\n\nOnce approved, these entries will be locked.`);
}

function confirmRejectSelected() {
    const count = document.querySelectorAll('.payroll-checkbox:checked').length;
    if (count === 0) { alert('Please select at least one payroll entry to reject.'); return false; }
    return confirm(`⚠️ Are you sure you want to REJECT ${count} selected payroll entries?\n\nThis will update their status to 'Rejected'.`);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.payroll-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', (e) => {
            e.target.closest('tr').classList.toggle('highlight-row', e.target.checked);
            updateSelectedCount();
        });
    });
});
</script>

<?php include_once '../templates/footer.php'; ?>