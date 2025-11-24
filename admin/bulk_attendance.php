<?php
// new_ufmhrm/admin/attendance_calendar.php (FIXED VERSION)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// Handle AJAX requests for updating attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $employee_id = (int)$_POST['employee_id'];
    $date = $_POST['date'];
    $status = $_POST['status']; // 'present', 'absent', or 'remove'
    
    try {
        // Check if attendance record exists
        $existing = $db->query(
            "SELECT id FROM attendance WHERE employee_id = ? AND DATE(clock_in) = ?",
            [$employee_id, $date]
        )->first();
        
        if ($status === 'remove') {
            // Remove attendance record
            if ($existing) {
                $db->query("DELETE FROM attendance WHERE id = ?", [$existing->id]);
                echo json_encode(['success' => true, 'message' => 'Attendance removed']);
            } else {
                echo json_encode(['success' => true, 'message' => 'No record to remove']);
            }
        } else {
            // Add or update attendance
            $clock_in = ($status === 'present') ? "$date 09:00:00" : "$date 00:00:00";
            $clock_out = ($status === 'present') ? "$date 17:00:00" : NULL;
            
            if ($existing) {
                $db->query(
                    "UPDATE attendance SET status = ?, clock_in = ?, clock_out = ?, manual_entry = 1 WHERE id = ?",
                    [$status, $clock_in, $clock_out, $existing->id]
                );
            } else {
                $db->insert('attendance', [
                    'employee_id' => $employee_id,
                    'clock_in' => $clock_in,
                    'clock_out' => $clock_out,
                    'status' => $status,
                    'manual_entry' => 1
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'Attendance updated']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle AJAX request to get attendance data for an employee
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'get_attendance') {
    header('Content-Type: application/json');
    
    $employee_id = (int)$_GET['employee_id'];
    $month = $_GET['month'];
    $year = $_GET['year'];
    
    // Get all attendance records for this employee in the selected month
    $startDate = "$year-$month-01";
    $endDate = date("Y-m-t", strtotime($startDate));
    
    $records = $db->query(
        "SELECT DATE(clock_in) as date, status FROM attendance 
         WHERE employee_id = ? AND DATE(clock_in) BETWEEN ? AND ?",
        [$employee_id, $startDate, $endDate]
    )->results();
    
    $attendance = [];
    foreach ($records as $record) {
        $attendance[$record->date] = $record->status;
    }
    
    echo json_encode(['success' => true, 'attendance' => $attendance]);
    exit();
}

$pageTitle = 'Calendar View - ' . APP_NAME;
include_once '../templates/header.php';

// Get all active employees
$employees = $db->query(
    "SELECT e.id, e.first_name, e.last_name, p.name as position_name, d.name as department_name 
     FROM employees e 
     LEFT JOIN positions p ON e.position_id = p.id 
     LEFT JOIN departments d ON p.department_id = d.id 
     WHERE e.status = 'active' 
     ORDER BY e.first_name, e.last_name"
)->results();

?>

<style>
    .calendar-day {
        min-height: 60px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: relative;
        border-radius: 8px;
        font-weight: 600;
        font-size: 1.1rem;
        transition: all 0.2s;
    }
    
    .calendar-day:not(.disabled):hover {
        transform: scale(1.05);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .calendar-day.selected {
        border: 3px solid #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }
    
    .calendar-day .status-icon {
        position: absolute;
        bottom: 4px;
        right: 4px;
        font-size: 0.75rem;
    }
</style>

<div class="space-y-4" x-data="attendanceCalendar()">
    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-xl border p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <div class="h-10 w-10 bg-primary-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-calendar-check text-primary-600"></i>
                </div>
                Interactive Attendance Calendar
            </h1>
            <a href="attendance.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
        </div>
    </div>

    <!-- Employee Selection & Legend -->
    <div class="bg-white rounded-2xl shadow-xl border p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Select Employee</label>
                <select x-model="selectedEmployee" @change="loadAttendance()" 
                        class="w-full px-4 py-2 rounded-lg border-2 focus:border-primary-500 outline-none">
                    <option value="">-- Choose an Employee --</option>
                    <?php foreach($employees as $emp): ?>
                        <option value="<?php echo $emp->id; ?>">
                            <?php echo htmlspecialchars($emp->first_name . ' ' . $emp->last_name); ?> 
                            (<?php echo htmlspecialchars($emp->position_name ?? 'N/A'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Legend -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Legend</label>
                <div class="flex flex-wrap gap-3">
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 bg-green-500 rounded"></div>
                        <span class="text-sm">Present</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 bg-red-500 rounded"></div>
                        <span class="text-sm">Absent</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 bg-gray-200 rounded border-2 border-gray-300"></div>
                        <span class="text-sm">Unmarked</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 bg-white rounded border-3 border-blue-500"></div>
                        <span class="text-sm">Selected</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div x-show="selectedEmployee" x-cloak class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
            <p class="text-sm text-blue-800">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Instructions:</strong> Click on any day to select it, then use the buttons below to mark as Present or Absent. 
                You can select multiple days by clicking them one by one.
            </p>
        </div>
    </div>

    <!-- Calendar -->
    <div x-show="selectedEmployee" x-cloak class="bg-white rounded-2xl shadow-xl border">
        <!-- Month Navigation -->
        <div class="p-4 border-b flex justify-between items-center bg-gray-50">
            <button @click="previousMonth()" 
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            
            <h2 class="text-xl font-bold" x-text="getMonthYearDisplay()"></h2>
            
            <button @click="nextMonth()" 
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <!-- Calendar Grid -->
        <div class="p-4">
            <!-- Day Headers -->
            <div class="grid grid-cols-7 gap-2 mb-2">
                <template x-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']">
                    <div class="text-center font-bold text-gray-600 text-sm py-2" x-text="day"></div>
                </template>
            </div>

            <!-- Calendar Days -->
            <div class="grid grid-cols-7 gap-2">
                <template x-for="(day, index) in calendarDays" :key="index">
                    <div 
                        @click="selectDay(day)"
                        :class="{
                            'opacity-30': !day.inCurrentMonth,
                            'cursor-pointer': day.inCurrentMonth && !day.isFuture,
                            'cursor-not-allowed opacity-50': day.isFuture,
                            'bg-green-500 text-white': day.status === 'present',
                            'bg-red-500 text-white': day.status === 'absent',
                            'bg-gray-100 hover:bg-gray-200': !day.status && day.inCurrentMonth && !day.isFuture,
                            'selected': day.selected,
                            'ring-4 ring-yellow-400': day.isToday
                        }"
                        class="calendar-day"
                        :title="getDayTitle(day)">
                        <span x-text="day.day"></span>
                        <template x-if="day.status">
                            <i :class="day.status === 'present' ? 'fa-check text-white' : 'fa-times text-white'" 
                               class="fas status-icon"></i>
                        </template>
                        <template x-if="day.selected">
                            <div class="absolute top-1 right-1 w-2 h-2 bg-blue-500 rounded-full"></div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- Action Buttons -->
        <div x-show="selectedDays.length > 0" class="p-4 border-t bg-gray-50">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="text-sm font-semibold text-gray-700">
                    <span x-text="selectedDays.length"></span> day(s) selected
                </div>
                <div class="flex gap-2 flex-wrap justify-center">
                    <button @click="markAsPresent()" 
                            class="px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-semibold shadow-md">
                        <i class="fas fa-check mr-2"></i>Mark as Present
                    </button>
                    <button @click="markAsAbsent()" 
                            class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition font-semibold shadow-md">
                        <i class="fas fa-times mr-2"></i>Mark as Absent
                    </button>
                    <button @click="clearSelected()" 
                            class="px-6 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition font-semibold shadow-md">
                        <i class="fas fa-eraser mr-2"></i>Clear Attendance
                    </button>
                    <button @click="deselectAll()" 
                            class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500 transition">
                        <i class="fas fa-times-circle mr-2"></i>Deselect All
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="p-4 border-t">
            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="bg-green-50 rounded-lg p-3">
                    <div class="text-3xl font-bold text-green-600" x-text="monthStats.present"></div>
                    <div class="text-xs text-gray-600 mt-1">Days Present</div>
                </div>
                <div class="bg-red-50 rounded-lg p-3">
                    <div class="text-3xl font-bold text-red-600" x-text="monthStats.absent"></div>
                    <div class="text-xs text-gray-600 mt-1">Days Absent</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-3xl font-bold text-gray-600" x-text="monthStats.unmarked"></div>
                    <div class="text-xs text-gray-600 mt-1">Days Unmarked</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function attendanceCalendar() {
    return {
        selectedEmployee: '',
        currentMonth: new Date().getMonth(),
        currentYear: new Date().getFullYear(),
        calendarDays: [],
        attendanceData: {},
        selectedDays: [],
        monthStats: {
            present: 0,
            absent: 0,
            unmarked: 0
        },

        init() {
            this.generateCalendar();
        },

        async loadAttendance() {
            if (!this.selectedEmployee) return;

            this.selectedDays = []; // Clear selection when changing employee

            try {
                const response = await fetch(
                    `?ajax=get_attendance&employee_id=${this.selectedEmployee}&month=${String(this.currentMonth + 1).padStart(2, '0')}&year=${this.currentYear}`
                );
                const data = await response.json();
                
                if (data.success) {
                    this.attendanceData = data.attendance;
                    this.generateCalendar();
                }
            } catch (error) {
                console.error('Error loading attendance:', error);
                alert('Failed to load attendance data');
            }
        },

        generateCalendar() {
            const firstDay = new Date(this.currentYear, this.currentMonth, 1);
            const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay();
            
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            this.calendarDays = [];
            let presentCount = 0, absentCount = 0, unmarkedCount = 0;

            // Previous month days
            const prevMonthLastDay = new Date(this.currentYear, this.currentMonth, 0).getDate();
            for (let i = startingDayOfWeek - 1; i >= 0; i--) {
                this.calendarDays.push({
                    day: prevMonthLastDay - i,
                    date: null,
                    inCurrentMonth: false,
                    status: null,
                    isFuture: false,
                    isToday: false,
                    selected: false
                });
            }

            // Current month days
            for (let day = 1; day <= daysInMonth; day++) {
                const date = `${this.currentYear}-${String(this.currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dayDate = new Date(this.currentYear, this.currentMonth, day);
                const isFuture = dayDate > today;
                const isToday = dayDate.getTime() === today.getTime();
                const status = this.attendanceData[date] || null;

                if (!isFuture) {
                    if (status === 'present') presentCount++;
                    else if (status === 'absent') absentCount++;
                    else unmarkedCount++;
                }

                this.calendarDays.push({
                    day: day,
                    date: date,
                    inCurrentMonth: true,
                    status: status,
                    isFuture: isFuture,
                    isToday: isToday,
                    selected: false
                });
            }

            // Next month days
            const remainingDays = 42 - this.calendarDays.length;
            for (let day = 1; day <= remainingDays; day++) {
                this.calendarDays.push({
                    day: day,
                    date: null,
                    inCurrentMonth: false,
                    status: null,
                    isFuture: false,
                    isToday: false,
                    selected: false
                });
            }

            this.monthStats = { present: presentCount, absent: absentCount, unmarked: unmarkedCount };
        },

        selectDay(day) {
            if (!day.inCurrentMonth || day.isFuture || !this.selectedEmployee) return;

            day.selected = !day.selected;
            
            if (day.selected) {
                if (!this.selectedDays.includes(day.date)) {
                    this.selectedDays.push(day.date);
                }
            } else {
                const index = this.selectedDays.indexOf(day.date);
                if (index > -1) {
                    this.selectedDays.splice(index, 1);
                }
            }
        },

        async markAsPresent() {
            await this.updateMultipleDays('present');
        },

        async markAsAbsent() {
            await this.updateMultipleDays('absent');
        },

        async clearSelected() {
            if (!confirm(`Remove attendance records for ${this.selectedDays.length} selected day(s)?`)) return;
            await this.updateMultipleDays('remove');
        },

        deselectAll() {
            this.calendarDays.forEach(day => day.selected = false);
            this.selectedDays = [];
        },

        async updateMultipleDays(status) {
            if (this.selectedDays.length === 0) return;

            const updates = this.selectedDays.map(date => this.updateAttendance(date, status));
            
            try {
                await Promise.all(updates);
                this.deselectAll();
                await this.loadAttendance();
                alert(`Successfully updated ${updates.length} day(s)`);
            } catch (error) {
                alert('Some updates failed. Please try again.');
            }
        },

        async updateAttendance(date, status) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('employee_id', this.selectedEmployee);
            formData.append('date', date);
            formData.append('status', status);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message);
            }
            return data;
        },

        previousMonth() {
            if (this.currentMonth === 0) {
                this.currentMonth = 11;
                this.currentYear--;
            } else {
                this.currentMonth--;
            }
            this.loadAttendance();
        },

        nextMonth() {
            if (this.currentMonth === 11) {
                this.currentMonth = 0;
                this.currentYear++;
            } else {
                this.currentMonth++;
            }
            this.loadAttendance();
        },

        getMonthYearDisplay() {
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                              'July', 'August', 'September', 'October', 'November', 'December'];
            return `${monthNames[this.currentMonth]} ${this.currentYear}`;
        },

        getDayTitle(day) {
            if (day.isFuture) return 'Future date';
            if (!day.inCurrentMonth) return '';
            if (day.status) return `Current: ${day.status}. Click to select, then use buttons below to update`;
            return 'Click to select this day';
        }
    }
}
</script>

<?php include_once '../templates/footer.php'; ?>