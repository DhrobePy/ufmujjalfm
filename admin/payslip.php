<?php
// new_ufmhrm/admin/payslip.php (Fajracct Style)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// --- Fetch all active employees for the dropdown selector ---
$employees = $db->query("
    SELECT e.id, e.first_name, e.last_name, p.name as position_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.status = 'active'
    ORDER BY e.first_name, e.last_name
")->results();

$payslip_data = null;
$payslip_error = null;
$payroll_id = null;

// Helper function to convert numbers to words (Bangladeshi style)
function number_to_words($number) {
    // This function remains unchanged
    $number = floatval($number);
    $no = floor($number);
    $decimal = round(($number - $no) * 100);
    
    $words = array(
        '0' => '', '1' => 'one', '2' => 'two', '3' => 'three', '4' => 'four',
        '5' => 'five', '6' => 'six', '7' => 'seven', '8' => 'eight', '9' => 'nine',
        '10' => 'ten', '11' => 'eleven', '12' => 'twelve', '13' => 'thirteen',
        '14' => 'fourteen', '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
        '18' => 'eighteen', '19' => 'nineteen', '20' => 'twenty', '30' => 'thirty',
        '40' => 'forty', '50' => 'fifty', '60' => 'sixty', '70' => 'seventy',
        '80' => 'eighty', '90' => 'ninety'
    );
    
    $result = '';
    $num_str = str_pad($no, 9, '0', STR_PAD_LEFT);

    if (substr($num_str, 0, 2) != '00') { $result .= number_to_words_helper(substr($num_str, 0, 2), $words) . ' crore '; }
    if (substr($num_str, 2, 2) != '00') { $result .= number_to_words_helper(substr($num_str, 2, 2), $words) . ' lakh '; }
    if (substr($num_str, 4, 2) != '00') { $result .= number_to_words_helper(substr($num_str, 4, 2), $words) . ' thousand '; }
    if (substr($num_str, 6, 1) != '0') { $result .= $words[substr($num_str, 6, 1)] . ' hundred '; }
    if (substr($num_str, 7, 2) != '00') { $result .= number_to_words_helper(substr($num_str, 7, 2), $words); }
    
    $result = trim($result);
    if ($decimal > 0) { $result .= ' and ' . number_to_words_helper($decimal, $words) . ' paisa'; }
    
    return empty($result) ? 'zero' : ucfirst($result) . ' Taka Only';
}

function number_to_words_helper($num, $words) {
    $num = intval($num);
    if ($num < 21) { return $words[$num]; }
    if ($num < 100) { $tens = floor($num / 10) * 10; $ones = $num % 10; return $words[$tens] . ($ones ? ' ' . $words[$ones] : ''); }
    return '';
}

// Determine if we are generating from a form POST or a GET request from the history page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payslip'])) {
    $employee_id = (int)$_POST['employee_id'];
    $month = str_pad((int)$_POST['month'], 2, '0', STR_PAD_LEFT);
    $year = (int)$_POST['year'];
    
    $end_date = date('Y-m-t', strtotime("$year-$month-01"));

    // Find the payroll ID from the payrolls table
    $stmt_payroll = $db->query(
        "SELECT id FROM payrolls 
         WHERE employee_id = ? 
         AND pay_period_end = ? 
         AND status IN ('approved', 'disbursed', 'paid') 
         ORDER BY id DESC 
         LIMIT 1", 
        [$employee_id, $end_date]
    );
    
    if ($stmt_payroll && $stmt_payroll->count()) {
        $payroll_id = $stmt_payroll->first()->id;
    } else {
        $payslip_error = "No approved/paid payroll record found for the selected employee and period. Please ensure payroll has been generated and approved.";
    }

} elseif (isset($_GET['id'])) {
    $payroll_id = (int)$_GET['id'];
}

