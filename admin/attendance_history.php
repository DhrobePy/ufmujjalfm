<?php
require_once __DIR__ . '/../core/init.php';

if (!is_superadmin()) {
    header('Location: ../auth/login.php');
    exit();
}

$employee_handler = new Employee($pdo);

// Get date range for filtering
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$employee_filter = $_GET['employee_id'] ?? '';

// Build query for attendance history
$query = "
    SELECT a.*, e.first_name, e.last_name, e.id as employee_id,
           DATE(a.clock_in) as attendance_date,
           CASE 
               WHEN a.manual_entry = 1 THEN 'Manual'
               ELSE 'Self-Service'
           END as entry_type
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    WHERE DATE(a.clock_in) BETWEEN ? AND ?
";

$params = [$start_date, $end_date];

if ($employee_filter) {
    $query .= " AND e.id = ?";
    $params[] = $employee_filter;
}

$query .= " ORDER BY a.clock_in DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll();

// Get summary statistics
$summary_query = "
    SELECT 
        DATE(a.clock_in) as date,
        COUNT(*) as total_employees,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    WHERE DATE(a.clock_in) BETWEEN ? AND ?
    GROUP BY DATE(a.clock_in)
    ORDER BY DATE(a.clock_in) DESC
";

$stmt = $pdo->prepare($summary_query);
$stmt->execute([$start_date, $end_date]);
$daily_summary = $stmt->fetchAll();

$employees = $employee_handler->get_all_active();

$page_title = 'Daily Attendance History';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Daily Attendance History</h1>
<p class="text-muted">View complete attendance records with daily summaries and detailed employee attendance.</p>

<div class="card mb-4">
    <div class="card-header">Filter Attendance Records</div>
    <div class="card-body">
        <form action="attendance_history.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" id="start_date" 
                       value="<?php echo htmlspecialchars($start_date); ?>" required>
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" id="end_date" 
                       value="<?php echo htmlspecialchars($end_date); ?>" required>
            </div>
            <div class="col-md-4">
                <label for="employee_id" class="form-label">Employee (Optional)</label>
                <select class="form-select" name="employee_id" id="employee_id">
                    <option value="">All Employees</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['id']; ?>" 
                                <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Daily Summary -->
<div class="card mb-4">
    <div class="card-header">Daily Attendance Summary</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr class="table-dark">
                        <th>Date</th>
                        <th>Total Employees</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Attendance Rate</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daily_summary as $summary): ?>
                        <?php 
                        $attendance_rate = $summary['total_employees'] > 0 
                            ? ($summary['present_count'] / $summary['total_employees']) * 100 
                            : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo date('M d, Y', strtotime($summary['date'])); ?></strong></td>
                            <td><?php echo $summary['total_employees']; ?></td>
                            <td><span class="badge bg-success"><?php echo $summary['present_count']; ?></span></td>
                            <td><span class="badge bg-danger"><?php echo $summary['absent_count']; ?></span></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $attendance_rate; ?>%">
                                        <?php echo number_format($attendance_rate, 1); ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="manual_attendance.php?date=<?php echo $summary['date']; ?>" 
                                   class="btn btn-sm btn-primary">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detailed Records -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Detailed Attendance Records</span>
        <span class="badge bg-info"><?php echo count($attendance_records); ?> records</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Status</th>
                        <th>Entry Type</th>
                        <th>Working Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance_records as $record): ?>
                        <?php
                        $working_hours = 0;
                        if ($record['clock_in'] && $record['clock_out'] && $record['status'] === 'present') {
                            $start = new DateTime($record['clock_in']);
                            $end = new DateTime($record['clock_out']);
                            $diff = $start->diff($end);
                            $working_hours = $diff->h + ($diff->i / 60);
                        }
                        ?>
                        <tr class="<?php echo $record['status'] === 'present' ? 'table-light' : 'table-warning'; ?>">
                            <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                            </td>
                            <td>
                                <?php if ($record['status'] === 'present'): ?>
                                    <?php echo date('H:i:s', strtotime($record['clock_in'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['clock_out'] && $record['status'] === 'present'): ?>
                                    <?php echo date('H:i:s', strtotime($record['clock_out'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['status'] === 'present'): ?>
                                    <span class="badge bg-success">Present</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Absent</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $record['entry_type'] === 'Manual' ? 'warning' : 'info'; ?>">
                                    <?php echo $record['entry_type']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($working_hours > 0): ?>
                                    <?php echo number_format($working_hours, 1); ?> hrs
                                <?php else: ?>
                                    <span class="text-muted">0 hrs</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($attendance_records)): ?>
            <div class="text-center py-4">
                <h5 class="text-muted">No attendance records found</h5>
                <p>Try adjusting your date range or employee filter.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../templates/footer.php';
?>
