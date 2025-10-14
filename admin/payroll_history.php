<?php
// new_ufmhrm/admin/payroll_history.php (Fajracct Style)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Payroll History - ' . APP_NAME;
include_once '../templates/header.php';

// --- FILTERING AND PAGINATION LOGIC ---
$searchTerm = $_GET['search'] ?? '';
$filterMonth = $_GET['month'] ?? '';
$filterYear = $_GET['year'] ?? '';

$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$whereClauses = ["p.status IN ('approved', 'disbursed', 'paid')"];
$params = [];

if (!empty($searchTerm)) {
    $whereClauses[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR CONCAT(e.first_name, ' ', e.last_name) LIKE ?)";
    $searchTermWildcard = '%' . $searchTerm . '%';
    array_push($params, $searchTermWildcard, $searchTermWildcard, $searchTermWildcard);
}

if (!empty($filterMonth)) {
    $whereClauses[] = "MONTH(p.pay_period_end) = ?";
    $params[] = $filterMonth;
}

if (!empty($filterYear)) {
    $whereClauses[] = "YEAR(p.pay_period_end) = ?";
    $params[] = $filterYear;
}

$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

$countSql = "SELECT COUNT(p.id) as total FROM payrolls p JOIN employees e ON p.employee_id = e.id $whereSql";
$totalRecords = $db->query($countSql, $params)->first()->total;
$totalPages = ceil($totalRecords / $limit);

$historySql = "
    SELECT 
        p.id, p.employee_id, p.pay_period_start, p.pay_period_end, p.gross_salary,
        p.deductions, p.net_salary, p.status,
        e.first_name, e.last_name, 
        pos.name as position_name, d.name as department_name,
        pd.basic_salary, pd.house_allowance, pd.transport_allowance, pd.medical_allowance,
        pd.other_allowances, pd.absence_deduction, pd.salary_advance_deduction,
        pd.loan_installment_deduction, pd.provident_fund, pd.tax_deduction,
        pd.other_deductions, pd.total_deductions, pd.days_in_month, pd.absent_days
    FROM payrolls p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN payroll_details pd ON p.id = pd.payroll_id
    LEFT JOIN positions pos ON e.position_id = pos.id
    LEFT JOIN departments d ON pos.department_id = d.id
    $whereSql
    ORDER BY p.pay_period_end DESC, e.first_name
    LIMIT $limit OFFSET $offset
";
$payrollHistory = $db->query($historySql, $params)->results();

// Set defaults for records without payroll_details
foreach ($payrollHistory as $item) {
    $item->basic_salary = $item->basic_salary ?? 0;
    $item->house_allowance = $item->house_allowance ?? 0;
    $item->transport_allowance = $item->transport_allowance ?? 0;
    $item->medical_allowance = $item->medical_allowance ?? 0;
    $item->other_allowances = $item->other_allowances ?? 0;
    $item->absence_deduction = $item->absence_deduction ?? 0;
    $item->salary_advance_deduction = $item->salary_advance_deduction ?? 0;
    $item->loan_installment_deduction = $item->loan_installment_deduction ?? 0;
    $item->provident_fund = $item->provident_fund ?? 0;
    $item->tax_deduction = $item->tax_deduction ?? 0;
    $item->other_deductions = $item->other_deductions ?? 0;
    $item->total_deductions = $item->total_deductions ?? $item->deductions;
    $item->days_in_month = $item->days_in_month ?? date('t', strtotime($item->pay_period_end));
    $item->absent_days = $item->absent_days ?? 0;
}
?>

