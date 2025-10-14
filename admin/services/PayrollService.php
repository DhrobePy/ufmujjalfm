<?php
// new_ufmhrm/admin/services/PayrollService.php

class PayrollService {

    /**
     * Calculates the full payroll breakdown for a single employee.
     * This method contains all the business logic for salary calculation.
     *
     * @param array $employeeData An array of an employee's financial data from the EmployeeRepository.
     * @param int $daysInMonth The total number of days in the current pay period month.
     * @return array An array containing all calculated components for the payroll_details table.
     */
    public function calculatePayrollForEmployee(array $employeeData, int $daysInMonth): array {
        
        // a) Absence Deduction Calculation (Reliable Method)
        // We calculate absences by subtracting the number of 'present' days from the total days in the month.
        $absentDays = $daysInMonth - $employeeData['present_days'];
        
        // Ensure absent days cannot be a negative number, which could happen with data entry errors.
        if ($absentDays < 0) {
            $absentDays = 0; 
        }

        // Calculate the monetary value of the deduction based on the basic salary.
        $dailyRate = $daysInMonth > 0 ? $employeeData['basic_salary'] / $daysInMonth : 0;
        $absenceDeduction = $absentDays * $dailyRate;

        // b) Salary Advance Deduction
        // This is a direct value fetched by the repository.
        $advanceDeduction = $employeeData['advance_amount'];

        // c) Loan Installment Deduction
        // This is the fixed monthly payment amount for any active loan.
        $loanInstallment = $employeeData['loan_installment'];

        // Sum up all calculated and fetched deductions.
        $totalDeductions = $absenceDeduction + $advanceDeduction + $loanInstallment;
        
        // Calculate the final Net Salary.
        $netSalary = $employeeData['gross_salary'] - $totalDeductions;

        // Return the full breakdown, with values rounded for currency precision.
        // This array is a complete financial snapshot for the period.
        return [
            'basic_salary' => $employeeData['basic_salary'],
            'gross_salary' => $employeeData['gross_salary'],
            'days_in_month' => $daysInMonth,
            'absent_days' => $absentDays, // The newly calculated, reliable absent day count
            'daily_rate' => round($dailyRate, 2),
            'absence_deduction' => round($absenceDeduction, 2),
            'salary_advance_deduction' => $advanceDeduction,
            'loan_installment_deduction' => $loanInstallment,
            'other_deductions' => 0.00, // This is a placeholder for future admin adjustments
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => round($netSalary, 2)
        ];
    }
}
