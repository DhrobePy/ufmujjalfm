<?php
// new_ufmhrm/admin/attendance.php (Fully Corrected Attendance Hub)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// --- LOGIC: Handle Manual Attendance POST requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? date('Y-m-d');
    
    // Manual Entry Tab submission
    if (isset($_POST['mark_present_bulk'])) {
        $employee_ids = $_POST['employee_ids'] ?? [];
        if (!empty($employee_ids)) {
            $db->getPdo()->beginTransaction();
            try {
                foreach ($employee_ids as $employee_id) {
                    $employee_id = (int)$employee_id;
                    $existing = $db->query("SELECT id FROM attendance WHERE employee_id = ? AND DATE(clock_in) = ?", [$employee_id, $date])->first();
                    if (!$existing) {
                        $db->insert('attendance', ['employee_id' => $employee_id, 'clock_in' => "$date 09:00:00", 'clock_out' => "$date 17:00:00", 'status' => 'present', 'manual_entry' => 1]);
                    }
                }
                $db->getPdo()->commit();
                $_SESSION['success_flash'] = count($employee_ids) . ' employee(s) marked as present.';
            } catch (Exception $e) {
                $db->getPdo()->rollBack();
                $_SESSION['error_flash'] = 'An error occurred: ' . $e->getMessage();
            }
        }
        header('Location: attendance.php?tab=manual_entry');
        exit();
    }
    
    // Attendance Sheet individual update
    if (isset($_POST['update_attendance'])) {
        $employee_id = (int)$_POST['employee_id'];
        $status = $_POST['status'];
        
        $existingRecord = $db->query("SELECT id FROM attendance WHERE employee_id = ? AND DATE(clock_in) = ?", [$employee_id, $date])->first();

        if ($existingRecord) {
            $db->query("UPDATE attendance SET status = ?, manual_entry = 1, clock_in = ?, clock_out = ? WHERE id = ?", [$status, ($status === 'absent' ? "$date 00:00:00" : "$date 09:00:00"), ($status === 'absent' ? NULL : "$date 17:00:00"), $existingRecord->id]);
        } else {
            $db->insert('attendance', ['employee_id' => $employee_id, 'clock_in' => ($status === 'absent' ? "$date 00:00:00" : "$date 09:00:00"), 'clock_out' => ($status === 'absent' ? NULL : "$date 17:00:00"), 'status' => $status, 'manual_entry' => 1]);
        }
        $_SESSION['success_flash'] = 'Attendance updated.';
        header('Location: attendance.php?date=' . $date);
        exit();
    }
}


$pageTitle = 'Attendance Management - ' . APP_NAME;
include_once '../templates/header.php';

// --- DATA FOR TAB 1: ATTENDANCE SHEET ---
$filterDateSheet = $_GET['date'] ?? date('Y-m-d');
$sheetSql = "
    SELECT e.id, e.first_name, e.last_name, p.name as position_name, d.name as department_name,
           a.status, a.clock_in, a.clock_out
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN attendance a ON e.id = a.employee_id AND DATE(a.clock_in) = ?
    WHERE e.status = 'active' ORDER BY e.first_name, e.last_name
";
$attendanceList = $db->query($sheetSql, [$filterDateSheet])->results();
$presentCount = 0; $totalEmployees = count($attendanceList);
foreach ($attendanceList as $item) { if ($item->status === 'present') { $presentCount++; } }
$absentCount = $totalEmployees - $presentCount;

// --- DATA FOR TAB 2: MANUAL ENTRY ---
$manualEntrySql = "
    SELECT e.id, e.first_name, e.last_name, p.name as position_name, d.name as department_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    WHERE e.status = 'active' AND e.id NOT IN (SELECT employee_id FROM attendance WHERE DATE(clock_in) = CURDATE() AND status = 'present')
    ORDER BY e.first_name, e.last_name
";
$manualEntryList = $db->query($manualEntrySql)->results();

// --- DATA FOR TAB 3: HISTORY & REPORTS ---
$historySearch = $_GET['search'] ?? '';
$historyDept = $_GET['department'] ?? '';
$historyStart = $_GET['start_date'] ?? date('Y-m-01');
$historyEnd = $_GET['end_date'] ?? date('Y-m-t');

$whereClauses = ["DATE(a.clock_in) BETWEEN ? AND ?"];
$params = [$historyStart, $historyEnd];
if (!empty($historySearch)) { $whereClauses[] = "CONCAT(e.first_name, ' ', e.last_name) LIKE ?"; $params[] = '%' . $historySearch . '%'; }
if (!empty($historyDept)) { $whereClauses[] = "d.id = ?"; $params[] = $historyDept; }
$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

