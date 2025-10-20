<?php
// new_ufmhrm/accounts/attendance.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

// --- SECURITY & PERMISSIONS ---
if (!is_admin_logged_in()) {
    redirect('../auth/login.php');
}

$currentUser = getCurrentUser();
$user_branch_id = $currentUser['branch_id'];
$branch_account_roles = ['Accounts- Srg', 'Accounts- Rampura'];
$is_branch_accountant = in_array($currentUser['role'], $branch_account_roles);

// --- LOGIC: Handle ALL form submissions before any HTML is sent ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? date('Y-m-d');
    
    // --- Manual Entry Tab submission ---
    if (isset($_POST['mark_present_bulk'])) {
        $employee_ids = $_POST['employee_ids'] ?? [];
        if (!empty($employee_ids)) {
            $db->getPdo()->beginTransaction();
            try {
                foreach ($employee_ids as $employee_id) {
                    $employee_id = (int)$employee_id;
                    // Security check: ensure employee belongs to the user's branch
                    $empCheck = $db->query("SELECT id FROM employees WHERE id = ? AND branch_id = ?", [$employee_id, $user_branch_id])->first();
                    if ($empCheck) {
                        $existing = $db->query("SELECT id FROM attendance WHERE employee_id = ? AND date = ?", [$employee_id, $date])->first();
                        if (!$existing) {
                            $db->insert('attendance', ['employee_id' => $employee_id, 'clock_in' => "$date 09:00:00", 'clock_out' => "$date 17:00:00", 'status' => 'present', 'manual_entry' => 1, 'branch_id' => $user_branch_id, 'date' => $date]);
                        }
                    }
                }
                $db->getPdo()->commit();
                set_message(count($employee_ids) . ' employee(s) marked as present.', 'success');
            } catch (Exception $e) {
                $db->getPdo()->rollBack();
                set_message('An error occurred: ' . $e->getMessage(), 'error');
            }
        }
        redirect('attendance.php?tab=manual_entry');
    }
    
    // --- Attendance Sheet individual update ---
    if (isset($_POST['update_attendance'])) {
        $employee_id = (int)$_POST['employee_id'];
        $status = $_POST['update_attendance'];
        
        // Security check: ensure employee belongs to user's branch before updating
        $empCheck = $db->query("SELECT id FROM employees WHERE id = ? AND branch_id = ?", [$employee_id, $user_branch_id])->first();
        if ($empCheck) {
            $existingRecord = $db->query("SELECT id FROM attendance WHERE employee_id = ? AND date = ?", [$employee_id, $date])->first();
            $clock_in = ($status === 'present') ? "$date 09:00:00" : "$date 00:00:00";
            $clock_out = ($status === 'present') ? "$date 17:00:00" : NULL;

            if ($existingRecord) {
                $db->query("UPDATE attendance SET status = ?, manual_entry = 1, clock_in = ?, clock_out = ? WHERE id = ?", [$status, $clock_in, $clock_out, $existingRecord->id]);
            } else {
                $db->insert('attendance', ['employee_id' => $employee_id, 'clock_in' => $clock_in, 'clock_out' => $clock_out, 'status' => $status, 'manual_entry' => 1, 'branch_id' => $user_branch_id, 'date' => $date]);
            }
            set_message('Attendance updated.', 'success');
        } else {
            set_message('Error: You do not have permission to modify this employee\'s attendance.', 'error');
        }
        redirect('attendance.php?date=' . $date);
    }
    
    // --- Bulk Historical Entry submission ---
    if (isset($_POST['bulk_historical_update'])) {
        $employee_ids = $_POST['employee_ids'] ?? [];
        $status = $_POST['status'];
        $historical_date = $_POST['historical_date'];

        if (!empty($employee_ids) && !empty($historical_date)) {
            $db->getPdo()->beginTransaction();
            try {
                foreach ($employee_ids as $employee_id) {
                    $employee_id = (int)$employee_id;
                    // Security Check
                    $empCheck = $db->query("SELECT id FROM employees WHERE id = ? AND branch_id = ?", [$employee_id, $user_branch_id])->first();
                    if ($empCheck) {
                        $existing = $db->query("SELECT id FROM attendance WHERE employee_id = ? AND date = ?", [$employee_id, $historical_date])->first();
                        $clock_in = ($status === 'present') ? "$historical_date 09:00:00" : "$historical_date 00:00:00";
                        $clock_out = ($status === 'present') ? "$historical_date 17:00:00" : NULL;
                        if ($existing) {
                            $db->query("UPDATE attendance SET status = ?, clock_in = ?, clock_out = ?, manual_entry = 1 WHERE id = ?", [$status, $clock_in, $clock_out, $existing->id]);
                        } else {
                            $db->insert('attendance', ['employee_id' => $employee_id, 'clock_in' => $clock_in, 'clock_out' => $clock_out, 'status' => $status, 'manual_entry' => 1, 'branch_id' => $user_branch_id, 'date' => $historical_date]);
                        }
                    }
                }
                $db->getPdo()->commit();
                set_message(count($employee_ids) . ' records updated for ' . date('F j, Y', strtotime($historical_date)), 'success');
            } catch (Exception $e) {
                $db->getPdo()->rollBack();
                set_message('An error occurred: ' . $e->getMessage(), 'error');
            }
        }
        redirect('attendance.php?tab=bulk_entry&date=' . $historical_date);
    }
}

