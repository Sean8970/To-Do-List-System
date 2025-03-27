<?php
require_once(__DIR__ . '/../../config/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Handle different actions
$action = isset($_POST['ajax_action']) ? $_POST['ajax_action'] : '';

switch ($action) {
    case 'quick_add_task':
        handleQuickAddTask();
        break;
    case 'mark_completed':
        handleMarkCompleted();
        break;
    case 'archive_task':
        handleArchiveTask();
        break;
    case 'delete_task':
        handleDeleteTask();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
}

function handleQuickAddTask() {
    global $conn;
    
    // Validate required fields
    $requiredFields = ['title', 'due_date'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
            exit();
        }
    }
    
    // Get form data
    $title = $_POST['title'];
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $dueDate = $_POST['due_date'];
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'Medium';
    $category = isset($_POST['category']) ? $_POST['category'] : 'Others';
    $status = 'Pending';
    $userId = $_SESSION['user_id'];
    
    // Insert task
    $sql = "INSERT INTO tasks (user_id, title, description, due_date, priority, category, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "issssss", $userId, $title, $description, $dueDate, $priority, $category, $status);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Task added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add task']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function handleMarkCompleted() {
    global $conn;
    
    if (!isset($_POST['task_id'])) {
        echo json_encode(['success' => false, 'message' => 'Task ID is required']);
        exit();
    }
    
    $taskId = $_POST['task_id'];
    $userId = $_SESSION['user_id'];
    
    $sql = "UPDATE tasks SET status = 'Completed' WHERE task_id = ? AND user_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $taskId, $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Task marked as completed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update task']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function handleArchiveTask() {
    global $conn;
    
    if (!isset($_POST['task_id'])) {
        echo json_encode(['success' => false, 'message' => 'Task ID is required']);
        exit();
    }
    
    $taskId = $_POST['task_id'];
    $userId = $_SESSION['user_id'];
    
    $sql = "UPDATE tasks SET is_archived = 1, archived_date = NOW() WHERE task_id = ? AND user_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $taskId, $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Task archived successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to archive task']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function handleDeleteTask() {
    global $conn;
    
    if (!isset($_POST['task_id'])) {
        echo json_encode(['success' => false, 'message' => 'Task ID is required']);
        exit();
    }
    
    $taskId = $_POST['task_id'];
    $userId = $_SESSION['user_id'];
    
    $sql = "DELETE FROM tasks WHERE task_id = ? AND user_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $taskId, $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete task']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} 