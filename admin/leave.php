<?php
// new_ufmhrm/admin/leave.php (Fully Refactored Leave Hub)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

// Corrected function name
if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// --- LOGIC: Handle ALL form submissions before any HTML is sent ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Admin action: Approve, Reject, or change Paid status
    if (isset($_POST['update_leave_status'])) {
        $id = (int)$_POST['leave_id'];
        $action = $_POST['action'];
        $is_paid = isset($_POST['is_paid']) ? (int)$_POST['is_paid'] : 1;

        if ($action === 'approve') {
            $db->query("UPDATE leave_requests SET status = 'approved', is_paid = ? WHERE id = ?", [$is_paid, $id]);
            $_SESSION['success_flash'] = 'Leave request approved.';
        } elseif ($action === 'reject') {
            $db->query("UPDATE leave_requests SET status = 'rejected' WHERE id = ?", [$id]);
            $_SESSION['error_flash'] = 'Leave request rejected.';
        }
        header('Location: leave.php?tab=pending');
        exit();
    }
    // User action: Apply for leave
    if (isset($_POST['request_leave'])) {
        $db->insert('leave_requests', [
            'employee_id' => (int)$_POST['employee_id'],
            'leave_type'  => $_POST['leave_type'],
            'start_date'  => $_POST['start_date'],
            'end_date'    => $_POST['end_date'],
            'reason'      => $_POST['reason'],
            'status'      => 'pending'
        ]);
        $_SESSION['success_flash'] = 'Leave request submitted successfully!';
        header('Location: leave.php?tab=my_leaves');
        exit();
    }
}


$pageTitle = 'Leave Management - ' . APP_NAME;
include_once '../templates/header.php';

// --- DATA FETCHING for different roles and tabs ---
$currentUser = getCurrentUser(); // Assuming this returns an array with 'role' and 'employee_id'
$isAdmin = in_array($currentUser['role'], ['admin', 'superadmin']);

