<?php
require_once __DIR__ . '/../core/init.php';

// Security check: Ensure user is a superadmin and this is a POST request
if (!is_superadmin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$payroll_handler = new Payroll($pdo);

// Get data from the POST request
$payroll_id = $_POST['payroll_id'] ?? 0;
$data = [
    'gross_salary' => (float)($_POST['gross_salary'] ?? 0),
    'absence_deduction' => (float)($_POST['absence_deduction'] ?? 0),
    'advance_deduction' => (float)($_POST['advance_deduction'] ?? 0),
    'loan_deduction' => (float)($_POST['loan_deduction'] ?? 0),
];

if (!$payroll_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Payroll ID.']);
    exit();
}

// Call the new update method
if ($payroll_handler->update_single_payroll_record($payroll_id, $data)) {
    // Recalculate net salary to send back to the front-end
    $net_salary = $data['gross_salary'] - ($data['absence_deduction'] + $data['advance_deduction'] + $data['loan_deduction']);
    echo json_encode([
        'success' => true, 
        'message' => 'Payroll updated successfully!',
        'newData' => [
            'gross_salary' => number_format($data['gross_salary'], 2),
            'absence_deduction' => number_format($data['absence_deduction'], 2),
            'advance_deduction' => number_format($data['advance_deduction'], 2),
            'loan_deduction' => number_format($data['loan_deduction'], 2),
            'net_salary' => number_format($net_salary, 2)
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update payroll record.']);
}