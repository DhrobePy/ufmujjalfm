<?php
// new_ufmhrm/admin/approve_payroll.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// --- Handle Form Actions (Approve/Reject) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_selected'])) {
        $selectedIds = $_POST['selected_payrolls'] ?? [];
        if (!empty($selectedIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $db->query("UPDATE payrolls SET status = 'approved' WHERE id IN ($placeholders)", $selectedIds);
            $_SESSION['success_message'] = count($selectedIds) . ' payroll(s) have been approved successfully.';
        }
    } elseif (isset($_POST['reject_selected'])) {
        $selectedIds = $_POST['selected_payrolls'] ?? [];
        if (!empty($selectedIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $db->query("DELETE FROM payrolls WHERE id IN ($placeholders)", $selectedIds);
            $_SESSION['error_message'] = count($selectedIds) . ' payroll(s) have been rejected and deleted.';
        }
    } elseif (isset($_POST['update_payroll'])) {
        $payrollId = $_POST['payroll_id'];
        $grossSalary = floatval($_POST['gross_salary']);
        $deductions = floatval($_POST['deductions']);
        $netSalary = $grossSalary - $deductions;
        
        $db->query(
            "UPDATE payrolls SET gross_salary = ?, deductions = ?, net_salary = ? WHERE id = ?",
            [$grossSalary, $deductions, $netSalary, $payrollId]
        );
        
        $_SESSION['success_message'] = 'Payroll updated successfully.';
    }
    header('Location: approve_payroll.php');
    exit();
}

// --- Fetch Pending Payroll Data with Salary Details ---
$sql = "
    SELECT 
        p.*, 
        e.first_name, 
        e.last_name, 
        pos.name as position_name,
        d.name as department_name,
        ss.basic_salary,
        ss.house_allowance,
        ss.transport_allowance,
        ss.medical_allowance,
        ss.other_allowances,
        ss.provident_fund,
        ss.tax_deduction,
        ss.other_deductions
    FROM payrolls p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    LEFT JOIN departments d ON pos.department_id = d.id
    LEFT JOIN salary_structures ss ON e.id = ss.employee_id
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

// --- Calculate Financial Summaries ---
$totalGross = 0; 
$totalDeductions = 0; 
$totalNet = 0;
$employeeCount = count($payrollItems);

foreach ($payrollItems as $item) {
    $totalGross += $item->gross_salary;
    $totalDeductions += $item->deductions;
    $totalNet += $item->net_salary;
}

$payPeriodStart = $payrollItems[0]->pay_period_start;
$payPeriodEnd = $payrollItems[0]->pay_period_end;
$payrollMonthIdentifier = date('Y-n', strtotime($payPeriodEnd));

$pageTitle = 'Review Payroll - ' . date('F Y', strtotime($payPeriodEnd));
include_once '../templates/header.php';
?>

<style>
    .detail-row {
        display: none;
    }
    .detail-row.active {
        display: table-row;
    }
    .chevron-icon {
        transition: transform 0.3s ease;
    }
    .chevron-icon.rotated {
        transform: rotate(180deg);
    }
    .highlight-row {
        background-color: #fef3c7 !important;
    }
</style>

