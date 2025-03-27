<?php
require_once(__DIR__ . '/../../config/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/auth.php");
    exit();
}

// Check if task ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../../task.php");
    exit();
}

$task_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Fetch the task if it belongs to the current user
$task = null;
$sql = "SELECT * FROM tasks WHERE task_id = ? AND user_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $task_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $task = $row;
    } else {
        // Task not found or doesn't belong to user
        header("Location: ../../task.php");
        exit();
    }
    mysqli_stmt_close($stmt);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = $_POST['due_date'] ?? '';
    $due_time = $_POST['due_time'] ?? '00:00';
    $priority = $_POST['priority'] ?? 'Medium';
    $category = trim($_POST['category'] ?? '');
    $status = $_POST['status'] ?? 'Pending';
    
    $errors = [];
    
    // Validate inputs
    if (empty($title)) {
        $errors[] = "Task title is required";
    }
    
    if (empty($due_date)) {
        $errors[] = "Due date is required";
    }
    
    // If no errors, update the task
    if (empty($errors)) {
        // Combine date and time
        $due_datetime = $due_date . ' ' . $due_time;
        
        $sql = "UPDATE tasks SET title = ?, description = ?, due_date = ?, priority = ?, category = ?, status = ? 
                WHERE task_id = ? AND user_id = ?";
                
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssiii", 
                $title, 
                $description, 
                $due_datetime, 
                $priority, 
                $category,
                $status,
                $task_id,
                $user_id
            );
            
            if (mysqli_stmt_execute($stmt)) {
                // Redirect to tasks page after successful update
                header("Location: ../../task.php");
                exit();
            } else {
                $errors[] = "Something went wrong. Please try again. Error: " . mysqli_error($conn);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = "Database error. Please try again.";
        }
    }
}

// Extract date and time from due_date for the form
$due_date_value = $task ? date('Y-m-d', strtotime($task['due_date'])) : '';
$due_time_value = $task ? date('H:i', strtotime($task['due_date'])) : '';

// Get categories for dropdown
$categories = [];
$sql = "SELECT DISTINCT category FROM tasks WHERE user_id = ? AND category != ''";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['category'])) {
            $categories[] = $row['category'];
        }
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task - To-Do List System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f8f9fa;
        }
        .soft-shadow {
            box-shadow: 6px 6px 12px #b8b9be, -6px -6px 12px #ffffff;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed left-[-2rem] top-0 z-40 h-screen w-64 bg-white soft-shadow transform lg:translate-x-0">
        <div class="h-full px-3 py-4 flex flex-col">
            <div class="flex items-center mb-8 px-2">
                <span class="text-2xl font-semibold text-gray-800">To-Do List System</span>
            </div>
            <nav class="flex-1 space-y-2">
                <a href="../../dashboard.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-home w-5 h-5"></i>
                    <span class="ml-3">Dashboard</span>
                </a>
                <a href="../../task.php" class="flex items-center px-4 py-3 text-gray-700 bg-gray-100 rounded-lg">
                    <i class="fas fa-tasks w-5 h-5"></i>
                    <span class="ml-3">My Tasks</span>
                </a>
                <a href="../../calendar.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-calendar w-5 h-5"></i>
                    <span class="ml-3">Calendar</span>
                </a>
                <a href="../../archive.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-archive w-5 h-5"></i>
                    <span class="ml-3">Archived Tasks</span>
                </a>
            </nav>
            <div class="mt-auto">
                <button onclick="logout()" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-sign-out-alt w-5 h-5"></i>
                    <span class="ml-3">Logout</span>
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="lg:ml-64">
        <!-- Top Navigation -->
        <nav class="bg-white soft-shadow sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Mobile menu button -->
                    <div class="flex items-center lg:hidden">
                        <button id="menuButton" class="text-gray-600 hover:text-gray-900">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="flex items-center">
                        <h1 class="text-xl font-semibold text-gray-800">Edit Task</h1>
                    </div>

                    <div class="flex items-center">
                        <a href="../../task.php" class="flex items-center gap-2 text-blue-500 hover:text-blue-600">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Tasks</span>
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Form Content -->
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white rounded-xl soft-shadow p-6">
                <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700">
                    <h3 class="font-medium">Please fix the following errors:</h3>
                    <ul class="mt-2 ml-4 list-disc">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $task_id); ?>">
                    <div class="space-y-6">
                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Task Title</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($task['title'] ?? ''); ?>" required 
                                class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="description" name="description" rows="4" 
                                class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
                        </div>

                        <!-- Due Date and Time -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="due_date" class="block text-sm font-medium text-gray-700">Due Date</label>
                                <input type="date" id="due_date" name="due_date" value="<?php echo $due_date_value; ?>" required 
                                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="due_time" class="block text-sm font-medium text-gray-700">Due Time</label>
                                <input type="time" id="due_time" name="due_time" value="<?php echo $due_time_value; ?>" 
                                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <!-- Priority and Category -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="priority" class="block text-sm font-medium text-gray-700">Priority</label>
                                <select id="priority" name="priority" 
                                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Low" <?php echo (isset($task['priority']) && $task['priority'] === 'Low') ? 'selected' : ''; ?>>Low</option>
                                    <option value="Medium" <?php echo (isset($task['priority']) && $task['priority'] === 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                    <option value="High" <?php echo (isset($task['priority']) && $task['priority'] === 'High') ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                                <input type="text" id="category" name="category" list="category-list" value="<?php echo htmlspecialchars($task['category'] ?? ''); ?>" 
                                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <datalist id="category-list">
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>

                        <!-- Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status" name="status" 
                                class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="Pending" <?php echo (isset($task['status']) && $task['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo (isset($task['status']) && $task['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo (isset($task['status']) && $task['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end gap-4">
                            <a href="../../task.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('menuButton').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('left-[-2rem]');
            sidebar.classList.toggle('left-0');
        });

        // Logout function
        function logout() {
            // Destroy session serverside
            fetch('../../task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax_action=logout'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear any client-side cookies
                    document.cookie = "PHPSESSID=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                    window.location.href = "../../auth/auth.php";
                } else {
                    console.error('Logout failed:', data.message);
                    alert('Logout failed. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error during logout:', error);
                // Fallback if the fetch fails
                document.cookie = "PHPSESSID=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                window.location.href = "../../auth/auth.php";
            });
        }
    </script>
</body>
</html> 