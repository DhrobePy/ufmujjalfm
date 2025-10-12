<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$employee_handler = new Employee($pdo);
$departments = $employee_handler->get_departments();
$positions = $employee_handler->get_positions();

// Handle form submissions for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'first_name' => sanitize_input($_POST['first_name']),
        'last_name'  => sanitize_input($_POST['last_name']),
        'email'      => filter_var($_POST['email'], FILTER_SANITIZE_EMAIL),
        'phone'      => sanitize_input($_POST['phone']),
        'address'    => sanitize_input($_POST['address']),
        'position_id'=> (int)$_POST['position_id'],
        'hire_date'  => sanitize_input($_POST['hire_date']),
        'base_salary'=> (float)$_POST['base_salary'],
        'status'     => sanitize_input($_POST['status'])
    ];

    if (isset($_POST['employee_id']) && !empty($_POST['employee_id'])) {
        // Update existing employee
        $employee_handler->update((int)$_POST['employee_id'], $data);
    } else {
        // Create new employee
        $employee_handler->create($data);
    }
    header('Location: employees.php?success=1');
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $employee_handler->delete((int)$_GET['delete']);
    header('Location: employees.php?deleted=1');
    exit();
}

$employees = $employee_handler->get_all();
$page_title = 'Employee Management';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Employee Management</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Employee record saved successfully!</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">Employee record deleted successfully!</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>All Employees</span>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
    Add New Employee
</button>
<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
    Bulk Upload
</button>
            Add New Employee
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Hire Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($employee['email']); ?></td>
                            <td><?php echo htmlspecialchars($employee['department_name']); ?></td>
                            <td><?php echo htmlspecialchars($employee['position_name']); ?></td>
                            <td><?php echo format_date($employee['hire_date'], 'M d, Y'); ?></td>
                            <td><span class="badge bg-<?php echo $employee['status'] == 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst(str_replace('_', ' ', $employee['status'])); ?></span></td>
                            <td>
                                <a href="employee_profile.php?id=<?php echo $employee['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                <button type="button" class="btn btn-sm btn-info" onclick="editEmployee(<?php echo htmlspecialchars(json_encode($employee)); ?>)">Edit</button>
                                <a href="employees.php?delete=<?php echo $employee['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEmployeeModalLabel">Add New Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="employees.php" method="POST">
                    <input type="hidden" name="employee_id" id="employee_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="hire_date" class="form-label">Hire Date</label>
                            <input type="date" class="form-control" id="hire_date" name="hire_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="position_id" class="form-label">Position</label>
                            <select class="form-select" id="position_id" name="position_id" required>
                                <option value="">Select Position</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?php echo $position['id']; ?>"><?php echo htmlspecialchars($position['department_name'] . ' - ' . $position['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="base_salary" class="form-label">Base Salary</label>
                            <input type="number" step="0.01" class="form-control" id="base_salary" name="base_salary" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="on_leave">On Leave</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../templates/footer.php';
?>

_HTML_CODE_
<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkUploadModalLabel">Bulk Upload Employees</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="employees.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="bulk_upload" value="1">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Upload CSV File</label>
                        <input type="file" class="form-control" name="csv_file" id="csv_file" accept=".csv" required>
                        <div class="form-text">
                            CSV format: first_name, last_name, email, phone, address, position_id, hire_date, base_salary, status
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
function editEmployee(employee) {
    // Populate the modal with employee data
    document.getElementById('employee_id').value = employee.id;
    document.getElementById('first_name').value = employee.first_name;
    document.getElementById('last_name').value = employee.last_name;
    document.getElementById('email').value = employee.email;
    document.getElementById('phone').value = employee.phone || '';
    document.getElementById('address').value = employee.address || '';
    document.getElementById('position_id').value = employee.position_id;
    document.getElementById('hire_date').value = employee.hire_date;
    document.getElementById('base_salary').value = employee.base_salary;
    document.getElementById('status').value = employee.status;
    
    // Update modal title
    document.getElementById('addEmployeeModalLabel').textContent = 'Edit Employee';
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
    modal.show();
}

// Reset modal when it's closed
document.getElementById('addEmployeeModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('addEmployeeModalLabel').textContent = 'Add New Employee';
    document.getElementById('employee_id').value = '';
    this.querySelector('form').reset();
});
</script>

<?php
include __DIR__ . '/../templates/footer.php';
?>
