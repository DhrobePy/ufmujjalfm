<?php
// new_ufmhrm/admin/monthly_attendance_report.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// --- 1. Handle Inputs ---
$selectedDate = $_GET['date'] ?? date('Y-m-d'); // Default to today
$timestamp = strtotime($selectedDate);
$month = date('m', $timestamp);
$year = date('Y', $timestamp);
$dayLimit = (int)date('d', $timestamp); // The day part (e.g., 15 if date is 15th)
$startDate = date('Y-m-01', $timestamp);

$pageTitle = 'Monthly Attendance Report';
include_once '../templates/header.php';

// --- 2. Fetch Data ---

// Fetch All Active Employees
// FIXED: Removed 'employee_id' from the SELECT list as it doesn't exist in this table
$employees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->results();

// Fetch Attendance for the date range
$attendanceSql = "
    SELECT employee_id, DATE(clock_in) as att_date, status 
    FROM attendance 
    WHERE DATE(clock_in) BETWEEN ? AND ?
";
$attendanceData = $db->query($attendanceSql, [$startDate, $selectedDate])->results();

// Organize attendance data into a 2D array: $attendanceMap[employee_id][day] = status
$attendanceMap = [];
foreach ($attendanceData as $record) {
    $day = (int)date('d', strtotime($record->att_date));
    $attendanceMap[$record->employee_id][$day] = $record->status;
}

// --- 3. Helper for Status Colors ---
function getStatusBadge($status) {
    switch ($status) {
        case 'present': return '<span class="px-2 py-1 text-xs font-bold text-green-700 bg-green-100 rounded">P</span>';
        case 'absent': return '<span class="px-2 py-1 text-xs font-bold text-red-700 bg-red-100 rounded">A</span>';
        case 'late': return '<span class="px-2 py-1 text-xs font-bold text-amber-700 bg-amber-100 rounded">L</span>';
        case 'on_leave': return '<span class="px-2 py-1 text-xs font-bold text-blue-700 bg-blue-100 rounded">LV</span>';
        case 'holiday': return '<span class="px-2 py-1 text-xs font-bold text-gray-700 bg-gray-200 rounded">H</span>';
        default: return '<span class="text-gray-300">-</span>';
    }
}
?>

<style>
    /* Print Styling */
    @media print {
        @page { size: landscape; }
        body * { visibility: hidden; }
        #printableArea, #printableArea * { visibility: visible; }
        #printableArea { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
    /* Sticky Columns for large tables */
    .sticky-col { position: sticky; left: 0; background-color: white; z-index: 10; }
    .sticky-col-2 { position: sticky; left: 60px; background-color: white; z-index: 10; border-right: 2px solid #e5e7eb; }
</style>

<div class="space-y-6">
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 no-print">
        <div class="flex flex-col md:flex-row justify-between items-end gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-table text-primary-600 mr-3"></i>Monthly Attendance Report</h1>
                <p class="mt-1 text-sm text-gray-600">Generating report from <strong><?php echo date('M 01, Y', $timestamp); ?></strong> to <strong><?php echo date('M d, Y', $timestamp); ?></strong></p>
            </div>
            
            <form method="GET" class="flex gap-3 items-end">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Report Up To</label>
                    <input type="date" name="date" value="<?php echo $selectedDate; ?>" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-medium transition-colors">
                    Generate
                </button>
                <button type="button" onclick="window.print()" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 font-medium transition-colors">
                    <i class="fas fa-print mr-2"></i>Print
                </button>
            </form>
        </div>
    </div>

    <div id="printableArea" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left font-medium text-gray-500 uppercase tracking-wider sticky-col w-16">#</th>
                        <th class="px-3 py-3 text-left font-medium text-gray-500 uppercase tracking-wider sticky-col-2 w-48">Employee Name</th>
                        
                        <?php for ($i = 1; $i <= $dayLimit; $i++): 
                            $currentDayDate = date('Y-m-d', mktime(0, 0, 0, $month, $i, $year));
                            $dayName = date('D', strtotime($currentDayDate));
                            $isWeekend = ($dayName == 'Fri'); // Adjust for your weekend
                        ?>
                            <th class="px-1 py-3 text-center font-medium text-gray-500 uppercase w-10 <?php echo $isWeekend ? 'bg-red-50 text-red-600' : ''; ?>">
                                <div class="flex flex-col leading-tight">
                                    <span><?php echo $i; ?></span>
                                    <span class="text-[10px] font-normal"><?php echo substr($dayName, 0, 1); ?></span>
                                </div>
                            </th>
                        <?php endfor; ?>
                        
                        <th class="px-3 py-3 text-center font-medium text-gray-500 uppercase tracking-wider border-l">Stats</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($employees as $index => $emp): 
                        $present = 0; $absent = 0; $late = 0; $leave = 0;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-3 whitespace-nowrap text-gray-500 sticky-col"><?php echo $index + 1; ?></td>
                        <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900 sticky-col-2">
                            <?php echo htmlspecialchars($emp->first_name . ' ' . $emp->last_name); ?>
                        </td>

                        <?php for ($i = 1; $i <= $dayLimit; $i++): 
                            $status = $attendanceMap[$emp->id][$i] ?? null;
                            
                            // Calculate Stats
                            if ($status == 'present') $present++;
                            if ($status == 'absent') $absent++;
                            if ($status == 'late') $late++;
                            if ($status == 'on_leave') $leave++;
                            
                            // Weekend highlighting
                            $currentDayDate = date('Y-m-d', mktime(0, 0, 0, $month, $i, $year));
                            $dayName = date('D', strtotime($currentDayDate));
                            $isWeekend = ($dayName == 'Fri');
                        ?>
                            <td class="px-1 py-2 text-center border-r border-gray-100 <?php echo $isWeekend ? 'bg-red-50' : ''; ?>">
                                <?php echo getStatusBadge($status); ?>
                            </td>
                        <?php endfor; ?>

                        <td class="px-3 py-2 whitespace-nowrap text-xs border-l bg-gray-50">
                            <div class="flex gap-2 justify-center">
                                <span class="text-green-600 font-bold" title="Present">P:<?php echo $present + $late; ?></span>
                                <span class="text-red-600 font-bold" title="Absent">A:<?php echo $absent; ?></span>
                                <span class="text-blue-600 font-bold" title="Leave">L:<?php echo $leave; ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                <div class="flex items-center"><span class="w-3 h-3 bg-green-100 border border-green-200 rounded mr-2"></span> <span class="font-medium">P</span> = Present</div>
                <div class="flex items-center"><span class="w-3 h-3 bg-amber-100 border border-amber-200 rounded mr-2"></span> <span class="font-medium">L</span> = Late</div>
                <div class="flex items-center"><span class="w-3 h-3 bg-red-100 border border-red-200 rounded mr-2"></span> <span class="font-medium">A</span> = Absent</div>
                <div class="flex items-center"><span class="w-3 h-3 bg-blue-100 border border-blue-200 rounded mr-2"></span> <span class="font-medium">LV</span> = On Leave</div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>