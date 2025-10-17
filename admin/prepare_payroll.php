<?php
// /admin/prepare_payroll.php (Final Definitive Logic)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

// Include our new architectural components
require_once 'repositories/EmployeeRepository.php';
require_once 'services/PayrollService.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    $payPeriodStart = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
    $payPeriodEnd = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    // --- 1. Validation ---
    $existingPayroll = $db->query("SELECT id FROM payrolls WHERE pay_period_start = ? AND pay_period_end = ?", [$payPeriodStart, $payPeriodEnd])->first();
    if ($existingPayroll) {
        $_SESSION['error_flash'] = 'Payroll for ' . date('F Y', strtotime($payPeriodStart)) . ' has already been generated.';
        header('Location: payroll.php');
        exit();
    }

    // --- Instantiate our new service and repository ---
    $employeeRepo = new EmployeeRepository($db);
    $payrollService = new PayrollService();

    $db->getPdo()->beginTransaction();
    try {
        // --- 2. Fetch all data efficiently ---
        $allEmployeeDetails = $employeeRepo->getActiveEmployeesWithDetails(
            $payPeriodStart, 
            $payPeriodEnd, 
            str_pad($month, 2, '0', STR_PAD_LEFT), 
            (string)$year
        );

        foreach ($allEmployeeDetails as $employeeId => $employeeData) {
            
            // --- 3. Calculate payroll using the dedicated service ---
            $payrollCalculations = $payrollService->calculatePayrollForEmployee($employeeData, $daysInMonth);

            // --- 4. Insert summary record into the 'payrolls' table ---
            $db->insert('payrolls', [
                'employee_id' => $employeeId,
                'pay_period_start' => $payPeriodStart,
                'pay_period_end' => $payPeriodEnd,
                'gross_salary' => $payrollCalculations['gross_salary'],
                'deductions' => $payrollCalculations['total_deductions'],
                'net_salary' => $payrollCalculations['net_salary'],
                'status' => 'pending_approval'
            ]);

            // --- 5. Get the ID of the payroll summary we just created ---
            $newPayrollId = $db->getPdo()->lastInsertId();

            // --- 6. Insert the detailed breakdown into the 'payroll_details' table ---
            $db->insert('payroll_details', [
                'payroll_id' => $newPayrollId,
                'basic_salary' => $payrollCalculations['basic_salary'],
                'gross_salary' => $payrollCalculations['gross_salary'],
                'days_in_month' => $payrollCalculations['days_in_month'],
                'absent_days' => $payrollCalculations['absent_days'],
                'daily_rate' => $payrollCalculations['daily_rate'],
                'absence_deduction' => $payrollCalculations['absence_deduction'],
                'salary_advance_deduction' => $payrollCalculations['salary_advance_deduction'],
                'loan_installment_deduction' => $payrollCalculations['loan_installment_deduction'],
                'other_deductions' => $payrollCalculations['other_deductions'],
                'total_deductions' => $payrollCalculations['total_deductions'],
                'net_salary' => $payrollCalculations['net_salary']
            ]);
        }

        $db->getPdo()->commit();
        $_SESSION['success_flash'] = 'Payroll for ' . date('F Y', strtotime($payPeriodStart)) . ' generated successfully and is ready for review.';
        header('Location: approve_payroll.php');
        exit();

    } catch (Exception $e) {
        $db->getPdo()->rollBack();
        $_SESSION['error_flash'] = 'An error occurred while generating payroll: ' . $e->getMessage();
        header('Location: payroll.php');
        exit();
    }
} else {
    header('Location: payroll.php');
    exit();
}