<div class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 py-8">
    <div class="max-w-7xl mx-auto px-4 space-y-6">
        
        <!-- Header Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
            <a href="payroll.php" class="inline-flex items-center text-sm text-gray-600 hover:text-indigo-600 mb-4 transition-colors group">
                <i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i>
                Back to Payroll Hub
            </a>
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        <div class="h-12 w-12 bg-indigo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-tasks text-indigo-600 text-xl"></i>
                        </div>
                        Review & Approve Payroll
                    </h1>
                    <p class="mt-2 text-gray-600">
                        Review the generated payroll for <strong class="text-indigo-600"><?php echo date('F Y', strtotime($payPeriodEnd)); ?></strong>
                    </p>
                </div>
                <div class="bg-indigo-50 px-6 py-3 rounded-xl border-2 border-indigo-200">
                    <p class="text-sm text-indigo-600 font-semibold">Total Employees</p>
                    <p class="text-3xl font-bold text-indigo-900"><?php echo $employeeCount; ?></p>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-6 text-white shadow-xl transform hover:scale-105 transition-all">
                <div class="flex items-center justify-between mb-4">
                    <div class="h-14 w-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-3xl"></i>
                    </div>
                </div>
                <p class="font-semibold text-blue-100 mb-2">Pay Period</p>
                <p class="text-2xl font-bold"><?php echo date('M d', strtotime($payPeriodStart)) . ' - ' . date('M d, Y', strtotime($payPeriodEnd)); ?></p>
            </div>

            <div class="bg-gradient-to-br from-green-500 to-teal-600 rounded-2xl p-6 text-white shadow-xl transform hover:scale-105 transition-all">
                <div class="flex items-center justify-between mb-4">
                    <div class="h-14 w-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-3xl"></i>
                    </div>
                </div>
                <p class="font-semibold text-green-100 mb-2">Total Gross Salary</p>
                <p class="text-3xl font-bold" id="summary-gross">৳<?php echo number_format($totalGross, 2); ?></p>
            </div>

            <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-6 text-white shadow-xl transform hover:scale-105 transition-all">
                <div class="flex items-center justify-between mb-4">
                    <div class="h-14 w-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-minus-circle text-3xl"></i>
                    </div>
                </div>
                <p class="font-semibold text-amber-100 mb-2">Total Deductions</p>
                <p class="text-3xl font-bold" id="summary-deductions">৳<?php echo number_format($totalDeductions, 2); ?></p>
            </div>

            <div class="bg-gradient-to-br from-purple-500 to-violet-600 rounded-2xl p-6 text-white shadow-xl transform hover:scale-105 transition-all border-4 border-purple-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="h-14 w-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-hand-holding-usd text-3xl"></i>
                    </div>
                </div>
                <p class="font-semibold text-purple-100 mb-2">Total Net Pay</p>
                <p class="text-3xl font-bold" id="summary-net">৳<?php echo number_format($totalNet, 2); ?></p>
            </div>
        </div>

        <!-- Search and Actions Bar -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
            <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                <div class="flex-1 max-w-md">
                    <div class="relative">
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Search by name, position, or department..." 
                               class="w-full pl-12 pr-4 py-3 rounded-xl border-2 border-gray-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all outline-none"
                               onkeyup="searchPayroll()">
                        <i class="fas fa-search absolute left-4 top-4 text-gray-400 text-lg"></i>
                    </div>
                </div>
                <div class="flex gap-3">
                    <span id="selectedCount" class="text-sm text-gray-600 font-semibold py-3 px-4 bg-gray-100 rounded-lg">
                        <i class="fas fa-check-square mr-2"></i>0 selected
                    </span>
                </div>
            </div>
        </div>

        <!-- Payroll Table -->
        <form id="payrollForm" method="POST">
            <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
                <div class="p-6 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-3">
                                <i class="fas fa-list-alt text-indigo-600"></i>
                                Payroll Details
                            </h2>
                            <p class="text-sm text-gray-600 mt-1">Click on any row to view/edit detailed breakdown</p>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer bg-white px-4 py-2 rounded-lg shadow-sm hover:shadow-md transition-all">
                            <input type="checkbox" 
                                   id="selectAll" 
                                   class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                   onchange="toggleSelectAll()">
                            <span class="font-semibold text-gray-700">Select All</span>
                        </label>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-12 px-4 py-4">
                                    <i class="fas fa-check-circle text-gray-400"></i>
                                </th>
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
                            <?php foreach ($payrollItems as $index => $item): ?>
                                <tr class="hover:bg-indigo-50 transition-colors payroll-row" 
                                    data-employee="<?php echo strtolower($item->first_name . ' ' . $item->last_name); ?>"
                                    data-position="<?php echo strtolower($item->position_name ?? ''); ?>"
                                    data-department="<?php echo strtolower($item->department_name ?? ''); ?>">
                                    <td class="px-4 py-4 text-center">
                                        <input type="checkbox" 
                                               name="selected_payrolls[]" 
                                               value="<?php echo $item->id; ?>"
                                               class="payroll-checkbox w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                               onchange="updateSelectedCount()">
                                    </td>
                                    <td class="px-4 py-4 text-center cursor-pointer" onclick="toggleDetail(<?php echo $index; ?>)">
                                        <i class="fas fa-chevron-down text-gray-400 chevron-icon" id="chevron-<?php echo $index; ?>"></i>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                                <span class="text-indigo-600 font-bold text-sm">
                                                    <?php echo strtoupper(substr($item->first_name, 0, 1) . substr($item->last_name, 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></div>
                                                <div class="text-xs text-gray-500">EMP-<?php echo str_pad($item->employee_id, 4, '0', STR_PAD_LEFT); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item->position_name ?? 'N/A'); ?></span>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($item->department_name ?? 'N/A'); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <span class="text-sm font-semibold text-gray-900" id="gross-<?php echo $index; ?>">৳<?php echo number_format($item->gross_salary, 2); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <span class="text-sm font-semibold text-red-600" id="deduction-<?php echo $index; ?>">৳<?php echo number_format($item->deductions, 2); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <span class="text-sm font-bold text-green-600" id="net-<?php echo $index; ?>">৳<?php echo number_format($item->net_salary, 2); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <button type="button" 
                                                onclick="toggleDetail(<?php echo $index; ?>)"
                                                class="text-indigo-600 hover:text-indigo-800 font-semibold text-sm">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </button>
                                    </td>
                                </tr>
                                
                                <!-- Detailed Breakdown Row -->
                                <tr class="detail-row" id="detail-<?php echo $index; ?>">
                                    <td colspan="8" class="p-0">
                                        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 p-6 border-l-4 border-indigo-500">
                                            <div class="flex items-center justify-between mb-4">
                                                <h4 class="font-bold text-indigo-900 text-lg flex items-center gap-2">
                                                    <i class="fas fa-info-circle"></i>
                                                    Salary Breakdown for <?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?>
                                                </h4>
                                                <button type="button" 
                                                        onclick="toggleEditMode(<?php echo $index; ?>)"
                                                        id="editBtn-<?php echo $index; ?>"
                                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors">
                                                    <i class="fas fa-edit mr-2"></i>Edit Breakdown
                                                </button>
                                            </div>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <!-- Earnings -->
                                                <div class="bg-white rounded-xl p-5 shadow-sm">
                                                    <h5 class="font-bold text-green-700 mb-4 flex items-center gap-2 text-lg">
                                                        <div class="h-8 w-8 bg-green-100 rounded-lg flex items-center justify-center">
                                                            <i class="fas fa-plus-circle text-green-600"></i>
                                                        </div>
                                                        Earnings
                                                    </h5>
                                                    <div class="space-y-3">
                                                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                                            <span class="text-sm font-medium text-gray-600">Basic Salary</span>
                                                            <div class="view-mode-<?php echo $index; ?>">
                                                                <span class="font-semibold text-gray-900">৳<?php echo number_format($item->basic_salary ?? 0, 2); ?></span>
                                                            </div>
                                                            <div class="edit-mode-<?php echo $index; ?> hidden">
                                                                <input type="number" 
                                                                       step="0.01" 
                                                                       value="<?php echo $item->basic_salary ?? 0; ?>"
                                                                       id="basic-<?php echo $index; ?>"
                                                                       class="w-32 px-3 py-1 border-2 border-gray-300 rounded-lg text-right font-semibold"
                                                                       onchange="calculateTotal(<?php echo $index; ?>)">
                                                            </div>
                                                        </div>
                                                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                                            <span class="text-sm font-medium text-gray-600">House Allowance</span>
                                                            <div class="view-mode-<?php echo $index; ?>">
                                                                <span class="font-semibold text-gray-900">৳<?php echo number_format($item->house_allowance ?? 0, 2); ?></span>
                                                            </div>
                                                            <div class="edit-mode-<?php echo $index; ?> hidden">
                                                                <input type="number" 
                                                                       step="0.01" 
                                                                       value="<?php echo $item->house_allowance ?? 0; ?>"
                                                                       id="house-<?php echo $index; ?>"
                                                                       class="w-32 px-3 py-1 border-2 border-gray-300 rounded-lg text-right font-semibold"
                                                                       onchange="calculateTotal(<?php echo $index; ?>)">
                                                            </div>
                                                        </div>
                                                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                                            <span class="text-sm font-medium text-gray-600">Transport Allowance</span>
                                                            <div class="view-mode-<?php echo $index; ?>">
                                                                <span class="font-semibold text-gray-900">৳<?php echo number_format($item->transport_allowance ?? 0, 2); ?></span>
                                                            </div>
                                                            <div class="edit-mode-<?php echo $index; ?> hidden">
                                                                <input type="number" 
                                                                       step="0.01" 
                                                                       value="<?php echo $item->transport_allowance ?? 0; ?>"
                                                                       id="transport-<?php echo $index; ?>"
                                                                       class="w-32 px-3 py-1 border-2 border-gray-300 rounded-lg text-right font-semibold"
                                                                       onchange="calculateTotal(<?php echo $index; ?>)">
                                                            </div>
                                                        </div>
                                                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                                            <span class="text-sm font-medium text-gray-600">Medical Allowance</span>
                                                            <div class="view-mode-<?php echo $index; ?>">
                                                                <span class="font-semibold text-gray-900">৳<?php echo number_format($item->medical_allowance ?? 0, 2); ?></span>
                                                            </div>
                                                            <div class="edit-mode-<?php echo $index; ?> hidden">
                                                                <input type="number" 
                                                                       step="0.01" 
                                                                       value="<?php echo $item->medical_allowance ?? 0; ?>"
                                                                       id="medical-<?php echo $index; ?>"
                                                                       class="w-32 px-3 py-1 border-2 border-gray-300 rounded-lg text-right font-semibold"
                                                                       onchange="calculateTotal(<?php echo $index; ?>)">
                                                            </div>
                                                        </div>
                                                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                                            <span class="text-sm font-medium text-gray-600">Other Allowances</span>
                                                            <div class="view-mode-<?php echo $index; ?>">
                                                                <span class="font-semibold text-gray-900">৳<?php echo number_format($item->other_allowances ?? 0, 2); ?></span>
                                                            </div>
                                                            <div class="edit-mode-<?php echo $index; ?> hidden">
                                                                <input type="number" 
                                                                       step="0.01" 
                                                                       value="<?php echo $item->other_allowances ?? 0; ?>"
                                                                       id="other-allowance-<?php echo $index; ?>"
                                                                       class="w-32 px-3 py-1 border-2 border-gray-300 rounded-lg text-right font-semibold"
                                                                       onchange="calculateTotal(<?php echo $index; ?>)">
                                                            </div>
                                                        </div>
                                                        <div class="flex justify-between items-center py-3 bg-green-50 px-3 rounded-lg mt-3">
                                                            <span class="font-bold text-green-700">Total Earnings</span>
                                                            <span class="font-bold text-green-700 text-lg" id="total-earnings-<?php echo $index; ?>">৳<?php echo number_format($item->gross_salary, 2); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Deductions -->
                                                <div class="bg-white rounded-xl p-5 shadow-sm">
                                                    <h5 class="font-bold text-red-700 mb-4 flex items-center gap-2 text-lg">
                                                        <div class="h-8 w-8 bg-red-100 rounded-lg flex items-center justify-center">
                                                            <i class="fas fa-minus-circle text-red-600"></i>
                                                        </div>
                                                        Deductions
                                                    </h5>
                                                    <div class="space-y-3">
                                                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                                            <span class="text-sm font-medium text-gray-600">Provident Fund</span>
                                                            <div class="view-mode-<?php echo $index; ?>">
                                                                <span class="font-semibold text-gray-900">৳<?php echo number_format($item->provident_fund ?? 0, 2); ?></span>
                                                            </div>
                                                            <div class="edit-mode-<?php echo $index; ?> hidden">
                                                                <input type="number" 
                                                                       step="0.01" 
                                                                       value="<?php echo $item->provident_fund ?? 0; ?>"
                                                                       id="provident-<?php echo $index; ?>"
                                                                       class="w-32 px-3 py-1 border-2 border-gray-300 rounded-lg text-right font-semibold"
                                                                       onchange="calculateTotal(<?php echo $index; ?>)">
                                                            </div>
                                                        </div>
                                                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                                            <span class="text-sm font-medium text-gray-600">Tax Deduction</span>
                                                            <div class="view-mode-<?php echo $index; ?>">
                                                                <span class="font-semibold text-gray-900">৳<?php echo number_format($item->tax_deduction ?? 0, 2); ?></span>
                                                            </div>
                                                            <div class="edit-mode-<?php echo $index; ?> hidden">
                                                                <input type="number" 
                                                                       step="0.01" 
                                                                       value="<?php echo $item->tax_deduction ?? 0; ?>"
                                                                       id="tax-<?php echo $index; ?>"
                                                                       class="w-32 px-3 py-1 border-2 border-gray-300 rounded-lg text-right font-semibold"
                                                                       onchange="calculateTotal(<?php echo $index; ?>)">
                                                            </div>
                                                        </div>
                                                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                                            <span class="text-sm font-medium text-gray-600">Other Deductions</span>
                                                            <div class="view-mode-<?php echo $index; ?>">
                                                                <span class="font-semibold text-gray-900">৳<?php echo number_format($item->other_deductions ?? 0, 2); ?></span>
                                                            </div>
                                                            <div class="edit-mode-<?php echo $index; ?> hidden">
                                                                <input type="number" 
                                                                       step="0.01" 
                                                                       value="<?php echo $item->other_deductions ?? 0; ?>"
                                                                       id="other-deduction-<?php echo $index; ?>"
                                                                       class="w-32 px-3 py-1 border-2 border-gray-300 rounded-lg text-right font-semibold"
                                                                       onchange="calculateTotal(<?php echo $index; ?>)">
                                                            </div>
                                                        </div>
                                                        <div class="flex justify-between items-center py-3 bg-red-50 px-3 rounded-lg mt-3">
                                                            <span class="font-bold text-red-700">Total Deductions</span>
 <span class="font-bold text-red-700 text-lg" id="total-deductions-<?php echo $index; ?>">৳<?php echo number_format($item->deductions, 2); ?></span>
                                                        </div>
                                                        <div class="flex justify-between items-center py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white px-3 rounded-lg mt-4 shadow-lg">
                                                            <span class="font-bold text-lg">NET SALARY</span>
                                                            <span class="font-bold text-2xl" id="net-salary-<?php echo $index; ?>">৳<?php echo number_format($item->net_salary, 2); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Save/Cancel Buttons (Hidden by default) -->
                                            <div class="edit-mode-<?php echo $index; ?> hidden mt-6 flex justify-end gap-3">
                                                <button type="button" 
                                                        onclick="cancelEdit(<?php echo $index; ?>)"
                                                        class="px-6 py-3 bg-gray-500 text-white rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                                                    <i class="fas fa-times mr-2"></i>Cancel
                                                </button>
                                                <button type="button" 
                                                        onclick="savePayrollEdit(<?php echo $index; ?>, <?php echo $item->id; ?>)"
                                                        class="px-6 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors">
                                                    <i class="fas fa-save mr-2"></i>Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-200 mt-6">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-exclamation-triangle text-amber-500 mr-2"></i>
                        Once approved, selected payroll entries cannot be edited or deleted.
                    </div>
                    <div class="flex gap-4">
                        <button type="submit" 
                                name="reject_selected"
                                onclick="return confirmRejectSelected()"
                                class="px-8 py-4 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl font-bold hover:from-red-600 hover:to-red-700 transition-all shadow-lg hover:shadow-xl transform hover:scale-105 flex items-center gap-3">
                            <i class="fas fa-times-circle text-xl"></i>
                            Reject Selected
                        </button>
                        
                        <button type="submit" 
                                name="approve_selected"
                                onclick="return confirmApproveSelected()"
                                class="px-8 py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl font-bold hover:from-green-600 hover:to-emerald-700 transition-all shadow-lg hover:shadow-xl transform hover:scale-105 flex items-center gap-3">
                            <i class="fas fa-check-circle text-xl"></i>
                            Approve Selected
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle detail row
function toggleDetail(index) {
    const detailRow = document.getElementById('detail-' + index);
    const chevron = document.getElementById('chevron-' + index);
    
    if (detailRow.classList.contains('active')) {
        detailRow.classList.remove('active');
        chevron.classList.remove('rotated');
    } else {
        detailRow.classList.add('active');
        chevron.classList.add('rotated');
    }
}

// Toggle edit mode
function toggleEditMode(index) {
    const viewElements = document.querySelectorAll('.view-mode-' + index);
    const editElements = document.querySelectorAll('.edit-mode-' + index);
    const editBtn = document.getElementById('editBtn-' + index);
    
    if (editElements[0].classList.contains('hidden')) {
        // Switch to edit mode
        viewElements.forEach(el => el.classList.add('hidden'));
        editElements.forEach(el => el.classList.remove('hidden'));
        editBtn.innerHTML = '<i class="fas fa-eye mr-2"></i>View Mode';
        editBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
        editBtn.classList.add('bg-gray-600', 'hover:bg-gray-700');
    } else {
        // Switch to view mode
        viewElements.forEach(el => el.classList.remove('hidden'));
        editElements.forEach(el => el.classList.add('hidden'));
        editBtn.innerHTML = '<i class="fas fa-edit mr-2"></i>Edit Breakdown';
        editBtn.classList.remove('bg-gray-600', 'hover:bg-gray-700');
        editBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
    }
}

// Cancel edit
function cancelEdit(index) {
    toggleEditMode(index);
    // Reset values to original
    location.reload();
}

// Calculate totals in real-time
function calculateTotal(index) {
    // Get earnings
    const basic = parseFloat(document.getElementById('basic-' + index).value) || 0;
    const house = parseFloat(document.getElementById('house-' + index).value) || 0;
    const transport = parseFloat(document.getElementById('transport-' + index).value) || 0;
    const medical = parseFloat(document.getElementById('medical-' + index).value) || 0;
    const otherAllowance = parseFloat(document.getElementById('other-allowance-' + index).value) || 0;
    
    // Get deductions
    const provident = parseFloat(document.getElementById('provident-' + index).value) || 0;
    const tax = parseFloat(document.getElementById('tax-' + index).value) || 0;
    const otherDeduction = parseFloat(document.getElementById('other-deduction-' + index).value) || 0;
    
    // Calculate totals
    const totalEarnings = basic + house + transport + medical + otherAllowance;
    const totalDeductions = provident + tax + otherDeduction;
    const netSalary = totalEarnings - totalDeductions;
    
    // Update display
    document.getElementById('total-earnings-' + index).textContent = '৳' + totalEarnings.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    document.getElementById('total-deductions-' + index).textContent = '৳' + totalDeductions.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    document.getElementById('net-salary-' + index).textContent = '৳' + netSalary.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Save payroll edit
function savePayrollEdit(index, payrollId) {
    const grossSalary = parseFloat(document.getElementById('total-earnings-' + index).textContent.replace(/[৳,]/g, ''));
    const deductions = parseFloat(document.getElementById('total-deductions-' + index).textContent.replace(/[৳,]/g, ''));
    const netSalary = parseFloat(document.getElementById('net-salary-' + index).textContent.replace(/[৳,]/g, ''));
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="update_payroll" value="1">
        <input type="hidden" name="payroll_id" value="${payrollId}">
        <input type="hidden" name="gross_salary" value="${grossSalary}">
        <input type="hidden" name="deductions" value="${deductions}">
        <input type="hidden" name="net_salary" value="${netSalary}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Search functionality
function searchPayroll() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.payroll-row');
    
    rows.forEach(row => {
        const employee = row.dataset.employee;
        const position = row.dataset.position;
        const department = row.dataset.department;
        
        if (employee.includes(searchTerm) || position.includes(searchTerm) || department.includes(searchTerm)) {
            row.style.display = '';
            // Also show/hide the detail row if it exists
            const index = Array.from(rows).indexOf(row);
            const detailRow = document.getElementById('detail-' + index);
            if (detailRow && detailRow.classList.contains('active')) {
                detailRow.style.display = '';
            }
        } else {
            row.style.display = 'none';
            // Hide detail row too
            const index = Array.from(rows).indexOf(row);
            const detailRow = document.getElementById('detail-' + index);
            if (detailRow) {
                detailRow.style.display = 'none';
            }
        }
    });
}

// Select all functionality
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.payroll-checkbox');
    
    checkboxes.forEach(checkbox => {
        // Only select visible checkboxes
        const row = checkbox.closest('.payroll-row');
        if (row.style.display !== 'none') {
            checkbox.checked = selectAll.checked;
        }
    });
    
    updateSelectedCount();
}

// Update selected count
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.payroll-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').innerHTML = `<i class="fas fa-check-square mr-2"></i>${count} selected`;
    
    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.payroll-checkbox');
    const visibleCheckboxes = Array.from(allCheckboxes).filter(cb => cb.closest('.payroll-row').style.display !== 'none');
    const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
    document.getElementById('selectAll').checked = allVisibleChecked;
    
    // Update summary if editing
    updateSummaryTotals();
}

