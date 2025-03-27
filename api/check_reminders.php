<?php
require_once('../config/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Set timezone to Kuala Lumpur
date_default_timezone_set('Asia/Kuala_Lumpur');

// Return JSON response
header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'reminders' => []];

$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');
$current_time = date('H:i');
$current_datetime = date('Y-m-d H:i:s');

// Debug info
$response['debug'] = [
    'current_date' => $current_date,
    'current_time' => $current_time,
    'current_datetime' => $current_datetime,
    'timezone' => date_default_timezone_get(),
    'user_id' => $user_id
];

// Get all active reminders for debugging from reminders_v2
$all_reminders_sql = "SELECT r.reminder_id, r.task_id, r.reminder_date, r.reminder_time, r.remain_time,
                             r.notification_count, r.notification_max, r.notification_interval, r.last_notified, r.is_triggered, 
                             t.title, t.description, t.due_date
                      FROM reminders_v2 r
                      JOIN tasks t ON r.task_id = t.task_id
                      WHERE t.user_id = ? 
                      AND t.status != 'Completed'
                      ORDER BY r.reminder_date ASC, r.reminder_time ASC
                      LIMIT 10";

if ($debug_stmt = mysqli_prepare($conn, $all_reminders_sql)) {
    mysqli_stmt_bind_param($debug_stmt, "i", $user_id);
    if (mysqli_stmt_execute($debug_stmt)) {
        $debug_result = mysqli_stmt_get_result($debug_stmt);
        $all_reminders = [];
        while ($task = mysqli_fetch_assoc($debug_result)) {
            $all_reminders[] = $task;
        }
        $response['debug']['all_active_reminders_v2'] = $all_reminders;
    }
    mysqli_stmt_close($debug_stmt);
}

// Get tasks with reminders for today where the time is within the current time frame
// Using remain_time and notification system to find reminders that need notifications
$sql = "SELECT r.reminder_id, r.task_id, r.reminder_date, r.reminder_time, r.remain_time, 
               r.notification_count, r.notification_max, r.notification_interval, r.last_notified, r.is_triggered, 
               t.title, t.description, t.due_date
        FROM reminders_v2 r
        JOIN tasks t ON r.task_id = t.task_id
        WHERE t.user_id = ? 
        AND r.reminder_date = ?
        AND (r.remain_time <= 5 OR TIME_FORMAT(r.reminder_time, '%H:%i') <= ?)
        AND r.notification_count < r.notification_max
        AND (r.last_notified IS NULL OR TIMESTAMPDIFF(MINUTE, r.last_notified, NOW()) >= r.notification_interval)
        AND t.status != 'Completed'
        ORDER BY r.remain_time ASC, r.reminder_date ASC, r.reminder_time ASC
        LIMIT 5";  

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $current_date, $current_time);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($task = mysqli_fetch_assoc($result)) {
            // Format the date and time for display - convert to H:i without seconds
            $task['reminder_time_formatted'] = date('H:i', strtotime($task['reminder_time']));
            $task['reminder_datetime'] = $task['reminder_date'] . ' ' . $task['reminder_time_formatted'];
            $response['reminders'][] = $task;
        }
        
        $response['success'] = true;
        $response['message'] = "Found " . count($response['reminders']) . " active reminders";
        
        // Update notification count and last_notified time for reminders
        // Mark as triggered only when notification_count reaches notification_max
        if (count($response['reminders']) > 0) {
            $response['debug']['notification_updates'] = [];
            
            foreach ($response['reminders'] as $reminder) {
                // Increment notification count and update last_notified time
                $new_count = $reminder['notification_count'] + 1;
                $is_triggered = ($new_count >= $reminder['notification_max']) ? 1 : 0;
                
                $update_sql = "UPDATE reminders_v2 
                               SET notification_count = ?, 
                                   last_notified = NOW(), 
                                   is_triggered = ? 
                               WHERE reminder_id = ?";
                               
                if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "iii", $new_count, $is_triggered, $reminder['reminder_id']);
                    $update_result = mysqli_stmt_execute($update_stmt);
                    $response['debug']['notification_updates'][] = [
                        'reminder_id' => $reminder['reminder_id'],
                        'new_count' => $new_count,
                        'is_triggered' => $is_triggered,
                        'update_success' => $update_result
                    ];
                    mysqli_stmt_close($update_stmt);
                }
            }
        }
    } else {
        $response['message'] = "Database error: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
} else {
    $response['message'] = "Prepare statement failed: " . mysqli_error($conn);
}

echo json_encode($response);
exit();