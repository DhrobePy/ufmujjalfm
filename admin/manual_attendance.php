<?php
require_once __DIR__ . '/../core/init.php';

if (!is_superadmin()) {
    header('Location: ../auth/login.php');
    exit();
}

$employee_handler = new Employee($pdo);
$attendance_handler = new Attendance($pdo);

// Handle manual attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $attendance_date = $_POST['attendance_date'];
    $present_employees = $_POST['present_employees'] ?? [];
    
    // Get all active employees
    $all_employees = $employee_handler->get_all_active();
    
    // Clear existing attendance for this date
    $stmt = $pdo->prepare('DELETE FROM attendance WHERE DATE(clock_in) = ?');
    $stmt->execute([$attendance_date]);
    
    foreach ($all_employees as $employee) {
        $employee_id = $employee['id'];
        $is_present = in_array($employee_id, $present_employees);
        
        if ($is_present) {
            // Mark as present with default clock in/out times
            $stmt = $pdo->prepare('
                INSERT INTO attendance (employee_id, clock_in, clock_out, status, manual_entry) 
                VALUES (?, ?, ?, ?, 1)
            ');
            $clock_in = $attendance_date . ' 09:00:00';
            $clock_out = $attendance_date . ' 17:00:00';
            $stmt->execute([$employee_id, $clock_in, $clock_out, 'present']);
        } else {
            // Mark as absent
            $stmt = $pdo->prepare('
                INSERT INTO attendance (employee_id, clock_in, status, manual_entry) 
                VALUES (?, ?, ?, 1)
            ');
            $clock_in = $attendance_date . ' 00:00:00';
            $stmt->execute([$employee_id, $clock_in, 'absent']);
        }
    }
    
    header('Location: manual_attendance.php?success=1&date=' . $attendance_date);
    exit();
}

// Get attendance for selected date
$selected_date = $_GET['date'] ?? date('Y-m-d');
$employees = $employee_handler->get_all_active();

// Get existing attendance for the selected date
$existing_attendance = [];
$stmt = $pdo->prepare('
    SELECT employee_id, status FROM attendance 
    WHERE DATE(clock_in) = ?
');
$stmt->execute([$selected_date]);
while ($row = $stmt->fetch()) {
    $existing_attendance[$row['employee_id']] = $row['status'];
}

$page_title = 'Manual Attendance Entry';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Manual Attendance Entry</h1>
<p class="text-muted">Super Admin can manually mark attendance for all employees on any date.</p>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        Attendance saved successfully for <?php echo htmlspecialchars($_GET['date']); ?>!
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Select Date for Attendance Entry</div>
    <div class="card-body">
        <form action="manual_attendance.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="date" class="form-label">Attendance Date</label>
                <input type="date" class="form-control" name="date" id="date" 
                       value="<?php echo htmlspecialchars($selected_date); ?>" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Load Date</button>
            </div>
            <div class="col-md-6">
                <div class="text-muted">
                    <small>Selected: <strong><?php echo date('F d, Y', strtotime($selected_date)); ?></strong></small>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Employee Attendance - <?php echo date('F d, Y', strtotime($selected_date)); ?></span>
        <div>
            <button type="button" class="btn btn-sm btn-success" onclick="selectAll()">Select All</button>
            <button type="button" class="btn btn-sm btn-warning" onclick="clearAll()">Clear All</button>
        </div>
    </div>
    <div class="card-body">
        <form action="manual_attendance.php" method="POST">
            <input type="hidden" name="save_attendance" value="1">
            <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="50">Present</th>
                            <th>Employee Name</th>
                            <th>Position</th>
                            <th>Department</th>
                            <th>Current Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <?php 
                            $current_status = $existing_attendance[$employee['id']] ?? 'not_marked';
                            $is_present = $current_status === 'present';
                            ?>
                            <tr class="<?php echo $current_status === 'present' ? 'table-success' : ($current_status === 'absent' ? 'table-danger' : ''); ?>">
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input attendance-checkbox" type="checkbox" 
                                               name="present_employees[]" value="<?php echo $employee['id']; ?>"
                                               id="emp_<?php echo $employee['id']; ?>"
                                               <?php echo $is_present ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="emp_<?php echo $employee['id']; ?>"></label>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($employee['position_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($current_status === 'present'): ?>
                                        <span class="badge bg-success">Present</span>
                                    <?php elseif ($current_status === 'absent'): ?>
                                        <span class="badge bg-danger">Absent</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Marked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Are you sure you want to save attendance for <?php echo date('F d, Y', strtotime($selected_date)); ?>?')">
                    Save Attendance
                </button>
                <a href="attendance_history.php" class="btn btn-secondary btn-lg">View Attendance History</a>
            </div>
        </form>
    </div>
</div>

<script>
function selectAll() {
    const checkboxes = document.querySelectorAll('.attendance-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

function clearAll() {
    const checkboxes = document.querySelectorAll('.attendance-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

// Update row styling based on checkbox state
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.attendance-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            if (this.checked) {
                row.classList.remove('table-danger');
                row.classList.add('table-success');
            } else {
                row.classList.remove('table-success');
                row.classList.add('table-danger');
            }
        });
    });
});
</script>

<?php
include __DIR__ . '/../templates/footer.php';
?>
