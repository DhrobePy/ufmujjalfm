<?php
// new_ufmhrm/admin/repositories/EmployeeRepository.php

class EmployeeRepository {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Fetches all active employees and their related financial data for a given pay period.
     * This method is optimized to prevent N+1 query issues by fetching data in bulk.
     *
     * @param string $payPeriodStart The start date of the pay period (Y-m-d).
     * @param string $payPeriodEnd The end date of the pay period (Y-m-d).
     * @param string $month The numeric month (e.g., '09').
     * @param string $year The numeric year (e.g., '2025').
     * @return array A structured array of employee data indexed by employee ID.
     */
    public function getActiveEmployeesWithDetails(string $payPeriodStart, string $payPeriodEnd, string $month, string $year): array {
        
        // --- Query 1: Get all active employees and their salary structures ---
        // Uses COALESCE to fall back to base_salary if no specific structure is found.
        $employeesQuery = "
            SELECT 
                e.id, 
                e.base_salary, 
                COALESCE(ss.gross_salary, e.base_salary) as gross_salary,
                COALESCE(ss.basic_salary, e.base_salary) as basic_salary
            FROM employees e
            LEFT JOIN salary_structures ss ON e.id = ss.employee_id
            WHERE e.status = 'active'
        ";
        $employees = $this->db->query($employeesQuery)->results();

        // --- Query 2: Get all PRESENT days for all employees within the period ---
        // This is more reliable than counting absences, as it correctly handles employees with no records.
        $presentsQuery = "
            SELECT employee_id, COUNT(*) as count 
            FROM attendance 
            WHERE status = 'present' AND clock_in BETWEEN ? AND ? 
            GROUP BY employee_id
        ";
        $presentsData = $this->db->query($presentsQuery, [$payPeriodStart, $payPeriodEnd])->results();
        $presentDays = [];
        foreach ($presentsData as $row) {
            $presentDays[$row->employee_id] = $row->count;
        }

        // --- Query 3: Get all approved salary advances for the period ---
        $advancesQuery = "
            SELECT employee_id, SUM(amount) as total 
            FROM salary_advances 
            WHERE advance_month = ? AND advance_year = ? AND status = 'approved'
            GROUP BY employee_id
        ";
        $advancesData = $this->db->query($advancesQuery, [$month, $year])->results();
        $advances = [];
        foreach ($advancesData as $row) {
            $advances[$row->employee_id] = $row->total;
        }

        // --- Query 4: Get all active loans ---
        $loansQuery = "
            SELECT employee_id, monthly_payment 
            FROM loans 
            WHERE status = 'active'
        ";
        $loansData = $this->db->query($loansQuery)->results();
        $loans = [];
        foreach ($loansData as $row) {
            $loans[$row->employee_id] = $row->monthly_payment;
        }

        // --- Combine all data into a single, structured array ---
        // This makes it easy for the PayrollService to consume.
        $employeeDetails = [];
        foreach ($employees as $employee) {
            $employeeDetails[$employee->id] = [
                'id' => $employee->id,
                'gross_salary' => (float)$employee->gross_salary,
                'basic_salary' => (float)$employee->basic_salary,
                'present_days' => $presentDays[$employee->id] ?? 0, // Default to 0 if no present records are found
                'advance_amount' => $advances[$employee->id] ?? 0,
                'loan_installment' => $loans[$employee->id] ?? 0,
            ];
        }

        return $employeeDetails;
    }
}
