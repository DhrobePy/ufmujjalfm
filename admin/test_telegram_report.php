<?php
// new_ufmhrm/admin/test_telegram_report.php

// --- START: CONFIGURATION ---
// Paste your two secret keys here
$bot_token = '8406596906:AAHRlrgmiaXqfbdOUvzP_-2f6km95WSpO7I';
$chat_id   = '-5051698271';
$secret_key = '111'; // A simple key to run this test. Change this!
// --- END: CONFIGURATION ---

// 1. Security Check
// To run this, you must visit: .../test_telegram_report.php?key=hrm_test_key
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    exit('Access Denied. Please provide the correct secret key in the URL (e.g., ?key=...)');
}

// 2. Load Database
// We must set the directory to the file's location for 'init.php' to work
chdir(dirname(__FILE__));
require_once '../core/init.php';

// 3. --- GATHER DATA ---
$today = date('Y-m-d');
$today_formatted = date('l, F j, Y');

// --- Query 1: Present Count ---
$presentResult = $db->query("SELECT COUNT(*) as count FROM attendance WHERE DATE(clock_in) = ? AND status = 'present'", [$today]);
$presentCount = $presentResult ? $presentResult->first()->count : 0;

// --- Query 2: On Leave Count (Corrected Logic) ---
// This finds people who are *actually* on approved leave today.
$leaveResult = $db->query("SELECT COUNT(DISTINCT employee_id) as count FROM leave_requests WHERE ? BETWEEN start_date AND end_date AND status = 'approved'", [$today]);
$onLeaveCount = $leaveResult ? $leaveResult->first()->count : 0;