// Update summary totals based on selected items
function updateSummaryTotals() {
    const checkboxes = document.querySelectorAll('.payroll-checkbox:checked');
    let totalGross = 0;
    let totalDeductions = 0;
    let totalNet = 0;
    
    checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const index = Array.from(document.querySelectorAll('.payroll-row')).indexOf(row);
        
        const grossText = document.getElementById('gross-' + index).textContent.replace(/[৳,]/g, '');
        const deductionText = document.getElementById('deduction-' + index).textContent.replace(/[৳,]/g, '');
        const netText = document.getElementById('net-' + index).textContent.replace(/[৳,]/g, '');
        
        totalGross += parseFloat(grossText) || 0;
        totalDeductions += parseFloat(deductionText) || 0;
        totalNet += parseFloat(netText) || 0;
    });
    
    if (checkboxes.length > 0) {
        document.getElementById('summary-gross').textContent = '৳' + totalGross.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        document.getElementById('summary-deductions').textContent = '৳' + totalDeductions.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        document.getElementById('summary-net').textContent = '৳' + totalNet.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
}

// Confirmation dialogs
function confirmApproveSelected() {
    const checkboxes = document.querySelectorAll('.payroll-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Please select at least one payroll entry to approve.');
        return false;
    }
    
    const count = checkboxes.length;
    const netTotal = document.getElementById('summary-net').textContent;
    
    return confirm(
        '✓ Approve ' + count + ' selected payroll entries?\n\n' +
        'Total Payout: ' + netTotal + '\n\n' +
        'Once approved, these entries will be locked and cannot be modified.'
    );
}

function confirmRejectSelected() {
    const checkboxes = document.querySelectorAll('.payroll-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Please select at least one payroll entry to reject.');
        return false;
    }
    
    const count = checkboxes.length;
    
    return confirm(
        '⚠️ Are you sure you want to REJECT and DELETE ' + count + ' selected payroll entries?\n\n' +
        'This action CANNOT be undone!'
    );
}

// Highlight selected rows
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.payroll-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            if (this.checked) {
                row.classList.add('highlight-row');
            } else {
                row.classList.remove('highlight-row');
            }
        });
    });
});
</script>

<?php include_once '../templates/footer.php'; ?>                                                           
                                                            