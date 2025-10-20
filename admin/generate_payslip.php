<?php
// new_ufmhrm/admin/generate_payslip.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// Get employee ID and month/year
$employeeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

if (!$employeeId) {
    die('Invalid employee ID');
}

// Fetch employee details
$sql = "
    SELECT 
        e.*, 
        p.name as position_name, 
        d.name as department_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    WHERE e.id = ?
";

$employee = $db->query($sql, [$employeeId])->first();

if (!$employee) {
    die('Employee not found');
}

// Fetch salary structure
$salaryStructure = $db->query("
    SELECT * FROM salary_structures 
    WHERE employee_id = ? 
    ORDER BY created_date DESC 
    LIMIT 1
", [$employeeId])->first();

// Get month name and year
$monthYear = date('F Y', strtotime($month . '-01'));
$currentDate = date('d F, Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($employee->first_name . ' ' . $employee->last_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <!-- Print Button -->
        <div class="no-print mb-4 flex justify-end gap-2">
            <button onclick="window.print()" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                <i class="fas fa-print mr-2"></i>Print Payslip
            </button>
            <button onclick="window.close()" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-times mr-2"></i>Close
            </button>
        </div>

        <!-- Payslip -->
        <div class="bg-white shadow-2xl rounded-xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-8">
                <div class="text-center">
                    <h1 class="text-3xl font-bold mb-2">SALARY SLIP</h1>
                    <p class="text-lg">For the month of <?php echo $monthYear; ?></p>
                </div>
            </div>

            <!-- Company Info -->
            <div class="bg-gray-50 p-6 border-b-2 border-indigo-600">
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-gray-900">Your Company Name</h2>
                    <p class="text-gray-600 mt-1">Company Address Line 1, City, Country</p>
                    <p class="text-gray-600">Phone: +880 XXXX-XXXXXX | Email: info@company.com</p>
                </div>
            </div>

            <!-- Employee Details -->
            <div class="p-8">
                <div class="grid grid-cols-2 gap-6 mb-8">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-4 border-b-2 border-indigo-600 pb-2">Employee Details</h3>
                        <div class="space-y-2">
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Name:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($employee->first_name . ' ' . $employee->last_name); ?></span>
                            </div>
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Employee ID:</span>
                                <span class="text-gray-900">EMP-<?php echo str_pad($employee->id, 4, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Designation:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($employee->position_name ?? 'N/A'); ?></span>
                            </div>
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Department:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($employee->department_name ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-4 border-b-2 border-indigo-600 pb-2">Payment Details</h3>
                        <div class="space-y-2">
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Pay Period:</span>
                                <span class="text-gray-900"><?php echo $monthYear; ?></span>
                            </div>
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Pay Date:</span>
                                <span class="text-gray-900"><?php echo $currentDate; ?></span>
                            </div>
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Status:</span>
                                <span class="text-green-600 font-semibold"><?php echo ucfirst($employee->status); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Salary Breakdown -->
                <div class="grid grid-cols-2 gap-6">
                    <!-- Earnings -->
                    <div>
                        <div class="bg-green-50 rounded-lg p-4 border-2 border-green-200">
                            <h3 class="text-lg font-bold text-green-900 mb-4 flex items-center">
                                <i class="fas fa-plus-circle mr-2"></i>
                                Earnings
                            </h3>
                            <div class="space-y-2">
                                <div class="flex justify-between border-b border-green-200 pb-2">
                                    <span class="text-gray-700">Basic Salary</span>
                                    <span class="font-semibold text-gray-900">৳<?php echo number_format($salaryStructure->basic_salary ?? 0, 2); ?></span>
                                </div>
                                <div class="flex justify-between border-b border-green-200 pb-2">
                                    <span class="text-gray-700">House Allowance</span>
                                    <span class="font-semibold text-gray-900">৳<?php echo number_format($salaryStructure->house_allowance ?? 0, 2); ?></span>
                                </div>
                                <div class="flex justify-between border-b border-green-200 pb-2">
                                    <span class="text-gray-700">Transport Allowance</span>
                                    <span class="font-semibold text-gray-900">৳<?php echo number_format($salaryStructure->transport_allowance ?? 0, 2); ?></span>
                                </div>
                                <div class="flex justify-between border-b border-green-200 pb-2">
                                    <span class="text-gray-700">Medical Allowance</span>
                                    <span class="font-semibold text-gray-900">৳<?php echo number_format($salaryStructure->medical_allowance ?? 0, 2); ?></span>
                                </div>
                                <div class="flex justify-between border-b border-green-200 pb-2">
                                    <span class="text-gray-700">Other Allowances</span>
                                    <span class="font-semibold text-gray-900">৳<?php echo number_format($salaryStructure->other_allowances ?? 0, 2); ?></span>
                                </div>
                                <div class="flex justify-between pt-2 bg-green-100 -mx-4 px-4 py-2 rounded">
                                    <span class="font-bold text-green-900">Gross Earnings</span>
                                    <span class="font-bold text-green-900 text-lg">৳<?php echo number_format($salaryStructure->gross_salary ?? 0, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Deductions -->
                    <div>
                        <div class="bg-red-50 rounded-lg p-4 border-2 border-red-200">
                            <h3 class="text-lg font-bold text-red-900 mb-4 flex items-center">
                                <i class="fas fa-minus-circle mr-2"></i>
                                Deductions
                            </h3>
                            <div class="space-y-2">
                                <div class="flex justify-between border-b border-red-200 pb-2">
                                    <span class="text-gray-700">Provident Fund</span>
                                    <span class="font-semibold text-gray-900">৳<?php echo number_format($salaryStructure->provident_fund ?? 0, 2); ?></span>
                                </div>
                                <div class="flex justify-between border-b border-red-200 pb-2">
                                    <span class="text-gray-700">Tax Deduction</span>
                                    <span class="font-semibold text-gray-900">৳<?php echo number_format($salaryStructure->tax_deduction ?? 0, 2); ?></span>
                                </div>
                                <div class="flex justify-between border-b border-red-200 pb-2">
                                    <span class="text-gray-700">Other Deductions</span>
                                    <span class="font-semibold text-gray-900">৳<?php echo number_format($salaryStructure->other_deductions ?? 0, 2); ?></span>
                                </div>
                                <div class="flex justify-between pt-2 bg-red-100 -mx-4 px-4 py-2 rounded mt-auto">
                                    <span class="font-bold text-red-900">Total Deductions</span>
                                    <span class="font-bold text-red-900 text-lg">৳<?php 
                                        $totalDeductions = ($salaryStructure->provident_fund ?? 0) + 
                                                          ($salaryStructure->tax_deduction ?? 0) + 
                                                          ($salaryStructure->other_deductions ?? 0);
                                        echo number_format($totalDeductions, 2); 
                                    ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Net Salary -->
                <div class="mt-8 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl p-6 shadow-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm opacity-90 mb-1">Net Salary (Take Home)</p>
                            <p class="text-4xl font-bold">৳<?php echo number_format($salaryStructure->net_salary ?? 0, 2); ?></p>
                            <p class="text-xs opacity-75 mt-2">In Words: <?php 
                                // Simple number to words conversion for demonstration
                                echo 'Taka ' . ucwords(str_replace('-', ' ', number_format($salaryStructure->net_salary ?? 0, 2))) . ' Only';
                            ?></p>
                        </div>
                        <div class="text-right">
                            <i class="fas fa-wallet text-6xl opacity-20"></i>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-8 pt-6 border-t-2 border-gray-200">
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-xs text-gray-500 mb-4">This is a computer-generated payslip and does not require a signature.</p>
                            <p class="text-xs text-gray-500">Generated on: <?php echo date('d F, Y h:i A'); ?></p>
                        </div>
                        <div class="text-right">
                            <div class="border-t-2 border-gray-400 pt-2 w-48">
                                <p class="text-sm font-semibold text-gray-700">Authorized Signature</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

