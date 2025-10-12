<?php
require_once __DIR__ . '/../core/init.php';

// Role Guard: Only superadmins can access this page
if (!is_superadmin()) {
    // You can redirect to index or show an access denied message
    exit('Access Denied: You do not have permission to manage users.');
}

$user_handler = new User($pdo);

// Handle POST requests (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Handle Delete User ---
    if (isset($_POST['delete_user'])) {
        $user_handler->delete_user((int)$_POST['user_id']);
        header('Location: users.php?deleted=1');
        exit();
    }

    // --- Handle Add/Edit User ---
    $data = [
        'username' => sanitize_input($_POST['username']),
        'phone_number' => sanitize_input($_POST['phone_number']),
        'password' => $_POST['password'], // Don't sanitize password before hashing
        'role' => sanitize_input($_POST['role']),
    ];

    if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        // Update existing user
        $user_handler->update_user((int)$_POST['user_id'], $data);
        header('Location: users.php?updated=1');
    } else {
        // Create new user
        $user_handler->create_user($data);
        header('Location: users.php?created=1');
    }
    exit();
}

$users = $user_handler->get_all_users();
$page_title = 'User Management';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">User Management</h1>
<p class="text-muted">As a superadmin, you can add, edit, and remove system users.</p>

<?php if (isset($_GET['created'])): ?>
    <div class="alert alert-success">User created successfully!</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">User updated successfully!</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-danger">User deleted successfully!</div>
<?php endif; ?>


<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>System Users</span>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="prepareAddModal()">
            <i class="fas fa-plus"></i> Add New User
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Phone Number</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                            <td><span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" onclick='prepareEditModal(<?php echo json_encode($user); ?>)'>Edit</button>
                                <?php if ($user['id'] != 1): // Prevent deleting the main superadmin ?>
                                    <form action="users.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="userForm" action="users.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="user_id">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="phone_number" name="phone_number">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small id="passwordHelp" class="form-text text-muted">Leave blank to keep the current password when editing.</small>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="employee">Employee</option>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const userModal = new bootstrap.Modal(document.getElementById('userModal'));
const modalLabel = document.getElementById('userModalLabel');
const userForm = document.getElementById('userForm');
const passwordInput = document.getElementById('password');

function prepareAddModal() {
    userForm.reset();
    document.getElementById('user_id').value = '';
    modalLabel.textContent = 'Add New User';
    passwordInput.setAttribute('required', 'required');
    userModal.show();
}

function prepareEditModal(user) {
    userForm.reset();
    document.getElementById('user_id').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('phone_number').value = user.phone_number;
    document.getElementById('role').value = user.role;
    
    modalLabel.textContent = 'Edit User: ' + user.username;
    passwordInput.removeAttribute('required');
    userModal.show();
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>