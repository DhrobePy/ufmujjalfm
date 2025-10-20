<?php
// new_ufmhrm/admin/payroll_reports.php (Final Version with Exports)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Payroll Reports - ' . APP_NAME;
include_once '../templates/header.php';

// --- DATA FETCHING FOR REPORTS ---
$currentYear = date('Y');
$currentMonth = date('m');

// 1. High-Level Summary KPIs
$ytdDisbursed = $db->query("SELECT SUM(net_salary) as total FROM payrolls WHERE status = 'paid' AND YEAR(pay_period_end) = ?", [$currentYear])->first()->total ?? 0;
$thisMonthPaidCount = $db->query("SELECT COUNT(id) as count FROM payrolls WHERE status = 'paid' AND YEAR(pay_period_end) = ? AND MONTH(pay_period_end) = ?", [$currentYear, $currentMonth])->first()->count ?? 0;
$thisMonthAvgSalary = $db->query("SELECT AVG(net_salary) as avg_net FROM payrolls WHERE status = 'paid' AND YEAR(pay_period_end) = ? AND MONTH(pay_period_end) = ?", [$currentYear, $currentMonth])->first()->avg_net ?? 0;
$thisMonthDeductions = $db->query("SELECT SUM(total_deductions) as total_deduct FROM payroll_details pd JOIN payrolls p ON pd.payroll_id = p.id WHERE p.status = 'paid' AND YEAR(p.pay_period_end) = ? AND MONTH(p.pay_period_end) = ?", [$currentYear, $currentMonth])->first()->total_deduct ?? 0;

// 2. Monthly Breakdown Report
$monthlyBreakdown = $db->query("SELECT DATE_FORMAT(p.pay_period_end, '%Y-%m') as payroll_month, COUNT(p.id) as employee_count, SUM(pd.gross_salary) as total_gross, SUM(pd.total_deductions) as total_deductions, SUM(pd.net_salary) as total_net FROM payrolls p JOIN payroll_details pd ON p.id = pd.payroll_id WHERE p.status = 'paid' GROUP BY payroll_month ORDER BY payroll_month DESC")->results();

// 3. Department-wise Report
$filterDeptMonth = $_GET['dept_month'] ?? date('Y-m');
$departmentReport = $db->query("SELECT d.name as department_name, COUNT(p.id) as employee_count, SUM(pd.net_salary) as total_net_salary FROM payrolls p JOIN employees e ON p.employee_id = e.id JOIN positions pos ON e.position_id = pos.id JOIN departments d ON pos.department_id = d.id JOIN payroll_details pd ON p.id = pd.payroll_id WHERE p.status = 'paid' AND DATE_FORMAT(p.pay_period_end, '%Y-%m') = ? GROUP BY d.name ORDER BY total_net_salary DESC", [$filterDeptMonth])->results();

