<?php
// /admin/repositories/EmployeeRepository.php (Final Definitive Logic)

class EmployeeRepository {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Fetches all active employees and their related financial data for a given pay period.
     */
    public function getActiveEmployeesWithDetails(string $payPeriodStart, string $payPeriodEnd, string $month, string $year): array {
        
        // --- Query 1: Get all active employees and their salary structures ---
        $employeesQuery = "
            SELECT 
                e.id, 
                COALESCE(ss.gross_salary, e.base_salary) as gross_salary,
                COALESCE(ss.basic_salary, e.base_salary) as basic_salary
            FROM employees e
            LEFT JOIN salary_structures ss ON e.id = ss.employee_id
            WHERE e.status = 'active'
        ";
        $employees = $this->db->query($employeesQuery)->results();

        // --- Query 2: Get all PRESENT days for all employees ---
        $presentsQuery = "
            SELECT employee_id, COUNT(*) as count 
            FROM attendance 
            WHERE status = 'present' AND DATE(clock_in) BETWEEN ? AND ? 
            GROUP BY employee_id
        ";
        $presentsData = $this->db->query($presentsQuery, [$payPeriodStart, $payPeriodEnd])->results();
        $presentDays = [];
        foreach ($presentsData as $row) {
            $presentDays[$row->employee_id] = $row->count;
        }

        // --- Query 3 & 4: Get advances and loans ---
        $advancesQuery = "SELECT employee_id, SUM(amount) as total FROM salary_advances WHERE advance_month = ? AND advance_year = ? AND status = 'approved' GROUP BY employee_id";
        $advancesData = $this->db->query($advancesQuery, [$month, $year])->results();
        $advances = [];
        foreach ($advancesData as $row) { $advances[$row->employee_id] = $row->total; }

        $loansQuery = "SELECT employee_id, monthly_payment FROM loans WHERE status = 'active' AND installment_type = 'fixed'";
        $loansData = $this->db->query($loansQuery)->results();
        $loans = [];
        foreach ($loansData as $row) { $loans[$row->employee_id] = $row->monthly_payment; }

        // --- Combine all data into a structured array ---
        $employeeDetails = [];
        foreach ($employees as $employee) {
            $employeeDetails[$employee->id] = [
                'id' => $employee->id,
                'gross_salary' => (float)$employee->gross_salary,
                'basic_salary' => (float)$employee->basic_salary,
                'present_days' => $presentDays[$employee->id] ?? 0,
                'advance_amount' => $advances[$row->employee_id ?? 0] ?? 0,
                'loan_installment' => $loans[$row->employee_id ?? 0] ?? 0,
            ];
        }

        return $employeeDetails;
    }
}