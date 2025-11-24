<?php
// new_ufmhrm/admin/leave.php

ini_set('display_errors', 1); error_reporting(E_ALL);
require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$currentUser = getCurrentUser();
$isSuperAdmin = is_superadmin(); // Helper function we added earlier

// --- LOGIC: Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Apply for Leave (Admin Action)
    if (isset($_POST['apply_leave'])) {
        $employee_id = (int)$_POST['employee_id'];
        $leave_type = $_POST['leave_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $is_paid = (int)$_POST['is_paid'];
        $reason = trim($_POST['reason']);

        if ($employee_id && $start_date && $end_date) {
            $db->insert('leave_requests', [
                'employee_id' => $employee_id,
                'leave_type' => $leave_type,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'reason' => $reason,
                'status' => 'pending', // Needs approval
                'is_paid' => $is_paid
            ]);
            $_SESSION['success_flash'] = 'Leave application submitted successfully.';
        } else {
            $_SESSION['error_flash'] = 'Please fill in all required fields.';
        }
        header('Location: leave.php');
        exit();
    }

    // 2. Approve/Reject Leave (Super Admin Only)
    if (isset($_POST['update_status']) && $isSuperAdmin) {
        $request_id = (int)$_POST['request_id'];
        $action = $_POST['action']; // 'approve' or 'reject'
        $final_paid_status = (int)$_POST['final_paid_status']; // Allow changing paid status

        if ($action === 'approve') {
            // FIX: Use $final_paid_status and $request_id instead of $is_paid and $id
            $db->query("UPDATE leave_requests SET status = 'approved', is_paid = ? WHERE id = ?", [$final_paid_status, $request_id]);

            // 2. AUTOMATICALLY UPDATE ATTENDANCE RECORDS
            // FIX: Use $request_id here as well
            $req = $db->query("SELECT * FROM leave_requests WHERE id = ?", [$request_id])->first();
            
            if ($req) {
                $period = new DatePeriod(
                    new DateTime($req->start_date),
                    new DateInterval('P1D'),
                    (new DateTime($req->end_date))->modify('+1 day')
                );

                // Logic: If Paid -> 'on_leave', If Unpaid -> 'absent'
                // FIX: Use $final_paid_status logic
                $attendanceStatus = ($final_paid_status == 1) ? 'on_leave' : 'absent';

                foreach ($period as $date) {
                    $dateStr = $date->format('Y-m-d');
                    
                    $exists = $db->query("SELECT id FROM attendance WHERE employee_id = ? AND DATE(clock_in) = ?", [$req->employee_id, $dateStr])->first();

                    if ($exists) {
                        $db->query("UPDATE attendance SET status = ?, manual_entry = 1 WHERE id = ?", [$attendanceStatus, $exists->id]);
                    } else {
                        $db->insert('attendance', [
                            'employee_id' => $req->employee_id,
                            'clock_in'    => $dateStr . ' 09:00:00',
                            'clock_out'   => $dateStr . ' 17:00:00',
                            'status'      => $attendanceStatus,
                            'manual_entry' => 1
                        ]);
                    }
                }
            }
            $_SESSION['success_flash'] = 'Leave approved and attendance updated successfully.';
        
        } elseif ($action === 'reject') {
            $db->query("UPDATE leave_requests SET status = 'rejected' WHERE id = ?", [$request_id]);
            $_SESSION['error_flash'] = 'Leave request rejected.';
        }
        header('Location: leave.php?tab=pending');
        exit();
    }
}

// --- DATA FETCHING ---

// 1. Pending Requests (For Super Admin Approval)
$pendingLeaves = [];
if ($isSuperAdmin) {
    $pendingSql = "
        SELECT lr.*, e.first_name, e.last_name, p.name as position_name, d.name as department_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        WHERE lr.status = 'pending'
        ORDER BY lr.start_date ASC
    ";
    $pendingLeaves = $db->query($pendingSql)->results();
}

