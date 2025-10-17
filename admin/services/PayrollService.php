<?php
// /admin/services/PayrollService.php (Final Definitive Logic v2)

class PayrollService {

    /**
     * Calculates the full payroll breakdown for a single employee using the final specified logic.
     *
     * @param array $employeeData The structured array from the EmployeeRepository.
     * @param int   $daysInMonth  The total number of calendar days in the month.
     * @return array An array containing all calculated components for the payroll_details table.
     */
    public function calculatePayrollForEmployee(array $employeeData, int $daysInMonth): array {
        
        // --- Absence Calculation ---
        $absentDays = $daysInMonth - $employeeData['present_days'];
        if ($absentDays < 0) {
            $absentDays = 0; // Prevent negative absences
        }

        // --- Absence Deduction Calculation ---
        $absenceDeduction = 0;
        if ($employeeData['present_days'] == 0) {
            // SPECIAL CASE: If absent the whole month, deduct the entire basic salary.
            $absenceDeduction = $employeeData['basic_salary'];
            $dailyRate = $employeeData['basic_salary'] / 30; // Still record a rate for consistency
        } else {
            // STANDARD CASE: Calculate deduction based on a fixed 30-day rate.
            $dailyRate = $employeeData['basic_salary'] / 30;
            $absenceDeduction = $absentDays * $dailyRate;
        }
        
        // Other Deductions
        $advanceDeduction = $employeeData['advance_amount'] ?? 0;
        $loanInstallment = $employeeData['loan_installment'] ?? 0;

        // Sum up all deductions.
        $totalDeductions = $absenceDeduction + $advanceDeduction + $loanInstallment;
        
        // Calculate the final Net Salary.
        $netSalary = $employeeData['gross_salary'] - $totalDeductions;

        // Return the full, detailed breakdown for storage.
        return [
            'basic_salary' => $employeeData['basic_salary'],
            'gross_salary' => $employeeData['gross_salary'],
            'days_in_month' => $daysInMonth,
            'absent_days' => $absentDays,
            'daily_rate' => round($dailyRate, 2),
            'absence_deduction' => round($absenceDeduction, 2),
            'salary_advance_deduction' => $advanceDeduction,
            'loan_installment_deduction' => $loanInstallment,
            'other_deductions' => 0.00,
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => round($netSalary, 2)
        ];
    }
}