<style>
    .detail-row { display: none; }
    .detail-row.active { display: table-row; background-color: #f8fafc; }
    .chevron-icon { transition: transform 0.3s ease; }
    .chevron-icon.rotated { transform: rotate(180deg); }
</style>

<div class="min-h-screen bg-gradient-to-br from-primary-50 via-blue-50 to-purple-50 py-8">
    <div class="max-w-7xl mx-auto px-4 space-y-6">
        
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
            <a href="payroll.php" class="inline-flex items-center text-sm text-gray-600 hover:text-primary-600 mb-4 transition-colors group">
                <i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i>
                Back to Payroll Hub
            </a>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                <div class="h-12 w-12 bg-primary-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-history text-primary-600 text-xl"></i>
                </div>
                Payroll History
            </h1>
            <p class="mt-2 text-gray-600">Search and review all previously processed payroll records.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div class="md:col-span-2">
                    <label for="search" class="block text-sm font-semibold text-gray-700 mb-2">Search Employee</label>
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                           placeholder="Enter employee name..." 
                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary-500 focus:ring-4 focus:ring-primary-100 transition-all outline-none">
                </div>
                <div>
                    <label for="month" class="block text-sm font-semibold text-gray-700 mb-2">Month</label>
                    <select name="month" id="month" 
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary-500 focus:ring-4 focus:ring-primary-100 transition-all outline-none">
                        <option value="">All Months</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo ($filterMonth == $m) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="year" class="block text-sm font-semibold text-gray-700 mb-2">Year</label>
                    <select name="year" id="year" 
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary-500 focus:ring-4 focus:ring-primary-100 transition-all outline-none">
                        <option value="">All Years</option>
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($filterYear == $y) ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <a href="payroll_history.php" 
                       class="px-6 py-3 bg-gray-500 text-white rounded-xl font-semibold hover:bg-gray-600 transition-colors">
                        <i class="fas fa-redo mr-2"></i>Clear
                    </a>
                    <button type="submit" 
                            class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-xl font-semibold hover:from-primary-600 hover:to-primary-700 transition-all flex items-center gap-2">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gradient-to-r from-primary-50 to-blue-50">
                        <tr>
                            <th class="w-12 px-4 py-4"></th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Employee</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Pay Period</th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Gross Salary</th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Deductions</th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Net Salary</th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($payrollHistory)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-12 text-gray-500">
                                    <i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i>
                                    <p class="text-lg">No payroll history found for the selected filters.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($payrollHistory as $item): ?>
                            <tr class="hover:bg-primary-50 transition-colors">
                                <td class="px-4 py-4 text-center cursor-pointer" onclick="toggleDetail(<?php echo $item->id; ?>)">
                                    <i class="fas fa-chevron-down text-gray-400 chevron-icon" id="chevron-<?php echo $item->id; ?>"></i>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 bg-primary-100 rounded-full flex items-center justify-center">
                                            <span class="text-primary-600 font-bold text-sm">
                                                <?php echo strtoupper(substr($item->first_name, 0, 1) . substr($item->last_name, 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($item->position_name ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo date('F Y', strtotime($item->pay_period_end)); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo date('M d', strtotime($item->pay_period_start)) . ' - ' . date('M d, Y', strtotime($item->pay_period_end)); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900">
                                    ৳<?php echo number_format($item->gross_salary, 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-red-600">
                                    ৳<?php echo number_format($item->total_deductions, 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-green-600">
                                    ৳<?php echo number_format($item->net_salary, 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <a href="payslip.php?id=<?php echo $item->id; ?>" target="_blank" 
                                       class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-semibold">
                                        <i class="fas fa-print mr-2"></i> Payslip
                                    </a>
                                </td>
                            </tr>
                            
                            <tr class="detail-row" id="detail-<?php echo $item->id; ?>">
                                <td colspan="7" class="p-0">
                                    <div class="bg-gradient-to-r from-primary-50 to-blue-50 p-6 border-l-4 border-primary-500">
                                        <h4 class="font-bold text-primary-900 text-lg mb-4">Salary Breakdown</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div class="bg-white rounded-xl p-5 shadow-sm border-2 border-green-200">
                                                <h5 class="font-bold text-green-700 mb-3 text-lg"><i class="fas fa-plus-circle mr-2"></i>Earnings</h5>
                                                <div class="space-y-2 text-sm border-t pt-2">
                                                    <div class="flex justify-between py-1"><span class="text-gray-700">Basic Salary:</span><span class="font-semibold text-gray-900">৳<?php echo number_format($item->basic_salary, 2); ?></span></div>
                                                    <div class="flex justify-between py-1"><span class="text-gray-700">House Allowance:</span><span class="font-semibold text-gray-900">৳<?php echo number_format($item->house_allowance, 2); ?></span></div>
                                                    <div class="flex justify-between py-1"><span class="text-gray-700">Transport Allowance:</span><span class="font-semibold text-gray-900">৳<?php echo number_format($item->transport_allowance, 2); ?></span></div>
                                                    <div class="flex justify-between py-1"><span class="text-gray-700">Medical Allowance:</span><span class="font-semibold text-gray-900">৳<?php echo number_format($item->medical_allowance, 2); ?></span></div>
                                                    <div class="flex justify-between py-1"><span class="text-gray-700">Other Allowances:</span><span class="font-semibold text-gray-900">৳<?php echo number_format($item->other_allowances, 2); ?></span></div>
                                                    <div class="flex justify-between font-bold bg-green-50 px-3 py-3 rounded-lg mt-2"><span>Gross Salary:</span><span>৳<?php echo number_format($item->gross_salary, 2); ?></span></div>
                                                </div>
                                            </div>
                                            <div class="bg-white rounded-xl p-5 shadow-sm border-2 border-red-200">
                                                <h5 class="font-bold text-red-700 mb-3 text-lg"><i class="fas fa-minus-circle mr-2"></i>Deductions</h5>
                                                <div class="space-y-2 text-sm border-t pt-2">
                                                    <div class="flex justify-between py-1"><span class="text-gray-700">Absence Deduction:</span><span class="font-semibold text-gray-900">৳<?php echo number_format($item->absence_deduction, 2); ?></span></div>
                                                    <div class="flex justify-between py-1"><span class="text-gray-700">Salary Advance:</span><span class="font-semibold text-gray-900">৳<?php echo number_format($item->salary_advance_deduction, 2); ?></span></div>
                                                    <div class="flex justify-between py-1"><span class="text-gray-700">Loan Installment:</span><span class="font-semibold text-gray-900">৳<?php echo number_format($item->loan_installment_deduction, 2); ?></span></div>
                                                    <div class="flex justify-between py-1"><span class="text-gray-700">Provident Fund:</span><span class="font-semibold text-gray-900">৳<?php echo number_format($item->provident_fund, 2); ?></span></div>
                                                    <div class="flex justify-between py-1"><span class="text-gray-700">Tax Deduction:</span><span class="font-semibold text-gray-900">৳<?php echo number_format($item->tax_deduction, 2); ?></span></div>
                                                    <div class="flex justify-between py-1"><span class="text-gray-700">Other Deductions:</span><span class="font-semibold text-gray-900">৳<?php echo number_format($item->other_deductions, 2); ?></span></div>
                                                    <div class="flex justify-between font-bold bg-red-50 px-3 py-3 rounded-lg mt-2"><span>Total Deductions:</span><span>৳<?php echo number_format($item->total_deductions, 2); ?></span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="p-6 bg-gray-50 border-t flex items-center justify-between">
                <span class="text-sm text-gray-700">Showing <span class="font-semibold"><?php echo $offset + 1; ?></span> to <span class="font-semibold"><?php echo min($offset + $limit, $totalRecords); ?></span> of <span class="font-semibold"><?php echo $totalRecords; ?></span> results</span>
                <div class="inline-flex rounded-lg shadow-sm">
                    <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&month=<?php echo $filterMonth; ?>&year=<?php echo $filterYear; ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-50"><i class="fas fa-chevron-left mr-1"></i> Previous</a><?php endif; ?>
                    <span class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border-t border-b border-gray-300">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&month=<?php echo $filterMonth; ?>&year=<?php echo $filterYear; ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-50">Next <i class="fas fa-chevron-right ml-1"></i></a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleDetail(payrollId) {
    const detailRow = document.getElementById('detail-' + payrollId);
    const chevron = document.getElementById('chevron-' + payrollId);
    if (detailRow && chevron) {
        detailRow.classList.toggle('active');
        chevron.classList.toggle('rotated');
    }
}
</script>

<?php include_once '../templates/footer.php'; ?>