// 2. Leave History (With Filters)
$filter_employee = $_GET['employee_id'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_start = $_GET['start_date'] ?? '';
$filter_end = $_GET['end_date'] ?? '';

$historySql = "
    SELECT lr.*, e.first_name, e.last_name, d.name as department_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    WHERE 1=1
";
$params = [];

if (!empty($filter_employee)) {
    $historySql .= " AND lr.employee_id = ?";
    $params[] = $filter_employee;
}
if (!empty($filter_status)) {
    $historySql .= " AND lr.status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_type)) {
    $historySql .= " AND lr.leave_type = ?";
    $params[] = $filter_type;
}
if (!empty($filter_start)) {
    $historySql .= " AND lr.start_date >= ?";
    $params[] = $filter_start;
}
if (!empty($filter_end)) {
    $historySql .= " AND lr.end_date <= ?";
    $params[] = $filter_end;
}

$historySql .= " ORDER BY lr.start_date DESC";
$historyList = $db->query($historySql, $params)->results();

// 3. Fetch Employees for Dropdowns
$employees = $db->query("SELECT id, first_name, last_name, phone FROM employees WHERE status = 'active' ORDER BY first_name")->results();

// --- EXPORT LOGIC (CSV) ---
// --- EXPORT LOGIC (CSV) ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // CRITICAL FIX: Clear any previous output (whitespace, HTML, errors)
    if (ob_get_length()) ob_end_clean();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leave_history.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Fix for Excel: Add BOM (Byte Order Mark)
    fputs($output, "\xEF\xBB\xBF");
    
    // FIXED: Added explicit separator, enclosure, and escape arguments to prevent deprecation warning
    fputcsv($output, ['Employee Name', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Type', 'Status', 'Reason'], ",", "\"", "\\");
    
    foreach ($historyList as $row) {
        $start = new DateTime($row->start_date);
        $end = new DateTime($row->end_date);
        $days = $start->diff($end)->days + 1;
        
        // FIXED: Added explicit separator, enclosure, and escape arguments here too
        fputcsv($output, [
            $row->first_name . ' ' . $row->last_name,
            $row->department_name ?? '-',
            $row->leave_type,
            $row->start_date,
            $row->end_date,
            $days,
            $row->is_paid ? 'Paid' : 'Unpaid',
            ucfirst($row->status),
            $row->reason
        ], ",", "\"", "\\");
    }
    
    fclose($output);
    exit();
}

$pageTitle = 'Leave Management';
include_once '../templates/header.php';
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="space-y-6" x-data="{ activeTab: '<?php echo $_GET['tab'] ?? 'apply'; ?>' }">
    
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-calendar-alt text-primary-600 mr-3"></i>Leave Management</h1>
        <p class="mt-1 text-sm text-gray-600">Manage employee leave requests, approvals, and history.</p>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="flex border-b border-gray-200">
            <button @click="activeTab = 'apply'" :class="{'border-primary-500 text-primary-600': activeTab === 'apply', 'text-gray-500 hover:text-gray-700': activeTab !== 'apply'}" class="flex-1 py-4 text-center font-medium border-b-2 border-transparent transition-colors">Apply for Leave</button>
            
            <?php if ($isSuperAdmin): ?>
            <button @click="activeTab = 'pending'" :class="{'border-primary-500 text-primary-600': activeTab === 'pending', 'text-gray-500 hover:text-gray-700': activeTab !== 'pending'}" class="flex-1 py-4 text-center font-medium border-b-2 border-transparent transition-colors relative">
                Approvals
                <?php if (count($pendingLeaves) > 0): ?>
                    <span class="absolute top-3 right-10 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full"><?php echo count($pendingLeaves); ?></span>
                <?php endif; ?>
            </button>
            <?php endif; ?>

            <button @click="activeTab = 'history'" :class="{'border-primary-500 text-primary-600': activeTab === 'history', 'text-gray-500 hover:text-gray-700': activeTab !== 'history'}" class="flex-1 py-4 text-center font-medium border-b-2 border-transparent transition-colors">Leave History</button>
        </div>

        <div class="p-6">
            
            <!-- TAB 1: APPLY FOR LEAVE -->
            <div x-show="activeTab === 'apply'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <form method="POST" class="max-w-4xl mx-auto">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                        <div class="flex"><div class="flex-shrink-0"><i class="fas fa-info-circle text-blue-500"></i></div><div class="ml-3"><p class="text-sm text-blue-700">Use this form to submit a leave request on behalf of an employee.</p></div></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Employee Search -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Employee <span class="text-red-500">*</span></label>
                            <select name="employee_id" class="select2 w-full" required>
                                <option value="">Search by name...</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp->id; ?>"><?php echo htmlspecialchars($emp->first_name . ' ' . $emp->last_name . ' (' . $emp->phone . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Leave Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type <span class="text-red-500">*</span></label>
                            <select name="leave_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                <option value="Casual Leave">Casual Leave</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Annual Leave">Annual Leave</option>
                                <option value="Maternity Leave">Maternity Leave</option>
                                <option value="Unpaid Leave">Unpaid Leave</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <!-- Paid Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Compensation Type <span class="text-red-500">*</span></label>
                            <select name="is_paid" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="1">Paid Leave (Salary not deducted)</option>
                                <option value="0">Leave Without Pay (Salary deducted)</option>
                            </select>
                        </div>

                        <!-- Dates -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date <span class="text-red-500">*</span></label>
                            <input type="date" name="start_date" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date <span class="text-red-500">*</span></label>
                            <input type="date" name="end_date" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>

                        <!-- Remarks -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reason / Remarks</label>
                            <textarea name="reason" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Enter the reason for leave..."></textarea>
                        </div>
                    </div>

                    <div class="text-right">
                        <button type="submit" name="apply_leave" class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <i class="fas fa-paper-plane mr-2"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB 2: PENDING APPROVALS (SUPER ADMIN ONLY) -->
            <?php if ($isSuperAdmin): ?>
            <div x-show="activeTab === 'pending'" x-cloak>
                <?php if (empty($pendingLeaves)): ?>
                    <div class="text-center py-12"><i class="fas fa-clipboard-check text-gray-300 text-6xl mb-4"></i><p class="text-gray-500 text-lg">No pending leave requests.</p></div>
                <?php else: ?>
                    <div class="grid gap-6">
                        <?php foreach ($pendingLeaves as $req): 
                            $days = (new DateTime($req->start_date))->diff(new DateTime($req->end_date))->days + 1;
                        ?>
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($req->first_name . ' ' . $req->last_name); ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($req->position_name); ?> • <?php echo htmlspecialchars($req->department_name); ?></p>
                                </div>
                                <div class="mt-2 md:mt-0 text-right">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                        <?php echo $req->leave_type; ?> • <?php echo $days; ?> Day(s)
                                    </span>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 text-sm">
                                <div><span class="text-gray-500 block">Start Date</span><span class="font-semibold"><?php echo date('M d, Y', strtotime($req->start_date)); ?></span></div>
                                <div><span class="text-gray-500 block">End Date</span><span class="font-semibold"><?php echo date('M d, Y', strtotime($req->end_date)); ?></span></div>
                                <div><span class="text-gray-500 block">Requested Type</span><span class="font-semibold <?php echo $req->is_paid ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $req->is_paid ? 'Paid Leave' : 'Without Pay'; ?></span></div>
                            </div>
                            
                            <div class="bg-gray-50 p-3 rounded-md text-gray-700 text-sm mb-4">
                                <strong>Reason:</strong> <?php echo htmlspecialchars($req->reason); ?>
                            </div>

                            <!-- Approval Form -->
                            <form method="POST" class="flex flex-col sm:flex-row gap-4 items-end sm:items-center border-t pt-4">
                                <input type="hidden" name="request_id" value="<?php echo $req->id; ?>">
                                <input type="hidden" name="update_status" value="1"> <div class="flex-grow w-full sm:w-auto">
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Final Approval Status</label>
                                    <select name="final_paid_status" class="block w-full text-sm rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="1" <?php echo $req->is_paid ? 'selected' : ''; ?>>Approve as PAID Leave</option>
                                        <option value="0" <?php echo !$req->is_paid ? 'selected' : ''; ?>>Approve as UNPAID Leave</option>
                                    </select>
                                </div>
                                
                                <div class="flex gap-2 w-full sm:w-auto">
                                    <button type="submit" name="action" value="approve" class="flex-1 sm:flex-none bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-sm font-medium transition-colors">
                                        Approve
                                    </button>
                                    <button type="submit" name="action" value="reject" class="flex-1 sm:flex-none bg-red-100 text-red-700 px-4 py-2 rounded-md hover:bg-red-200 text-sm font-medium transition-colors">
                                        Reject
                                    </button>
                                </div>
                            </form>
                            
                            
                            
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- TAB 3: LEAVE HISTORY & REPORT -->
            <div x-show="activeTab === 'history'" x-cloak>
                
                <!-- Filter Bar -->
                <form method="GET" class="bg-gray-50 p-4 rounded-lg mb-6 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <input type="hidden" name="tab" value="history">
                    
                    <div><label class="text-xs text-gray-500">Employee</label><select name="employee_id" class="w-full text-sm rounded-md border-gray-300"><option value="">All Employees</option><?php foreach($employees as $e): ?><option value="<?php echo $e->id; ?>" <?php echo $filter_employee == $e->id ? 'selected' : ''; ?>><?php echo $e->first_name . ' ' . $e->last_name; ?></option><?php endforeach; ?></select></div>
                    
                    <div><label class="text-xs text-gray-500">Type</label><select name="type" class="w-full text-sm rounded-md border-gray-300"><option value="">All Types</option><option value="Casual Leave">Casual</option><option value="Sick Leave">Sick</option><option value="Annual Leave">Annual</option></select></div>
                    
                    <div><label class="text-xs text-gray-500">Start Date</label><input type="date" name="start_date" value="<?php echo $filter_start; ?>" class="w-full text-sm rounded-md border-gray-300"></div>
                    
                    <div><label class="text-xs text-gray-500">End Date</label><input type="date" name="end_date" value="<?php echo $filter_end; ?>" class="w-full text-sm rounded-md border-gray-300"></div>
                    
                    <div class="flex gap-2">
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm flex-1"><i class="fas fa-filter"></i></button>
                        <a href="?tab=history" class="bg-gray-200 text-gray-600 px-4 py-2 rounded-md hover:bg-gray-300 text-sm"><i class="fas fa-undo"></i></a>
                    </div>
                </form>

                <!-- Export Buttons -->
                <div class="flex justify-end mb-4 gap-2">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="text-green-600 hover:text-green-800 text-sm font-medium"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
                    <a href="#" onclick="window.print()" class="text-gray-600 hover:text-gray-800 text-sm font-medium"><i class="fas fa-print mr-1"></i> Print / PDF</a>
                </div>

                <!-- History Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dept</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pay</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($historyList)): ?>
                                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No history found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($historyList as $item): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item->department_name ?? '-'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item->leave_type); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('d M', strtotime($item->start_date)) . ' - ' . date('d M Y', strtotime($item->end_date)); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $item->status === 'approved' ? 'bg-green-100 text-green-800' : ($item->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                            <?php echo ucfirst($item->status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                        <?php if($item->status === 'approved'): ?>
                                            <span class="<?php echo $item->is_paid ? 'text-green-600 font-bold' : 'text-red-600 font-bold'; ?>">
                                                <?php echo $item->is_paid ? 'Paid' : 'Unpaid'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            placeholder: "Search for an employee...",
            allowClear: true,
            width: '100%'
        });
    });
</script>

<?php include_once '../templates/footer.php'; ?>