<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$attendance_handler = new Attendance($pdo);

// Manual clock-in/out for admins
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_attendance'])) {
    $employee_id = (int)$_POST['employee_id'];
    if ($_POST['action'] == 'clock_in') {
        $attendance_handler->clock_in($employee_id);
    } elseif ($_POST['action'] == 'clock_out') {
        $attendance_handler->clock_out($employee_id);
    }
    header('Location: attendance.php?success=1');
    exit();
}

$today_attendance = $attendance_handler->get_today_attendance();
$employee_handler = new Employee($pdo);
$employees = $employee_handler->get_all();
$page_title = 'Attendance';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Daily Attendance</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Attendance record updated successfully!</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Today's Records</span>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#manualAttendanceModal">
            Manual Entry
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($today_attendance as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                            <td><?php echo $record['clock_in'] ? format_date($record['clock_in']) : 'N/A'; ?></td>
                            <td><?php echo $record['clock_out'] ? format_date($record['clock_out']) : 'N/A'; ?></td>
                            <td><span class="badge bg-success"><?php echo ucfirst($record['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Manual Attendance Modal -->
<div class="modal fade" id="manualAttendanceModal" tabindex="-1" aria-labelledby="manualAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manualAttendanceModalLabel">Manual Attendance Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="attendance.php" method="POST">
                    <input type="hidden" name="manual_attendance" value="1">
                    <div class="mb-3">
                        <label for="employee_id" class="form-label">Employee</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="action" class="form-label">Action</label>
                        <select class="form-select" name="action" required>
                            <option value="clock_in">Clock In</option>
                            <option value="clock_out">Clock Out</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../templates/footer.php';
?>
