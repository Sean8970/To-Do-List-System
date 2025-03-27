<?php
require_once('config/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/auth.php");
    exit();
}

// Check for dark mode preference in URL parameter
$dark_mode = isset($_GET['dark_mode']) && $_GET['dark_mode'] === 'true' ? 'true' : 'false';

// Add completed_date column if it doesn't exist
$check_column = "SHOW COLUMNS FROM tasks LIKE 'completed_date'";
$column_exists = mysqli_query($conn, $check_column)->num_rows > 0;

if (!$column_exists) {
    $add_column = "ALTER TABLE tasks ADD COLUMN completed_date DATETIME DEFAULT NULL";
    mysqli_query($conn, $add_column);
    
    // Update existing completed tasks with current timestamp
    $update_completed = "UPDATE tasks SET completed_date = NOW() WHERE status = 'Completed' AND completed_date IS NULL";
    mysqli_query($conn, $update_completed);
}

// Add updated_at column if it doesn't exist
$check_updated_column = "SHOW COLUMNS FROM tasks LIKE 'updated_at'";
$updated_column_exists = mysqli_query($conn, $check_updated_column)->num_rows > 0;

if (!$updated_column_exists) {
    $add_updated_column = "ALTER TABLE tasks ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    mysqli_query($conn, $add_updated_column);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['ajax_action'] === 'logout') {
        // Destroy the session
        session_unset();
        session_destroy();
        $response['success'] = true;
        $response['message'] = "Logged out successfully";
        echo json_encode($response);
        exit();
    }
    
    switch ($_POST['ajax_action']) {
        case 'restore_task':
            $task_id = $_POST['task_id'];
            
            // Debug
            error_log("Restoring task ID: " . $task_id . " for user: " . $_SESSION['user_id']);
            
            $sql = "UPDATE tasks SET is_archived = 0, archived_date = NULL WHERE task_id = ? AND user_id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $task_id, $_SESSION['user_id']);
                if (mysqli_stmt_execute($stmt)) {
                    $response['success'] = true;
                    $response['message'] = 'Task restored successfully';
                    error_log("Restore successful, affected rows: " . mysqli_stmt_affected_rows($stmt));
                } else {
                    $response['message'] = "Database error: " . mysqli_error($conn);
                    error_log("Restore error: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt);
            } else {
                $response['message'] = "Prepare statement failed: " . mysqli_error($conn);
                error_log("Restore prepare error: " . mysqli_error($conn));
            }
            break;
            
        case 'bulk_delete':
            $task_ids = json_decode($_POST['task_ids']);
            $placeholders = str_repeat('?,', count($task_ids) - 1) . '?';
            $sql = "DELETE FROM tasks WHERE task_id IN ($placeholders) AND user_id = ?";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                $types = str_repeat('i', count($task_ids)) . 'i';
                $params = array_merge($task_ids, [$_SESSION['user_id']]);
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                
                if (mysqli_stmt_execute($stmt)) {
                    $response['success'] = true;
                    $response['message'] = 'Selected tasks deleted successfully';
                }
                mysqli_stmt_close($stmt);
            }
            break;
    }
    
    // Clear any output buffering before sending JSON
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Debug information
    error_log("AJAX Response: " . json_encode($response));
    
    // Output the response
    echo json_encode($response);
    exit();
}

// Fetch archived tasks with filters
$where_conditions = ['t.user_id = ?', 't.is_archived = 1'];
$params = [$_SESSION['user_id']];
$types = 'i';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = '(t.title LIKE ? OR t.description LIKE ?)';
    $search_term = '%' . $_GET['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $where_conditions[] = 't.category = ?';
    $params[] = $_GET['category'];
    $types .= 's';
}

if (isset($_GET['priority']) && !empty($_GET['priority'])) {
    $where_conditions[] = 't.priority = ?';
    $params[] = $_GET['priority'];
    $types .= 's';
}