$historySql = "
    SELECT a.clock_in, a.clock_out, a.status, e.first_name, e.last_name, d.name as department_name
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    $whereSql ORDER BY a.clock_in DESC
";
$historyList = $db->query($historySql, $params)->results();
$departments = $db->query("SELECT id, name FROM departments ORDER BY name")->results();
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

<div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3"><div class="h-12 w-12 bg-primary-100 rounded-xl flex items-center justify-center"><i class="fas fa-user-check text-primary-600 text-xl"></i></div>Attendance Management</h1>
        <p class="mt-2 text-gray-600">View daily records, perform manual entries, and review attendance history.</p>
    </div>

    <div x-data="{ activeTab: 'sheet' }" x-init="()=>{ const params = new URLSearchParams(window.location.search); if (params.get('tab')) { activeTab = params.get('tab'); } window.history.replaceState({}, document.title, window.location.pathname); }">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <a href="#sheet" @click.prevent="activeTab = 'sheet'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'sheet', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': true }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-list-alt"></i> Attendance Sheet</a>
                <a href="#manual_entry" @click.prevent="activeTab = 'manual_entry'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'manual_entry', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': true }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-edit"></i> Manual Entry <span class="bg-amber-100 text-amber-800 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo count($manualEntryList); ?></span></a>
                <a href="#history" @click.prevent="activeTab = 'history'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'history', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': true }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-history"></i> History & Reports</a>
                <a href="#biometric" @click.prevent="activeTab = 'biometric'" :class="{ 'border-primary-500 text-primary-600': activeTab === 'biometric', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': true }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2"><i class="fas fa-fingerprint"></i> Biometric</a>
            </nav>
        </div>

        <div class="mt-6">
            <div x-show="activeTab === 'sheet'" x-cloak>
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                    <div class="lg:col-span-1 bg-white rounded-2xl shadow-xl border p-6"><h2 class="font-bold text-lg mb-4">Select Date</h2><form method="GET"><input type="date" name="date" value="<?php echo htmlspecialchars($filterDateSheet); ?>" onchange="this.form.submit()" class="w-full px-4 py-3 rounded-xl border-2 focus:border-primary-500 focus:ring-primary-100 outline-none"></form></div>
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
                    <div class="p-6 border-b"><h2 class="text-xl font-bold">Manual Entry for Today (<?php echo date('F j, Y'); ?>)</h2><p class="text-sm text-gray-600 mt-1">Select employees who are present today and have not clocked in automatically.</p></div>
                    <?php if (!empty($manualEntryList)): ?>
                        <form method="POST">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left w-12"><input type="checkbox" onchange="toggleSelectAll(this, 'manual-entry-checkbox')"></th><th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase">Employee</th><th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase">Department</th></tr></thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach($manualEntryList as $item): ?>
                                            <tr><td class="px-6 py-4"><input type="checkbox" name="employee_ids[]" value="<?php echo $item->id; ?>" class="manual-entry-checkbox h-4 w-4 text-primary-600"></td><td class="px-6 py-4"><div class="font-medium"><?php echo htmlspecialchars($item->first_name . ' ' . $item->last_name); ?></div><div class="text-sm text-gray-500"><?php echo htmlspecialchars($item->position_name ?? 'N/A'); ?></div></td><td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($item->department_name ?? 'N/A'); ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-6 bg-gray-50 border-t flex justify-end">
                                <button type="submit" name="mark_present_bulk" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg font-semibold shadow-md"><i class="fas fa-check-double mr-2"></i>Mark Selected as Present</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="p-8 text-center"><i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i><h3 class="text-lg font-medium">All employees are accounted for today!</h3></div>
                    <?php endif; ?>
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

            <div x-show="activeTab === 'biometric'" x-cloak>
                <div class="bg-white rounded-2xl shadow-xl border p-12 text-center"><i class="fas fa-fingerprint text-primary-300 text-6xl mb-6"></i><h2 class="text-2xl font-bold text-gray-800">Biometric Integration</h2><p class="text-gray-500 mt-2">This feature is coming soon. It will allow direct synchronization with your biometric attendance device.</p></div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSelectAll(source, className) {
        document.querySelectorAll('.' + className).forEach(checkbox => checkbox.checked = source.checked);
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