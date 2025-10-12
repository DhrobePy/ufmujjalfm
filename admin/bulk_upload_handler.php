<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_upload'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $employee_handler = new Employee($pdo);
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");

        // Skip header row if your CSV has one
        fgetcsv($handle);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Basic validation
            if (count($data) >= 8) {
                $employee_data = [
                    'first_name'  => $data[0],
                    'last_name'   => $data[1],
                    'email'       => $data[2],
                    'phone'       => $data[3],
                    'address'     => $data[4],
                    'position_id' => (int)$data[5],
                    'hire_date'   => $data[6],
                    'base_salary' => (float)$data[7],
                    'status'      => $data[8] ?? 'active'
                ];
                $employee_handler->add($employee_data);
            }
        }
        fclose($handle);
        header('Location: employees.php?bulk_success=1');
        exit();
    } else {
        header('Location: employees.php?bulk_error=1');
        exit();
    }
} else {
    header('Location: employees.php');
    exit();
}
?>
