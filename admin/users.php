<?php
// new_ufmhrm/admin/users.php (User Management Hub with RBAC)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$currentUser = getCurrentUser();
// This will work correctly with your updated getCurrentUser() function
$isSuperAdmin = in_array('superadmin', $currentUser['roles'] ?? []);

// --- Master List of All Permissions & Roles in the System ---
$permissions = [
    'Dashboard' => ['dashboard:view'],
    'Employees' => ['employee:create', 'employee:view', 'employee:edit', 'employee:delete'],
    'Attendance' => ['attendance:view', 'attendance:manual_entry'],
    'Leave' => ['leave:apply', 'leave:approve'],
    'Loans' => ['loan:apply', 'loan:approve'],
    'Payroll' => ['payroll:generate', 'payroll:approve', 'payroll:disburse', 'payslip:view'],
    'Users' => ['user:view', 'user:create', 'user:edit_roles', 'user:delete'],
    'Reports' => ['report:view_all', 'report:view_location'],
    'System' => ['activity_log:view', 'permissions:manage']
];
$roles = ['superadmin', 'admin-sirajgonj', 'admin-rampura', 'accounts-sirajgonj', 'accounts-rampura', 'attendant-sirajgonj', 'attendant-rampura', 'employee'];


// --- LOGIC: Handle ALL form submissions before any HTML is sent ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isSuperAdmin) {
    // 1. Handle New User Creation
    if (isset($_POST['create_user'])) {
        $employee_id = (int)$_POST['employee_id'];
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $user_roles = $_POST['roles'] ?? [];

        if (!empty($username) && !empty($password) && !empty($user_roles) && !empty($employee_id)) {
            $db->getPdo()->beginTransaction();
            try {
                $employee = $db->query("SELECT first_name, last_name FROM employees WHERE id = ?", [$employee_id])->first();
                $db->insert('users', ['employee_id' => $employee_id, 'username' => $username, 'password' => password_hash($password, PASSWORD_DEFAULT), 'full_name' => $employee->first_name . ' ' . $employee->last_name]);
                $userId = $db->getPdo()->lastInsertId();
                foreach ($user_roles as $role) {
                    $db->insert('user_roles', ['user_id' => $userId, 'role' => $role]);
                }
                $db->getPdo()->commit();
                $_SESSION['success_flash'] = 'New user created successfully.';
            } catch (Exception $e) {
                $db->getPdo()->rollBack();
                $_SESSION['error_flash'] = 'Error: Username or employee may already be linked.';
            }
        } else {
            $_SESSION['error_flash'] = 'Please fill in all required fields.';
        }
        header('Location: users.php?tab=create');
        exit();
    }

    // 2. Handle Role Update
    if (isset($_POST['update_roles'])) {
        $userId = (int)$_POST['user_id'];
        $newRoles = $_POST['roles'] ?? [];
        
        $db->getPdo()->beginTransaction();
        try {
            $db->query("DELETE FROM user_roles WHERE user_id = ?", [$userId]);
            if (!empty($newRoles)) {
                foreach ($newRoles as $role) {
                    $db->insert('user_roles', ['user_id' => $userId, 'role' => $role]);
                }
            }
            $db->getPdo()->commit();
            $_SESSION['success_flash'] = 'User roles updated successfully.';
        } catch (Exception $e) {
            $db->getPdo()->rollBack();
            $_SESSION['error_flash'] = 'Error updating roles.';
        }
        header('Location: users.php');
        exit();
    }
    
    // 3. Handle User Deletion (Soft Delete)
    if (isset($_POST['delete_user'])) {
        $userId = (int)$_POST['user_id'];
        if ($userId === $currentUser['id']) {
            $_SESSION['error_flash'] = 'You cannot delete your own account.';
        } else {
            $db->query("UPDATE users SET deleted_at = NOW(), status = 'inactive' WHERE id = ?", [$userId]);
            $_SESSION['success_flash'] = 'User has been deactivated.';
        }
        header('Location: users.php');
        exit();
    }
    
    // 4. Handle Permissions Update
    if (isset($_POST['save_permissions'])) {
        $submitted_permissions = $_POST['permissions'] ?? [];
        $db->getPdo()->beginTransaction();
        try {
            $db->query("DELETE FROM role_permissions");
            // Always ensure superadmin has full permissions
            foreach ($permissions as $module => $perms) {
                foreach ($perms as $permission) {
                    $db->insert('role_permissions', ['role' => 'superadmin', 'permission' => $permission]);
                }
            }
            // Save the rest of the roles
            foreach ($submitted_permissions as $role => $perms) {
                if ($role === 'superadmin') continue;
                foreach ($perms as $permission => $value) {
                    $db->insert('role_permissions', ['role' => $role, 'permission' => $permission]);
                }
            }
            $db->getPdo()->commit();
            $_SESSION['success_flash'] = 'Permissions have been updated successfully.';
        } catch (Exception $e) {
            $db->getPdo()->rollBack();
            $_SESSION['error_flash'] = 'An error occurred while saving permissions.';
        }
        header('Location: users.php?tab=permissions');
        exit();
    }
}