// --- Query 3: Absent List (Corrected Logic) ---
// This finds active employees who are NOT present and NOT on approved leave
$absentResult = $db->query("
    SELECT e.first_name, e.last_name
    FROM employees e
    LEFT JOIN attendance a ON e.id = a.employee_id AND DATE(a.clock_in) = ?
    LEFT JOIN leave_requests l ON e.id = l.employee_id AND ? BETWEEN l.start_date AND l.end_date AND l.status = 'approved'
    WHERE e.status = 'active'
    AND a.id IS NULL -- Not present
    AND l.id IS NULL -- Not on approved leave
    ORDER BY e.first_name
", [$today, $today]);
$absentList = $absentResult ? $absentResult->results() : [];
$absentCount = count($absentList); // Get the count from the list

// --- Query 4: New Loan Applications ---
$loansResult = $db->query("SELECT COUNT(*) as count FROM loan_applications WHERE DATE(applied_date) = ?", [$today]);
$newLoansCount = $loansResult ? $loansResult->first()->count : 0;

// --- 4. FORMAT THE TELEGRAM MESSAGE (Text Part) ---

// --- FIX: Message 1 (The Caption) ---
// This will be the short caption for the photo.
$caption = "☀️ *Daily HRM Report - $today_formatted*\n\n";
$caption .= "*ATTENDANCE SUMMARY*\n";
$caption .= "```\n"; // Start code block
$caption .= "Present:     $presentCount\n";
$caption .= "Absent:      $absentCount\n";
$caption .= "On Leave:    $onLeaveCount\n";
$caption .= "```\n\n"; // End code block
$caption .= "*FINANCE SUMMARY*\n";
$caption .= "```\n";
$caption .= "New Loan Requests: $newLoansCount\n";
$caption .= "```"; // No more newlines, keep it short!

// --- FIX: Message 2 (The Absent List) ---
// This will be a separate text message.
$absent_list_message = ""; // Initialize as empty
if ($absentCount > 0) {
    $absent_list_message = "📋 *Absent Employees List - $today_formatted*\n\n";
    $i = 1;
    foreach ($absentList as $emp) {
        $name = htmlspecialchars($emp->first_name . ' ' . $emp->last_name);
        $absent_list_message .= "$i. $name\n";
        $i++;
    }
}
// --- END FIX ---


// --- 5. BUILD THE CHART IMAGE URL (The Fun Part) ---
$chartConfig = [
    'type' => 'pie',
    'data' => [
        'labels' => ['Present', 'Absent', 'On Leave'],
        'datasets' => [[
            'data' => [(int)$presentCount, (int)$absentCount, (int)$onLeaveCount],
            'backgroundColor' => ['#22C55E', '#EF4444', '#F59E0B'], // Green, Red, Amber
        ]]
    ],
    'options' => [
        'title' => [
            'display' => true,
            'text' => "Today's Employee Status"
        ],
        'legend' => [
            'position' => 'bottom'
        ]
    ]
];

// Generate the chart URL using QuickChart.io
$chartUrl = 'https://quickchart.io/chart?width=500&height=300&c=' . urlencode(json_encode($chartConfig));


// --- 6. SEND TO TELEGRAM API ---
// --- FIX: This is now Message 1 (Photo + Caption) ---
$api_url_photo = "https://api.telegram.org/bot" . $bot_token . "/sendPhoto";
$payload_photo = [
    'chat_id' => $chat_id,
    'photo' => $chartUrl,
    'caption' => $caption, // Use the short $caption variable
    'parse_mode' => 'Markdown' // Use Markdown for formatting
];

$ch_photo = curl_init();
curl_setopt($ch_photo, CURLOPT_URL, $api_url_photo);
curl_setopt($ch_photo, CURLOPT_POST, 1);
curl_setopt($ch_photo, CURLOPT_POSTFIELDS, $payload_photo);
curl_setopt($ch_photo, CURLOPT_RETURNTRANSFER, true);
$response_json_photo = curl_exec($ch_photo);
$http_code_photo = curl_getinfo($ch_photo, CURLINFO_HTTP_CODE);
curl_close($ch_photo);


// --- FIX: This is Message 2 (The Absent List Text) ---
$response_json_text = null;
$http_code_text = null;
if (!empty($absent_list_message)) {
    // NOTE: Telegram's text message limit is 4096 characters.
    // Your list of 110 names is fine, but if it gets much larger,
    // we would need to split this into multiple messages.
    
    $api_url_text = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
    $payload_text = [
        'chat_id' => $chat_id,
        'text' => $absent_list_message, // Use the new $absent_list_message
        'parse_mode' => 'Markdown'
    ];

    $ch_text = curl_init();
    curl_setopt($ch_text, CURLOPT_URL, $api_url_text);
    curl_setopt($ch_text, CURLOPT_POST, 1);
    curl_setopt($ch_text, CURLOPT_POSTFIELDS, $payload_text);
    curl_setopt($ch_text, CURLOPT_RETURNTRANSFER, true);
    $response_json_text = curl_exec($ch_text);
    $http_code_text = curl_getinfo($ch_text, CURLINFO_HTTP_CODE);
    curl_close($ch_text);
}
// --- END FIX ---


// --- 7. SHOW FEEDBACK IN BROWSER ---
header('Content-Type: text/html');
echo "<!DOCTYPE html><html><head><title>Telegram Test</title><style>body { font-family: sans-serif; line-height: 1.6; } pre { background: #f4f4f4; padding: 15px; border-radius: 5px; }</style></head><body>";
echo "<h1>Telegram Report Test</h1>";

// --- FIX: Updated Feedback ---
$response_photo = json_decode($response_json_photo, true);
if ($http_code_photo == 200 && isset($response_photo['ok']) && $response_photo['ok']) {
    echo "<h2 style='color: green;'>✅ Success! (Part 1: Chart)</h2>";
    echo "The report chart and summary was sent to your Telegram group.";
} else {
    echo "<h2 style='color: red;'>❌ Error! (Part 1: Chart)</h2>";
    echo "Something went wrong. HTTP Code: " . $http_code_photo;
    echo "<h3>Telegram's Response:</h3>";
    echo "<pre>" . htmlspecialchars($response_json_photo) . "</pre>";
}

if (!empty($absent_list_message)) {
    $response_text = json_decode($response_json_text, true);
    if ($http_code_text == 200 && $response_text && isset($response_text['ok']) && $response_text['ok']) {
        echo "<h2 style='color: green;'>✅ Success! (Part 2: Absent List)</h2>";
        echo "The absent list was sent as a follow-up message.";
    } else {
        echo "<h2 style='color: red;'>❌ Error! (Part 2: Absent List)</h2>";
        echo "Something went wrong. HTTP Code: " . $http_code_text;
        echo "<h3>Telegram's Response:</h3>";
        echo "<pre>" . htmlspecialchars($response_json_text) . "</pre>";
    }
}
// --- END FIX ---

echo "<h3>Chart Image URL:</h3>";
echo "<p><a href='" . htmlspecialchars($chartUrl) . "' target='_blank'>Click to see chart image</a></p>";
echo "<img src='" . htmlspecialchars($chartUrl) . "' alt='Attendance Chart'>";
echo "<h3>Message Content (as sent):</h3>";
echo "<h4>Caption (Message 1):</h4>";
echo "<pre>" . htmlspecialchars($caption) . "</pre>";
echo "<h4>Absent List (Message 2):</h4>";
echo "<pre>" . htmlspecialchars($absent_list_message) . "</pre>";
echo "</body></html>";
exit();

?>