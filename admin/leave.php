<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$attendance_handler = new Attendance($pdo);
$employee_handler = new Employee($pdo);

// Handle leave request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_leave'])) {
    $data = [
        'employee_id' => (int)$_POST['employee_id'],
        'leave_type'  => sanitize_input($_POST['leave_type']),
        'start_date'  => sanitize_input($_POST['start_date']),
        'end_date'    => sanitize_input($_POST['end_date']),
        'reason'      => sanitize_input($_POST['reason'])
    ];
    $attendance_handler->request_leave($data);
    header('Location: leave.php?success=1');
    exit();
}

// Handle leave status update
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action == 'approve') {
        $attendance_handler->update_leave_status($id, 'approved');
    } elseif ($action == 'reject') {
        $attendance_handler->update_leave_status($id, 'rejected');
    }
    header('Location: leave.php?updated=1');
    exit();
}

$leave_requests = $attendance_handler->get_all_leave_requests();
$employees = $employee_handler->get_all();
$page_title = 'Leave Management';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Leave Management</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Leave request submitted successfully!</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">Leave status updated successfully!</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>All Leave Requests</span>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveRequestModal">
            Apply for Leave
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leave_requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                            <td><?php echo format_date($request['start_date'], 'M d, Y') . ' - ' . format_date($request['end_date'], 'M d, Y'); ?></td>
                            <td><?php echo htmlspecialchars($request['reason']); ?></td>
                            <td>
                                <?php 
                                $status_class = 'secondary';
                                if ($request['status'] == 'approved') $status_class = 'success';
                                if ($request['status'] == 'rejected') $status_class = 'danger';
                                ?>
                                <span class="badge bg-<?php echo $status_class; ?>"><?php echo ucfirst($request['status']); ?></span>
                            </td>
                            <td>
                                <?php if ($request['status'] == 'pending'): ?>
                                    <a href="leave.php?action=approve&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-success">Approve</a>
                                    <a href="leave.php?action=reject&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-danger">Reject</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Leave Request Modal -->
<div class="modal fade" id="leaveRequestModal" tabindex="-1" aria-labelledby="leaveRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leaveRequestModalLabel">Apply for Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="leave.php" method="POST">
                    <input type="hidden" name="request_leave" value="1">
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
                        <label for="leave_type" class="form-label">Leave Type</label>
                        <input type="text" class="form-control" name="leave_type" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../templates/footer.php';
?>
