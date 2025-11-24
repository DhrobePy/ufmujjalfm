<?php
// Database configuration
$host = 'localhost';
$dbname = 'ujjalfmc_hr';
$username = 'ujjalfmc_hr';
$password = 'ujjalfmhr1234';



header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Get POST data
$employees = isset($_POST['employees']) ? $_POST['employees'] : [];
$dates = isset($_POST['dates']) ? $_POST['dates'] : [];
$status = isset($_POST['status']) ? $_POST['status'] : 'present';
$branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 1;

// Validate input
if (empty($employees) || empty($dates)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select employees and dates'
    ]);
    exit;
}

// Validate status
if (!in_array($status, ['present', 'absent'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    $successCount = 0;
    $updateCount = 0;
    $insertCount = 0;

    foreach ($employees as $employee_id) {
        foreach ($dates as $date) {
            // Check if attendance record already exists
            $checkSql = "SELECT id FROM attendance 
                        WHERE employee_id = :employee_id 
                        AND date = :date";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([
                ':employee_id' => $employee_id,
                ':date' => $date
            ]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing record
                $updateSql = "UPDATE attendance 
                            SET status = :status,
                                clock_in = :clock_in,
                                clock_out = :clock_out,
                                manual_entry = 1,
                                branch_id = :branch_id
                            WHERE id = :id";
                
                $updateStmt = $pdo->prepare($updateSql);
                
                // Set clock_in and clock_out based on status
                if ($status === 'present') {
                    $clock_in = $date . ' 09:00:00';
                    $clock_out = $date . ' 17:00:00';
                } else {
                    $clock_in = $date . ' 00:00:00';
                    $clock_out = null;
                }
                
                $updateStmt->execute([
                    ':status' => $status,
                    ':clock_in' => $clock_in,
                    ':clock_out' => $clock_out,
                    ':branch_id' => $branch_id,
                    ':id' => $existing['id']
                ]);
                
                $updateCount++;
            } else {
                // Insert new record
                $insertSql = "INSERT INTO attendance 
                            (employee_id, date, status, clock_in, clock_out, manual_entry, branch_id) 
                            VALUES 
                            (:employee_id, :date, :status, :clock_in, :clock_out, 1, :branch_id)";
                
                $insertStmt = $pdo->prepare($insertSql);
                
                // Set clock_in and clock_out based on status
                if ($status === 'present') {
                    $clock_in = $date . ' 09:00:00';
                    $clock_out = $date . ' 17:00:00';
                } else {
                    $clock_in = $date . ' 00:00:00';
                    $clock_out = null;
                }
                
                $insertStmt->execute([
                    ':employee_id' => $employee_id,
                    ':date' => $date,
                    ':status' => $status,
                    ':clock_in' => $clock_in,
                    ':clock_out' => $clock_out,
                    ':branch_id' => $branch_id
                ]);
                
                $insertCount++;
            }
            
            $successCount++;
        }
    }

    $pdo->commit();

    $message = "Successfully marked attendance for " . count($employees) . " employee(s) across " . count($dates) . " date(s). ";
    if ($insertCount > 0) {
        $message .= "Created $insertCount new record(s). ";
    }
    if ($updateCount > 0) {
        $message .= "Updated $updateCount existing record(s).";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'details' => [
            'total' => $successCount,
            'inserted' => $insertCount,
            'updated' => $updateCount
        ]
    ]);

} catch(PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Error saving attendance: ' . $e->getMessage()
    ]);
}
?>