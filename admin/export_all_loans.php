<?php
// new_ufmhrm/admin/export_all_loans.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

// 1. Security Check
if (!is_admin_logged_in()) {
    exit('Access Denied.');
}
$currentUser = getCurrentUser();
$isAdmin = in_array($currentUser['role'], ['Admin', 'superadmin']);
if (!$isAdmin) {
    exit('Access Denied.');
}

// 2. Set CSV Headers
$filename = "master_loan_report_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 3. Open output stream
$output = fopen('php://output', 'w');

// 4. Write CSV Header Row (Your new requested order)
fputcsv($output, [
    'Sl',
    'Loan Creation Date',
    'Employee Name',
    'Employee ID',
    'Branch',
    'Loan Amount (৳)',
    'Loan Type',
    'Salary Advance For (Month)',
    'Installment Amount (৳)',
    'Repaid Amount (৳)',
    'Outstanding Amount (৳)'
], ",", "\"", "\\"); // Added fix for PHP deprecation warning

// 5. Build the NEW UNION Query
// This query now includes monthly_payment, advance_month, and advance_year
$query = "
    (
        -- Part 1: Get data from 'loans' table (Fixed & Random)
        SELECT
            l.loan_date AS creation_date,
            l.installment_type AS loan_type,
            e.id AS employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            b.name AS branch_name,
            l.amount AS loan_amount,
            (SELECT IFNULL(SUM(li.amount), 0) FROM loan_installments li WHERE li.loan_id = l.id) AS paid_amount,
            (l.amount - (SELECT IFNULL(SUM(li.amount), 0) FROM loan_installments li WHERE li.loan_id = l.id)) AS outstanding_amount,
            l.monthly_payment AS installment_amount,
            NULL AS advance_month, -- No advance month for regular loans
            NULL AS advance_year   -- No advance year for regular loans
        FROM loans l
        JOIN employees e ON l.employee_id = e.id
        LEFT JOIN branches b ON l.branch_id = b.id
        WHERE l.status IN ('active', 'paid')
    )
    UNION ALL
    (
        -- Part 2: Get data from 'salary_advances' table
        SELECT
            sa.advance_date AS creation_date,
            'Salary Advance' AS loan_type,
            e.id AS employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            b.name AS branch_name,
            sa.amount AS loan_amount,
            (CASE WHEN sa.status = 'paid' THEN sa.amount ELSE 0 END) AS paid_amount,
            (CASE WHEN sa.status = 'approved' THEN sa.amount ELSE 0 END) AS outstanding_amount,
            NULL AS installment_amount, -- No installment for advances
            sa.advance_month,
            sa.advance_year
        FROM salary_advances sa
        JOIN employees e ON sa.employee_id = e.id
        LEFT JOIN branches b ON sa.branch_id = b.id
        WHERE sa.status IN ('approved', 'paid')
    )
    ORDER BY creation_date DESC
";

$all_loans = $db->query($query)->results();

// 6. Loop and write data to CSV
if ($all_loans) {
    $serial_number = 1; // Initialize serial number
    
    foreach ($all_loans as $loan) {
        
        // Format the new fields
        $loan_type_formatted = ucfirst($loan->loan_type);
        $advance_period = 'N/A';
        $installment_amount = 'N/A';

        if ($loan->loan_type === 'Salary Advance') {
            $loan_type_formatted = 'Salary Advance';
            // Create a readable date like "May-2025"
            $advance_period = date('M-Y', mktime(0, 0, 0, $loan->advance_month, 1, $loan->advance_year));
        }
        
        if ($loan->installment_amount > 0) {
            $installment_amount = number_format($loan->installment_amount, 2);
        }

        // Build the row in the new order
        $row = [
            $serial_number,
            date('d-M-Y', strtotime($loan->creation_date)),
            $loan->employee_name,
            $loan->employee_id,
            $loan->branch_name ?? 'N/A',
            number_format($loan->loan_amount, 2),
            $loan_type_formatted,
            $advance_period,
            $installment_amount,
            number_format($loan->paid_amount, 2),
            number_format($loan->outstanding_amount, 2)
        ];
        
        fputcsv($output, $row, ",", "\"", "\\"); // Write the row
        
        $serial_number++; // Increment serial number
    }
}

// 7. Close stream and exit
fclose($output);
exit();
?>