<?php
require_once __DIR__ . '/../core/init.php';

if (!is_superadmin()) { 
    exit('Access Denied: You do not have permission to view this page.'); 
}

$payroll_handler = new Payroll($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payroll_ids'])) {
    $payroll_ids = $_POST['payroll_ids'];
    $new_status = isset($_POST['approve']) ? 'approved' : 'rejected';
    
    if (!empty($payroll_ids)) {
        $payroll_handler->update_payroll_status($payroll_ids, $new_status);
    }
    
    header('Location: approve_payroll.php?status_updated=1');
    exit();
}

$pending_payrolls = $payroll_handler->get_pending_payroll_details();

$grouped_payrolls = [];
foreach ($pending_payrolls as $payroll) {
    $period_key = $payroll['pay_period_start'] . '_' . $payroll['pay_period_end'];
    $grouped_payrolls[$period_key][] = $payroll;
}

$page_title = 'Approve Payroll';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Approve Payroll Disbursement</h1>
<p class="text-muted">Step 2: Admin reviews, edits (if necessary), and approves the salary list.</p>

<?php if (isset($_GET['status_updated'])): ?>
    <div class="alert alert-success">Payroll statuses updated successfully!</div>
<?php endif; ?>

<?php if (empty($grouped_payrolls)): ?>
    <div class="alert alert-info">There are no payrolls currently pending approval.</div>
