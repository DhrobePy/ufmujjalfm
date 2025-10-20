<?php
// new_ufmhrm/accounts/prepare_payroll.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

// --- SECURITY & PERMISSIONS ---
if (!is_admin_logged_in()) {
    redirect('../auth/login.php');
}

$currentUser = getCurrentUser();
$user_branch_id = $currentUser['branch_id'];
$is_admin = in_array($currentUser['role'], ['superadmin', 'Admin', 'Accounts-HO', 'Admin-HO']);
$is_branch_accountant = in_array($currentUser['role'], ['Accounts- Srg', 'Accounts- Rampura']);

if (!$is_branch_accountant && !$is_admin) {
    set_message('You do not have permission to run payroll.', 'error');
    redirect('payroll.php');
}

// --- HANDLE POST REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT);
    $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);

    if (!$month || !$year) {
        set_message('Invalid month or year selected.', 'error');
        redirect('payroll.php');
    }

    $pay_period_start = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
    $pay_period_end = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
    $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));

    // --- DUPLICATE CHECK ---
    $checkSql = "SELECT id FROM payrolls WHERE pay_period_end = ? AND branch_id = ?";
    $existingPayroll = $db->query($checkSql, [$pay_period_end, $user_branch_id])->first();
    if ($existingPayroll) {
        set_message('Payroll for ' . date('F Y', strtotime($pay_period_end)) . ' has already been generated or is pending approval.', 'error');
        redirect('payroll.php');
    }

    // --- FETCH EMPLOYEES FOR THE BRANCH ---
    $employees = $db->query("SELECT * FROM employees WHERE status = 'active' AND branch_id = ?", [$user_branch_id])->results();

    if (empty($employees)) {
        set_message('No active employees found for this branch.', 'error');
        redirect('payroll.php');
    }

    // --- START DATABASE TRANSACTION ---
    $db->getPdo()->beginTransaction();
    try {
        foreach ($employees as $employee) {
            // 1. Get latest salary structure
            $salaryStructure = $db->query("SELECT * FROM salary_structures WHERE employee_id = ? ORDER BY created_date DESC LIMIT 1", [$employee->id])->first();
            if (!$salaryStructure) continue; // Skip if no salary structure

            // 2. Get attendance for the month
            $attendance = $db->query("SELECT COUNT(*) as absent_days FROM attendance WHERE employee_id = ? AND status = 'absent' AND `date` BETWEEN ? AND ?", [$employee->id, $pay_period_start, $pay_period_end])->first();
            $absent_days = $attendance->absent_days ?? 0;

            // 3. Get salary advances for the month
            $advances = $db->query("SELECT SUM(amount) as total_advances FROM salary_advances WHERE employee_id = ? AND advance_month = ? AND advance_year = ? AND status = 'approved'", [$employee->id, str_pad($month, 2, '0', STR_PAD_LEFT), $year])->first();
            $total_advances = $advances->total_advances ?? 0;

            // 4. Get loan installments due for the month
            $loans = $db->query("SELECT SUM(li.amount) as total_emi FROM loan_installments li JOIN loans l ON li.loan_id = l.id WHERE l.employee_id = ? AND l.status = 'active' AND li.payment_date BETWEEN ? AND ?", [$employee->id, $pay_period_start, $pay_period_end])->first();
            $total_emi = $loans->total_emi ?? 0;

            // --- CALCULATIONS ---
            $gross_salary = $salaryStructure->gross_salary;
            $daily_rate = ($daysInMonth > 0) ? $gross_salary / $daysInMonth : 0;
            $absence_deduction = $daily_rate * $absent_days;
            $total_deductions = $salaryStructure->provident_fund + $salaryStructure->tax_deduction + $total_advances + $total_emi;
            $net_salary = $gross_salary - $absence_deduction - $total_deductions;

            // --- INSERT PAYROLL RECORD ---
            $payrollData = [
                'employee_id' => $employee->id,
                'pay_period_start' => $pay_period_start,
                'pay_period_end' => $pay_period_end,
                'gross_salary' => $gross_salary,
                'deductions' => $total_deductions,
                'net_salary' => $net_salary,
                'status' => 'pending_approval',
                'branch_id' => $employee->branch_id,
                'generated_by' => $currentUser['id']
            ];
            $db->insert('payrolls', $payrollData);
        }

        // --- COMMIT TRANSACTION ---
        $db->getPdo()->commit();
        set_message('Payroll for ' . date('F Y', strtotime($pay_period_end)) . ' has been generated successfully and is now pending approval.', 'success');
        redirect('approve_payroll.php');

    } catch (Exception $e) {
        // --- ROLLBACK TRANSACTION ON ERROR ---
        $db->getPdo()->rollBack();
        set_message('An error occurred during payroll generation: ' . $e->getMessage(), 'error');
        redirect('payroll.php');
    }
} else {
    // Redirect if accessed directly
    redirect('payroll.php');
}
?>