// If we have a valid payroll ID, fetch the complete historical snapshot
if ($payroll_id && !$payslip_error) {
    $sql = "
        SELECT 
            p.id as payroll_id, p.employee_id, p.pay_period_start, p.pay_period_end,
            p.gross_salary as p_gross_salary, p.deductions as p_deductions, p.net_salary as p_net_salary, p.status,
            e.first_name, e.last_name, e.email, e.phone,
            pos.name as position_name, d.name as department_name,
            pd.basic_salary, pd.house_allowance, pd.transport_allowance, pd.medical_allowance, pd.other_allowances,
            pd.gross_salary, pd.days_in_month, pd.absent_days, pd.daily_rate, pd.absence_deduction,
            pd.salary_advance_deduction, pd.loan_installment_deduction, pd.provident_fund,
            pd.tax_deduction, pd.other_deductions, pd.total_deductions, pd.net_salary
        FROM payrolls p
        JOIN employees e ON p.employee_id = e.id
        LEFT JOIN payroll_details pd ON p.id = pd.payroll_id
        LEFT JOIN positions pos ON e.position_id = pos.id
        LEFT JOIN departments d ON pos.department_id = d.id
        WHERE p.id = ? 
        AND p.status IN ('approved', 'disbursed', 'paid')
    ";
    
    $payslipResult = $db->query($sql, [$payroll_id]);

    if ($payslipResult && $payslipResult->count() > 0) {
        $payslip_data = $payslipResult->first();
        
        if (is_null($payslip_data->basic_salary)) {
            $payslip_error = "Could not find the detailed payroll snapshot for this record. It may be an older record without details.";
            $payslip_data = null;
        } else {
            $payslip_data->present_days = $payslip_data->days_in_month - $payslip_data->absent_days;
        }
        
    } else {
        $payslip_error = "The requested payroll record was not found or has not been approved yet.";
    }
}

$page_title = 'Payslip Generation';
include __DIR__ . '/../templates/header.php';
?>