$pageTitle = 'User Management - ' . APP_NAME;
include_once '../templates/header.php';

// --- DATA FETCHING for different tabs ---
$allUsers = $db->query("SELECT u.id, u.username, e.first_name, e.last_name, p.name as position_name, (SELECT GROUP_CONCAT(role ORDER BY role SEPARATOR ', ') FROM user_roles WHERE user_id = u.id) as roles FROM users u LEFT JOIN employees e ON u.employee_id = e.id LEFT JOIN positions p ON e.position_id = p.id WHERE u.deleted_at IS NULL ORDER BY e.first_name")->results();
$unlinkedEmployees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' AND id NOT IN (SELECT employee_id FROM users WHERE employee_id IS NOT NULL AND deleted_at IS NULL)")->results();
$activityLog = $isSuperAdmin ? $db->query("SELECT al.action, al.description, al.created_at, u.username FROM activity_log al JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 100")->results() : [];
$saved_permissions_raw = $db->query("SELECT * FROM role_permissions")->results();
$saved_permissions = [];
foreach ($saved_permissions_raw as $perm) { $saved_permissions[$perm->role][$perm->permission] = true; }
?>

<div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-xl border p-6">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3"><div class="h-12 w-12 bg-primary-100 rounded-xl flex items-center justify-center"><i class="fas fa-users-cog text-primary-600 text-xl"></i></div>User Management</h1>
    </div>

    <div x-data="{ activeTab: 'users', editUserId: null }" x-init="()=>{ const params = new URLSearchParams(window.location.search); if (params.get('tab')) { activeTab = params.get('tab'); } window.history.replaceState({}, document.title, window.location.pathname); }">
        <div class="border-b border-gray-200"><nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="#users" @click.prevent="activeTab = 'users'; editUserId = null" :class="{'border-primary-500 text-primary-600': activeTab === 'users'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-list"></i> All Users</a>
            <?php if ($isSuperAdmin): ?>
            <a href="#create" @click.prevent="activeTab = 'create'; editUserId = null" :class="{'border-primary-500 text-primary-600': activeTab === 'create'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-user-plus"></i> Create User</a>
            <a href="#activity" @click.prevent="activeTab = 'activity'; editUserId = null" :class="{'border-primary-500 text-primary-600': activeTab === 'activity'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-history"></i> Activity Log</a>
            <a href="#permissions" @click.prevent="activeTab = 'permissions'; editUserId = null" :class="{'border-primary-500 text-primary-600': activeTab === 'permissions'}" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-user-shield"></i> Permissions</a>
            <?php endif; ?>
        </nav></div>

        <div class="mt-6">
            <div x-show="activeTab === 'users'" x-cloak>
                <div class="bg-white rounded-2xl shadow-xl border overflow-hidden"><div class="p-6 border-b"><h2 class="text-xl font-bold">System Users</h2></div><div class="overflow-x-auto"><table class="min-w-full divide-y">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Full Name</th><th class="px-6 py-3 text-left">Username</th><th class="px-6 py-3 text-left">Assigned Roles</th><?php if($isSuperAdmin): ?><th class="px-6 py-3 text-center">Actions</th><?php endif; ?></tr></thead>
                    <tbody class="divide-y"><?php foreach ($allUsers as $user): ?><tr>
                        <td class="px-6 py-4"><div class="font-semibold"><?php echo htmlspecialchars($user->first_name . ' ' . $user->last_name); ?></div><div class="text-sm text-gray-500"><?php echo htmlspecialchars($user->position_name ?? 'N/A'); ?></div></td>
                        <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($user->username); ?></td>
                        <td class="px-6 py-4"><div class="flex flex-wrap gap-2 max-w-md">
                            <?php $current_roles = $user->roles ? explode(', ', $user->roles) : [];
                            foreach ($current_roles as $role): ?><span class="px-2 py-1 text-xs font-semibold rounded-full bg-primary-100 text-primary-800"><?php echo ucfirst(str_replace(['-','_'], ' ', $role)); ?></span><?php endforeach; ?>
                        </div></td>
                        <?php if ($isSuperAdmin): ?><td class="px-6 py-4 text-center whitespace-nowrap">
                            <button @click="activeTab = 'edit'; editUserId = <?php echo $user->id; ?>" class="px-3 py-1 text-sm bg-blue-500 text-white rounded-md hover:bg-blue-600">Edit Roles</button>
                            <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to deactivate this user?');"><input type="hidden" name="delete_user" value="1"><input type="hidden" name="user_id" value="<?php echo $user->id; ?>"><button type="submit" class="px-3 py-1 text-sm bg-red-500 text-white rounded-md hover:bg-red-600">Delete</button></form>
                        </td><?php endif; ?>
                    </tr><?php endforeach; ?></tbody>
                </table></div></div>
            </div>

            <?php if ($isSuperAdmin): ?>
            <div x-show="activeTab === 'create'" x-cloak>
                <div class="bg-white rounded-2xl shadow-xl border p-8 max-w-2xl mx-auto"><h2 class="text-2xl font-bold mb-6">Create New User</h2>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="create_user" value="1">
                        <div><label class="block text-sm font-medium">Link to Employee</label><select name="employee_id" required class="mt-1 w-full rounded-md border-gray-300"><option value="">Select an unlinked employee...</option><?php foreach($unlinkedEmployees as $emp): ?><option value="<?php echo $emp->id; ?>"><?php echo htmlspecialchars($emp->first_name . ' ' . $emp->last_name); ?></option><?php endforeach; ?></select></div>
                        <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-medium">Username</label><input type="text" name="username" required class="mt-1 w-full rounded-md border-gray-300"></div><div><label class="block text-sm font-medium">Password</label><input type="password" name="password" required class="mt-1 w-full rounded-md border-gray-300"></div></div>
                        <div><label class="block text-sm font-medium">Assign Roles</label><select name="roles[]" multiple required class="mt-1 w-full rounded-md border-gray-300 h-40"><?php foreach ($roles as $role): ?><option value="<?php echo $role; ?>"><?php echo ucfirst(str_replace(['-','_'], ' ', $role)); ?></option><?php endforeach; ?></select><p class="text-xs text-gray-500 mt-1">Hold Ctrl (or Cmd on Mac) to select multiple roles.</p></div>
                        <div class="flex justify-end"><button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg font-bold">Create User Account</button></div>
                    </form>
                </div>
            </div>
            <div x-show="activeTab === 'edit'" x-cloak>
                <?php foreach($allUsers as $user): ?>
                <div x-show="editUserId === <?php echo $user->id; ?>" class="bg-white rounded-2xl shadow-xl border p-8 max-w-2xl mx-auto">
                    <h2 class="text-2xl font-bold mb-6">Edit Roles for <?php echo htmlspecialchars($user->first_name . ' ' . $user->last_name); ?></h2>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="update_roles" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $user->id; ?>">
                        <div><label class="block text-sm font-medium">Assign Roles</label><select name="roles[]" multiple required class="mt-1 w-full rounded-md border-gray-300 h-40">
                            <?php $current_roles = $user->roles ? explode(', ', $user->roles) : [];
                            foreach ($roles as $role): ?><option value="<?php echo $role; ?>" <?php if(in_array($role, $current_roles)) echo 'selected'; ?>><?php echo ucfirst(str_replace(['-','_'], ' ', $role)); ?></option><?php endforeach; ?>
                        </select><p class="text-xs text-gray-500 mt-1">Hold Ctrl (or Cmd on Mac) to select multiple roles.</p></div>
                        <div class="flex justify-end gap-4"><button type="button" @click="activeTab = 'users'; editUserId = null" class="px-6 py-3 bg-gray-500 text-white rounded-lg font-semibold">Cancel</button><button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg font-bold">Save Changes</button></div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <div x-show="activeTab === 'activity'" x-cloak>
                <div class="bg-white rounded-2xl shadow-xl border overflow-hidden"><div class="p-6 border-b"><h2 class="text-xl font-bold">Recent User Activity</h2></div><div class="overflow-x-auto"><table class="min-w-full divide-y">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Timestamp</th><th class="px-6 py-3 text-left">User</th><th class="px-6 py-3 text-left">Action</th><th class="px-6 py-3 text-left">Description</th></tr></thead>
                    <tbody class="divide-y"><?php foreach ($activityLog as $log): ?><tr><td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap"><?php echo date('M d, Y h:i A', strtotime($log->created_at)); ?></td><td class="px-6 py-4 font-semibold text-primary-700"><?php echo htmlspecialchars($log->username); ?></td><td class="px-6 py-4 text-sm font-medium"><?php echo htmlspecialchars($log->action); ?></td><td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($log->description); ?></td></tr><?php endforeach; ?></tbody>
                </table></div></div>
            </div>
            <div x-show="activeTab === 'permissions'" x-cloak>
                <form method="POST">
                    <div class="bg-white rounded-2xl shadow-xl border overflow-hidden">
                        <div class="p-6 border-b"><h2 class="text-xl font-bold">Role Permissions</h2><p class="text-sm text-gray-600 mt-1">Check the boxes to grant permissions to each role.</p></div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50"><tr>
                                    <th class="sticky left-0 bg-primary-50 px-6 py-3 text-left text-xs font-bold text-primary-800 uppercase">Permission</th>
                                    <?php foreach ($roles as $role): ?><th class="px-6 py-3 text-center text-xs font-bold text-primary-800 uppercase"><?php echo str_replace(['-','_'], ' ', $role); ?></th><?php endforeach; ?>
                                </tr></thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($permissions as $module => $perms): ?>
                                        <tr class="bg-gray-50"><td colspan="<?php echo count($roles) + 1; ?>" class="px-6 py-2 text-sm font-semibold text-gray-600"><?php echo $module; ?></td></tr>
                                        <?php foreach ($perms as $permission): ?><tr class="hover:bg-gray-50">
                                            <td class="sticky left-0 bg-white hover:bg-gray-50 px-6 py-4 text-sm font-medium"><?php echo ucwords(str_replace(':', ' - ', str_replace('_', ' ', $permission))); ?></td>
                                            <?php foreach ($roles as $role): ?><td class="px-6 py-4 text-center">
                                                <?php $isChecked = isset($saved_permissions[$role][$permission]); $isDisabled = ($role === 'superadmin'); ?>
                                                <input type="checkbox" name="permissions[<?php echo $role; ?>][<?php echo $permission; ?>]" class="h-5 w-5 text-primary-600" <?php if ($isChecked) echo 'checked'; ?> <?php if ($isDisabled) echo 'checked disabled'; ?>>
                                            </td><?php endforeach; ?>
                                        </tr><?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-6 bg-gray-50 border-t flex justify-end">
                            <button type="submit" name="save_permissions" class="px-8 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg font-bold shadow-md"><i class="fas fa-save mr-2"></i>Save Permissions</button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>