if (isset($_GET['date_range']) && !empty($_GET['date_range'])) {
    switch ($_GET['date_range']) {
        case 'today':
            $where_conditions[] = 'DATE(IFNULL(t.completed_date, t.due_date)) = CURDATE()';
            break;
        case 'week':
            $where_conditions[] = 'YEARWEEK(IFNULL(t.completed_date, t.due_date)) = YEARWEEK(CURDATE())';
            break;
        case 'month':
            $where_conditions[] = 'MONTH(IFNULL(t.completed_date, t.due_date)) = MONTH(CURDATE()) AND YEAR(IFNULL(t.completed_date, t.due_date)) = YEAR(CURDATE())';
            break;
        case 'custom':
            if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
                $where_conditions[] = 'IFNULL(t.completed_date, t.due_date) BETWEEN ? AND ?';
                $params[] = $_GET['start_date'];
                $params[] = $_GET['end_date'];
                $types .= 'ss';
            }
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);
$sql = "SELECT t.*, COUNT(DISTINCT tr.reminder_id) as reminder_count 
        FROM tasks t 
        LEFT JOIN reminders tr ON t.task_id = tr.task_id 
        WHERE $where_clause 
        GROUP BY t.task_id 
        ORDER BY IFNULL(t.completed_date, t.due_date) DESC";

// Debug information
error_log("Archive SQL: " . $sql);
error_log("Archive Params: " . implode(", ", $params));