$pageTitle = 'Attendance Management - ' . APP_NAME;

// --- DYNAMIC FILTERS FOR DATA FETCHING ---
$branch_filter_sql = "";
$branch_params = [];
if ($is_branch_accountant && !empty($user_branch_id)) {
    $branch_filter_sql = " AND e.branch_id = ? ";
    $branch_params[] = $user_branch_id;
}

// --- DATA FOR TAB 1: ATTENDANCE SHEET ---
$filterDateSheet = $_GET['date'] ?? date('Y-m-d');
$sheetSql = "SELECT e.id, e.first_name, e.last_name, p.name as position_name, d.name as department_name, a.status, a.clock_in, a.clock_out FROM employees e LEFT JOIN positions p ON e.position_id = p.id LEFT JOIN departments d ON p.department_id = d.id LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = ? WHERE e.status = 'active' $branch_filter_sql ORDER BY e.first_name, e.last_name";
$attendanceList = $db->query($sheetSql, array_merge([$filterDateSheet], $branch_params))->results();
$presentCount = 0; $totalEmployees = count($attendanceList);
foreach ($attendanceList as $item) { if ($item->status === 'present') { $presentCount++; } }
$absentCount = $totalEmployees - $presentCount;

// --- DATA FOR TAB 2: MANUAL ENTRY ---
$manualEntrySql = "SELECT e.id, e.first_name, e.last_name, p.name as position_name, d.name as department_name FROM employees e LEFT JOIN positions p ON e.position_id = p.id LEFT JOIN departments d ON p.department_id = d.id WHERE e.status = 'active' AND e.id NOT IN (SELECT employee_id FROM attendance WHERE date = CURDATE() AND status = 'present') $branch_filter_sql ORDER BY e.first_name, e.last_name";
$manualEntryList = $db->query($manualEntrySql, $branch_params)->results();

// --- DATA FOR TAB 3: BULK HISTORICAL ENTRY ---
$allEmployeesSql = "SELECT e.id, e.first_name, e.last_name, d.name as department_name FROM employees e LEFT JOIN positions p ON e.position_id = p.id LEFT JOIN departments d ON p.department_id = d.id WHERE e.status='active' $branch_filter_sql ORDER BY e.first_name";
$allEmployees = $db->query($allEmployeesSql, $branch_params)->results();
$filterDateBulk = $_GET['date'] ?? date('Y-m-d');

