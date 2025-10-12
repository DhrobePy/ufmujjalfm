<?php
// new_ufmhrm/admin/employees.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/init.php';

if (!is_admin_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Employee Management - ' . APP_NAME;
include_once '../templates/header.php';

// --- Get Filter Parameters ---
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$departmentFilter = isset($_GET['department']) ? trim($_GET['department']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$addressFilter = isset($_GET['address']) ? trim($_GET['address']) : '';

// --- Fetch Departments for Filter Dropdown ---
$departmentsResult = $db->query("SELECT id, name FROM departments ORDER BY name");
$departments = $departmentsResult ? $departmentsResult->results() : [];

// --- Fetch Unique Addresses for Filter Dropdown ---
$addressesResult = $db->query("SELECT DISTINCT address FROM employees WHERE address IS NOT NULL AND address != '' ORDER BY address");
$addresses = $addressesResult ? $addressesResult->results() : [];

// --- Build Dynamic SQL Query with Filters ---
$sql = "
    SELECT 
        e.*, 
        p.name as position_name, 
        d.name as department_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    WHERE 1=1
";

$params = [];

// Search filter (name, email, phone)
if (!empty($searchQuery)) {
    $sql .= " AND (
        e.first_name LIKE ? OR 
        e.last_name LIKE ? OR 
        e.email LIKE ? OR 
        e.phone LIKE ? OR
        CONCAT(e.first_name, ' ', e.last_name) LIKE ?
    )";
    $searchParam = '%' . $searchQuery . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Department filter
if (!empty($departmentFilter)) {
    $sql .= " AND d.id = ?";
    $params[] = $departmentFilter;
}

// Status filter
if (!empty($statusFilter)) {
    $sql .= " AND e.status = ?";
    $params[] = $statusFilter;
}

// Address filter
if (!empty($addressFilter)) {
    $sql .= " AND e.address = ?";
    $params[] = $addressFilter;
}

$sql .= " ORDER BY e.first_name, e.last_name";

$employeesResult = $db->query($sql, $params);
$employees = $employeesResult ? $employeesResult->results() : [];
$totalEmployees = count($employees);

?>

<div class="space-y-6">
    <!-- Header Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    <i class="fas fa-users text-primary-600 mr-3"></i>
                    Employee Management
                </h1>
                <p class="mt-1 text-sm text-gray-600">
                    View, search, and manage all employee records. <span class="font-semibold text-primary-600"><?php echo $totalEmployees; ?></span> employee(s) found.
                </p>
            </div>
            <div class="mt-4 sm:mt-0">
                <a href="#" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                    <i class="fas fa-plus mr-2"></i>
                    Add New Employee
                </a>
            </div>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Search Input -->
                <div class="lg:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-search text-gray-400 mr-2"></i>Search Employees
                    </label>
                    <input 
                        type="text" 
                        name="search" 
                        id="search" 
                        value="<?php echo htmlspecialchars($searchQuery); ?>"
                        placeholder="Search by name, email, or phone..." 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                    >
                </div>

                <!-- Department Filter -->
                <div>
                    <label for="department" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-building text-gray-400 mr-2"></i>Department
                    </label>
                    <select 
                        name="department" 
                        id="department" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                    >
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept->id; ?>" <?php echo ($departmentFilter == $dept->id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-toggle-on text-gray-400 mr-2"></i>Status
                    </label>
                    <select 
                        name="status" 
                        id="status" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                    >
                        <option value="">All Status</option>
                        <option value="active" <?php echo ($statusFilter === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="on_leave" <?php echo ($statusFilter === 'on_leave') ? 'selected' : ''; ?>>On Leave</option>
                        <option value="suspended" <?php echo ($statusFilter === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                        <option value="terminated" <?php echo ($statusFilter === 'terminated') ? 'selected' : ''; ?>>Terminated</option>
                    </select>
                </div>
            </div>

            <!-- Address Filter (Full Width) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="lg:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt text-gray-400 mr-2"></i>Address
                    </label>
                    <select 
                        name="address" 
                        id="address" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                    >
                        <option value="">All Addresses</option>
                        <?php foreach ($addresses as $addr): ?>
                            <option value="<?php echo htmlspecialchars($addr->address); ?>" <?php echo ($addressFilter === $addr->address) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($addr->address); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="lg:col-span-2 flex items-end space-x-3">
                    <button 
                        type="submit" 
                        class="flex-1 px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 font-medium"
                    >
                        <i class="fas fa-filter mr-2"></i>Apply Filters
                    </button>
                    <a 
                        href="employees.php" 
                        class="flex-1 px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 font-medium text-center"
                    >
                        <i class="fas fa-redo mr-2"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Active Filters Display -->
    <?php if (!empty($searchQuery) || !empty($departmentFilter) || !empty($statusFilter) || !empty($addressFilter)): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2 flex-wrap">
                <span class="text-sm font-medium text-blue-900">Active Filters:</span>
                
                <?php if (!empty($searchQuery)): ?>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-200 text-blue-800">
                    Search: "<?php echo htmlspecialchars($searchQuery); ?>"
                    <a href="?<?php echo http_build_query(array_filter(['department' => $departmentFilter, 'status' => $statusFilter, 'address' => $addressFilter])); ?>" class="ml-2 hover:text-blue-900">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>

                <?php if (!empty($departmentFilter)): ?>
                <?php 
                    $deptName = '';
                    foreach ($departments as $dept) {
                        if ($dept->id == $departmentFilter) {
                            $deptName = $dept->name;
                            break;
                        }
                    }
                ?>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-200 text-blue-800">
                    Department: <?php echo htmlspecialchars($deptName); ?>
                    <a href="?<?php echo http_build_query(array_filter(['search' => $searchQuery, 'status' => $statusFilter, 'address' => $addressFilter])); ?>" class="ml-2 hover:text-blue-900">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>

                <?php if (!empty($statusFilter)): ?>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-200 text-blue-800">
                    Status: <?php echo ucfirst(str_replace('_', ' ', $statusFilter)); ?>
                    <a href="?<?php echo http_build_query(array_filter(['search' => $searchQuery, 'department' => $departmentFilter, 'address' => $addressFilter])); ?>" class="ml-2 hover:text-blue-900">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>

                <?php if (!empty($addressFilter)): ?>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-200 text-blue-800">
                    Address: <?php echo htmlspecialchars($addressFilter); ?>
                    <a href="?<?php echo http_build_query(array_filter(['search' => $searchQuery, 'department' => $departmentFilter, 'status' => $statusFilter])); ?>" class="ml-2 hover:text-blue-900">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>
            </div>
            <a href="employees.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                Clear All
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Employee Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 border-b-2 border-primary-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hire Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($employees): ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 bg-gradient-to-br from-primary-400 to-primary-600 rounded-full flex items-center justify-center">
                                            <span class="text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($emp->first_name, 0, 1) . substr($emp->last_name, 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($emp->first_name . ' ' . $emp->last_name); ?></div>
                                            <div class="text-sm text-gray-500">
                                                <i class="fas fa-envelope text-gray-400 mr-1"></i>
                                                <?php echo htmlspecialchars($emp->email); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($emp->department_name ?? 'N/A'); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-600"><?php echo htmlspecialchars($emp->position_name ?? 'N/A'); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-600">
                                        <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>
                                        <?php echo htmlspecialchars($emp->address ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-600"><?php echo date("M d, Y", strtotime($emp->hire_date)); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                            if ($emp->status === 'active') echo 'bg-green-100 text-green-800';
                                            elseif ($emp->status === 'terminated') echo 'bg-red-100 text-red-800';
                                            elseif ($emp->status === 'on_leave') echo 'bg-yellow-100 text-yellow-800';
                                            else echo 'bg-gray-100 text-gray-800';
                                        ?>
                                    ">
                                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($emp->status))); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="#" class="text-blue-600 hover:text-blue-900 transition-colors" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="text-green-600 hover:text-green-900 ml-4 transition-colors" title="Edit Employee">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-users text-gray-300 text-6xl mb-4"></i>
                                    <p class="text-gray-500 text-lg font-medium">No employees found</p>
                                    <p class="text-gray-400 text-sm mt-2">Try adjusting your search or filter criteria</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Auto-submit form on filter change (optional - for better UX)
    document.addEventListener('DOMContentLoaded', function() {
        const filterSelects = document.querySelectorAll('#department, #status, #address');
        
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                // Optional: Auto-submit on change
                // this.form.submit();
            });
        });
    });
</script>

<?php include_once '../templates/footer.php'; ?>