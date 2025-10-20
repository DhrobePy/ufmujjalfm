<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$advance_handler = new SalaryAdvance($pdo);
$employee_handler = new Employee($pdo);

// Handle advance request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_advance'])) {
    $data = [
        'employee_id' => (int)$_POST['employee_id'],
        'amount'      => (float)$_POST['amount']
    ];
    $advance_handler->request_advance($data);
    header('Location: salary_advance.php?success=1');
    exit();
}

// Handle advance status update
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action == 'approve') {
        $advance_handler->update_advance_status($id, 'approved');
    } elseif ($action == 'reject') {
        $advance_handler->update_advance_status($id, 'rejected');
    }
    header('Location: salary_advance.php?updated=1');
    exit();
}

$advances = $advance_handler->get_all_advances();
$employees = $employee_handler->get_all();
$page_title = 'Salary Advance';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Salary Advance Management</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Advance request submitted successfully!</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">Advance status updated successfully!</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>All Advance Requests</span>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#advanceRequestModal">
            Request Advance
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advances as $advance): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($advance['first_name'] . ' ' . $advance['last_name']); ?></td>
                            <td><?php echo format_date($advance['advance_date'], 'M d, Y'); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($advance['amount'], 2)); ?></td>
                            <td>
                                <?php 
                                $status_class = 'secondary';
                                if ($advance['status'] == 'approved') $status_class = 'success';
                                if ($advance['status'] == 'rejected') $status_class = 'danger';
                                if ($advance['status'] == 'paid') $status_class = 'info';
                                ?>
                                <span class="badge bg-<?php echo $status_class; ?>"><?php echo ucfirst($advance['status']); ?></span>
                            </td>
                            <td>
                                <?php if ($advance['status'] == 'pending'): ?>
                                    <a href="salary_advance.php?action=approve&id=<?php echo $advance['id']; ?>" class="btn btn-sm btn-success">Approve</a>
                                    <a href="salary_advance.php?action=reject&id=<?php echo $advance['id']; ?>" class="btn btn-sm btn-danger">Reject</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Advance Request Modal -->
<div class="modal fade" id="advanceRequestModal" tabindex="-1" aria-labelledby="advanceRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="advanceRequestModalLabel">Request Salary Advance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="salary_advance.php" method="POST">
                    <input type="hidden" name="request_advance" value="1">
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
                        <label for="amount" class="form-label">Amount</label>
                        <input type="number" step="0.01" class="form-control" name="amount" required>
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
