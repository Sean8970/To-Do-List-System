<?php
require_once('../config/database.php');

// Set timezone to Kuala Lumpur
date_default_timezone_set('Asia/Kuala_Lumpur');

// Return JSON response
header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'updated_count' => 0];

// Get current date and time
$current_date = date('Y-m-d');
$current_time = date('H:i');

// Debug info
$response['debug'] = [
    'current_date' => $current_date,
    'current_time' => $current_time,
    'timezone' => date_default_timezone_get()
];

// Update remain_time for all reminders scheduled for today
$update_sql = "UPDATE reminders_v2 
              SET remain_time = TIME_TO_SEC(TIMEDIFF(reminder_time, ?))/60 
              WHERE reminder_date = ? 
              AND is_triggered = FALSE";

if ($stmt = mysqli_prepare($conn, $update_sql)) {
    mysqli_stmt_bind_param($stmt, "ss", $current_time, $current_date);
    
    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        $response['updated_count'] = $affected_rows;
        $response['success'] = true;
        $response['message'] = "Updated remain_time for $affected_rows reminders";
    } else {
        $response['message'] = "Database error: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
} else {
    $response['message'] = "Prepare statement failed: " . mysqli_error($conn);
}

// Get some sample updated reminders for debugging
$sample_sql = "SELECT reminder_id, task_id, reminder_date, reminder_time, remain_time, is_triggered 
              FROM reminders_v2 
              WHERE reminder_date = ? 
              ORDER BY remain_time ASC 
              LIMIT 5";

if ($sample_stmt = mysqli_prepare($conn, $sample_sql)) {
    mysqli_stmt_bind_param($sample_stmt, "s", $current_date);
    
    if (mysqli_stmt_execute($sample_stmt)) {
        $sample_result = mysqli_stmt_get_result($sample_stmt);
        $samples = [];
        
        while ($row = mysqli_fetch_assoc($sample_result)) {
            $samples[] = $row;
        }
        
        $response['debug']['sample_reminders'] = $samples;
    }
    
    mysqli_stmt_close($sample_stmt);
}

echo json_encode($response);
exit();