<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$employee_handler = new Employee($pdo);

// Handle salary structure updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_salary_structure'])) {
    $employee_id = (int)$_POST['employee_id'];
    $basic_salary = (float)$_POST['basic_salary'];
    $house_allowance = (float)$_POST['house_allowance'];
    $transport_allowance = (float)$_POST['transport_allowance'];
    $medical_allowance = (float)$_POST['medical_allowance'];
    $other_allowances = (float)$_POST['other_allowances'];
    $provident_fund = (float)$_POST['provident_fund'];
    $tax_deduction = (float)$_POST['tax_deduction'];
    $other_deductions = (float)$_POST['other_deductions'];
    
    $total_allowances = $house_allowance + $transport_allowance + $medical_allowance + $other_allowances;
    $total_deductions = $provident_fund + $tax_deduction + $other_deductions;
    $gross_salary = $basic_salary + $total_allowances;
    $net_salary = $gross_salary - $total_deductions;
    
    // Update employee's base salary to match the gross salary
    $employee_handler->update($employee_id, [
        'first_name' => $_POST['first_name'],
        'last_name' => $_POST['last_name'],
        'email' => $_POST['email'],
        'phone' => $_POST['phone'],
        'address' => $_POST['address'],
        'position_id' => $_POST['position_id'],
        'hire_date' => $_POST['hire_date'],
        'base_salary' => $gross_salary,
        'status' => $_POST['status']
    ]);
    
    // Store salary structure details
    $stmt = $pdo->prepare('
        INSERT INTO salary_structures (employee_id, basic_salary, house_allowance, transport_allowance, 
        medical_allowance, other_allowances, provident_fund, tax_deduction, other_deductions, 
        gross_salary, net_salary, created_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
        ON DUPLICATE KEY UPDATE
        basic_salary = VALUES(basic_salary),
        house_allowance = VALUES(house_allowance),
        transport_allowance = VALUES(transport_allowance),
        medical_allowance = VALUES(medical_allowance),
        other_allowances = VALUES(other_allowances),
        provident_fund = VALUES(provident_fund),
        tax_deduction = VALUES(tax_deduction),
        other_deductions = VALUES(other_deductions),
        gross_salary = VALUES(gross_salary),
        net_salary = VALUES(net_salary),
        updated_date = CURDATE()
    ');
    
    $stmt->execute([$employee_id, $basic_salary, $house_allowance, $transport_allowance, 
                   $medical_allowance, $other_allowances, $provident_fund, $tax_deduction, 
                   $other_deductions, $gross_salary, $net_salary]);
    
    header('Location: salary_structure.php?success=1');
    exit();
}

$employees = $employee_handler->get_all();
$positions = $employee_handler->get_positions();

// Get salary structure for selected employee
$selected_employee = null;
$salary_structure = null;
if (isset($_GET['employee_id'])) {
    $employee_id = (int)$_GET['employee_id'];
    $selected_employee = $employee_handler->get_by_id($employee_id);
    
    $stmt = $pdo->prepare('SELECT * FROM salary_structures WHERE employee_id = ?');
    $stmt->execute([$employee_id]);
    $salary_structure = $stmt->fetch();
}

$page_title = 'Salary Structure';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<h1 class="mt-4">Salary Structure Management</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Salary structure updated successfully!</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Select Employee</div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($employees as $employee): ?>
                        <a href="salary_structure.php?employee_id=<?php echo $employee['id']; ?>" 
                           class="list-group-item list-group-item-action <?php echo (isset($_GET['employee_id']) && $_GET['employee_id'] == $employee['id']) ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($employee['position_name'] ?? 'No Position'); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <?php if ($selected_employee): ?>
        <div class="card">
            <div class="card-header">
                Salary Structure - <?php echo htmlspecialchars($selected_employee['first_name'] . ' ' . $selected_employee['last_name']); ?>
            </div>
            <div class="card-body">
                <form action="salary_structure.php" method="POST">
                    <input type="hidden" name="update_salary_structure" value="1">
                    <input type="hidden" name="employee_id" value="<?php echo $selected_employee['id']; ?>">
                    
                    <!-- Hidden employee fields to maintain data -->
                    <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($selected_employee['first_name']); ?>">
                    <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($selected_employee['last_name']); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($selected_employee['email']); ?>">
                    <input type="hidden" name="phone" value="<?php echo htmlspecialchars($selected_employee['phone']); ?>">
                    <input type="hidden" name="address" value="<?php echo htmlspecialchars($selected_employee['address']); ?>">
                    <input type="hidden" name="position_id" value="<?php echo $selected_employee['position_id']; ?>">
                    <input type="hidden" name="hire_date" value="<?php echo $selected_employee['hire_date']; ?>">
                    <input type="hidden" name="status" value="<?php echo $selected_employee['status']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="text-success">Earnings</h5>
                            <div class="mb-3">
                                <label class="form-label">Basic Salary</label>
                                <input type="number" step="0.01" class="form-control" name="basic_salary" 
                                       value="<?php echo $salary_structure['basic_salary'] ?? $selected_employee['base_salary']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">House Allowance</label>
                                <input type="number" step="0.01" class="form-control" name="house_allowance" 
                                       value="<?php echo $salary_structure['house_allowance'] ?? 0; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Transport Allowance</label>
                                <input type="number" step="0.01" class="form-control" name="transport_allowance" 
                                       value="<?php echo $salary_structure['transport_allowance'] ?? 0; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Medical Allowance</label>
                                <input type="number" step="0.01" class="form-control" name="medical_allowance" 
                                       value="<?php echo $salary_structure['medical_allowance'] ?? 0; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Other Allowances</label>
                                <input type="number" step="0.01" class="form-control" name="other_allowances" 
                                       value="<?php echo $salary_structure['other_allowances'] ?? 0; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="text-danger">Deductions</h5>
                            <div class="mb-3">
                                <label class="form-label">Provident Fund</label>
                                <input type="number" step="0.01" class="form-control" name="provident_fund" 
                                       value="<?php echo $salary_structure['provident_fund'] ?? 0; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tax Deduction</label>
                                <input type="number" step="0.01" class="form-control" name="tax_deduction" 
                                       value="<?php echo $salary_structure['tax_deduction'] ?? 0; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Other Deductions</label>
                                <input type="number" step="0.01" class="form-control" name="other_deductions" 
                                       value="<?php echo $salary_structure['other_deductions'] ?? 0; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($salary_structure): ?>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Current Salary Summary</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Gross Salary:</strong> $<?php echo number_format($salary_structure['gross_salary'], 2); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Daily Rate:</strong> $<?php echo number_format($salary_structure['gross_salary'] / 30, 2); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Net Salary:</strong> $<?php echo number_format($salary_structure['net_salary'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Update Salary Structure</button>
                        <a href="salary_structure.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center">
                <h5>Select an employee from the list to manage their salary structure</h5>
                <p class="text-muted">Choose an employee from the left panel to view and edit their salary components.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../templates/footer.php';
?>