<?php else: ?>
    <form action="approve_payroll.php" method="POST">
        <?php foreach ($grouped_payrolls as $period => $payrolls): ?>
            <?php
                [$start_date, $end_date] = explode('_', $period);
                $period_heading = "Pay Period: " . date('F d, Y', strtotime($start_date)) . " to " . date('F d, Y', strtotime($end_date));
            ?>
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><?php echo $period_heading; ?></h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" style="font-size: 0.9rem;">
                            <thead class="table-light">
                                <tr>
                                    <th><input type="checkbox" onclick="toggleGroup(this, '<?php echo $period; ?>');"></th>
                                    <th>Employee</th>
                                    <th>Position</th>
                                    <th>Address</th>
                                    <th class="text-end">Gross Salary</th>
                                    <th class="text-center">Absent Days</th>
                                    <th class="text-end text-danger">Absence Deduction</th>
                                    <th class="text-end text-danger">Advance Deduction</th>
                                    <th class="text-end text-danger">Loan (EMI)</th>
                                    <th class="text-end fw-bold">Net Salary</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payrolls as $payroll): ?>
                                    <tr id="payroll-row-<?php echo $payroll['id']; ?>">
                                        <td><input type="checkbox" class="payroll-checkbox-<?php echo $period; ?>" name="payroll_ids[]" value="<?php echo $payroll['id']; ?>"></td>
                                        <td><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payroll['position_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($payroll['address']); ?></td>
                                        <td class="text-end" data-field="gross_salary">Tk.<?php echo number_format($payroll['gross_salary'], 2); ?></td>
                                        <td class="text-center"><?php echo $payroll['absent_days']; ?></td>
                                        <td class="text-end text-danger" data-field="absence_deduction">(Tk.<?php echo number_format($payroll['salary_deducted_for_absent'], 2); ?>)</td>
                                        <td class="text-end text-danger" data-field="advance_deduction">(Tk.<?php echo number_format($payroll['advance_salary_deducted'], 2); ?>)</td>
                                        <td class="text-end text-danger" data-field="loan_deduction">(Tk.<?php echo number_format($payroll['loan_deduction'], 2); ?>)</td>
                                        <td class="text-end fw-bold" data-field="net_salary">Tk.<?php echo number_format($payroll['net_salary'], 2); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-info" onclick='openEditModal(<?php echo json_encode($payroll, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="card-footer text-end">
            <button type="submit" name="reject" class="btn btn-danger btn-lg">Reject Selected</button>
            <button type="submit" name="approve" class="btn btn-success btn-lg">Approve Selected for Disbursement</button>
        </div>
    </form>
<?php endif; ?>

<div class="modal fade" id="editPayrollModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Payroll Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editPayrollForm">
                    <input type="hidden" id="edit_payroll_id" name="payroll_id">
                    <h6 id="employeeNameHeader"></h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gross Salary (Based on Attendance)</label>
                            <div class="input-group">
                                <span class="input-group-text">Tk.</span>
                                <input type="number" step="0.01" class="form-control" id="edit_gross_salary" name="gross_salary" readonly>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Absence Deduction</label>
                             <div class="input-group">
                                <span class="input-group-text">Tk.</span>
                                <input type="number" step="0.01" class="form-control" id="edit_absence_deduction" name="absence_deduction" readonly>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h5 class="text-danger">Adjustable Deductions</h5>
                    <div class="row">
                         <div class="col-md-6 mb-3">
                            <label class="form-label">Salary Advance Deduction</label>
                             <div class="input-group">
                                <span class="input-group-text">Tk.</span>
                                <input type="number" step="0.01" class="form-control" id="edit_advance_deduction" name="advance_deduction">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Loan Deduction</label>
                             <div class="input-group">
                                <span class="input-group-text">Tk.</span>
                                <input type="number" step="0.01" class="form-control" id="edit_loan_deduction" name="loan_deduction">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="savePayrollChanges()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // This ensures the code runs only after the Bootstrap JS is loaded.
    const editModalEl = document.getElementById('editPayrollModal');
    if (editModalEl) {
        const editModal = new bootstrap.Modal(editModalEl);
        
        // Make the openEditModal function globally accessible
        window.openEditModal = function(payrollData) {
            const grossSalary = parseFloat(payrollData.gross_salary) || 0;
            const absenceDeduction = parseFloat(payrollData.salary_deducted_for_absent) || 0;
            const advanceDeduction = parseFloat(payrollData.advance_salary_deducted) || 0;
            const loanDeduction = parseFloat(payrollData.loan_deduction) || 0;

            document.getElementById('edit_payroll_id').value = payrollData.id;
            document.getElementById('employeeNameHeader').textContent = 'Editing for: ' + payrollData.first_name + ' ' + payrollData.last_name;
            document.getElementById('edit_gross_salary').value = grossSalary.toFixed(2);
            document.getElementById('edit_absence_deduction').value = absenceDeduction.toFixed(2);
            document.getElementById('edit_advance_deduction').value = advanceDeduction.toFixed(2);
            document.getElementById('edit_loan_deduction').value = loanDeduction.toFixed(2);
            
            editModal.show();
        };

        // Make the save function globally accessible
        window.savePayrollChanges = function() {
            const form = document.getElementById('editPayrollForm');
            const formData = new FormData(form);

            fetch('ajax_update_payroll.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const payrollId = formData.get('payroll_id');
                    const row = document.getElementById('payroll-row-' + payrollId);
                    
                    row.querySelector('[data-field="gross_salary"]').textContent = 'Tk.' + data.newData.gross_salary;
                    row.querySelector('[data-field="absence_deduction"]').textContent = '(Tk.' + data.newData.absence_deduction + ')';
                    row.querySelector('[data-field="advance_deduction"]').textContent = '(Tk.' + data.newData.advance_deduction + ')';
                    row.querySelector('[data-field="loan_deduction"]').textContent = '(Tk.' + data.newData.loan_deduction + ')';
                    row.querySelector('[data-field="net_salary"]').textContent = 'Tk.' + data.newData.net_salary;
                    
                    editModal.hide();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred. Please try again.');
            });
        };
    }
});

function toggleGroup(source, groupKey) {
    const checkboxes = document.querySelectorAll('.payroll-checkbox-' + groupKey);
    checkboxes.forEach(checkbox => {
        checkbox.checked = source.checked;
    });
}
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>