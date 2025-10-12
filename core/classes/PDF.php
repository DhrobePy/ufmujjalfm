<?php
class PDF {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function generate_employee_report($format = 'html') {
        $employee_handler = new Employee($this->pdo);
        $employees = $employee_handler->get_all();
        
        if ($format === 'pdf') {
            return $this->generate_html_to_pdf($this->build_employee_html($employees), 'Employee_Report');
        }
        
        return $this->build_employee_html($employees);
    }

    public function generate_attendance_report($format = 'html') {
        $attendance_handler = new Attendance($this->pdo);
        $attendance = $attendance_handler->get_today_attendance();
        
        if ($format === 'pdf') {
            return $this->generate_html_to_pdf($this->build_attendance_html($attendance), 'Attendance_Report');
        }
        
        return $this->build_attendance_html($attendance);
    }

    public function generate_payroll_report($format = 'html') {
        $payroll_handler = new Payroll($this->pdo);
        $payrolls = $payroll_handler->get_payroll_history();
        
        if ($format === 'pdf') {
            return $this->generate_html_to_pdf($this->build_payroll_html($payrolls), 'Payroll_Report');
        }
        
        return $this->build_payroll_html($payrolls);
    }

    private function build_employee_html($employees) {
        $html = '<h2>Employee Report</h2>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse;">';
        $html .= '<thead><tr style="background-color: #f8f9fa;">';
        $html .= '<th>Name</th><th>Email</th><th>Position</th><th>Hire Date</th><th>Salary</th><th>Status</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($employees as $employee) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($employee['email']) . '</td>';
            $html .= '<td>' . htmlspecialchars($employee['position_name'] ?? 'N/A') . '</td>';
            $html .= '<td>' . date('M d, Y', strtotime($employee['hire_date'])) . '</td>';
            $html .= '<td>$' . number_format($employee['base_salary'], 2) . '</td>';
            $html .= '<td>' . ucfirst($employee['status']) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }

    private function build_attendance_html($attendance) {
        $html = '<h2>Attendance Report - ' . date('M d, Y') . '</h2>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse;">';
        $html .= '<thead><tr style="background-color: #f8f9fa;">';
        $html .= '<th>Employee</th><th>Clock In</th><th>Clock Out</th><th>Status</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($attendance as $record) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) . '</td>';
            $html .= '<td>' . ($record['clock_in'] ? date('H:i:s', strtotime($record['clock_in'])) : 'N/A') . '</td>';
            $html .= '<td>' . ($record['clock_out'] ? date('H:i:s', strtotime($record['clock_out'])) : 'N/A') . '</td>';
            $html .= '<td>' . ucfirst($record['status']) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }

    private function build_payroll_html($payrolls) {
        $html = '<h2>Payroll Report</h2>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse;">';
        $html .= '<thead><tr style="background-color: #f8f9fa;">';
        $html .= '<th>Employee</th><th>Pay Period</th><th>Gross Salary</th><th>Deductions</th><th>Net Salary</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($payrolls as $payroll) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']) . '</td>';
            $html .= '<td>' . date('M d', strtotime($payroll['pay_period_start'])) . ' - ' . date('M d, Y', strtotime($payroll['pay_period_end'])) . '</td>';
            $html .= '<td>$' . number_format($payroll['gross_salary'], 2) . '</td>';
            $html .= '<td>$' . number_format($payroll['deductions'], 2) . '</td>';
            $html .= '<td>$' . number_format($payroll['net_salary'], 2) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }

    private function generate_html_to_pdf($html, $filename) {
        // Simple HTML to PDF conversion using browser print functionality
        $full_html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . $filename . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
                th { background-color: #f8f9fa; font-weight: bold; }
                h2 { color: #333; margin-bottom: 20px; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="margin-bottom: 20px;">
                <button onclick="window.print()">Print PDF</button>
                <button onclick="window.close()">Close</button>
            </div>
            ' . $html . '
            <script>
                // Auto-print when page loads
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                };
            </script>
        </body>
        </html>';
        
        return $full_html;
    }

    public function generate_salary_certificate($employee_id) {
        $employee_handler = new Employee($this->pdo);
        $employee = $employee_handler->get_by_id($employee_id);
        
        if (!$employee) {
            return false;
        }
        
        $html = '<div style="text-align: center; margin-bottom: 30px;">';
        $html .= '<h1>SALARY CERTIFICATE</h1>';
        $html .= '</div>';
        
        $html .= '<p>Date: ' . date('F d, Y') . '</p>';
        $html .= '<p><strong>TO WHOM IT MAY CONCERN</strong></p>';
        
        $html .= '<p>This is to certify that <strong>' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '</strong> ';
        $html .= 'is employed with our organization as <strong>' . htmlspecialchars($employee['position_name'] ?? 'Employee') . '</strong> ';
        $html .= 'since <strong>' . date('F d, Y', strtotime($employee['hire_date'])) . '</strong>.</p>';
        
        $html .= '<p>His/Her current monthly salary is <strong>$' . number_format($employee['base_salary'], 2) . '</strong>.</p>';
        
        $html .= '<p>This certificate is issued upon his/her request for official purposes.</p>';
        
        $html .= '<div style="margin-top: 50px;">';
        $html .= '<p>Sincerely,</p>';
        $html .= '<p><strong>HR Department</strong></p>';
        $html .= '<p>Company Name</p>';
        $html .= '</div>';
        
        return $this->generate_html_to_pdf($html, 'Salary_Certificate_' . $employee['first_name'] . '_' . $employee['last_name']);
    }
}
?>