<style>
@media print {
    .no-print { display: none !important; }
    .payslip-card { border: none !important; box-shadow: none !important; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
.payslip-card { page-break-inside: avoid; }
</style>

<div class="min-h-screen bg-gradient-to-br from-primary-50 via-blue-50 to-purple-50 py-8">
    <div class="max-w-5xl mx-auto px-4 space-y-6">
        
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 no-print">
            <a href="payroll.php" class="inline-flex items-center text-sm text-gray-600 hover:text-primary-600 mb-4 transition-colors group">
                <i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i>
                Back to Payroll Hub
            </a>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                <div class="h-12 w-12 bg-primary-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-file-invoice-dollar text-primary-600 text-xl"></i>
                </div>
                Payslip Generation
            </h1>
            <p class="mt-2 text-gray-600">Select an employee and pay period to view or print their payslip.</p>
        </div>

        <?php if ($payslip_error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-6 rounded-xl shadow-sm no-print" role="alert">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle text-red-500 text-2xl mr-3"></i>
                    <div>
                        <p class="font-bold text-lg">Error</p>
                        <p class="mt-1"><?php echo htmlspecialchars($payslip_error); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 no-print">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Select Payslip Details</h2>
            <form action="payslip.php" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div class="md:col-span-2">
                    <label for="employee_id" class="block text-sm font-semibold text-gray-700 mb-2">Employee</label>
                    <select name="employee_id" id="employee_id" required 
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary-500 focus:ring-4 focus:ring-primary-100 transition-all outline-none">
                        <option value="">Select Employee...</option>
                        <?php 
                        $selected_employee_id = $_POST['employee_id'] ?? ($payslip_data->employee_id ?? '');
                        foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee->id; ?>" <?php echo ($selected_employee_id == $employee->id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($employee->first_name . ' ' . $employee->last_name . ' - ' . ($employee->position_name ?? 'No Position')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="month" class="block text-sm font-semibold text-gray-700 mb-2">Month</label>
                    <select name="month" id="month" required 
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary-500 focus:ring-4 focus:ring-primary-100 transition-all outline-none">
                        <?php 
                        $selected_month = $_POST['month'] ?? ($payslip_data ? date('m', strtotime($payslip_data->pay_period_end)) : date('m'));
                        for ($i = 1; $i <= 12; $i++): 
                            $month_value = sprintf('%02d', $i); 
                        ?>
                            <option value="<?php echo $month_value; ?>" <?php echo ($selected_month == $month_value) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="year" class="block text-sm font-semibold text-gray-700 mb-2">Year</label>
                    <select name="year" id="year" required 
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary-500 focus:ring-4 focus:ring-primary-100 transition-all outline-none">
                        <?php 
                        $selected_year = $_POST['year'] ?? ($payslip_data ? date('Y', strtotime($payslip_data->pay_period_end)) : date('Y'));
                        for ($y = date('Y'); $y >= date('Y') - 5; $y--): 
                        ?>
                            <option value="<?php echo $y; ?>" <?php echo ($selected_year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" name="generate_payslip" 
                            class="w-full px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-xl font-bold hover:from-primary-700 hover:to-primary-800 transition-all shadow-lg hover:shadow-xl transform hover:scale-105">
                        <i class="fas fa-search mr-2"></i>Generate
                    </button>
                </div>
            </form>
        </div>

        <?php if ($payslip_data): ?>
        <div class="bg-white rounded-2xl shadow-2xl border-2 border-gray-300 payslip-card" id="payslip">
            <div class="p-10">
                <div class="text-center mb-8 border-b-4 border-primary-600 pb-6">
                    <h2 class="text-4xl font-bold text-gray-900">উজ্জ্বল ফ্লাওয়ার মিলস</h2>
                    <p class="text-gray-600 mt-2">১৭, নুরাইবাগ, ডেমরা, ঢাকা</p>
                    <div class="mt-4 inline-block bg-gradient-to-r from-primary-500 to-primary-600 text-white px-8 py-3 rounded-full">
                        <h3 class="text-2xl font-bold tracking-widest">SALARY SLIP</h3>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-6 mb-8 text-sm bg-primary-50 p-6 rounded-xl border-2 border-primary-200">
                    <div class="space-y-2">
                        <p class="flex justify-between"><strong class="text-gray-700">Employee Name:</strong> <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($payslip_data->first_name . ' ' . $payslip_data->last_name); ?></span></p>
                        <p class="flex justify-between"><strong class="text-gray-700">Employee ID:</strong> <span class="font-semibold text-gray-900">EMP-<?php echo str_pad($payslip_data->employee_id, 4, '0', STR_PAD_LEFT); ?></span></p>
                        <p class="flex justify-between"><strong class="text-gray-700">Designation:</strong> <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($payslip_data->position_name ?? 'N/A'); ?></span></p>
                        <p class="flex justify-between"><strong class="text-gray-700">Department:</strong> <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($payslip_data->department_name ?? 'N/A'); ?></span></p>
                    </div>
                    <div class="space-y-2 text-right">
                        <p class="flex justify-between"><strong class="text-gray-700">Pay Period:</strong> <span class="font-semibold text-gray-900"><?php echo date('F Y', strtotime($payslip_data->pay_period_end)); ?></span></p>
                        <p class="flex justify-between"><strong class="text-gray-700">Pay Date:</strong> <span class="font-semibold text-gray-900"><?php echo date('d M, Y'); ?></span></p>
                        <p class="flex justify-between"><strong class="text-gray-700">Working Days:</strong> <span class="font-semibold text-gray-900"><?php echo $payslip_data->present_days; ?> / <?php echo $payslip_data->days_in_month; ?></span></p>
                        <p class="flex justify-between"><strong class="text-gray-700">Absent Days:</strong> <span class="font-semibold text-red-600"><?php echo $payslip_data->absent_days; ?></span></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <div class="border-2 border-green-200 rounded-xl p-6 bg-gradient-to-br from-green-50 to-emerald-50">
                        <h4 class="text-xl font-bold text-green-700 mb-4 border-b-2 border-green-300 pb-2 flex items-center gap-2"><i class="fas fa-plus-circle"></i>Earnings</h4>
                        <table class="w-full text-sm"><tbody class="divide-y divide-green-100">
                            <tr class="hover:bg-green-100"><td class="py-3">Basic Salary</td><td class="text-right font-semibold">৳ <?php echo number_format($payslip_data->basic_salary, 2); ?></td></tr>
                            <tr class="hover:bg-green-100"><td class="py-3">House Allowance</td><td class="text-right font-semibold">৳ <?php echo number_format($payslip_data->house_allowance, 2); ?></td></tr>
                            <tr class="hover:bg-green-100"><td class="py-3">Transport Allowance</td><td class="text-right font-semibold">৳ <?php echo number_format($payslip_data->transport_allowance, 2); ?></td></tr>
                            <tr class="hover:bg-green-100"><td class="py-3">Medical Allowance</td><td class="text-right font-semibold">৳ <?php echo number_format($payslip_data->medical_allowance, 2); ?></td></tr>
                            <tr class="hover:bg-green-100"><td class="py-3">Other Allowances</td><td class="text-right font-semibold">৳ <?php echo number_format($payslip_data->other_allowances, 2); ?></td></tr>
                        </tbody><tfoot class="bg-green-600 text-white font-bold"><tr><td class="py-3 px-2 rounded-bl-lg">GROSS SALARY</td><td class="text-right py-3 px-2 rounded-br-lg">৳ <?php echo number_format($payslip_data->gross_salary, 2); ?></td></tr></tfoot></table>
                    </div>
                    <div class="border-2 border-red-200 rounded-xl p-6 bg-gradient-to-br from-red-50 to-pink-50">
                        <h4 class="text-xl font-bold text-red-700 mb-4 border-b-2 border-red-300 pb-2 flex items-center gap-2"><i class="fas fa-minus-circle"></i>Deductions</h4>
                        <table class="w-full text-sm"><tbody class="divide-y divide-red-100">
                            <tr class="hover:bg-red-100"><td class="py-3">Absence Deduction</td><td class="text-right font-semibold">৳ <?php echo number_format($payslip_data->absence_deduction, 2); ?></td></tr>
                            <tr class="hover:bg-red-100"><td class="py-3">Salary Advance</td><td class="text-right font-semibold">৳ <?php echo number_format($payslip_data->salary_advance_deduction, 2); ?></td></tr>
                            <tr class="hover:bg-red-100"><td class="py-3">Loan Installment</td><td class="text-right font-semibold">৳ <?php echo number_format($payslip_data->loan_installment_deduction, 2); ?></td></tr>
                            <tr class="hover:bg-red-100"><td class="py-3">Provident Fund</td><td class="text-right font-semibold">৳ <?php echo number_format($payslip_data->provident_fund, 2); ?></td></tr>
                            <tr class="hover:bg-red-100"><td class="py-3">Tax Deduction</td><td class="text-right font-semibold">৳ <?php echo number_format($payslip_data->tax_deduction, 2); ?></td></tr>
                            <tr class="hover:bg-red-100"><td class="py-3">Other Deductions</td><td class="text-right font-semibold">৳ <?php echo number_format($payslip_data->other_deductions, 2); ?></td></tr>
                        </tbody><tfoot class="bg-red-600 text-white font-bold"><tr><td class="py-3 px-2 rounded-bl-lg">TOTAL DEDUCTIONS</td><td class="text-right py-3 px-2 rounded-br-lg">৳ <?php echo number_format($payslip_data->total_deductions, 2); ?></td></tr></tfoot></table>
                    </div>
                </div>
                
                <div class="mt-8">
                    <div class="bg-gradient-to-r from-primary-600 via-primary-700 to-primary-800 rounded-2xl text-white px-8 py-6 shadow-2xl text-center">
                        <p class="text-lg font-semibold mb-2">NET SALARY PAID</p>
                        <h4 class="text-5xl font-bold mb-3">৳ <?php echo number_format($payslip_data->net_salary, 2); ?></h4>
                        <p class="text-sm opacity-90 italic"><?php echo ucwords(number_to_words($payslip_data->net_salary)); ?></p>
                    </div>
                </div>
                
                <div class="mt-12 pt-8 border-t-2 border-gray-200">
                    <div class="grid grid-cols-2 gap-8 text-center text-sm text-gray-600">
                        <div><div class="h-20 flex items-end justify-center"><div class="border-t-2 border-gray-400 w-48 pt-2"><p class="font-semibold text-gray-900">Employee Signature</p></div></div></div>
                        <div><div class="h-20 flex items-end justify-center"><div class="border-t-2 border-gray-400 w-48 pt-2"><p class="font-semibold text-gray-900">Authorized Signature</p></div></div></div>
                    </div>
                    <p class="text-center text-xs text-gray-500 mt-6"><i class="fas fa-info-circle mr-1"></i>This is a computer-generated payslip. Generated on <?php echo date('d F, Y h:i A'); ?></p>
                </div>
            </div>
        </div>

        <div class="mt-6 no-print flex justify-center gap-4">
            <button onclick="window.print()" class="px-8 py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl font-bold hover:from-green-600 hover:to-emerald-700 transition-all shadow-lg flex items-center gap-3"><i class="fas fa-print text-xl"></i>Print Payslip</button>
            <button onclick="downloadPayslipPDF()" 
                    class="bg-primary-600 text-white px-6 py-2 rounded-lg hover:bg-primary-700 shadow">
                <i class="fas fa-file-pdf mr-2"></i> Download PDF
            </button>


            <a href="payslip.php" class="px-8 py-4 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-xl font-bold hover:from-primary-600 hover:to-primary-700 transition-all shadow-lg flex items-center gap-3"><i class="fas fa-file-alt text-xl"></i>Generate Another</a>
            <a href="payroll_history.php" class="px-8 py-4 bg-gradient-to-r from-gray-500 to-gray-600 text-white rounded-xl font-bold hover:from-gray-600 hover:to-gray-700 transition-all shadow-lg flex items-center gap-3"><i class="fas fa-history text-xl"></i>View History</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; 
?>
<script>
function downloadPayslipPDF() {
    const payslip = document.getElementById('payslip');
    
    // Configure PDF options
    const opt = {
        margin:       0.3,
        filename:     'Payslip_<?php echo date("F_Y"); ?>.pdf',
        image:        { type: 'jpeg', quality: 1 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'in', format: 'A4', orientation: 'portrait' }
    };

    // Generate and download
    html2pdf().set(opt).from(payslip).save();
}
</script>