<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in() || !isset($_GET['id'])) {
    header('Location: employees.php');
    exit();
}

$employee_id = (int)$_GET['id'];
$employee_handler = new Employee($pdo);
$employee = $employee_handler->get_by_id($employee_id);

if (!$employee) {
    header('Location: employees.php');
    exit();
}

$attendance_handler = new Attendance($pdo);
$payroll_handler = new Payroll($pdo);

$attendance_history = $attendance_handler->get_attendance_by_employee($employee_id);
$payroll_history = $payroll_handler->get_payroll_history(); // This should be filtered by employee

$page_title = 'Employee Profile';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Employee Profile</h1>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <img src="../assets/img/<?php echo $employee['profile_picture'] ?? 'default-avatar.png'; ?>" alt="Profile Picture" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                <h4><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></p>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#photoModal">
                    <i class="fas fa-camera"></i> Update Photo
                </button>
                
                <?php if (isset($_GET['photo_updated'])): ?>
                    <div class="alert alert-success mt-2">
                        <i class="fas fa-check"></i> Profile photo updated successfully!
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger mt-2">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <?php 
                        switch($_GET['error']) {
                            case 'upload_failed': echo 'Failed to upload photo. Please try again.'; break;
                            case 'invalid_type': echo 'Invalid file type. Please use JPG, PNG, or GIF.'; break;
                            case 'file_too_large': echo 'File too large. Maximum size is 2MB.'; break;
                            case 'invalid_image': echo 'Invalid image file.'; break;
                            default: echo 'An error occurred. Please try again.';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="profile-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">Profile Details</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab" aria-controls="attendance" aria-selected="false">Attendance</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button" role="tab" aria-controls="payroll" aria-selected="false">Payroll</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="salary-advance-tab" data-bs-toggle="tab" data-bs-target="#salary-advance" type="button" role="tab" aria-controls="salary-advance" aria-selected="false">Salary Advance</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="loan-tab" data-bs-toggle="tab" data-bs-target="#loan" type="button" role="tab" aria-controls="loan" aria-selected="false">Loan</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payslip-tab" data-bs-toggle="tab" data-bs-target="#payslip" type="button" role="tab" aria-controls="payslip" aria-selected="false">Payslip</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="certificate-tab" data-bs-toggle="tab" data-bs-target="#certificate" type="button" role="tab" aria-controls="certificate" aria-selected="false">Certificate</button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="profile-tabs-content">
                    <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                        <table class="table table-striped">
                            <tbody>
                                <tr><th>First Name</th><td><?php echo htmlspecialchars($employee['first_name']); ?></td></tr>
                                <tr><th>Last Name</th><td><?php echo htmlspecialchars($employee['last_name']); ?></td></tr>
                                <tr><th>Email</th><td><?php echo htmlspecialchars($employee['email']); ?></td></tr>
                                <tr><th>Phone</th><td><?php echo htmlspecialchars($employee['phone']); ?></td></tr>
                                <tr><th>Address</th><td><?php echo htmlspecialchars($employee['address']); ?></td></tr>
                                <tr><th>Hire Date</th><td><?php echo format_date($employee['hire_date'], 'M d, Y'); ?></td></tr>
                                <tr><th>Base Salary</th><td>$<?php echo htmlspecialchars(number_format($employee['base_salary'], 2)); ?></td></tr>
                                <tr><th>Status</th><td><span class="badge bg-<?php echo $employee['status'] == 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst(str_replace('_', ' ', $employee['status'])); ?></span></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                        <table class="table table-bordered">
                            <thead><tr><th>Clock In</th><th>Clock Out</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($attendance_history as $record): ?>
                                    <tr>
                                        <td><?php echo $record['clock_in'] ? format_date($record['clock_in']) : 'N/A'; ?></td>
                                        <td><?php echo $record['clock_out'] ? format_date($record['clock_out']) : 'N/A'; ?></td>
                                        <td><span class="badge bg-success"><?php echo ucfirst($record['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="payroll" role="tabpanel" aria-labelledby="payroll-tab">
                        <table class="table table-bordered">
                            <thead><tr><th>Pay Period</th><th>Gross Salary</th><th>Deductions</th><th>Net Salary</th></tr></thead>
                            <tbody>
                                <?php foreach ($payroll_history as $payroll): if($payroll['employee_id'] == $employee_id): ?>
                                    <tr>
                                        <td><?php echo format_date($payroll['pay_period_start'], 'M d, Y') . ' - ' . format_date($payroll['pay_period_end'], 'M d, Y'); ?></td>
                                        <td>$<?php echo htmlspecialchars(number_format($payroll['gross_salary'], 2)); ?></td>
                                        <td>$<?php echo htmlspecialchars(number_format($payroll['deductions'], 2)); ?></td>
                                        <td>$<?php echo htmlspecialchars(number_format($payroll['net_salary'], 2)); ?></td>
                                    </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Salary Advance Tab -->
                    <div class="tab-pane fade" id="salary-advance" role="tabpanel">
                        <?php
                        $stmt = $pdo->prepare('
                            SELECT * FROM salary_advances 
                            WHERE employee_id = ? 
                            ORDER BY advance_date DESC
                        ');
                        $stmt->execute([$employee_id]);
                        $advances = $stmt->fetchAll();
                        ?>
                        
                        <?php if (empty($advances)): ?>
                            <p class="text-muted">No salary advances found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>For Month</th>
                                            <th>Status</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($advances as $advance): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($advance['advance_date'])); ?></td>
                                                <td>$<?php echo number_format($advance['amount'], 2); ?></td>
                                                <td><?php echo date('F Y', mktime(0, 0, 0, $advance['advance_month'], 1, $advance['advance_year'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $advance['status'] === 'approved' ? 'success' : ($advance['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                        <?php echo ucfirst($advance['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($advance['reason'] ?: 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Loan Tab -->
                    <div class="tab-pane fade" id="loan" role="tabpanel">
                        <?php
                        $stmt = $pdo->prepare('
                            SELECT l.*, 
                                   COALESCE(SUM(li.amount), 0) as total_paid,
                                   (l.amount - COALESCE(SUM(li.amount), 0)) as remaining_balance
                            FROM loans l
                            LEFT JOIN loan_installments li ON l.id = li.loan_id
                            WHERE l.employee_id = ?
                            GROUP BY l.id
                            ORDER BY l.loan_date DESC
                        ');
                        $stmt->execute([$employee_id]);
                        $loans = $stmt->fetchAll();
                        ?>
                        
                        <?php if (empty($loans)): ?>
                            <p class="text-muted">No loans found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Type</th>
                                            <th>Paid</th>
                                            <th>Remaining</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loans as $loan): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($loan['loan_date'])); ?></td>
                                                <td>$<?php echo number_format($loan['amount'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $loan['installment_type'] === 'fixed' ? 'primary' : 'warning'; ?>">
                                                        <?php echo ucfirst($loan['installment_type']); ?> EMI
                                                    </span>
                                                </td>
                                                <td>$<?php echo number_format($loan['total_paid'], 2); ?></td>
                                                <td>$<?php echo number_format($loan['remaining_balance'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $loan['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($loan['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Payslip Tab -->
                    <div class="tab-pane fade" id="payslip" role="tabpanel">
                        <form action="payslip.php" method="POST" class="row g-3">
                            <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                            <div class="col-md-6">
                                <label for="payslip_month" class="form-label">Month</label>
                                <select class="form-select" name="month" id="payslip_month" required>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo sprintf('%02d', $i); ?>" 
                                                <?php echo sprintf('%02d', $i) == date('m') ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="payslip_year" class="form-label">Year</label>
                                <select class="form-select" name="year" id="payslip_year" required>
                                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" name="generate_payslip" class="btn btn-primary">Generate Payslip</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Certificate Tab -->
                    <div class="tab-pane fade" id="certificate" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h5>Salary Certificate</h5>
                                        <p class="text-muted">Generate official salary certificate</p>
                                        <a href="pdf_handler.php?type=salary_certificate&employee_id=<?php echo $employee_id; ?>" 
                                           target="_blank" class="btn btn-primary">Generate</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h5>Employment Certificate</h5>
                                        <p class="text-muted">Generate employment verification letter</p>
                                        <a href="pdf_handler.php?type=employment_certificate&employee_id=<?php echo $employee_id; ?>" 
                                           target="_blank" class="btn btn-secondary">Generate</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Photo Update Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="photoModalLabel">Update Profile Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="update_photo.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                    <div class="mb-3">
                        <label for="profile_photo" class="form-label">Choose Photo</label>
                        <input type="file" class="form-control" name="profile_photo" id="profile_photo" 
                               accept="image/*" required>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> Maximum file size: 2MB. Supported formats: JPG, PNG, GIF
                        </div>
                    </div>
                    <div class="mb-3">
                        <img id="photo_preview" src="#" alt="Preview" style="max-width: 200px; display: none;" class="img-thumbnail">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_photo" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Update Photo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Photo preview functionality
document.getElementById('profile_photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Validate file size
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            this.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please select a valid image file (JPG, PNG, or GIF)');
            this.value = '';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('photo_preview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});

// Clear preview when modal is closed
document.getElementById('photoModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('profile_photo').value = '';
    document.getElementById('photo_preview').style.display = 'none';
});
</script>

<?php
include __DIR__ . '/../templates/footer.php';
?>
