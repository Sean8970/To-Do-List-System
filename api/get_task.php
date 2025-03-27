<?php
require_once('../config/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Return JSON response
header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'task' => null];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['task_id'])) {
    $task_id = intval($_GET['task_id']);
    $user_id = $_SESSION['user_id'];
    
    $sql = "SELECT * FROM tasks WHERE task_id = ? AND user_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $task_id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($task = mysqli_fetch_assoc($result)) {
                $response['success'] = true;
                $response['task'] = $task;
            } else {
                $response['message'] = "Task not found";
            }
        } else {
            $response['message'] = "Database error: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $response['message'] = "Prepare statement failed: " . mysqli_error($conn);
    }
} else {
    $response['message'] = "Task ID not provided";
}

echo json_encode($response);
exit(); 