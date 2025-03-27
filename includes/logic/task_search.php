<?php
require_once(__DIR__ . '/../../config/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get search parameters
$searchQuery = isset($_GET['query']) ? trim($_GET['query']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$isArchived = isset($_GET['archived']) ? (bool)$_GET['archived'] : false;

// Build the SQL query
$sql = "SELECT * FROM tasks WHERE user_id = ? AND is_archived = ?";
$params = [$_SESSION['user_id'], $isArchived ? 1 : 0];
$types = "ii";

// Add search conditions
if (!empty($searchQuery)) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $types .= "ss";
}

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($priority)) {
    $sql .= " AND priority = ?";
    $params[] = $priority;
    $types .= "s";
}

if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($startDate)) {
    $sql .= " AND DATE(due_date) >= ?";
    $params[] = $startDate;
    $types .= "s";
}

if (!empty($endDate)) {
    $sql .= " AND DATE(due_date) <= ?";
    $params[] = $endDate;
    $types .= "s";
}

$sql .= " ORDER BY due_date ASC";

// Execute the query
$tasks = [];
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $tasks[] = [
            'task_id' => $row['task_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'due_date' => $row['due_date'],
            'priority' => $row['priority'],
            'category' => $row['category'],
            'status' => $row['status']
        ];
    }
    mysqli_stmt_close($stmt);
}

// Return results as JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'tasks' => $tasks]); 