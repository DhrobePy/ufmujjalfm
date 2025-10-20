<?php
// new_ufmhrm/admin/generate_salary_certificate.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// Get employee ID
$employeeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

// Calculate tenure
$hireDate = new DateTime($employee->hire_date);
$today = new DateTime();
$tenure = $hireDate->diff($today);

$currentDate = date('d F, Y');
$certificateNumber = 'SC-' . date('Y') . '-' . str_pad($employee->id, 4, '0', STR_PAD_LEFT);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Certificate - <?php echo htmlspecialchars($employee->first_name . ' ' . $employee->last_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
        .certificate-border {
            border: 8px double #4f46e5;
            position: relative;
        }
        .certificate-border::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border: 2px solid #e0e7ff;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <!-- Print Button -->
        <div class="no-print mb-4 flex justify-end gap-2">
            <button onclick="window.print()" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                <i class="fas fa-print mr-2"></i>Print Certificate
            </button>
            <button onclick="window.close()" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-times mr-2"></i>Close
            </button>
        </div>

        <!-- Certificate -->
        <div class="bg-white shadow-2xl certificate-border">
            <div class="p-12">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="inline-block bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-8 py-3 rounded-full mb-4">
                        <i class="fas fa-certificate text-2xl"></i>
                    </div>
                    <h1 class="text-4xl font-bold text-gray-900 mb-2">SALARY CERTIFICATE</h1>
                    <p class="text-gray-600">Certificate No: <?php echo $certificateNumber; ?></p>
                </div>

                <!-- Company Letterhead -->
                <div class="text-center mb-8 pb-6 border-b-2 border-indigo-200">
                    <h2 class="text-2xl font-bold text-indigo-900">Your Company Name</h2>
                    <p class="text-gray-600 mt-2">Company Address Line 1, City, Country</p>
                    <p class="text-gray-600">Phone: +880 XXXX-XXXXXX | Email: info@company.com</p>
                </div>

                <!-- Date -->
                <div class="text-right mb-8">
                    <p class="text-gray-700"><span class="font-semibold">Date:</span> <?php echo $currentDate; ?></p>
                </div>

                <!-- Salutation -->
                <div class="mb-6">
                    <p class="text-gray-900 text-lg font-semibold">To Whom It May Concern,</p>
                </div>

                <!-- Certificate Body -->
                <div class="space-y-6 text-gray-800 leading-relaxed text-justify">
                    <p class="text-lg">
                        This is to certify that <span class="font-bold text-indigo-900"><?php echo htmlspecialchars($employee->first_name . ' ' . $employee->last_name); ?></span>, 
                        bearing Employee ID <span class="font-bold">EMP-<?php echo str_pad($employee->id, 4, '0', STR_PAD_LEFT); ?></span>, 
                        is a bonafide employee of our organization.
                    </p>

                    <p class="text-lg">
                        <?php echo htmlspecialchars($employee->first_name . ' ' . $employee->last_name); ?> is currently working with us as 
                        <span class="font-bold text-indigo-900"><?php echo htmlspecialchars($employee->position_name ?? 'N/A'); ?></span> 
                        in the <span class="font-bold text-indigo-900"><?php echo htmlspecialchars($employee->department_name ?? 'N/A'); ?></span> department 
                        since <span class="font-bold"><?php echo date('d F, Y', strtotime($employee->hire_date)); ?></span>.
                    </p>

                    <p class="text-lg">
                        The employee has been associated with our organization for approximately 
                        <span class="font-bold"><?php echo $tenure->y; ?> year(s) and <?php echo $tenure->m; ?> month(s)</span>, 
                        and has been a valuable member of our team.
                    </p>

                    <!-- Salary Details Box -->
                    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 border-2 border-indigo-200 rounded-xl p-6 my-6">
                        <h3 class="text-xl font-bold text-indigo-900 mb-4 text-center">Salary Details</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-white rounded-lg p-4 shadow-sm">
                                <p class="text-sm text-gray-600 mb-1">Basic Salary</p>
                                <p class="text-2xl font-bold text-gray-900">৳<?php echo number_format($salaryStructure->basic_salary ?? 0, 2); ?></p>
                            </div>
                            <div class="bg-white rounded-lg p-4 shadow-sm">
                                <p class="text-sm text-gray-600 mb-1">Gross Salary</p>
                                <p class="text-2xl font-bold text-green-600">৳<?php echo number_format($salaryStructure->gross_salary ?? 0, 2); ?></p>
                            </div>
                        </div>
                        <div class="mt-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg p-4 text-center">
                            <p class="text-sm opacity-90 mb-1">Monthly Net Salary</p>
                            <p class="text-3xl font-bold">৳<?php echo number_format($salaryStructure->net_salary ?? 0, 2); ?></p>
                            <p class="text-xs opacity-75 mt-2">
                                (Taka <?php echo ucwords(str_replace('-', ' ', number_format($salaryStructure->net_salary ?? 0, 2))); ?> Only)
                            </p>
                        </div>
                    </div>

                    <p class="text-lg">
                        This certificate is issued upon the request of the employee for official purposes. 
                        We wish <?php echo htmlspecialchars($employee->first_name); ?> all the best in their future endeavors.
                    </p>
                </div>

                <!-- Closing -->
                <div class="mt-12 pt-8">
                    <p class="text-gray-900 text-lg mb-8">Sincerely,</p>
                    
                    <div class="grid grid-cols-2 gap-8">
                        <div>
                            <div class="border-t-2 border-gray-400 pt-2 inline-block min-w-[200px]">
                                <p class="font-bold text-gray-900">HR Manager</p>
                                <p class="text-sm text-gray-600">Human Resources Department</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="border-t-2 border-gray-400 pt-2 inline-block min-w-[200px]">
                                <p class="font-bold text-gray-900">Managing Director</p>
                                <p class="text-sm text-gray-600">Your Company Name</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Company Seal Area -->
                <div class="mt-12 text-center">
                    <div class="inline-block border-4 border-indigo-600 rounded-full w-32 h-32 flex items-center justify-center">
                        <div class="text-center">
                            <p class="text-xs font-bold text-indigo-900">COMPANY</p>
                            <p class="text-xs font-bold text-indigo-900">SEAL</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                    <p class="text-xs text-gray-500">
                        This is a computer-generated certificate. For verification, please contact HR Department.
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        Generated on: <?php echo date('d F, Y h:i A'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Decorative Elements -->
        <div class="no-print mt-4 text-center text-sm text-gray-500">
            <p><i class="fas fa-info-circle mr-1"></i> This certificate is valid and can be verified by contacting the company.</p>
        </div>
    </div>
</body>
</html>