$tasks = [];
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $tasks[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Debug task count
error_log("Archive Tasks Count: " . count($tasks));

// Get categories for filter
$categories = [];
$cat_sql = "SELECT DISTINCT category FROM tasks WHERE user_id = ? AND category IS NOT NULL";
if ($stmt = mysqli_prepare($conn, $cat_sql)) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row['category'];
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo $dark_mode === 'true' ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive - To-Do List System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f8f9fa;
        }
        .dark body {
            background-color: #1a1a2e;
            color: #e2e8f0;
        }
        .soft-shadow {
            box-shadow: 6px 6px 12px #b8b9be, -6px -6px 12px #ffffff;
        }
        .dark .soft-shadow {
            box-shadow: 6px 6px 12px rgba(0, 0, 0, 0.3), -6px -6px 12px rgba(20, 20, 40, 0.3);
        }
        .table-shadow {
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .dark .table-shadow {
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .hover-shadow:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .dark .hover-shadow:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        
        /* Modern gradient styles */
        .gradient-border {
            position: relative;
            border-radius: 0.75rem;
            z-index: 0;
        }
        .gradient-border::before {
            content: "";
            position: absolute;
            z-index: -1;
            inset: 0;
            padding: 2px;
            border-radius: 0.75rem;
            background: linear-gradient(to bottom right, #3B82F6, #7C3AED);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }
        
        /* Dark mode toggle button */
        .dark-mode-toggle {
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .dark-mode-toggle:hover {
            background-color: rgba(156, 163, 175, 0.1);
        }
    </style>
</head>
<body class="min-h-screen dark:bg-gray-900 dark:text-gray-100">
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed left-0 top-0 z-40 h-screen w-64 bg-white dark:bg-gray-800 soft-shadow transform -translate-x-full lg:translate-x-0">
        <div class="h-full px-5 py-4 flex flex-col">
            <div class="flex items-center justify-center mb-8 px-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 flex items-center justify-center bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg shadow-md">
                        <i class="fas fa-check-circle text-white text-xl"></i>
                    </div>
                    <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">To-Do List</span>
                </div>
            </div>
            
            <nav class="flex-1 space-y-3 py-3">
                <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 dark:text-white rounded-xl transition-all duration-200 group hover:bg-gradient-to-r from-blue-50 to-indigo-50 dark:hover:bg-gradient-to-r dark:hover:from-blue-900/20 dark:hover:to-indigo-900/20 hover:shadow-md 
                    <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-gradient-to-r from-blue-50 to-indigo-50 dark:bg-gradient-to-r dark:from-blue-900/20 dark:to-indigo-900/20 shadow-md' : ''; ?>">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-500 dark:text-blue-400 group-hover:bg-blue-200 dark:group-hover:bg-blue-800/50 transition-colors duration-200
                        <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-blue-200 dark:bg-blue-800/50' : ''; ?>">
                        <i class="fas fa-home"></i>
                    </div>
                    <span class="ml-3 font-medium">Dashboard</span>
                </a>
                <a href="task.php" class="flex items-center px-4 py-3 text-gray-700 dark:text-white rounded-xl transition-all duration-200 group hover:bg-gradient-to-r from-green-50 to-teal-50 dark:hover:bg-gradient-to-r dark:hover:from-green-900/20 dark:hover:to-teal-900/20 hover:shadow-md
                    <?php echo basename($_SERVER['PHP_SELF']) == 'task.php' ? 'bg-gradient-to-r from-green-50 to-teal-50 dark:bg-gradient-to-r dark:from-green-900/20 dark:to-teal-900/20 shadow-md' : ''; ?>">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-500 dark:text-green-400 group-hover:bg-green-200 dark:group-hover:bg-green-800/50 transition-colors duration-200
                        <?php echo basename($_SERVER['PHP_SELF']) == 'task.php' ? 'bg-green-200 dark:bg-green-800/50' : ''; ?>">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <span class="ml-3 font-medium">My Tasks</span>
                </a>
                <a href="calendar.php" class="flex items-center px-4 py-3 text-gray-700 dark:text-white rounded-xl transition-all duration-200 group hover:bg-gradient-to-r from-amber-50 to-yellow-50 dark:hover:bg-gradient-to-r dark:hover:from-amber-900/20 dark:hover:to-yellow-900/20 hover:shadow-md
                    <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'bg-gradient-to-r from-amber-50 to-yellow-50 dark:bg-gradient-to-r dark:from-amber-900/20 dark:to-yellow-900/20 shadow-md' : ''; ?>">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-500 dark:text-amber-400 group-hover:bg-amber-200 dark:group-hover:bg-amber-800/50 transition-colors duration-200
                        <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'bg-amber-200 dark:bg-amber-800/50' : ''; ?>">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <span class="ml-3 font-medium">Calendar</span>
                </a>
                <a href="archive.php" class="flex items-center px-4 py-3 text-gray-700 dark:text-white rounded-xl transition-all duration-200 group hover:bg-gradient-to-r from-purple-50 to-fuchsia-50 dark:hover:bg-gradient-to-r dark:hover:from-purple-900/20 dark:hover:to-fuchsia-900/20 hover:shadow-md
                    <?php echo basename($_SERVER['PHP_SELF']) == 'archive.php' ? 'bg-gradient-to-r from-purple-50 to-fuchsia-50 dark:bg-gradient-to-r dark:from-purple-900/20 dark:to-fuchsia-900/20 shadow-md' : ''; ?>">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 text-purple-500 dark:text-purple-400 group-hover:bg-purple-200 dark:group-hover:bg-purple-800/50 transition-colors duration-200
                        <?php echo basename($_SERVER['PHP_SELF']) == 'archive.php' ? 'bg-purple-200 dark:bg-purple-800/50' : ''; ?>">
                        <i class="fas fa-archive"></i>
                    </div>
                    <span class="ml-3 font-medium">Archived Tasks</span>
                </a>
            </nav>
            
            <div class="mt-auto pt-4 border-t border-gray-100 dark:border-gray-700/50">
                <button id="darkModeToggle" class="flex items-center w-full px-4 py-3 text-gray-700 dark:text-white rounded-xl transition-all duration-200 group hover:bg-gradient-to-r from-indigo-50 to-purple-50 dark:hover:bg-gradient-to-r dark:hover:from-indigo-900/20 dark:hover:to-purple-900/20 hover:shadow-md mb-2">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 dark:hidden text-indigo-500">
                        <i class="fas fa-moon"></i>
                    </div>
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 hidden dark:flex text-yellow-500">
                        <i class="fas fa-sun"></i>
                    </div>
                    <span id="darkModeText" class="ml-3 font-medium dark:hidden">Dark Mode</span>
                    <span id="lightModeText" class="ml-3 font-medium hidden dark:block">Light Mode</span>
                </button>
                <button onclick="logout()" class="flex items-center w-full px-4 py-3 text-gray-700 dark:text-white rounded-xl transition-all duration-200 group hover:bg-gradient-to-r from-red-50 to-rose-50 dark:hover:bg-gradient-to-r dark:hover:from-red-900/20 dark:hover:to-rose-900/20 hover:shadow-md">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-500 dark:text-red-400 group-hover:bg-red-200 dark:group-hover:bg-red-800/50 transition-colors duration-200">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <span class="ml-3 font-medium">Logout</span>
                </button>
            </div>
            <div class="mt-6 px-4 py-4 bg-gradient-to-r from-blue-500/10 to-purple-500/10 dark:from-blue-500/5 dark:to-purple-500/5 rounded-xl">
                <div class="flex items-center mb-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-white">
                        <span class="text-sm font-semibold">
                            <?php echo isset($_SESSION['username']) ? substr(htmlspecialchars($_SESSION['username']), 0, 1) : 'U'; ?>
                        </span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-800 dark:text-white">
                            <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?>
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Active Now</p>
                    </div>
                </div>
                <a href="#" onclick="openProfileModal()" class="mt-2 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 flex items-center">
                    <i class="fas fa-cog mr-2"></i> Edit Profile
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="lg:ml-64">
        <!-- Top Navigation -->
        <nav class="bg-white dark:bg-gray-800 soft-shadow sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Mobile menu button -->
                    <div class="flex items-center lg:hidden">
                        <button id="menuButton" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                    </div>
                    
                    <!-- Search Bar -->
                    <div class="flex-1 max-w-xl mx-4">
                        <div class="relative">
                            <input type="text" id="searchInput" 
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Search archived tasks...">
                            <button class="absolute right-3 top-2.5 text-gray-400 dark:text-gray-300">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Archive Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 soft-shadow gradient-border">
                <!-- Header with Filters -->
                <div class="flex flex-wrap justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 md:mb-0">Archived Tasks</h1>
                    
                    <div class="flex flex-wrap gap-4">
                        <!-- Category Filter -->
                        <select id="categoryFilter" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Priority Filter -->
                        <select id="priorityFilter" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Priorities</option>
                            <option value="High">High Priority</option>
                            <option value="Medium">Medium Priority</option>
                            <option value="Low">Low Priority</option>
                        </select>

                        <!-- Date Range Filter -->
                        <select id="dateRangeFilter" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="custom">Custom Range</option>
                        </select>

                        <!-- Custom Date Range (initially hidden) -->
                        <div id="customDateRange" class="hidden flex gap-2">
                            <input type="date" id="startDate" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <input type="date" id="endDate" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="selectAll" class="rounded border-gray-300 dark:border-gray-600 text-blue-500 focus:ring-blue-500">
                        <label for="selectAll" class="text-sm text-gray-600 dark:text-gray-300">Select All</label>
                    </div>
                    
                    <button id="bulkDeleteBtn" class="px-4 py-2 bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed hidden shadow-md">
                        <i class="fas fa-trash-alt mr-2"></i> Delete Selected
                    </button>
                </div>

                <!-- Tasks Table -->
                <div class="overflow-x-auto">
                    <table class="w-full table-shadow">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700">
                                <th class="px-4 py-3 text-left"></th>
                                <th class="px-4 py-3 text-left dark:text-gray-200">Title</th>
                                <th class="px-4 py-3 text-left dark:text-gray-200">Category</th>
                                <th class="px-4 py-3 text-left dark:text-gray-200">Completed Date</th>
                                <th class="px-4 py-3 text-left dark:text-gray-200">Priority</th>
                                <th class="px-4 py-3 text-right dark:text-gray-200">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" class="task-checkbox rounded border-gray-300 dark:border-gray-600 text-blue-500 focus:ring-blue-500"
                                               data-task-id="<?php echo $task['task_id']; ?>">
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <?php if (!empty($task['description'])): ?>
                                            <div class="text-sm text-gray-500 dark:text-gray-400 line-clamp-1"><?php echo htmlspecialchars($task['description']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-sm rounded-full bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-gray-200">
                                            <?php echo htmlspecialchars($task['category']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        <?php 
                                            $display_date = !empty($task['completed_date']) ? 
                                                $task['completed_date'] : 
                                                $task['due_date'];
                                            echo date('M d, Y', strtotime($display_date)); 
                                        ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-sm rounded-full 
                                            <?php echo $task['priority'] === 'High' ? 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200' : 
                                            ($task['priority'] === 'Medium' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/60 dark:text-yellow-200' : 'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200'); ?>">
                                            <?php echo $task['priority']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button onclick="restoreTask(<?php echo $task['task_id']; ?>)" 
                                            class="text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300 mr-2">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if (empty($tasks)): ?>
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <i class="fas fa-archive text-4xl mb-2"></i>
                            <p>No archived tasks found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 dark:bg-black dark:bg-opacity-70 z-50 flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-md w-full mx-4 soft-shadow">
            <h3 id="modalTitle" class="text-xl font-semibold text-gray-800 dark:text-white mb-4"></h3>
            <p id="modalMessage" class="text-gray-600 dark:text-gray-300 mb-6"></p>
            <div class="flex justify-end gap-4">
                <button onclick="closeModal()" class="px-4 py-2 text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white">
                    Cancel
                </button>
                <button id="confirmButton" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-lg shadow-md transition duration-300">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <script>
        // Dark mode initialization
        function initializeDarkMode() {
            // Check for saved dark mode preference
            const isDarkMode = localStorage.getItem('darkMode') === 'true';
            
            // Set initial state
            if (isDarkMode) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }

        // Toggle dark mode
        function toggleDarkMode() {
            const isDarkMode = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDarkMode);
            
            // Reload page with dark mode parameter
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('dark_mode', isDarkMode);
            window.location.href = currentUrl.toString();
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dark mode
            initializeDarkMode();
            
            // Add event listener for dark mode toggle
            document.getElementById('darkModeToggle').addEventListener('click', toggleDarkMode);
            
            // Initialize filters from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Set search input value
            if (urlParams.has('search')) {
                searchInput.value = urlParams.get('search');
            }
            
            // Set category filter value
            if (urlParams.has('category')) {
                categoryFilter.value = urlParams.get('category');
            }
            
            // Set priority filter value
            if (urlParams.has('priority')) {
                priorityFilter.value = urlParams.get('priority');
            }
            
            // Set date range filter value
            if (urlParams.has('date_range')) {
                dateRangeFilter.value = urlParams.get('date_range');
                
                // Handle custom date range if selected
                if (urlParams.get('date_range') === 'custom') {
                    customDateRange.classList.remove('hidden');
                    
                    if (urlParams.has('start_date')) {
                        startDate.value = urlParams.get('start_date');
                    }
                    
                    if (urlParams.has('end_date')) {
                        endDate.value = urlParams.get('end_date');
                    }
                }
            }
            
            // Initialize bulk actions
            updateBulkDeleteButton();
        });
    
        // Search and Filter Functionality
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const priorityFilter = document.getElementById('priorityFilter');
        const dateRangeFilter = document.getElementById('dateRangeFilter');
        const customDateRange = document.getElementById('customDateRange');
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');

        function applyFilters() {
            const params = new URLSearchParams(window.location.search);
            
            if (searchInput.value) params.set('search', searchInput.value);
            if (categoryFilter.value) params.set('category', categoryFilter.value);
            if (priorityFilter.value) params.set('priority', priorityFilter.value);
            if (dateRangeFilter.value) {
                params.set('date_range', dateRangeFilter.value);
                if (dateRangeFilter.value === 'custom') {
                    if (startDate.value) params.set('start_date', startDate.value);
                    if (endDate.value) params.set('end_date', endDate.value);
                }
            }
            
            window.location.href = `archive.php?${params.toString()}`;
        }

        // Event listeners for filters
        searchInput.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') applyFilters();
        });
        
        [categoryFilter, priorityFilter, dateRangeFilter].forEach(filter => {
            filter.addEventListener('change', applyFilters);
        });

        dateRangeFilter.addEventListener('change', (e) => {
            customDateRange.classList.toggle('hidden', e.target.value !== 'custom');
        });

        [startDate, endDate].forEach(date => {
            date.addEventListener('change', () => {
                if (startDate.value && endDate.value) applyFilters();
            });
        });

        // Bulk Selection
        const selectAll = document.getElementById('selectAll');
        const taskCheckboxes = document.querySelectorAll('.task-checkbox');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

        selectAll.addEventListener('change', () => {
            taskCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateBulkDeleteButton();
        });

        taskCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkDeleteButton);
        });

        function updateBulkDeleteButton() {
            const checkedCount = document.querySelectorAll('.task-checkbox:checked').length;
            bulkDeleteBtn.classList.toggle('hidden', checkedCount === 0);
        }

        // Confirmation Modal
        const modal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const confirmButton = document.getElementById('confirmButton');

        function showModal(title, message, onConfirm) {
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            confirmButton.onclick = onConfirm;
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        // Restore Task
        function restoreTask(taskId) {
            showModal(
                'Restore Task',
                'Are you sure you want to restore this task?',
                () => {
                    console.log(`Restoring task ID: ${taskId}`);
                    fetch('archive.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `ajax_action=restore_task&task_id=${taskId}`
                    })
                    .then(async response => {
                        console.log('Restore response status:', response.status);
                        
                        if (!response.ok) {
                            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                        }
                        
                        // Check content-type and try to parse JSON
                        const contentType = response.headers.get('content-type');
                        console.log('Content-Type:', contentType);
                        
                        // Get the raw text for debugging
                        const text = await response.text();
                        console.log('Raw response:', text);
                        
                        // Try to parse as JSON
                        try {
                            const data = JSON.parse(text);
                            console.log('Parsed data:', data);
                            return data;
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                            if (response.ok) {
                                // Create fallback response object
                                return {
                                    success: true,
                                    message: 'Task restored successfully (fallback)',
                                    _raw: text
                                };
                            } else {
                                throw new Error('Failed to parse server response');
                            }
                        }
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(`Failed to restore task: ${data.message || 'Unknown error'}`);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert(`Failed to restore task: ${error.message}`);
                    });
                }
            );
        }

        // Bulk Delete
        bulkDeleteBtn.addEventListener('click', () => {
            const selectedTasks = Array.from(document.querySelectorAll('.task-checkbox:checked'))
                .map(checkbox => checkbox.dataset.taskId);
            
            showModal(
                'Delete Selected Tasks',
                `Are you sure you want to permanently delete ${selectedTasks.length} selected task(s)?`,
                () => {
                    fetch('archive.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `ajax_action=bulk_delete&task_ids=${JSON.stringify(selectedTasks)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to delete tasks');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to delete tasks');
                    });
                }
            );
        });

        // Mobile menu toggle
        const menuButton = document.getElementById('menuButton');
        const sidebar = document.getElementById('sidebar');
        
        menuButton.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
        });

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 1024 && 
                !sidebar.contains(e.target) && 
                !menuButton.contains(e.target) && 
                !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.add('-translate-x-full');
            }
        });

        // Logout function
        function logout() {
            // Destroy session serverside
            fetch('archive.php', {
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
                    window.location.href = 'auth/auth.php';
                } else {
                    console.error('Logout failed:', data.message);
                    alert('Logout failed. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error during logout:', error);
                // Fallback if the fetch fails
                document.cookie = "PHPSESSID=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                window.location.href = 'auth/auth.php';
            });
        }
    </script>
</body>
</html> 