// --- DATA FOR TAB 4: HISTORY & REPORTS ---
$historySearch = $_GET['search'] ?? '';
$historyDept = $_GET['department'] ?? '';
$historyStart = $_GET['start_date'] ?? date('Y-m-01');
$historyEnd = $_GET['end_date'] ?? date('Y-m-t');
$whereClauses = ["DATE(a.date) BETWEEN ? AND ?"];
$params = [$historyStart, $historyEnd];
if($is_branch_accountant && !empty($user_branch_id)){ $whereClauses[] = "e.branch_id = ?"; $params[] = $user_branch_id; }
if (!empty($historySearch)) { $whereClauses[] = "CONCAT(e.first_name, ' ', e.last_name) LIKE ?"; $params[] = '%' . $historySearch . '%'; }
if (!empty($historyDept)) { $whereClauses[] = "d.id = ?"; $params[] = $historyDept; }
$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
$historySql = "SELECT a.date, a.clock_in, a.clock_out, a.status, e.first_name, e.last_name, d.name as department_name FROM attendance a JOIN employees e ON a.employee_id = e.id LEFT JOIN positions p ON e.position_id = p.id LEFT JOIN departments d ON p.department_id = d.id $whereSql ORDER BY a.date DESC";
$historyList = $db->query($historySql, $params)->results();
$departments = $db->query("SELECT id, name FROM departments ORDER BY name")->results();