// Get distinct months for filter dropdowns
$distinctMonths = $db->query("SELECT DISTINCT DATE_FORMAT(pay_period_end, '%Y-%m') as month FROM payrolls WHERE status='paid' ORDER BY month DESC")->results();
$selectedMonthForExport = $distinctMonths[0]->month ?? date('Y-m'); // Default to the latest available month
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
        <a href="payroll.php" class="inline-flex items-center text-sm text-gray-600 hover:text-primary-600 mb-4 transition-colors group"><i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i>Back to Payroll Hub</a>
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3"><div class="h-12 w-12 bg-primary-100 rounded-xl flex items-center justify-center"><i class="fas fa-chart-pie text-primary-600 text-xl"></i></div>Payroll Reports</h1>
        <p class="mt-2 text-gray-600">Analyze payroll trends, departmental costs, and financial summaries.</p>
    </div>

    <div id="brief-summary-report">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-200"><p class="text-sm font-semibold text-gray-500">Total Disbursed (<?php echo $currentYear; ?>)</p><p class="text-3xl font-bold text-primary-600 mt-2">৳<?php echo number_format($ytdDisbursed, 2); ?></p></div>
            <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-200"><p class="text-sm font-semibold text-gray-500">Employees Paid (This Month)</p><p class="text-3xl font-bold text-primary-600 mt-2"><?php echo $thisMonthPaidCount; ?></p></div>
            <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-200"><p class="text-sm font-semibold text-gray-500">Average Net Salary (This Month)</p><p class="text-3xl font-bold text-primary-600 mt-2">৳<?php echo number_format($thisMonthAvgSalary, 2); ?></p></div>
            <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-200"><p class="text-sm font-semibold text-gray-500">Total Deductions (This Month)</p><p class="text-3xl font-bold text-red-600 mt-2">৳<?php echo number_format($thisMonthDeductions, 2); ?></p></div>
        </div>
    </div>
    <div class="text-right -mt-2"><button onclick="downloadPdf('brief-summary-report', 'Brief_Summary_Report')" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-semibold hover:bg-red-600 transition-all"><i class="fas fa-file-pdf mr-2"></i>Export Summary PDF</button></div>


    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Download Detailed Excel Reports</h2>
        <form id="export-form" class="flex flex-col md:flex-row items-end gap-4">
            <div>
                <label for="export_month" class="block text-sm font-semibold text-gray-700 mb-2">Select Month for Report</label>
                <select id="export_month" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary-500">
                    <?php foreach ($distinctMonths as $month): ?>
                        <option value="<?php echo $month->month; ?>"><?php echo date("F Y", strtotime($month->month)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="downloadExcel('monthly_detailed')" class="px-6 py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 transition-all flex items-center gap-2"><i class="fas fa-file-excel"></i> Monthly Detailed</button>
                <button type="button" onclick="downloadExcel('address_wise')" class="px-6 py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition-all flex items-center gap-2"><i class="fas fa-map-marker-alt"></i> Address Wise</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
        <div class="p-6 border-b"><h2 class="text-xl font-bold text-gray-900">Monthly Payroll Summary</h2></div>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Month</th><th class="px-6 py-3 text-center">Employees</th><th class="px-6 py-3 text-right">Gross Salary</th><th class="px-6 py-3 text-right">Deductions</th><th class="px-6 py-3 text-right">Net Disbursed</th></tr></thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($monthlyBreakdown as $row): ?>
                <tr class="hover:bg-primary-50">
                    <td class="px-6 py-4 font-semibold text-primary-700"><?php echo date("F Y", strtotime($row->payroll_month)); ?></td>
                    <td class="px-6 py-4 text-center"><?php echo $row->employee_count; ?></td>
                    <td class="px-6 py-4 text-right">৳<?php echo number_format($row->total_gross, 2); ?></td>
                    <td class="px-6 py-4 text-right text-red-600">৳<?php echo number_format($row->total_deductions, 2); ?></td>
                    <td class="px-6 py-4 text-right font-bold text-green-600">৳<?php echo number_format($row->total_net, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-200" id="department-report">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                <h2 class="text-xl font-bold text-gray-900">Department-wise Expenses</h2>
                <form method="GET" class="flex items-center gap-2">
                    <select name="dept_month" onchange="this.form.submit()" class="rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <?php foreach ($distinctMonths as $month): ?>
                            <option value="<?php echo $month->month; ?>" <?php echo ($filterDeptMonth == $month->month) ? 'selected' : ''; ?>><?php echo date("F Y", strtotime($month->month)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Department</th><th class="px-6 py-3 text-center">Employees</th><th class="px-6 py-3 text-right">Total Net Salary</th></tr></thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($departmentReport)): ?>
                        <tr><td colspan="3" class="text-center py-10 text-gray-500">No data for the selected month.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($departmentReport as $row): ?>
                    <tr class="hover:bg-primary-50">
                        <td class="px-6 py-4 font-semibold text-primary-700"><?php echo htmlspecialchars($row->department_name); ?></td>
                        <td class="px-6 py-4 text-center"><?php echo $row->employee_count; ?></td>
                        <td class="px-6 py-4 text-right font-bold text-green-600">৳<?php echo number_format($row->total_net_salary, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-gray-50 border-t text-right">
            <button onclick="downloadPdf('department-report', 'Department_Report_<?php echo $filterDeptMonth; ?>')" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-semibold hover:bg-red-600 transition-all"><i class="fas fa-file-pdf mr-2"></i>Export Department PDF</button>
        </div>
    </div>
</div>

<script>
function downloadPdf(elementId, fileName) {
    const { jsPDF } = window.jspdf;
    const reportElement = document.getElementById(elementId);
    
    html2canvas(reportElement, { scale: 2 }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF({
            orientation: 'landscape',
            unit: 'px',
            format: [canvas.width, canvas.height]
        });
        pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
        pdf.save(fileName + '.pdf');
    });
}

function downloadExcel(reportType) {
    const selectedMonth = document.getElementById('export_month').value;
    if (selectedMonth) {
        window.location.href = `export_handler.php?report_type=${reportType}&month=${selectedMonth}`;
    } else {
        alert('Please select a month for the report.');
    }
}
</script>

<?php include_once '../templates/footer.php'; ?>