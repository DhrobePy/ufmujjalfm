<?php
// new_ufmhrm/admin/update_payroll_status.php

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payrollId = (int)$_POST['payroll_id'];
    $newStatus = $_POST['status'];
    $allowedStatuses = ['disbursed', 'paid'];

    if ($payrollId > 0 && in_array($newStatus, $allowedStatuses)) {
        $db->update('payrolls', $payrollId, ['status' => $newStatus]);
        $_SESSION['success_flash'] = 'Payroll status updated successfully.';
    } else {
        $_SESSION['error_flash'] = 'Invalid status update request.';
    }
}

header('Location: ' . $_SERVER['HTTP_REFERER']); // Go back to the previous page
exit();