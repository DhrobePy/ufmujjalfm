<?php
// new_ufmhrm/admin/export_loans.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

// 1. Security Check: Ensure admin is logged in
if (!is_admin_logged_in()) {
    exit('Access Denied.');
}
$currentUser = getCurrentUser();
$isAdmin = in_array($currentUser['role'], ['admin', 'superadmin']);
if (!$isAdmin) {
    exit('Access Denied.');
}

// 2. Set CSV Headers to force download
$filename = "all_employee_loans_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 3. Open the output stream
$output = fopen('php://output', 'w');

// 4. Write the CSV Header Row
// --- FIX IS ON THIS LINE (Line 28) ---
fputcsv($output, [
    'Employee Name',
    'Loan Date',
    'Total Amount (৳)',
    'Outstanding (৳)',
    'Status',
    'Installment Type',
    'Monthly EMI (৳)',
    'Total Installments'
], ",", "\"", "\\"); // <-- Added default parameters

// 5. Fetch the data (same query as the 'All Loans' tab)
$allLoans = $db->query("
    SELECT 
        l.*, 
        e.first_name, 
        e.last_name, 
        (l.amount - IFNULL((SELECT SUM(amount) FROM loan_installments WHERE loan_id = l.id), 0)) as outstanding_balance 
    FROM loans l 
    JOIN employees e ON l.employee_id = e.id 
    ORDER BY l.loan_date DESC
")->results();

// 6. Loop through data and write to the CSV
if ($allLoans) {
    foreach ($allLoans as $loan) {
        $row = [
            $loan->first_name . ' ' . $loan->last_name,
            date('d-M-Y', strtotime($loan->loan_date)),
            number_format($loan->amount, 2),
            number_format($loan->outstanding_balance, 2),
            ucfirst($loan->status),
            ucfirst($loan->installment_type),
            number_format($loan->monthly_payment, 2),
            $loan->installments
        ];
        
        // --- FIX IS ON THIS LINE ---
        fputcsv($output, $row, ",", "\"", "\\"); // <-- Added default parameters
    }
}

// 7. Close the stream and exit
fclose($output);
exit();
?>