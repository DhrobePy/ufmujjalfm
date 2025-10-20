<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in() || !is_superadmin()) {
    header('Location: ../auth/login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_holiday'])) {
        $stmt = $pdo->prepare('
            INSERT INTO holidays (holiday_date, holiday_name, description) 
            VALUES (?, ?, ?)
        ');
        $stmt->execute([
            $_POST['holiday_date'],
            sanitize_input($_POST['holiday_name']),
            sanitize_input($_POST['description'])
        ]);
        header('Location: holidays.php?success=1');
        exit();
    }
    
    if (isset($_POST['delete_holiday'])) {
        $stmt = $pdo->prepare('DELETE FROM holidays WHERE id = ?');
        $stmt->execute([$_POST['holiday_id']]);
        header('Location: holidays.php?deleted=1');
        exit();
    }
}

// Get holidays for current year and next year
$current_year = date('Y');
$stmt = $pdo->prepare('
    SELECT * FROM holidays 
    WHERE YEAR(holiday_date) >= ? 
    ORDER BY holiday_date ASC
');
$stmt->execute([$current_year]);
$holidays = $stmt->fetchAll();

// Get holiday count by month for current year
$stmt = $pdo->prepare('
    SELECT MONTH(holiday_date) as month, COUNT(*) as count 
    FROM holidays 
    WHERE YEAR(holiday_date) = ? 
    GROUP BY MONTH(holiday_date)
');
$stmt->execute([$current_year]);
$monthly_holidays = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$page_title = 'Holiday Management';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Holiday Management</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> Holiday added successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-trash"></i> Holiday deleted successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5>Total Holidays (<?php echo $current_year; ?>)</h5>
                <h2><?php echo count(array_filter($holidays, function($h) use ($current_year) { 
                    return date('Y', strtotime($h['holiday_date'])) == $current_year; 
                })); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5>Working Days/Month</h5>
                <h2><?php echo 30 - (array_sum($monthly_holidays) / 12); ?></h2>
                <small>Average</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5>Upcoming Holidays</h5>
                <h2><?php echo count(array_filter($holidays, function($h) { 
                    return strtotime($h['holiday_date']) >= strtotime('today'); 
                })); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5>This Month</h5>
                <h2><?php echo $monthly_holidays[date('n')] ?? 0; ?></h2>
                <small><?php echo date('F'); ?> holidays</small>
            </div>
        </div>
    </div>
</div>

<!-- Add Holiday -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-plus"></i> Add New Holiday
    </div>
    <div class="card-body">
        <form action="holidays.php" method="POST" class="row g-3">
            <div class="col-md-3">
                <label for="holiday_date" class="form-label">Holiday Date *</label>
                <input type="date" class="form-control" name="holiday_date" id="holiday_date" required>
            </div>
            <div class="col-md-4">
                <label for="holiday_name" class="form-label">Holiday Name *</label>
                <input type="text" class="form-control" name="holiday_name" id="holiday_name" 
                       placeholder="e.g., New Year's Day" required>
            </div>
            <div class="col-md-3">
                <label for="description" class="form-label">Description</label>
                <input type="text" class="form-control" name="description" id="description" 
                       placeholder="Optional description">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" name="add_holiday" class="btn btn-success w-100">
                    <i class="fas fa-plus"></i> Add Holiday
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Holidays List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-calendar-alt"></i> Holidays List</span>
        <span class="badge bg-info"><?php echo count($holidays); ?> total holidays</span>
    </div>
    <div class="card-body">
        <?php if (empty($holidays)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No holidays defined</h5>
                <p class="text-muted">Add holidays to exclude them from salary calculations and attendance requirements.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Holiday Name</th>
                            <th>Description</th>
                            <th>Day of Week</th>
                            <th>Days Until</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($holidays as $holiday): 
                            $holiday_date = strtotime($holiday['holiday_date']);
                            $today = strtotime('today');
                            $days_until = ceil(($holiday_date - $today) / (60 * 60 * 24));
                            $is_past = $holiday_date < $today;
                            $is_today = date('Y-m-d') === $holiday['holiday_date'];
                        ?>
                            <tr class="<?php echo $is_today ? 'table-warning' : ($is_past ? 'text-muted' : ''); ?>">
                                <td>
                                    <strong><?php echo date('M d, Y', $holiday_date); ?></strong>
                                    <?php if ($is_today): ?>
                                        <span class="badge bg-warning text-dark ms-2">Today</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($holiday['holiday_name']); ?></strong>
                                    <?php if ($is_past): ?>
                                        <small class="text-muted d-block">Past holiday</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($holiday['description'] ?: 'No description'); ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo date('l', $holiday_date); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($is_today): ?>
                                        <span class="badge bg-warning text-dark">Today</span>
                                    <?php elseif ($is_past): ?>
                                        <span class="text-muted"><?php echo abs($days_until); ?> days ago</span>
                                    <?php else: ?>
                                        <span class="text-success"><?php echo $days_until; ?> days</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="holidays.php" method="POST" class="d-inline">
                                        <input type="hidden" name="holiday_id" value="<?php echo $holiday['id']; ?>">
                                        <button type="submit" name="delete_holiday" class="btn btn-sm btn-outline-danger" 
                                                onclick="return confirm('Are you sure you want to delete this holiday?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Holiday Impact Information -->
<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-info-circle"></i> Holiday Impact on Payroll
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>How holidays affect salary calculation:</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success"></i> Holidays are excluded from total working days</li>
                    <li><i class="fas fa-check text-success"></i> Employees are not penalized for holiday absences</li>
                    <li><i class="fas fa-check text-success"></i> Daily salary = Monthly salary ÷ (30 - holidays)</li>
                    <li><i class="fas fa-check text-success"></i> Attendance on holidays is optional</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Monthly Holiday Distribution (<?php echo $current_year; ?>):</h6>
                <div class="row">
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                        <div class="col-3 mb-2">
                            <small class="text-muted"><?php echo date('M', mktime(0, 0, 0, $month, 1)); ?>:</small>
                            <span class="badge bg-primary"><?php echo $monthly_holidays[$month] ?? 0; ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
