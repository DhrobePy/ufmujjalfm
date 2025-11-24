<?php
// Database configuration
$host = 'localhost';
$dbname = 'ujjalfmc_hr';
$username = 'ujjalfmc_hr';
$password = 'ujjalfmhr1234';



header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
    exit;
}

$search = isset($_GET['q']) ? $_GET['q'] : '';
$branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 1;

// Build the query
$sql = "SELECT 
            e.id,
            CONCAT(e.first_name, ' ', e.last_name) as full_name,
            e.email,
            e.phone,
            p.title as position
        FROM employees e
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE e.status = 'active'
        AND e.branch_id = :branch_id";

$params = [':branch_id' => $branch_id];

// Add search filter if search term is provided
if (!empty($search)) {
    $sql .= " AND (
        e.first_name LIKE :search 
        OR e.last_name LIKE :search 
        OR e.email LIKE :search 
        OR e.phone LIKE :search
        OR CONCAT(e.first_name, ' ', e.last_name) LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY e.first_name, e.last_name LIMIT 50";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($employees as $emp) {
        $results[] = [
            'id' => $emp['id'],
            'text' => $emp['full_name'] . ' - ' . $emp['email'] . 
                     ($emp['position'] ? ' (' . $emp['position'] . ')' : '')
        ];
    }

    echo json_encode($results);

} catch(PDOException $e) {
    echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
}
?>