<?php
// Database configuration
$host = 'localhost';
$dbname = 'ujjalfmc_hr';
$username = 'ujjalfmc_hr';
$password = 'ujjalfmhr1234';



try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get current branch (you can modify this based on your session/login system)
$current_branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Ujjal FM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
        }
        .calendar-container {
            max-width: 100%;
            margin: 20px auto;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            font-weight: 500;
            position: relative;
        }
        .calendar-day:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .calendar-day.selected {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        .calendar-day.today {
            border-color: #198754;
            border-width: 3px;
        }
        .calendar-day.disabled {
            background-color: #f8f9fa;
            cursor: not-allowed;
            opacity: 0.5;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .month-nav {
            display: flex;
            gap: 10px;
        }
        .weekday-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 10px;
            font-weight: bold;
            text-align: center;
            color: #6c757d;
        }
        .status-btn {
            margin: 5px;
        }
        .employee-badge {
            display: inline-block;
            padding: 8px 15px;
            background: #e7f3ff;
            border-radius: 20px;
            margin: 5px;
        }
        .selected-dates {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .date-badge {
            display: inline-block;
            padding: 5px 12px;
            background: #0d6efd;
            color: white;
            border-radius: 15px;
            margin: 3px;
            font-size: 0.9em;
        }
        .select2-container {
            width: 100% !important;
        }
        .legend {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .legend-box {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-calendar-check"></i> Mark Employee Attendance</h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Employee Selection -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label for="employee_select" class="form-label"><strong>Select Employee:</strong></label>
                                <select id="employee_select" class="form-control" multiple>
                                    <option value="">-- Search and select employees --</option>
                                </select>
                                <small class="text-muted">Start typing to search by name, email, or phone</small>
                            </div>
                        </div>

                        <!-- Selected Employees Display -->
                        <div id="selected_employees_display" class="mb-4"></div>

                        <!-- Calendar Section -->
                        <div class="calendar-container">
                            <div class="card">
                                <div class="card-body">
                                    <div class="calendar-header">
                                        <h5 id="current_month_year" class="mb-0"></h5>
                                        <div class="month-nav">
                                            <button class="btn btn-outline-primary" id="prev_month">
                                                <i class="bi bi-chevron-left"></i> Previous
                                            </button>
                                            <button class="btn btn-outline-primary" id="today_btn">
                                                Today
                                            </button>
                                            <button class="btn btn-outline-primary" id="next_month">
                                                Next <i class="bi bi-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="weekday-header">
                                        <div>Sun</div>
                                        <div>Mon</div>
                                        <div>Tue</div>
                                        <div>Wed</div>
                                        <div>Thu</div>
                                        <div>Fri</div>
                                        <div>Sat</div>
                                    </div>

                                    <div class="calendar-grid" id="calendar_grid"></div>

                                    <div class="legend">
                                        <div class="legend-item">
                                            <div class="legend-box" style="background: #0d6efd; border-color: #0d6efd;"></div>
                                            <span>Selected</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-box" style="background: white; border-color: #198754;"></div>
                                            <span>Today</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Selected Dates Display -->
                        <div class="selected-dates">
                            <h6><strong>Selected Dates:</strong></h6>
                            <div id="selected_dates_display">
                                <span class="text-muted">No dates selected</span>
                            </div>
                        </div>

                        <!-- Status Selection -->
                        <div class="mt-4 text-center">
                            <h5>Mark Attendance Status:</h5>
                            <button class="btn btn-success btn-lg status-btn" id="mark_present">
                                <i class="bi bi-check-circle"></i> Mark Present
                            </button>
                            <button class="btn btn-danger btn-lg status-btn" id="mark_absent">
                                <i class="bi bi-x-circle"></i> Mark Absent
                            </button>
                            <button class="btn btn-secondary btn-lg status-btn" id="clear_selection">
                                <i class="bi bi-arrow-counterclockwise"></i> Clear Selection
                            </button>
                        </div>

                        <!-- Result Message -->
                        <div id="result_message" class="mt-3"></div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();
        let selectedDates = [];
        let selectedEmployees = [];

        $(document).ready(function() {
            // Initialize Select2 for employee search
            $('#employee_select').select2({
                ajax: {
                    url: 'search_employees.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term,
                            branch_id: <?php echo $current_branch_id; ?>
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0,
                placeholder: 'Search by name, email, or phone',
                allowClear: true
            });

            // Load all employees on focus
            $('#employee_select').on('select2:open', function() {
                if ($('#employee_select option').length <= 1) {
                    $.ajax({
                        url: 'search_employees.php',
                        data: { q: '', branch_id: <?php echo $current_branch_id; ?> },
                        success: function(data) {
                            $('#employee_select').empty();
                            $.each(data, function(i, item) {
                                $('#employee_select').append($('<option>', {
                                    value: item.id,
                                    text: item.text
                                }));
                            });
                        }
                    });
                }
            });

            // Handle employee selection
            $('#employee_select').on('change', function() {
                selectedEmployees = $(this).val() || [];
                updateSelectedEmployeesDisplay();
            });

            // Initialize calendar
            renderCalendar(currentMonth, currentYear);

            // Navigation buttons
            $('#prev_month').click(function() {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                renderCalendar(currentMonth, currentYear);
            });

            $('#next_month').click(function() {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                renderCalendar(currentMonth, currentYear);
            });

            $('#today_btn').click(function() {
                currentMonth = new Date().getMonth();
                currentYear = new Date().getFullYear();
                renderCalendar(currentMonth, currentYear);
            });

            // Mark Present
            $('#mark_present').click(function() {
                markAttendance('present');
            });

            // Mark Absent
            $('#mark_absent').click(function() {
                markAttendance('absent');
            });

            // Clear Selection
            $('#clear_selection').click(function() {
                selectedDates = [];
                selectedEmployees = [];
                $('#employee_select').val(null).trigger('change');
                updateSelectedDatesDisplay();
                updateSelectedEmployeesDisplay();
                renderCalendar(currentMonth, currentYear);
            });
        });

        function renderCalendar(month, year) {
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];

            $('#current_month_year').text(monthNames[month] + ' ' + year);

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const today = new Date();

            let calendarHTML = '';

            // Empty cells for days before first day of month
            for (let i = 0; i < firstDay; i++) {
                calendarHTML += '<div class="calendar-day disabled"></div>';
            }

            // Days of the month
            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const isToday = (day === today.getDate() && month === today.getMonth() && year === today.getFullYear());
                const isSelected = selectedDates.includes(dateStr);

                let classes = 'calendar-day';
                if (isToday) classes += ' today';
                if (isSelected) classes += ' selected';

                calendarHTML += `<div class="${classes}" data-date="${dateStr}">${day}</div>`;
            }

            $('#calendar_grid').html(calendarHTML);

            // Add click handlers
            $('.calendar-day:not(.disabled)').click(function() {
                const date = $(this).data('date');
                const index = selectedDates.indexOf(date);

                if (index > -1) {
                    selectedDates.splice(index, 1);
                    $(this).removeClass('selected');
                } else {
                    selectedDates.push(date);
                    $(this).addClass('selected');
                }

                updateSelectedDatesDisplay();
            });
        }

        function updateSelectedDatesDisplay() {
            if (selectedDates.length === 0) {
                $('#selected_dates_display').html('<span class="text-muted">No dates selected</span>');
            } else {
                selectedDates.sort();
                let html = '';
                selectedDates.forEach(date => {
                    html += `<span class="date-badge">${formatDate(date)}</span>`;
                });
                $('#selected_dates_display').html(html);
            }
        }

        function updateSelectedEmployeesDisplay() {
            if (selectedEmployees.length === 0) {
                $('#selected_employees_display').html('');
            } else {
                let html = '<div class="alert alert-info"><strong>Selected Employees:</strong><br>';
                $('#employee_select option:selected').each(function() {
                    html += `<span class="employee-badge">${$(this).text()}</span>`;
                });
                html += '</div>';
                $('#selected_employees_display').html(html);
            }
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        function markAttendance(status) {
            if (selectedEmployees.length === 0) {
                showMessage('Please select at least one employee', 'warning');
                return;
            }

            if (selectedDates.length === 0) {
                showMessage('Please select at least one date', 'warning');
                return;
            }

            // Show loading message
            showMessage('Processing attendance...', 'info');

            $.ajax({
                url: 'save_attendance.php',
                method: 'POST',
                data: {
                    employees: selectedEmployees,
                    dates: selectedDates,
                    status: status,
                    branch_id: <?php echo $current_branch_id; ?>
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(response.message, 'success');
                        // Clear selections after successful save
                        setTimeout(function() {
                            selectedDates = [];
                            updateSelectedDatesDisplay();
                            renderCalendar(currentMonth, currentYear);
                        }, 1500);
                    } else {
                        showMessage(response.message, 'danger');
                    }
                },
                error: function() {
                    showMessage('An error occurred while saving attendance', 'danger');
                }
            });
        }

        function showMessage(message, type) {
            $('#result_message').html(`
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);

            // Auto-dismiss success messages
            if (type === 'success') {
                setTimeout(function() {
                    $('#result_message .alert').alert('close');
                }, 3000);
            }
        }
    </script>
</body>
</html>