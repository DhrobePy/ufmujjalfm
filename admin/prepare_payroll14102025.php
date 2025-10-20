<?php
// new_ufmhrm/admin/prepare_payroll.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];

    // --- 1. Validation ---
    $payPeriodStart = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
    $payPeriodEnd = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
    $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));

    // Check if payroll for this period already exists
    $existingPayroll = $db->query("SELECT id FROM payrolls WHERE pay_period_start = ? AND pay_period_end = ?", [$payPeriodStart, $payPeriodEnd])->first();
    if ($existingPayroll) {
        $_SESSION['error_flash'] = 'Payroll for ' . date('F Y', strtotime($payPeriodStart)) . ' has already been generated.';
        header('Location: payroll.php');
        exit();
    }

    // --- 2. Fetch Data ---
    $activeEmployees = $db->query("SELECT * FROM employees WHERE status = 'active'")->results();
    
    $db->getPdo()->beginTransaction();
    try {
        foreach ($activeEmployees as $employee) {
            $salaryStructure = $db->query("SELECT * FROM salary_structures WHERE employee_id = ?", [$employee->id])->first();
            
            // If no salary structure, use base salary from employees table
            $basicSalary = $salaryStructure ? $salaryStructure->basic_salary : $employee->base_salary;
            $grossSalary = $salaryStructure ? $salaryStructure->gross_salary : $employee->base_salary;

            // --- 3. Calculate Deductions ---
            $totalDeductions = 0;
            
            // a) Absences Deduction
            $absentDaysResult = $db->query("SELECT COUNT(*) as count FROM attendance WHERE employee_id = ? AND status = 'absent' AND clock_in BETWEEN ? AND ?", [$employee->id, $payPeriodStart, $payPeriodEnd]);
            $absentDays = $absentDaysResult ? $absentDaysResult->first()->count : 0;
            $dailyRate = $basicSalary / $daysInMonth;
            $absenceDeduction = $absentDays * $dailyRate;
            $totalDeductions += $absenceDeduction;

            // b) Salary Advance Deduction
            $advanceResult = $db->query("SELECT SUM(amount) as total FROM salary_advances WHERE employee_id = ? AND advance_month = ? AND advance_year = ?", [$employee->id, str_pad($month, 2, '0', STR_PAD_LEFT), $year]);
            $advanceDeduction = $advanceResult ? $advanceResult->first()->total : 0;
            $totalDeductions += $advanceDeduction;

            // c) Loan Installment Deduction
            $loanInstallment = 0;
            $activeLoan = $db->query("SELECT * FROM loans WHERE employee_id = ? AND status = 'active'", [$employee->id])->first();
            if ($activeLoan) {
                $loanInstallment = $activeLoan->monthly_payment;
                $totalDeductions += $loanInstallment;
            }

            // --- 4. Calculate Net Salary ---
            $netSalary = $grossSalary - $totalDeductions;

            // --- 5. Insert into Payroll Table ---
            $db->insert('payrolls', [
                'employee_id' => $employee->id,
                'pay_period_start' => $payPeriodStart,
                'pay_period_end' => $payPeriodEnd,
                'gross_salary' => $grossSalary,
                'deductions' => $totalDeductions,
                'net_salary' => $netSalary,
                'status' => 'pending_approval' // Set initial status
            ]);
        }

        $db->getPdo()->commit();
        $_SESSION['success_flash'] = 'Payroll for ' . date('F Y', strtotime($payPeriodStart)) . ' has been generated successfully and is ready for review.';
        header('Location: approve_payroll.php'); // Redirect to the review page
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
?>