// --- Data for Admin: Pending Requests Tab ---
$pendingRequests = [];
if ($isAdmin) {
    $pendingRequests = $db->query("
        SELECT lr.*, e.first_name, e.last_name 
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        WHERE lr.status = 'pending' 
        ORDER BY lr.id DESC
    ")->results();
}

// --- Data for Current User: My Leaves Tab ---
$myLeaveHistory = []; // Default to an empty array
if (isset($currentUser['employee_id']) && !empty($currentUser['employee_id'])) {
    $myLeaveHistory = $db->query("
        SELECT * FROM leave_requests 
        WHERE employee_id = ? 
        ORDER BY start_date DESC", 
        [$currentUser['employee_id']]
    )->results();
}

// --- Data for Superadmin: History & Reports Tab ---
$historyList = [];
if ($currentUser['role'] === 'superadmin') {
    $historySearch = $_GET['search'] ?? '';
    $historyDept = $_GET['department'] ?? '';
    $historyStatus = $_GET['status'] ?? '';
    $historyStart = $_GET['start_date'] ?? date('Y-m-01');
    $historyEnd = $_GET['end_date'] ?? date('Y-m-t');

    $whereClauses = ["lr.start_date BETWEEN ? AND ?"];
    $params = [$historyStart, $historyEnd];
    if (!empty($historySearch)) { $whereClauses[] = "CONCAT(e.first_name, ' ', e.last_name) LIKE ?"; $params[] = '%' . $historySearch . '%'; }
    if (!empty($historyDept)) { $whereClauses[] = "d.id = ?"; $params[] = $historyDept; }
    if (!empty($historyStatus)) { $whereClauses[] = "lr.status = ?"; $params[] = $historyStatus; }
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

    $historySql = "
        SELECT lr.*, e.first_name, e.last_name, d.name as department_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        $whereSql ORDER BY lr.start_date DESC
    ";
    $historyList = $db->query($historySql, $params)->results();
    $departments = $db->query("SELECT id, name FROM departments ORDER BY name")->results();
}
?>

<div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-xl border p-6">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3"><div class="h-12 w-12 bg-primary-100 rounded-xl flex items-center justify-center"><i class="fas fa-calendar-alt text-primary-600 text-xl"></i></div>Leave Management</h1>
    </div>

    <div x-data="{ activeTab: '<?php echo $isAdmin ? 'pending' : 'apply'; ?>' }" x-init="()=>{ const params = new URLSearchParams(window.location.search); if (params.get('tab')) { activeTab = params.get('tab'); } }">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <?php if ($isAdmin): ?>
                    <a href="#pending" @click.prevent="activeTab = 'pending'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'pending' }" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-clock"></i> Pending Requests <span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full text-xs"><?php echo count($pendingRequests); ?></span></a>
                <?php endif; ?>
                <a href="#apply" @click.prevent="activeTab = 'apply'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'apply' }" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-paper-plane"></i> Apply for Leave</a>
                <a href="#my_leaves" @click.prevent="activeTab = 'my_leaves'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'my_leaves' }" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-history"></i> My Leaves</a>
                <?php if ($currentUser['role'] === 'superadmin'): ?>
                    <a href="#history" @click.prevent="activeTab = 'history'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'history' }" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-search"></i> History & Reports</a>
                <?php endif; ?>
            </nav>
        </div>

        <div class="mt-6">
            <!-- TAB: PENDING REQUESTS (ADMINS) -->
            <?php if ($isAdmin): ?>
            <div x-show="activeTab === 'pending'" x-cloak>
                <div class="bg-white rounded-2xl shadow-xl border overflow-hidden">
                    <div class="p-6 border-b"><h2 class="text-xl font-bold">Pending Leave Requests</h2></div>
                    <div class="overflow-x-auto"><table class="min-w-full divide-y">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Employee</th><th class="px-6 py-3 text-left">Type</th><th class="px-6 py-3 text-left">Dates</th><th class="px-6 py-3 text-left">Reason</th><th class="px-6 py-3 text-center">Actions</th></tr></thead>
                        <tbody class="divide-y"><?php foreach ($pendingRequests as $req): ?><tr>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($req->first_name . ' ' . $req->last_name); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($req->leave_type); ?></td>
                            <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($req->start_date)) . ' - ' . date('M d, Y', strtotime($req->end_date)); ?></td>
                            <td class="px-6 py-4 text-sm max-w-xs truncate"><?php echo htmlspecialchars($req->reason); ?></td>
                            <td class="px-6 py-4 text-center">
                                <form method="POST" class="inline-flex items-center gap-2">
                                    <input type="hidden" name="update_leave_status" value="1"> <input type="hidden" name="leave_id" value="<?php echo $req->id; ?>">
                                    
                                    <select name="is_paid" class="rounded-md border-gray-300 text-sm">
                                        <option value="1">Paid</option>
                                        <option value="0">Unpaid</option>
                                    </select>
                                    
                                    <button type="submit" name="action" value="approve" class="px-3 py-1 text-sm bg-green-500 text-white rounded-md hover:bg-green-600 transition-colors">
                                        Approve
                                    </button>
                                    
                                    <button type="submit" name="action" value="reject" class="px-3 py-1 text-sm bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors">
                                        Reject
                                    </button>
                                </form>
                            </td>
                        </tr><?php endforeach; ?></tbody>
                    </table></div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- TAB: APPLY FOR LEAVE -->
            <div x-show="activeTab === 'apply'" x-cloak>
                <div class="bg-white rounded-2xl shadow-xl border p-8 max-w-2xl mx-auto">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Submit a Leave Request</h2>
                    <form action="leave.php" method="POST" class="space-y-6">
                        <input type="hidden" name="request_leave" value="1">
                        <!-- If admin is applying for someone else -->
                        <?php if ($isAdmin): ?>
                        <div><label for="employee_id" class="block text-sm font-medium">Employee</label><select name="employee_id" required class="mt-1 w-full rounded-md border-gray-300"><option value="">Select Employee</option><?php foreach($db->query("SELECT id, first_name, last_name FROM employees WHERE status='active'")->results() as $emp): ?><option value="<?php echo $emp->id; ?>"><?php echo $emp->first_name . ' ' . $emp->last_name; ?></option><?php endforeach; ?></select></div>
                        <?php else: ?>
                            <input type="hidden" name="employee_id" value="<?php echo $currentUser['employee_id']; ?>">
                        <?php endif; ?>
                        <div><label for="leave_type" class="block text-sm font-medium">Leave Type</label><input type="text" name="leave_type" required class="mt-1 w-full rounded-md border-gray-300" placeholder="e.g., Sick Leave, Vacation"></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label for="start_date" class="block text-sm font-medium">Start Date</label><input type="date" name="start_date" required class="mt-1 w-full rounded-md border-gray-300"></div>
                            <div><label for="end_date" class="block text-sm font-medium">End Date</label><input type="date" name="end_date" required class="mt-1 w-full rounded-md border-gray-300"></div>
                        </div>
                        <div><label for="reason" class="block text-sm font-medium">Reason for Leave</label><textarea name="reason" rows="4" class="mt-1 w-full rounded-md border-gray-300"></textarea></div>
                        <div class="flex justify-end"><button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg font-bold">Submit Request</button></div>
                    </form>
                </div>
            </div>

            <!-- TAB: MY LEAVES -->
            <div x-show="activeTab === 'my_leaves'" x-cloak>
                 <div class="bg-white rounded-2xl shadow-xl border overflow-hidden">
                    <div class="p-6 border-b"><h2 class="text-xl font-bold">My Leave History</h2></div>
                    <div class="overflow-x-auto"><table class="min-w-full divide-y">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Type</th><th class="px-6 py-3 text-left">Dates</th><th class="px-6 py-3 text-left">Reason</th><th class="px-6 py-3 text-center">Status</th><th class="px-6 py-3 text-center">Paid Status</th></tr></thead>
                        <tbody class="divide-y"><?php foreach ($myLeaveHistory as $req): ?><tr>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($req->leave_type); ?></td>
                            <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($req->start_date)) . ' - ' . date('M d, Y', strtotime($req->end_date)); ?></td>
                            <td class="px-6 py-4 max-w-xs truncate"><?php echo htmlspecialchars($req->reason); ?></td>
                            <td class="px-6 py-4 text-center"><?php $s_class = 'bg-gray-100 text-gray-800'; if ($req->status == 'approved') $s_class = 'bg-green-100 text-green-800'; if ($req->status == 'rejected') $s_class = 'bg-red-100 text-red-800'; ?><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $s_class; ?>"><?php echo ucfirst($req->status); ?></span></td>
                            <td class="px-6 py-4 text-center text-sm"><?php echo ($req->status == 'approved') ? ($req->is_paid ? 'Paid' : 'Unpaid') : 'N/A'; ?></td>
                        </tr><?php endforeach; ?></tbody>
                    </table></div>
                </div>
            </div>

            <!-- TAB: HISTORY & REPORTS (SUPERADMIN) -->
            <?php if ($currentUser['role'] === 'superadmin'): ?>
            <div x-show="activeTab === 'history'" x-cloak>
                <div class="bg-white rounded-2xl shadow-xl border p-6 mb-6">
                    <form method="GET"><input type="hidden" name="tab" value="history"><div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                        <div><label for="search" class="block text-sm font-medium">Search</label><input type="text" name="search" value="<?php echo htmlspecialchars($historySearch); ?>" class="mt-1 w-full rounded-md border-gray-300"></div>
                        <div><label for="department" class="block text-sm font-medium">Department</label><select name="department" class="mt-1 w-full rounded-md border-gray-300"><option value="">All</option><?php foreach($departments as $dept): ?><option value="<?php echo $dept->id; ?>" <?php if($historyDept == $dept->id) echo 'selected'; ?>><?php echo $dept->name; ?></option><?php endforeach; ?></select></div>
                        <div><label for="status" class="block text-sm font-medium">Status</label><select name="status" class="mt-1 w-full rounded-md border-gray-300"><option value="">All</option><option value="approved" <?php if($historyStatus=='approved') echo 'selected'; ?>>Approved</option><option value="rejected" <?php if($historyStatus=='rejected') echo 'selected'; ?>>Rejected</option><option value="pending" <?php if($historyStatus=='pending') echo 'selected'; ?>>Pending</option></select></div>
                        <div><label for="start_date" class="block text-sm font-medium">Start Date</label><input type="date" name="start_date" value="<?php echo $historyStart; ?>" class="mt-1 w-full rounded-md border-gray-300"></div>
                        <div><label for="end_date" class="block text-sm font-medium">End Date</label><input type="date" name="end_date" value="<?php echo $historyEnd; ?>" class="mt-1 w-full rounded-md border-gray-300"></div>
                        <div class="md:col-start-5 flex justify-end gap-2"><a href="?tab=history" class="px-4 py-2 bg-gray-200 rounded-md">Clear</a><button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md">Filter</button></div>
                    </form>
                </div>
                <div class="bg-white rounded-2xl shadow-xl border"><div class="p-6 border-b flex justify-between"><h2 class="text-xl font-bold">Leave History Report</h2><div class="flex gap-2"><button onclick="exportToCSV('history-table')" class="px-4 py-2 text-sm bg-green-600 text-white rounded-md">CSV</button><button onclick="exportToPDF('history-table')" class="px-4 py-2 text-sm bg-red-600 text-white rounded-md">PDF</button></div></div>
                    <div class="overflow-x-auto"><table id="history-table" class="min-w-full divide-y"><thead>...</thead><tbody class="divide-y"><?php foreach ($historyList as $item): ?><tr>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($item->department_name); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($item->leave_type); ?></td><td class="px-6 py-4"><?php echo date('d M Y', strtotime($item->start_date)) . ' to ' . date('d M Y', strtotime($item->end_date)); ?></td><td class="px-6 py-4 text-center"><?php echo ucfirst($item->status); ?></td><td class="px-6 py-4 text-center"><?php echo ($item->status == 'approved') ? ($item->is_paid ? 'Paid' : 'Unpaid') : 'N/A'; ?></td>
                    </tr><?php endforeach; ?></tbody></table></div>
                </div>
            </div>
            <?php endif; ?>

             <!-- TAB 4: BIOMETRIC -->
            <div x-show="activeTab === 'biometric'" x-cloak>
                 <div class="bg-white rounded-2xl shadow-xl border p-12 text-center"><i class="fas fa-fingerprint text-primary-300 text-6xl mb-6"></i><h2 class="text-2xl font-bold">Biometric Integration</h2><p class="text-gray-500 mt-2">This feature is coming soon.</p></div>
            </div>
        </div>
    </div>
</div>

<script>
    function exportToCSV(tableId) { /* ... Same CSV export script as before ... */ }
    function exportToPDF(tableId) { /* ... Same PDF export script as before ... */ }
</script>

<?php include_once '../templates/footer.php'; ?>