if ($is_branch_accountant) {
    include_once '../templates/accounts_header.php';
} else {
    include_once '../templates/header.php';
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

<div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-xl border p-6">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3"><div class="h-12 w-12 bg-primary-100 rounded-xl flex items-center justify-center"><i class="fas fa-user-check text-primary-600 text-xl"></i></div>Attendance Management</h1>
    </div>

    <div x-data="{ activeTab: 'sheet' }" x-init="()=>{ const params = new URLSearchParams(window.location.search); if (params.get('tab')) { activeTab = params.get('tab'); } window.history.replaceState({}, document.title, window.location.pathname); }">
        <div class="border-b border-gray-200"><nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="#sheet" @click.prevent="activeTab = 'sheet'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'sheet' }" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-list-alt"></i> Daily Sheet</a>
            <a href="#manual_entry" @click.prevent="activeTab = 'manual_entry'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'manual_entry' }" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-edit"></i> Manual Entry</a>
            <a href="#bulk_entry" @click.prevent="activeTab = 'bulk_entry'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'bulk_entry' }" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-users-cog"></i> Bulk Historical Entry</a>
            <a href="#history" @click.prevent="activeTab = 'history'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'history' }" class="border-transparent text-gray-500 hover:border-gray-300 py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-history"></i> History & Reports</a>
        </nav></div>

        <div class="mt-6">
            <div x-show="activeTab === 'sheet'" x-cloak>
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                    <div class="lg:col-span-1 bg-white rounded-2xl shadow-xl border p-6"><h2 class="font-bold text-lg mb-4">Select Date</h2><form method="GET"><input type="date" name="date" value="<?php echo htmlspecialchars($filterDateSheet); ?>" onchange="this.form.submit()" class="w-full px-4 py-3 rounded-xl border-2 focus:border-primary-500 outline-none"></form></div>
                    <div class="lg:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-gradient-to-br from-green-500 to-teal-600 rounded-xl shadow-lg p-6 text-white"><p class="font-semibold">Present</p><p class="text-4xl font-bold mt-2"><?php echo $presentCount; ?></p></div>
                        <div class="bg-gradient-to-br from-red-500 to-pink-600 rounded-xl shadow-lg p-6 text-white"><p class="font-semibold">Absent</p><p class="text-4xl font-bold mt-2"><?php echo $absentCount; ?></p></div>
                        <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl shadow-lg p-6 text-white"><p class="font-semibold">On Leave</p><p class="text-4xl font-bold mt-2">0</p></div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl shadow-xl border"><div class="p-6 border-b flex justify-between items-center"><h2 class="text-xl font-bold text-gray-900">Attendance for <?php echo date("F j, Y", strtotime($filterDateSheet)); ?></h2><div class="relative max-w-xs w-full"><input type="text" id="sheetSearchInput" placeholder="Search employees..." onkeyup="filterSheetTable()" class="w-full pl-10 pr-4 py-2 rounded-xl border-2 focus:border-primary-500 outline-none"><i class="fas fa-search absolute left-3 top-3.5 text-gray-400"></i></div></div>
                    <div class="overflow-x-auto"><table class="min-w-full divide-y"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Employee</th><th class="px-6 py-3 text-left">Department</th><th class="px-6 py-3 text-center">Status</th><th class="px-6 py-3 text-center">Clock In / Out</th><th class="px-6 py-3 text-center">Manual Actions</th></tr></thead><tbody class="bg-white divide-y" id="sheetTableBody"><?php foreach ($attendanceList as $item): ?><tr class="hover:bg-primary-50 employee-row" data-name="<?php echo strtolower(htmlspecialchars($item->first_name . ' ' . $item->last_name)); ?>" data-department="<?php echo strtolower(htmlspecialchars($item->department_name ?? '')); ?>"><td class="px-6 py-4"><div class="font-semibold"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></div><div class="text-sm text-gray-500"><?php echo htmlspecialchars($item->position_name ?? 'N/A'); ?></div></td><td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($item->department_name ?? 'N/A'); ?></td><td class="px-6 py-4 text-center"><?php $status = $item->status ?? 'absent'; $colorClass = 'bg-red-100 text-red-800'; if ($status === 'present') $colorClass = 'bg-green-100 text-green-800'; ?><span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $colorClass; ?>"><?php echo ucfirst($status); ?></span></td><td class="px-6 py-4 text-center text-sm"><?php echo $item->clock_in ? date('h:i A', strtotime($item->clock_in)) . ' - ' . ($item->clock_out ? date('h:i A', strtotime($item->clock_out)) : 'N/A') : 'N/A'; ?></td><td class="px-6 py-4 text-center"><form method="POST" class="inline-flex gap-2"><input type="hidden" name="employee_id" value="<?php echo $item->id; ?>"><input type="hidden" name="date" value="<?php echo $filterDateSheet; ?>"><button type="submit" name="update_attendance" value="present" class="px-3 py-1 text-sm bg-green-500 text-white rounded-md hover:bg-green-600 disabled:opacity-50" <?php if($status === 'present') echo 'disabled'; ?>>Present</button><button type="submit" name="update_attendance" value="absent" class="px-3 py-1 text-sm bg-red-500 text-white rounded-md hover:bg-red-600 disabled:opacity-50" <?php if($status === 'absent') echo 'disabled'; ?>>Absent</button></form></td></tr><?php endforeach; ?></tbody></table></div>
                </div>
            </div>

            <div x-show="activeTab === 'manual_entry'" x-cloak>
                <div class="bg-white rounded-2xl shadow-xl border">
                    <div class="p-6 border-b">
                        <h2 class="text-xl font-bold">Manual Entry for Today (<?php echo date('F j, Y'); ?>)</h2>
                        <p class="text-sm text-gray-600 mt-1">Select employees who are present today and have not clocked in automatically.</p>
                    </div>
                    <?php if (!empty($manualEntryList)): ?>
                        <form method="POST">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left w-12"><input type="checkbox" onchange="toggleSelectAll(this, 'manual-entry-checkbox')"></th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase">Employee</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase">Department</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach($manualEntryList as $item): ?>
                                            <tr>
                                                <td class="px-6 py-4"><input type="checkbox" name="employee_ids[]" value="<?php echo $item->id; ?>" class="manual-entry-checkbox h-4 w-4 text-primary-600"></td>
                                                <td class="px-6 py-4">
                                                    <div class="font-medium"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($item->position_name ?? 'N/A'); ?></div>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($item->department_name ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-6 bg-gray-50 border-t flex justify-end">
                                <button type="submit" name="mark_present_bulk" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg font-semibold shadow-md">
                                    <i class="fas fa-check-double mr-2"></i>Mark Selected as Present
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium">All employees are accounted for today!</h3>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            
            
            <div x-show="activeTab === 'bulk_entry'" x-cloak>
                x
                 <div class="bg-white rounded-2xl shadow-xl border">
        <div class="p-6 border-b">
            <h2 class="text-xl font-bold">Bulk Historical Attendance Entry (Remind me to change this bulk after primary set up) </h2>
            <p class="text-sm text-gray-600 mt-1">Select a date, choose employees, and mark them as either present or absent in bulk.</p>
        </div>
        <form method="POST">
            <input type="hidden" name="bulk_historical_update" value="1">
            <div class="p-6 bg-gray-50 flex flex-col md:flex-row gap-4 items-center">
                <label for="historical_date" class="font-semibold whitespace-nowrap">Select Date:</label>
                <input type="date" name="historical_date" id="historical_date" value="<?php echo htmlspecialchars($filterDateBulk); ?>" class="rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <div class="relative w-full md:w-1/3 ml-auto">
                    <input type="text" id="bulkSearchInput" placeholder="Search employees..." onkeyup="filterBulkTable()" class="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    <i class="fas fa-search absolute left-3 top-3.5 text-gray-400"></i>
                </div>
            </div>
            <div class="overflow-y-auto" style="max-height: 50vh;">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr>
                            <th class="px-6 py-3 text-left w-12">
                                <input type="checkbox" onchange="toggleSelectAll(this, 'bulk-entry-checkbox')" class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase">Department</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="bulkTableBody">
                        <?php foreach($allEmployees as $item): ?>
                        <tr class="hover:bg-primary-50 bulk-employee-row" data-name="<?php echo strtolower(htmlspecialchars($item->first_name . ' ' . $item->last_name)); ?>" data-department="<?php echo strtolower(htmlspecialchars($item->department_name ?? '')); ?>">
                            <td class="px-6 py-4">
                                <input type="checkbox" name="employee_ids[]" value="<?php echo $item->id; ?>" class="bulk-entry-checkbox h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($item->department_name ?? 'N/A'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-6 bg-gray-50 border-t flex justify-end gap-4">
                <button type="submit" name="status" value="absent" class="px-6 py-3 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-lg font-semibold shadow-md hover:shadow-lg transition-transform transform hover:scale-105">
                    <i class="fas fa-times-circle mr-2"></i>Mark Selected as Absent
                </button>
                <button type="submit" name="status" value="present" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg font-semibold shadow-md hover:shadow-lg transition-transform transform hover:scale-105">
                    <i class="fas fa-check-double mr-2"></i>Mark Selected as Present
                </button>
            </div>
        </form>
    </div>
</div>

            <div x-show="activeTab === 'history'" x-cloak>
                <div class="bg-white rounded-2xl shadow-xl border p-6 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <input type="hidden" name="tab" value="history">
                        <div><label for="search" class="block text-sm font-medium">Search</label><input type="text" name="search" id="search" value="<?php echo htmlspecialchars($historySearch); ?>" placeholder="Employee name..." class="mt-1 block w-full rounded-md border-gray-300"></div>
                        <div><label for="department" class="block text-sm font-medium">Department</label><select name="department" id="department" class="mt-1 block w-full rounded-md border-gray-300"><option value="">All</option><?php foreach($departments as $dept): ?><option value="<?php echo $dept->id; ?>" <?php if($historyDept == $dept->id) echo 'selected'; ?>><?php echo htmlspecialchars($dept->name); ?></option><?php endforeach; ?></select></div>
                        <div><label for="start_date" class="block text-sm font-medium">Start Date</label><input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($historyStart); ?>" class="mt-1 block w-full rounded-md border-gray-300"></div>
                        <div><label for="end_date" class="block text-sm font-medium">End Date</label><input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($historyEnd); ?>" class="mt-1 block w-full rounded-md border-gray-300"></div>
                        <div class="md:col-start-4 flex justify-end gap-2"><a href="?tab=history" class="px-4 py-2 bg-gray-200 rounded-md">Clear</a><button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md">Filter</button></div>
                    </form>
                </div>
                <div class="bg-white rounded-2xl shadow-xl border">
                    <div class="p-6 border-b flex justify-between items-center"><h2 class="text-xl font-bold">Attendance History</h2><div class="flex gap-2"><button onclick="exportToCSV()" class="px-4 py-2 text-sm bg-green-600 text-white rounded-md"><i class="fas fa-file-csv mr-2"></i>CSV</button><button onclick="exportToPDF()" class="px-4 py-2 text-sm bg-red-600 text-white rounded-md"><i class="fas fa-file-pdf mr-2"></i>PDF</button></div></div>
                    <div class="overflow-x-auto"><table id="history-table" class="min-w-full divide-y"><thead><tr><th class="px-6 py-3 text-left">Date</th><th class="px-6 py-3 text-left">Employee</th><th class="px-6 py-3 text-left">Department</th><th class="px-6 py-3 text-center">Status</th><th class="px-6 py-3 text-center">Clock In/Out</th></tr></thead><tbody class="divide-y"><?php foreach($historyList as $item): ?><tr><td class="px-6 py-4"><?php echo date('d M, Y', strtotime($item->clock_in)); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($item->department_name); ?></td><td class="px-6 py-4 text-center"><?php echo ucfirst($item->status); ?></td><td class="px-6 py-4 text-center"><?php echo $item->clock_in ? date('h:i A', strtotime($item->clock_in)) . ' - ' . ($item->clock_out ? date('h:i A', strtotime($item->clock_out)) : 'N/A') : 'N/A'; ?></td></tr><?php endforeach; ?></tbody></table></div>
                </div>
            </div>

            <div x-show="activeTab === 'biometric'" x-cloak><div class="bg-white rounded-2xl shadow-xl border p-12 text-center"><i class="fas fa-fingerprint text-primary-300 text-6xl mb-6"></i><h2 class="text-2xl font-bold text-gray-800">Biometric Integration</h2><p class="text-gray-500 mt-2">This feature is coming soon.</p></div></div>
        </div>
    </div>
</div>


<script>
    function toggleSelectAll(source, className) {
        document.querySelectorAll('.' + className).forEach(checkbox => checkbox.checked = source.checked);
    }
    function filterBulkTable() {
        const searchTerm = document.getElementById('bulkSearchInput').value.toLowerCase();
        document.querySelectorAll('#bulkTableBody .bulk-employee-row').forEach(row => {
            const name = row.dataset.name || '';
            const department = row.dataset.department || '';
            row.style.display = (name.includes(searchTerm) || department.includes(searchTerm)) ? '' : 'none';
        });
    }
    function filterSheetTable() {
        const searchTerm = document.getElementById('sheetSearchInput').value.toLowerCase();
        document.querySelectorAll('#sheetTableBody .employee-row').forEach(row => {
            const name = row.dataset.name || '';
            const department = row.dataset.department || '';
            row.style.display = (name.includes(searchTerm) || department.includes(searchTerm)) ? '' : 'none';
        });
    }

    function exportToCSV() {
        const table = document.getElementById("history-table");
        let csv = [Array.from(table.rows[0].cells).map(cell => '"' + cell.innerText + '"').join(',')];
        for (let i = 1; i < table.rows.length; i++) {
            let row = [], cols = table.rows[i].cells;
            for (let j = 0; j < cols.length; j++) row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
            csv.push(row.join(","));
        }
        const blob = new Blob([csv.join("\n")], {type: "text/csv;charset=utf-8;"});
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `attendance_history_<?php echo $historyStart; ?>_to_<?php echo $historyEnd; ?>.csv`;
        link.click();
    }

    function exportToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.autoTable({ html: '#history-table' });
        doc.save(`attendance_history_<?php echo $historyStart; ?>_to_<?php echo $historyEnd; ?>.pdf`);
    }
</script>

<?php include_once '../templates/footer.php'; ?>