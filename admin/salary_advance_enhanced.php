<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$employee_handler = new Employee($pdo);
$advance_handler = new SalaryAdvance($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_advance'])) {
        $data = [
            'employee_id' => (int)$_POST['employee_id'],
            'amount' => (float)$_POST['amount'],
            'advance_month' => $_POST['advance_month'],
            'advance_year' => $_POST['advance_year'],
            'reason' => sanitize_input($_POST['reason']),
            'status' => 'pending'
        ];
        
        $stmt = $pdo->prepare('
            INSERT INTO salary_advances (employee_id, advance_date, amount, advance_month, advance_year, reason, status) 
            VALUES (:employee_id, CURDATE(), :amount, :advance_month, :advance_year, :reason, :status)
        ');
        $stmt->execute($data);
        
        header('Location: salary_advance_enhanced.php?success=1');
        exit();
    }
    
    if (isset($_POST['update_status'])) {
        $advance_id = (int)$_POST['advance_id'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare('UPDATE salary_advances SET status = ? WHERE id = ?');
        $stmt->execute([$status, $advance_id]);
        
        header('Location: salary_advance_enhanced.php?updated=1');
        exit();
    }
}

// Get filter parameters
$employee_filter = $_GET['employee_id'] ?? '';
$month_filter = $_GET['month'] ?? '';
$year_filter = $_GET['year'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query for advances
$query = "
    SELECT sa.*, e.first_name, e.last_name, p.name as position_name
    FROM salary_advances sa
    JOIN employees e ON sa.employee_id = e.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE 1=1
";

$params = [];

if ($employee_filter) {
    $query .= " AND e.id = ?";
    $params[] = $employee_filter;
}

if ($month_filter) {
    $query .= " AND sa.advance_month = ?";
    $params[] = $month_filter;
}

if ($year_filter) {
    $query .= " AND sa.advance_year = ?";
    $params[] = $year_filter;
}

if ($status_filter) {
    $query .= " AND sa.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY sa.advance_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$advances = $stmt->fetchAll();

$employees = $employee_handler->get_all_active();

$page_title = 'Salary Advance Management';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Salary Advance Management</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Salary advance request created successfully!</div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">Advance status updated successfully!</div>
<?php endif; ?>

<!-- Filter Section -->
<div class="card mb-4">
    <div class="card-header">Filter Advances</div>
    <div class="card-body">
        <form action="salary_advance_enhanced.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="employee_id" class="form-label">Employee</label>
                <select class="form-select employee-search" name="employee_id" id="employee_id">
                    <option value="">All Employees</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['id']; ?>" 
                                <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . ($employee['position_name'] ?? 'No Position')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="month" class="form-label">Month</label>
                <select class="form-select" name="month" id="month">
                    <option value="">All Months</option>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo sprintf('%02d', $i); ?>" 
                                <?php echo $month_filter == sprintf('%02d', $i) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" name="year" id="year">
                    <option value="">All Years</option>
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" 
                                <?php echo $year_filter == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" name="status" id="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Create New Advance -->
<div class="card mb-4">
    <div class="card-header">Create New Salary Advance</div>
    <div class="card-body">
        <form action="salary_advance_enhanced.php" method="POST" class="row g-3">
            <div class="col-md-4">
                <label for="new_employee_id" class="form-label">Employee</label>
                <select class="form-select employee-search" name="employee_id" id="new_employee_id" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['id']; ?>">
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . ($employee['position_name'] ?? 'No Position')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="advance_month" class="form-label">For Month</label>
                <select class="form-select" name="advance_month" id="advance_month" required>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo sprintf('%02d', $i); ?>" 
                                <?php echo sprintf('%02d', $i) == date('m') ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="advance_year" class="form-label">Year</label>
                <select class="form-select" name="advance_year" id="advance_year" required>
                    <?php for ($y = date('Y'); $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="amount" class="form-label">Amount</label>
                <input type="number" step="0.01" class="form-control" name="amount" id="amount" required>
            </div>
            <div class="col-md-2">
                <label for="reason" class="form-label">Reason</label>
                <input type="text" class="form-control" name="reason" id="reason" placeholder="Optional">
            </div>
            <div class="col-md-12">
                <button type="submit" name="create_advance" class="btn btn-success">Create Advance Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Advances List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Salary Advances</span>
        <span class="badge bg-info"><?php echo count($advances); ?> records</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Position</th>
                        <th>Amount</th>
                        <th>For Month</th>
                        <th>Request Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advances as $advance): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($advance['first_name'] . ' ' . $advance['last_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($advance['position_name'] ?? 'N/A'); ?></td>
                            <td><strong>$<?php echo number_format($advance['amount'], 2); ?></strong></td>
                            <td><?php echo date('F Y', mktime(0, 0, 0, $advance['advance_month'], 1, $advance['advance_year'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($advance['advance_date'])); ?></td>
                            <td><?php echo htmlspecialchars($advance['reason'] ?: 'N/A'); ?></td>
                            <td>
                                <?php
                                $badge_class = [
                                    'pending' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $badge_class[$advance['status']]; ?>">
                                    <?php echo ucfirst($advance['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($advance['status'] === 'pending'): ?>
                                    <form action="salary_advance_enhanced.php" method="POST" class="d-inline">
                                        <input type="hidden" name="advance_id" value="<?php echo $advance['id']; ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" name="update_status" class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                    <form action="salary_advance_enhanced.php" method="POST" class="d-inline">
                                        <input type="hidden" name="advance_id" value="<?php echo $advance['id']; ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" name="update_status" class="btn btn-sm btn-danger">Reject</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">No actions</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($advances)): ?>
            <div class="text-center py-4">
                <h5 class="text-muted">No salary advances found</h5>
                <p>Try adjusting your filters or create a new advance request.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.employee-search {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6'/%3e%3c/svg%3e");
}
</style>

<?php
include __DIR__ . '/../templates/footer.php';
?>
