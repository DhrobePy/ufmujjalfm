<?php
// new_ufmhrm/accounts/employees.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

// --- SECURITY & PERMISSIONS ---
if (!is_admin_logged_in()) {
    redirect('../auth/login.php');
}

$currentUser = getCurrentUser();
$branch_id = $currentUser['branch_id'];
$branch_account_roles = ['Accounts- Srg', 'Accounts- Rampura'];
$is_branch_accountant = in_array($currentUser['role'], $branch_account_roles);

// --- DATES & FILTERS ---
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');
$daysInMonth = date('t');

$params = [];
$where_clauses = ["e.status = 'active'"];

if ($is_branch_accountant && !empty($branch_id)) {
    $where_clauses[] = "e.branch_id = ?";
    $params[] = $branch_id;
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// --- COMPLEX FINANCIAL DATA QUERY ---
$employeesSql = "
    SELECT 
        e.id, e.first_name, e.last_name, e.address, e.phone, e.base_salary,
        p.name as position_name, 
        d.name as department_name,
        (SELECT IFNULL(ss.house_allowance, 0) + IFNULL(ss.transport_allowance, 0) + IFNULL(ss.medical_allowance, 0) + IFNULL(ss.other_allowances, 0) 
         FROM salary_structures ss WHERE ss.employee_id = e.id ORDER BY ss.created_date DESC LIMIT 1) as total_allowance,
        (SELECT COUNT(*) FROM attendance WHERE employee_id = e.id AND status = 'present' AND `date` BETWEEN ? AND ?) as attended_days,
        (SELECT COUNT(*) FROM attendance WHERE employee_id = e.id AND status = 'absent' AND `date` BETWEEN ? AND ?) as absent_days,
        (SELECT IFNULL(SUM(amount), 0) FROM salary_advances WHERE employee_id = e.id AND status = 'approved' AND advance_date BETWEEN ? AND ?) as salary_advance,
        (SELECT IFNULL(SUM(amount), 0) FROM loans WHERE employee_id = e.id AND status = 'active' AND loan_date BETWEEN ? AND ?) as loan_taken,
        (SELECT IFNULL(SUM(li.amount), 0) 
         FROM loan_installments li 
         JOIN loans l ON li.loan_id = l.id 
         WHERE l.employee_id = e.id AND l.status = 'active' AND li.payment_date BETWEEN ? AND ?) as emi_due
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    $where_sql
    ORDER BY e.first_name ASC
";

$query_params = array_merge(
    [$startOfMonth, $endOfMonth], [$startOfMonth, $endOfMonth], [$startOfMonth, $endOfMonth], 
    [$startOfMonth, $endOfMonth], [$startOfMonth, $endOfMonth], $params
);

$employeesResult = $db->query($employeesSql, $query_params);
$employees = $employeesResult ? $employeesResult->results() : [];

// --- CALCULATE TOTALS FOR SUMMARY CARDS ---
$total_gross_salary = 0;
$total_deductions = 0;
$total_net_payable = 0;

foreach ($employees as $employee) {
    $gross_salary = $employee->base_salary + $employee->total_allowance;
    $daily_rate = $gross_salary > 0 && $daysInMonth > 0 ? $gross_salary / $daysInMonth : 0;
    $absence_deduction = $daily_rate * $employee->absent_days;
    $current_deductions = $absence_deduction + $employee->salary_advance + $employee->emi_due;
    $total_gross_salary += $gross_salary;
    $total_deductions += $current_deductions;
    $total_net_payable += ($gross_salary - $current_deductions);
}

$pageTitle = 'Employee Financials - ' . APP_NAME;

// --- HEADER ---
if ($is_branch_accountant) {
    include_once '../templates/accounts_header.php';
} else {
    include_once '../templates/header.php';
}
?>

<!-- Google Font Import -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body {
        font-family: 'Poppins', sans-serif;
    }
    .table-header-box {
        color: white;
        padding: 12px 16px;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-radius: 8px;
        margin: 4px;
    }
</style>

<div class="space-y-8">
    <!-- Page Header -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-file-invoice-dollar text-primary-600 mr-3"></i>Employee Financial Overview</h1>
        <p class="mt-1 text-sm text-gray-600">
            Financial snapshot for <?php echo date('F, Y'); ?>.
            <?php if ($is_branch_accountant): ?>
                <span class="font-semibold text-primary-700">(Branch: Sirajgonj Mills)</span>
            <?php endif; ?>
        </p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-green-500 to-teal-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex justify-between items-center">
                <div>
                    <p class="font-semibold text-green-100">Total Gross Salary</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($total_gross_salary, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4"><i class="fas fa-arrow-up text-4xl"></i></div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-rose-500 to-pink-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex justify-between items-center">
                 <div>
                    <p class="font-semibold text-rose-100">Total Deductions</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($total_deductions, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4"><i class="fas fa-arrow-down text-4xl"></i></div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex justify-between items-center">
                <div>
                    <p class="font-semibold text-blue-100">Net Payable Salary</p>
                    <p class="text-3xl font-bold mt-2">৳<?php echo number_format($total_net_payable, 2); ?></p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-4"><i class="fas fa-equals text-4xl"></i></div>
            </div>
        </div>
    </div>

    <!-- Financial Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
         <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th><div class="table-header-box bg-gray-500">Employee</div></th>
                        <th><div class="table-header-box bg-green-500">Salary Breakdown</div></th>
                        <th><div class="table-header-box bg-blue-500">Attendance</div></th>
                        <th><div class="table-header-box bg-orange-500">Advances & Loans</div></th>
                        <th><div class="table-header-box bg-indigo-500">Total Payable</div></th>
                        <th><div class="table-header-box bg-gray-400">Actions</div></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $employee): 
                            $gross_salary = $employee->base_salary + $employee->total_allowance;
                            $daily_rate = $gross_salary > 0 && $daysInMonth > 0 ? $gross_salary / $daysInMonth : 0;
                            $absence_deduction = $daily_rate * $employee->absent_days;
                            $total_payable = $gross_salary - $absence_deduction - $employee->salary_advance - $employee->emi_due;
                        ?>
                            <tr class="hover:bg-primary-50 transition-colors duration-200">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <p class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($employee->first_name . ' ' . $employee->last_name); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($employee->position_name ?? 'N/A'); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($employee->department_name ?? 'N/A'); ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><i class="fas fa-phone-alt mr-1"></i><?php echo htmlspecialchars($employee->phone ?? 'N/A'); ?></p>
                                    <p class="text-xs text-gray-500 truncate" style="max-width: 150px;"><i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($employee->address ?? 'N/A'); ?></p>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs">
                                    <p><strong>Base:</strong> <span class="text-gray-700">৳<?php echo number_format($employee->base_salary, 2); ?></span></p>
                                    <p><strong>Allowance:</strong> <span class="text-gray-700">৳<?php echo number_format($employee->total_allowance, 2); ?></span></p>
                                    <p class="font-bold border-t pt-1 mt-1"><strong>Gross:</strong> <span class="text-green-600">৳<?php echo number_format($gross_salary, 2); ?></span></p>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs">
                                    <p><strong>Attended:</strong> <span class="text-blue-600 font-semibold"><?php echo $employee->attended_days; ?> days</span></p>
                                    <p><strong>Absence Ded.:</strong> <span class="text-red-600 font-semibold">৳<?php echo number_format($absence_deduction, 2); ?></span></p>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs">
                                    <p><strong>Advance:</strong> <span class="text-orange-600">৳<?php echo number_format($employee->salary_advance, 2); ?></span></p>
                                    <p><strong>Loan Taken:</strong> <span class="text-orange-600">৳<?php echo number_format($employee->loan_taken, 2); ?></span></p>
                                    <p class="font-semibold"><strong>EMI Due:</strong> <span class="text-red-600">৳<?php echo number_format($employee->emi_due, 2); ?></span></p>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-lg font-bold text-primary-700">
                                    ৳<?php echo number_format($total_payable, 2); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-4">
                                        <a href="employee_profile.php?id=<?php echo $employee->id; ?>" class="text-gray-500 hover:text-primary-600 transition-colors" title="View Profile">
                                            <i class="fas fa-eye text-lg"></i>
                                        </a>
                                        <div x-data="{ open: false }" class="relative">
                                            <button @click="open = !open" class="text-gray-500 hover:text-primary-600 transition-colors" title="More Actions">
                                                <i class="fas fa-ellipsis-v text-lg"></i>
                                            </button>
                                            <div x-show="open" @click.away="open = false" x-transition class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                                                <div class="py-1">
                                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center"><i class="fas fa-file-invoice w-5 mr-3 text-gray-400"></i>Generate Payslip</a>
                                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center"><i class="fas fa-certificate w-5 mr-3 text-gray-400"></i>Salary Certificate</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-16 text-gray-500">
                                <i class="fas fa-users text-4xl mb-3"></i>
                                <p class="text-lg">No active employees found for this branch.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>

