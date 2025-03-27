<?php
require_once('config/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check for dark mode preference in URL parameter
$dark_mode = isset($_GET['dark_mode']) && $_GET['dark_mode'] === 'true' ? 'true' : 'false';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    switch ($_POST['ajax_action']) {
        case 'mark_completed':
            if (isset($_POST['task_id'])) {
                $task_id = intval($_POST['task_id']);
                $user_id = $_SESSION['user_id'];
                
                $sql = "UPDATE tasks SET status = 'Completed' WHERE task_id = ? AND user_id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ii", $task_id, $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $response['success'] = true;
                        $response['message'] = "Task marked as completed successfully.";
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
            break;
            
        case 'logout':
            // Destroy the session
            session_unset();
            session_destroy();
            $response['success'] = true;
            $response['message'] = "Logged out successfully";
            break;
            
        case 'archive_task':
            if (isset($_POST['task_id'])) {
                $task_id = intval($_POST['task_id']);
                $user_id = $_SESSION['user_id'];
                
                // Debug
                error_log("Archiving task ID: " . $task_id . " for user: " . $user_id);
                
                $sql = "UPDATE tasks SET is_archived = 1, archived_date = NOW() WHERE task_id = ? AND user_id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ii", $task_id, $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $response['success'] = true;
                        $response['message'] = "Task archived successfully.";
                        
                        // Debug
                        error_log("Archive successful, affected rows: " . mysqli_stmt_affected_rows($stmt));
                        
                        // Verify the task was actually archived
                        $verify_sql = "SELECT is_archived, archived_date FROM tasks WHERE task_id = ? AND user_id = ?";
                        if ($verify_stmt = mysqli_prepare($conn, $verify_sql)) {
                            mysqli_stmt_bind_param($verify_stmt, "ii", $task_id, $user_id);
                            mysqli_stmt_execute($verify_stmt);
                            mysqli_stmt_bind_result($verify_stmt, $is_archived, $archived_date);
                            if (mysqli_stmt_fetch($verify_stmt)) {
                                error_log("Verification - is_archived: " . $is_archived . ", archived_date: " . $archived_date);
                            }
                            mysqli_stmt_close($verify_stmt);
                        }
                    } else {
                        $response['message'] = "Database error: " . mysqli_error($conn);
                        error_log("Archive error: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $response['message'] = "Prepare statement failed: " . mysqli_error($conn);
                    error_log("Archive prepare error: " . mysqli_error($conn));
                }
            } else {
                $response['message'] = "Task ID not provided";
                error_log("Archive error: Task ID not provided");
            }
            break;
            
        case 'restore_task':
            if (isset($_POST['task_id'])) {
                $task_id = intval($_POST['task_id']);
                $user_id = $_SESSION['user_id'];
                
                $sql = "UPDATE tasks SET is_archived = 0, archived_date = NULL WHERE task_id = ? AND user_id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ii", $task_id, $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $response['success'] = true;
                        $response['message'] = "Task restored successfully.";
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
            break;
            
        case 'delete_task':
            if (isset($_POST['task_id'])) {
                $task_id = intval($_POST['task_id']);
                $user_id = $_SESSION['user_id'];
                
                $sql = "DELETE FROM tasks WHERE task_id = ? AND user_id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ii", $task_id, $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $response['success'] = true;
                        $response['message'] = "Task deleted successfully.";
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
            break;
            
        case 'update_priority':
            if (isset($_POST['task_id']) && isset($_POST['priority'])) {
                $sql = "UPDATE tasks SET priority = ? WHERE task_id = ? AND user_id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "sii", $_POST['priority'], $_POST['task_id'], $_SESSION['user_id']);
                    $response['success'] = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
            break;
            
        case 'update_due_date':
            if (isset($_POST['task_id']) && isset($_POST['due_date'])) {
                $sql = "UPDATE tasks SET due_date = ? WHERE task_id = ? AND user_id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "sii", $_POST['due_date'], $_POST['task_id'], $_SESSION['user_id']);
                    $response['success'] = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
            break;
            
        default:
            $response['message'] = "Invalid action specified.";
            break;
    }
    
    // Clear any previous output that might have been sent accidentally
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Debug info
    error_log("AJAX Response: " . json_encode($response));
    
    // Output the JSON response
    echo json_encode($response);
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Handle filter parameter from dashboard
if (isset($_GET['filter'])) {
    switch ($_GET['filter']) {
        case 'high_priority':
            $priority_filter = 'High';
            break;
        case 'due_soon':
            $date_filter = 'next3days';
            break;
        case 'ongoing':
            $status_filter = 'Ongoing';
            break;
        case 'pending':
            $status_filter = 'Pending';
            break;
        case 'completed':
            $status_filter = 'Completed';
            break;
        case 'task_view':
            // If a specific task ID is provided, add a WHERE clause to show only that task
            if (isset($_GET['task_id']) && is_numeric($_GET['task_id'])) {
                $task_id_filter = (int)$_GET['task_id'];
            }
            break;
        case 'all':
            // Leave all filters empty to show all tasks
            break;
    }
}

// Build the SQL query with filters
$sql = "SELECT * FROM tasks WHERE user_id = ? AND is_archived = 0";
$params = [$_SESSION['user_id']];
$types = "i";

if ($status_filter) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($priority_filter) {
    $sql .= " AND priority = ?";
    $params[] = $priority_filter;
    $types .= "s";
}

if ($category_filter) {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $sql .= " AND DATE(due_date) = CURDATE()";
            break;
        case 'next3days':
            $sql .= " AND DATE(due_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)";
            break;
        case 'thisweek':
            $sql .= " AND YEARWEEK(due_date, 1) = YEARWEEK(CURDATE(), 1)";
            break;
    }
}

if ($search_query) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Add task_id filter if provided (for specific task view)
if (isset($task_id_filter)) {
    $sql .= " AND task_id = ?";
    $params[] = $task_id_filter;
    $types .= "i";
}

// Fetch tasks
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

// Get unique categories for filter dropdown
$categories = [];
$sql = "SELECT DISTINCT category FROM tasks WHERE user_id = ? AND is_archived = 0";
if ($stmt = mysqli_prepare($conn, $sql)) {
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
    <title>Tasks - To-Do List System</title>
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
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        },
                        dark: {
                            bg: '#1e1e2e',
                            card: '#313244',
                            border: '#45475a',
                            text: '#cdd6f4',
                            primary: '#89b4fa'
                        }
                    },
                    animation: {
                        'gradient': 'gradient 8s linear infinite',
                    },
                    keyframes: {
                        gradient: {
                            '0%, 100%': {
                                'background-size': '200% 200%',
                                'background-position': 'left center'
                            },
                            '50%': {
                                'background-size': '200% 200%',
                                'background-position': 'right center'
                            },
                        },
                    },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
        }
        .soft-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .dark .soft-shadow {
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.5);
        }
        .gradient-border {
            position: relative;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #a06cd5);
            background-size: 200% 200%;
            animation: gradient 15s ease infinite;
            padding: 3px;
        }
        .gradient-border::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 0.75rem;
            padding: 3px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #a06cd5);
            -webkit-mask: 
                linear-gradient(#fff 0 0) content-box, 
                linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }
        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
        .hover-scale {
            transition: transform 0.3s ease;
        }
        .hover-scale:hover {
            transform: translateY(-5px);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .dark .glass-effect {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Sidebar toggle animation */
        .sidebar-transition {
            transition: transform 0.3s ease-in-out, width 0.3s ease-in-out, margin-left 0.3s ease-in-out;
        }
        
        /* View toggle button styling */
        .view-toggle-button {
            @apply px-3 py-1.5 rounded-lg text-sm font-medium transition-all duration-200;
        }
        
        .view-toggle-button.active {
            @apply bg-blue-500 text-white;
        }
        
        .view-toggle-button:not(.active) {
            @apply bg-gray-100 dark:bg-dark-border text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700;
        }
        
        /* Dark Mode */
        .dark-mode-text {
            @apply text-gray-100;
        }
        
        .dark .dark-hover:hover {
            @apply bg-gray-700;
        }
        
        /* Search and filters */
        .filter-dropdown {
            @apply mt-1 block w-full rounded-md bg-white dark:bg-dark-input py-2 pl-3 pr-10 text-sm border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500;
        }
        
        /* Task highlighting */
        .highlighted-task {
            @apply ring-2 ring-blue-500 dark:ring-blue-400 ring-offset-2 dark:ring-offset-gray-800;
        }
        
        /* Task Cards & Table transition */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        #tableView, #cardView {
            animation: fadeIn 0.3s ease-out;
        }
        
        /* Task Card hover effect */
        .task-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .task-card:hover {
            transform: translateY(-2px);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .responsive-padding {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
        
        .content-container {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 1.5rem;
        }
        .navbar-fixed {
            position: sticky;
            top: 0;
            z-index: 30;
            width: 100%;
        }
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 16rem;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 dark:text-dark-text transition-colors duration-200">
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed left-0 top-0 z-40 h-screen w-64 bg-white dark:bg-dark-card dark:border-dark-border border-r border-gray-200 soft-shadow transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
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
    <div class="main-content min-h-screen transition-all duration-300">
        <!-- Top Navigation -->
        <nav class="navbar-fixed bg-white dark:bg-dark-card dark:border-dark-border border-b border-gray-200 mb-6">
            <div class="content-container">
                <div class="flex justify-between items-center h-16">
                    <!-- Mobile menu button -->
                    <div class="flex items-center lg:hidden">
                        <button id="menuButton" class="text-gray-600 dark:text-dark-text hover:text-gray-900 dark:hover:text-white p-2 rounded-lg">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="flex items-center">
                        <h1 class="text-xl font-semibold text-gray-800 dark:text-dark-text">My Tasks</h1>
                    </div>
                    
                    <!-- View Toggle Buttons -->
                    <div class="flex items-center gap-4">
                        <div class="flex rounded-lg overflow-hidden border border-gray-200 dark:border-dark-border shadow-sm">
                            <button id="tableViewBtn" class="view-toggle-button px-4 py-2 dark:text-white bg-gradient-to-r from-blue-500 to-indigo-600 text-white">
                                <i class="fas fa-list mr-2"></i>Table View
                            </button>
                            <button id="cardViewBtn" class="view-toggle-button px-4 py-2 dark:text-dark-text bg-gray-100 dark:bg-dark-border">
                                <i class="fas fa-th-large mr-2"></i>Card View
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                    

                        <!-- Profile dropdown -->
                        <div class="relative">
                            <button id="userDropdownButton" class="flex items-center space-x-2 focus:outline-none p-2 rounded-lg">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-white">
                                    <span class="text-sm font-semibold">
                                        <?php echo substr(htmlspecialchars($_SESSION['username']), 0, 1); ?>
                                    </span>
                                </div>
                                <span class="hidden md:inline-block text-gray-700 dark:text-dark-text"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                            </button>
                            
                            <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-dark-card rounded-lg shadow-lg z-50 border border-gray-200 dark:border-dark-border">
                                <div class="px-4 py-3 border-b border-gray-200 dark:border-dark-border">
                                    <p class="text-sm text-gray-500 dark:text-dark-text">Signed in as</p>
                                    <p class="font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                                </div>
                                <a href="#" onclick="toggleDarkMode()" class="block px-4 py-2 text-gray-700 dark:text-dark-text hover:bg-gray-100 dark:hover:bg-dark-border">
                                    <i class="fas fa-adjust mr-2"></i> 
                                    <span id="darkModeText">Dark Mode</span>
                                </a>
                                <button onclick="logout()" class="block w-full text-left px-4 py-2 text-gray-700 dark:text-dark-text hover:bg-gray-100 dark:hover:bg-dark-border rounded-b-lg">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </button>
                            </div>
                        </div>

                        <!-- Quick Add Task Button -->
                        <a href="task_form.php" class="flex items-center gap-2 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white px-4 py-2 rounded-lg transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                            <i class="fas fa-plus"></i>
                            <span>Add Task</span>
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Task Filters and Search -->
        <div class="content-container">
            <div class="gradient-border rounded-xl mb-6">
                <div class="bg-white dark:bg-dark-card rounded-xl p-6 relative overflow-hidden">
                    <!-- Decorative gradient background -->
                    <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-blue-100 to-transparent dark:from-blue-900/20 dark:to-transparent opacity-30 rounded-bl-full"></div>
                    
                    <div class="relative z-10">
                        <div class="flex flex-col md:flex-row items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 md:mb-0">Filter Tasks</h2>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Total: <span class="font-semibold text-blue-600 dark:text-blue-400"><?php echo count($tasks); ?></span></span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                            <!-- Search Bar -->
                            <div class="lg:col-span-2">
                                <div class="relative">
                                    <input type="text" id="searchInput" placeholder="Search tasks..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-dark-border dark:bg-dark-bg dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                </div>
                            </div>
                            
                            <!-- Filter Dropdowns -->
                            <div>
                                <select id="statusFilter" class="w-full rounded-lg border-gray-300 dark:border-dark-border dark:bg-dark-bg dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                    <option value="">All Statuses</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Ongoing">Ongoing</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            <div>
                                <select id="priorityFilter" class="w-full rounded-lg border-gray-300 dark:border-dark-border dark:bg-dark-bg dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                    <option value="">All Priorities</option>
                                    <option value="High">High Priority</option>
                                    <option value="Medium">Medium Priority</option>
                                    <option value="Low">Low Priority</option>
                                </select>
                            </div>
                            <div>
                                <select id="dateFilter" class="w-full rounded-lg border-gray-300 dark:border-dark-border dark:bg-dark-bg dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                    <option value="">All Dates</option>
                                    <option value="today">Due Today</option>
                                    <option value="next3days">Next 3 Days</option>
                                    <option value="thisweek">This Week</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task List Container -->
            <div id="taskContainer">
                <!-- Table View -->
                <div id="tableView" class="gradient-border rounded-xl overflow-hidden">
                    <div class="bg-white dark:bg-dark-card rounded-xl overflow-hidden shadow-md relative">
                        <!-- Decorative gradient background -->
                        <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-blue-100 to-transparent dark:from-blue-900/20 dark:to-transparent opacity-20 rounded-bl-full"></div>
                        
                        <div class="relative z-10">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-dark-border">
                                <thead class="bg-gray-50 dark:bg-dark-border">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Task</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Priority</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-dark-card divide-y divide-gray-200 dark:divide-dark-border">
                                    <?php if (empty($tasks)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fas fa-tasks text-4xl mb-2 text-gray-300 dark:text-gray-600"></i>
                                                <p>No tasks found</p>
                                                <a href="task_form.php" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                                                    <i class="fas fa-plus mr-2"></i> Add New Task
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($tasks as $task): ?>
                                        <?php
                                            $priorityColor = match($task['priority']) {
                                                'High' => 'text-red-500 bg-red-100 dark:bg-red-900/30',
                                                'Medium' => 'text-amber-500 bg-amber-100 dark:bg-amber-900/30',
                                                'Low' => 'text-green-500 bg-green-100 dark:bg-green-900/30',
                                            };
                                            
                                            $statusColor = match($task['status']) {
                                                'Completed' => 'text-green-500 bg-green-100 dark:bg-green-900/30',
                                                'Ongoing' => 'text-blue-500 bg-blue-100 dark:bg-blue-900/30',
                                                'Pending' => 'text-purple-500 bg-purple-100 dark:bg-purple-900/30',
                                            };
                                        ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-dark-border/70 transition-colors duration-150" data-task-id="<?php echo $task['task_id']; ?>">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($task['title']); ?></div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($task['category']); ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $statusColor; ?> dark:text-gray-100">
                                                    <?php echo htmlspecialchars($task['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $priorityColor; ?> dark:text-gray-100">
                                                    <?php echo htmlspecialchars($task['priority']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm font-medium flex space-x-2">
                                                <button onclick="showTaskDetails(<?php echo $task['task_id']; ?>)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 p-1.5 bg-blue-50 dark:bg-blue-900/20 rounded-lg transition-colors" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="editTask(<?php echo $task['task_id']; ?>)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 p-1.5 bg-blue-50 dark:bg-blue-900/20 rounded-lg transition-colors" title="Edit Task">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($task['status'] !== 'Completed'): ?>
                                                <button onclick="markAsCompleted(<?php echo $task['task_id']; ?>)" class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300 p-1.5 bg-green-50 dark:bg-green-900/20 rounded-lg transition-colors" title="Mark as Completed">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <?php else: ?>
                                                <button onclick="archiveTask(<?php echo $task['task_id']; ?>)" class="text-purple-600 dark:text-purple-400 hover:text-purple-900 dark:hover:text-purple-300 p-1.5 bg-purple-50 dark:bg-purple-900/20 rounded-lg transition-colors" title="Archive Task">
                                                    <i class="fas fa-archive"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button onclick="deleteTask(<?php echo $task['task_id']; ?>)" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 p-1.5 bg-red-50 dark:bg-red-900/20 rounded-lg transition-colors" title="Delete Task">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Card View -->
                <div id="cardView" class="hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if (empty($tasks)): ?>
                        <div class="col-span-full flex flex-col items-center justify-center py-20 px-6 border border-gray-200 dark:border-dark-border rounded-xl shadow-sm bg-white dark:bg-dark-card">
                            <i class="fas fa-tasks text-4xl mb-2 text-gray-300 dark:text-gray-600"></i>
                            <p class="text-center text-gray-500 dark:text-gray-400 mb-4">No tasks found</p>
                            <a href="task_form.php" class="mt-2 inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                                <i class="fas fa-plus mr-2"></i> Add New Task
                            </a>
                        </div>
                        <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <?php
                                $priorityColor = match($task['priority']) {
                                    'High' => 'text-red-500 bg-red-100 dark:bg-red-900/30',
                                    'Medium' => 'text-amber-500 bg-amber-100 dark:bg-amber-900/30',
                                    'Low' => 'text-green-500 bg-green-100 dark:bg-green-900/30',
                                };
                                
                                $statusColor = match($task['status']) {
                                    'Completed' => 'text-green-500 bg-green-100 dark:bg-green-900/30',
                                    'Ongoing' => 'text-blue-500 bg-blue-100 dark:bg-blue-900/30',
                                    'Pending' => 'text-purple-500 bg-purple-100 dark:bg-purple-900/30',
                                };
                                
                                $bgColorClass = match($task['priority']) {
                                    'High' => 'from-red-100',
                                    'Medium' => 'from-amber-100',
                                    'Low' => 'from-green-100',
                                };
                            ?>
                            <div class="gradient-border rounded-xl hover-scale" data-task-id="<?php echo $task['task_id']; ?>">
                                <div class="group p-5 h-full rounded-xl bg-white dark:bg-dark-card shadow-sm hover:shadow-md transition-shadow duration-200 relative overflow-hidden">
                                    <!-- Decorative gradient in corner -->
                                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br <?php echo $bgColorClass; ?> to-transparent opacity-50 rounded-bl-full"></div>
                                    
                                    <div class="relative z-10">
                                        <div class="flex justify-between items-start mb-4">
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($task['title']); ?></h3>
                                            <div class="flex space-x-1">
                                                <button onclick="showTaskDetails(<?php echo $task['task_id']; ?>)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 p-1.5 bg-blue-50 dark:bg-blue-900/20 rounded-lg opacity-70 group-hover:opacity-100 transition-opacity" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="editTask(<?php echo $task['task_id']; ?>)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 p-1.5 bg-blue-50 dark:bg-blue-900/20 rounded-lg opacity-70 group-hover:opacity-100 transition-opacity" title="Edit Task">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                                            <?php 
                                                $description = htmlspecialchars($task['description']);
                                                echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                                            ?>
                                        </div>
                                        <div class="flex items-center mb-3">
                                            <i class="fas fa-layer-group text-gray-400 dark:text-gray-500 mr-2"></i>
                                            <span class="text-sm text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars($task['category']); ?></span>
                                        </div>
                                        <div class="flex items-center mb-3">
                                            <i class="far fa-calendar text-gray-400 dark:text-gray-500 mr-2"></i>
                                            <span class="text-sm text-gray-600 dark:text-gray-300"><?php echo date('M j, Y', strtotime($task['due_date'])); ?></span>
                                        </div>
                                        <div class="flex justify-between items-center mt-4">
                                            <div class="flex space-x-2">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $statusColor; ?> dark:text-gray-100">
                                                    <?php echo htmlspecialchars($task['status']); ?>
                                                </span>
                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $priorityColor; ?> dark:text-gray-100">
                                                    <?php echo htmlspecialchars($task['priority']); ?>
                                                </span>
                                            </div>
                                            <div class="flex space-x-1">
                                                <?php if ($task['status'] !== 'Completed'): ?>
                                                <button onclick="markAsCompleted(<?php echo $task['task_id']; ?>)" class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300 p-1.5 bg-green-50 dark:bg-green-900/20 rounded-lg opacity-70 group-hover:opacity-100 transition-opacity" title="Mark as Completed">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <?php else: ?>
                                                <button onclick="archiveTask(<?php echo $task['task_id']; ?>)" class="text-purple-600 dark:text-purple-400 hover:text-purple-900 dark:hover:text-purple-300 p-1.5 bg-purple-50 dark:bg-purple-900/20 rounded-lg opacity-70 group-hover:opacity-100 transition-opacity" title="Archive Task">
                                                    <i class="fas fa-archive"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button onclick="deleteTask(<?php echo $task['task_id']; ?>)" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 p-1.5 bg-red-50 dark:bg-red-900/20 rounded-lg opacity-70 group-hover:opacity-100 transition-opacity" title="Delete Task">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Confirmation Dialog -->
    <div id="confirmationDialog" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-80 transition-opacity"></div>
            <div class="bg-white dark:bg-dark-card rounded-xl shadow-xl transform transition-all w-full max-w-md overflow-hidden">
                <div class="p-6">
                    <div class="sm:flex sm:items-start">
                        <div id="confirmationIcon" class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                            <i id="confirmationIconClass" class="fas"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="confirmationTitle">Confirmation</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400" id="confirmationMessage">Are you sure you want to do this?</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" id="confirmButton" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Confirm
                        </button>
                        <button type="button" id="cancelButton" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-dark-border text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Details Modal -->
    <div id="taskDetailsModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-80 transition-opacity"></div>
            <div class="bg-white dark:bg-dark-card rounded-xl shadow-xl transform transition-all w-full max-w-2xl overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-dark-border flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white" id="modalTaskTitle">Task Details</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" onclick="closeTaskDetails()">
                        <span class="sr-only">Close</span>
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</h4>
                            <span id="modalTaskStatus" class="px-2 py-1 text-xs font-medium rounded-full"></span>
                        </div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Priority</h4>
                            <span id="modalTaskPriority" class="px-2 py-1 text-xs font-medium rounded-full"></span>
                        </div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</h4>
                            <span id="modalTaskCategory" class="text-sm text-gray-700 dark:text-gray-300"></span>
                        </div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Due Date</h4>
                            <span id="modalTaskDueDate" class="text-sm text-gray-700 dark:text-gray-300"></span>
                        </div>
                    </div>
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Description</h4>
                        <p id="modalTaskDescription" class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line"></p>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-dark-border border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none" onclick="closeTaskDetails()">
                            Close
                        </button>
                        <button id="modalEditButton" type="button" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none">
                            Edit Task
                        </button>
                        <button id="modalCompleteButton" type="button" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none">
                            Mark as Completed
                        </button>
                        <button id="modalArchiveButton" type="button" class="hidden inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-purple-600 border border-transparent rounded-md hover:bg-purple-700 focus:outline-none">
                            Archive Task
                        </button>
                        <button id="modalDeleteButton" type="button" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize dark mode from URL parameter or localStorage
        document.addEventListener('DOMContentLoaded', function() {
            // Check if dark mode is set in URL parameter (takes precedence)
            const urlParams = new URLSearchParams(window.location.search);
            const urlDarkMode = urlParams.get('dark_mode');
            
            let isDarkMode = false;
            
            if (urlDarkMode !== null) {
                isDarkMode = urlDarkMode === 'true';
                // Update localStorage to match URL parameter
                localStorage.setItem('darkMode', isDarkMode);
            } else {
                // Fall back to localStorage if no URL parameter
                isDarkMode = localStorage.getItem('darkMode') === 'true';
            }
            
            if (isDarkMode) {
                document.documentElement.classList.add('dark');
                const darkModeIconMoon = document.getElementById('darkModeIconMoon');
                const darkModeIconSun = document.getElementById('darkModeIconSun');
                const darkModeText = document.getElementById('darkModeText');
                if (darkModeText) {
                    darkModeText.innerText = 'Light Mode';
                }
            }
            
            // Clear the dark_mode parameter from the URL to keep it clean
            if (urlDarkMode !== null) {
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.delete('dark_mode');
                window.history.replaceState({}, '', newUrl.toString());
            }
            
            // Setup view toggle buttons
            const tableViewBtn = document.getElementById('tableViewBtn');
            const cardViewBtn = document.getElementById('cardViewBtn');
            const tableView = document.getElementById('tableView');
            const cardView = document.getElementById('cardView');
            
            // Apply view preference from localStorage on page load
            const viewPreference = localStorage.getItem('viewPreference') || 'table';
            if (viewPreference === 'card' && tableView && cardView && tableViewBtn && cardViewBtn) {
                tableView.classList.add('hidden');
                cardView.classList.remove('hidden');
                
                cardViewBtn.classList.add('bg-gradient-to-r', 'from-blue-500', 'to-indigo-600', 'text-white');
                cardViewBtn.classList.remove('bg-gray-100', 'dark:bg-dark-border', 'text-gray-700', 'dark:text-gray-300');
                
                tableViewBtn.classList.remove('bg-gradient-to-r', 'from-blue-500', 'to-indigo-600', 'text-white');
                tableViewBtn.classList.add('bg-gray-100', 'dark:bg-dark-border', 'text-gray-700', 'dark:text-gray-300');
            }
            
            if (tableViewBtn) {
                tableViewBtn.addEventListener('click', function() {
                    const tableView = document.getElementById('tableView');
                    const cardView = document.getElementById('cardView');
                    
                    tableView.classList.remove('hidden');
                    cardView.classList.add('hidden');
                    
                    tableViewBtn.classList.add('bg-gradient-to-r', 'from-blue-500', 'to-indigo-600', 'text-white');
                    tableViewBtn.classList.remove('bg-gray-100', 'dark:bg-dark-border', 'text-gray-700', 'dark:text-gray-300');
                    
                    cardViewBtn.classList.remove('bg-gradient-to-r', 'from-blue-500', 'to-indigo-600', 'text-white');
                    cardViewBtn.classList.add('bg-gray-100', 'dark:bg-dark-border', 'text-gray-700', 'dark:text-gray-300');
                    
                    // Save preference
                    localStorage.setItem('viewPreference', 'table');
                });
            }
            
            if (cardViewBtn) {
                cardViewBtn.addEventListener('click', function() {
                    const tableView = document.getElementById('tableView');
                    const cardView = document.getElementById('cardView');
                    
                    tableView.classList.add('hidden');
                    cardView.classList.remove('hidden');
                    
                    cardViewBtn.classList.add('bg-gradient-to-r', 'from-blue-500', 'to-indigo-600', 'text-white');
                    cardViewBtn.classList.remove('bg-gray-100', 'dark:bg-dark-border', 'text-gray-700', 'dark:text-gray-300');
                    
                    tableViewBtn.classList.remove('bg-gradient-to-r', 'from-blue-500', 'to-indigo-600', 'text-white');
                    tableViewBtn.classList.add('bg-gray-100', 'dark:bg-dark-border', 'text-gray-700', 'dark:text-gray-300');
                    
                    // Save preference
                    localStorage.setItem('viewPreference', 'card');
                });
            }
            
            // Setup dark mode toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function() {
                    const html = document.documentElement;
                    const isDarkMode = html.classList.toggle('dark');
                    
                    localStorage.setItem('darkMode', isDarkMode);
                    
                    const darkModeText = document.getElementById('darkModeText');
                    if (darkModeText) {
                        darkModeText.innerText = isDarkMode ? 'Light Mode' : 'Dark Mode';
                    }
                    
                    // Reload the page with dark mode parameter to ensure server-side rendering matches
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('dark_mode', isDarkMode);
                    window.location.href = currentUrl.toString();
                });
            }
            
            // Setup user dropdown
            const userDropdownButton = document.getElementById('userDropdownButton');
            const userDropdown = document.getElementById('userDropdown');
            
            if (userDropdownButton && userDropdown) {
                userDropdownButton.addEventListener('click', function() {
                    userDropdown.classList.toggle('hidden');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userDropdownButton.contains(event.target) && !userDropdown.contains(event.target)) {
                        userDropdown.classList.add('hidden');
                    }
                });
            }
            
            // Setup task actions (mark as completed, archive, delete)
            setupTaskActions();
            
            // Setup filters
            const statusFilter = document.getElementById('statusFilter');
            const priorityFilter = document.getElementById('priorityFilter');
            const dateFilter = document.getElementById('dateFilter');
            const searchInput = document.getElementById('searchInput');
            
            // Set initial values based on URL parameters
            if (statusFilter) {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('status')) {
                    statusFilter.value = urlParams.get('status');
                }
            }
            
            if (priorityFilter) {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('priority')) {
                    priorityFilter.value = urlParams.get('priority');
                }
            }
            
            if (dateFilter) {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('date_filter')) {
                    dateFilter.value = urlParams.get('date_filter');
                }
            }
            
            if (searchInput) {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('search')) {
                    searchInput.value = urlParams.get('search');
                }
                
                // Add event listener for search input
                searchInput.addEventListener('keyup', function(event) {
                    // Apply filters when Enter key is pressed
                    if (event.key === 'Enter') {
                        applyFilters();
                    }
                });
            }
            
            // Add event listeners for filter changes
            if (statusFilter) {
                statusFilter.addEventListener('change', applyFilters);
            }
            
            if (priorityFilter) {
                priorityFilter.addEventListener('change', applyFilters);
            }
            
            if (dateFilter) {
                dateFilter.addEventListener('change', applyFilters);
            }
        });
        
        // Setup task actions
        function setupTaskActions() {
            // Setup confirmation dialog
            const confirmButton = document.getElementById('confirmButton');
            const cancelButton = document.getElementById('cancelButton');
            const confirmationDialog = document.getElementById('confirmationDialog');
            
            if (confirmButton && cancelButton && confirmationDialog) {
                // Global variable to store the callback function
                window.confirmCallback = null;
                
                confirmButton.addEventListener('click', function() {
                    confirmationDialog.classList.add('hidden');
                    if (typeof window.confirmCallback === 'function') {
                        window.confirmCallback();
                        window.confirmCallback = null;
                    }
                });
                
                cancelButton.addEventListener('click', function() {
                    confirmationDialog.classList.add('hidden');
                    window.confirmCallback = null;
                });
            }
        }
        
        // Task action helpers
        function markAsCompleted(taskId) {
            const confirmationDialog = document.getElementById('confirmationDialog');
            const confirmationTitle = document.getElementById('confirmationTitle');
            const confirmationMessage = document.getElementById('confirmationMessage');
            const confirmationIcon = document.getElementById('confirmationIcon');
            const confirmationIconClass = document.getElementById('confirmationIconClass');
            
            if (confirmationDialog && confirmationTitle && confirmationMessage && confirmationIcon && confirmationIconClass) {
                confirmationTitle.textContent = 'Mark as Completed';
                confirmationMessage.textContent = 'Are you sure you want to mark this task as completed?';
                confirmationIconClass.className = 'fas fa-check';
                confirmationIcon.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400 sm:mx-0 sm:h-10 sm:w-10';
                
                window.confirmCallback = function() {
                    fetch('task.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `ajax_action=mark_completed&task_id=${taskId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Preserve isDarkMode in the URL when reloading
                            const isDarkMode = document.documentElement.classList.contains('dark');
                            const currentUrl = new URL(window.location.href);
                            currentUrl.searchParams.set('dark_mode', isDarkMode);
                            window.location.href = currentUrl.toString();
                        } else {
                            console.error('Failed to update task status:', data.message);
                            alert('Failed to mark task as completed: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating task status:', error);
                        alert('Error marking task as completed. Please try again.');
                    });
                };
                
                confirmationDialog.classList.remove('hidden');
            }
        }
        
        function archiveTask(taskId) {
            const confirmationDialog = document.getElementById('confirmationDialog');
            const confirmationTitle = document.getElementById('confirmationTitle');
            const confirmationMessage = document.getElementById('confirmationMessage');
            const confirmationIcon = document.getElementById('confirmationIcon');
            const confirmationIconClass = document.getElementById('confirmationIconClass');
            
            if (confirmationDialog && confirmationTitle && confirmationMessage && confirmationIcon && confirmationIconClass) {
                confirmationTitle.textContent = 'Archive Task';
                confirmationMessage.textContent = 'Are you sure you want to archive this task?';
                confirmationIconClass.className = 'fas fa-archive';
                confirmationIcon.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-400 sm:mx-0 sm:h-10 sm:w-10';
                
                window.confirmCallback = function() {
                    fetch('task.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `ajax_action=archive_task&task_id=${taskId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Preserve isDarkMode in the URL when reloading
                            const isDarkMode = document.documentElement.classList.contains('dark');
                            const currentUrl = new URL(window.location.href);
                            currentUrl.searchParams.set('dark_mode', isDarkMode);
                            window.location.href = currentUrl.toString();
                        } else {
                            console.error('Failed to archive task:', data.message);
                            alert('Failed to archive task: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error archiving task:', error);
                        alert('Error archiving task. Please try again.');
                    });
                };
                
                confirmationDialog.classList.remove('hidden');
            }
        }
        
        function deleteTask(taskId) {
            const confirmationDialog = document.getElementById('confirmationDialog');
            const confirmationTitle = document.getElementById('confirmationTitle');
            const confirmationMessage = document.getElementById('confirmationMessage');
            const confirmationIcon = document.getElementById('confirmationIcon');
            const confirmationIconClass = document.getElementById('confirmationIconClass');
            
            if (confirmationDialog && confirmationTitle && confirmationMessage && confirmationIcon && confirmationIconClass) {
                confirmationTitle.textContent = 'Delete Task';
                confirmationMessage.textContent = 'Are you sure you want to delete this task? This action cannot be undone.';
                confirmationIconClass.className = 'fas fa-trash';
                confirmationIcon.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400 sm:mx-0 sm:h-10 sm:w-10';
                
                window.confirmCallback = function() {
                    fetch('task.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `ajax_action=delete_task&task_id=${taskId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Preserve isDarkMode in the URL when reloading
                            const isDarkMode = document.documentElement.classList.contains('dark');
                            const currentUrl = new URL(window.location.href);
                            currentUrl.searchParams.set('dark_mode', isDarkMode);
                            window.location.href = currentUrl.toString();
                        } else {
                            console.error('Failed to delete task:', data.message);
                            alert('Failed to delete task: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting task:', error);
                        alert('Error deleting task. Please try again.');
                    });
                };
                
                confirmationDialog.classList.remove('hidden');
            }
        }
        
        function logout() {
            fetch('task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax_action=logout'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = "auth/auth.php";
                } else {
                    console.error('Logout failed:', data.message);
                    alert('Logout failed. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error during logout:', error);
                alert('Error during logout. Please try again.');
            });
        }
        
        // Task details modal functionality
        function showTaskDetails(taskId) {
            const taskDetailsModal = document.getElementById('taskDetailsModal');
            
            if (taskDetailsModal) {
                // Store the task ID for edit/delete/complete actions
                window.currentTaskId = taskId;
                
                // Make AJAX request to get task details
                fetch(`api/get_task.php?task_id=${taskId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const task = data.task;
                            
                            // Set modal content
                            document.getElementById('modalTaskTitle').textContent = task.title;
                            document.getElementById('modalTaskDescription').textContent = task.description;
                            document.getElementById('modalTaskCategory').textContent = task.category;
                            document.getElementById('modalTaskDueDate').textContent = new Date(task.due_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                            
                            // Set status with appropriate color
                            const statusElement = document.getElementById('modalTaskStatus');
                            statusElement.textContent = task.status;
                            
                            let statusColor = '';
                            switch (task.status) {
                                case 'Completed':
                                    statusColor = 'text-green-700 bg-green-100 dark:bg-green-900/30 dark:text-green-300';
                                    break;
                                case 'Ongoing':
                                    statusColor = 'text-blue-700 bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300';
                                    break;
                                case 'Pending':
                                    statusColor = 'text-purple-700 bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300';
                                    break;
                            }
                            
                            statusElement.className = 'px-2 py-1 text-xs font-medium rounded-full ' + statusColor;
                            
                            // Set priority with appropriate color
                            const priorityElement = document.getElementById('modalTaskPriority');
                            priorityElement.textContent = task.priority;
                            
                            let priorityColor = '';
                            switch (task.priority) {
                                case 'High':
                                    priorityColor = 'text-red-700 bg-red-100 dark:bg-red-900/30 dark:text-red-300';
                                    break;
                                case 'Medium':
                                    priorityColor = 'text-amber-700 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300';
                                    break;
                                case 'Low':
                                    priorityColor = 'text-green-700 bg-green-100 dark:bg-green-900/30 dark:text-green-300';
                                    break;
                            }
                            
                            priorityElement.className = 'px-2 py-1 text-xs font-medium rounded-full ' + priorityColor;
                            
                            // Show/hide buttons based on task status
                            document.getElementById('modalCompleteButton').classList.toggle('hidden', task.status === 'Completed');
                            document.getElementById('modalArchiveButton').classList.toggle('hidden', task.status !== 'Completed');
                            
                            // Setup buttons
                            document.getElementById('modalEditButton').onclick = function() {
                                closeTaskDetails();
                                editTask(window.currentTaskId);
                            };
                            
                            document.getElementById('modalCompleteButton').onclick = function() {
                                closeTaskDetails();
                                markAsCompleted(window.currentTaskId);
                            };
                            
                            document.getElementById('modalArchiveButton').onclick = function() {
                                closeTaskDetails();
                                archiveTask(window.currentTaskId);
                            };
                            
                            document.getElementById('modalDeleteButton').onclick = function() {
                                closeTaskDetails();
                                deleteTask(window.currentTaskId);
                            };
                            
                            // Show the modal
                            taskDetailsModal.classList.remove('hidden');
                        } else {
                            console.error('Failed to get task details:', data.message);
                            alert('Failed to load task details: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching task details:', error);
                        alert('Error loading task details. Please try again.');
                    });
            }
        }
        
        function closeTaskDetails() {
            const taskDetailsModal = document.getElementById('taskDetailsModal');
            if (taskDetailsModal) {
                taskDetailsModal.classList.add('hidden');
                window.currentTaskId = null;
            }
        }
        
        function editTask(taskId) {
            window.location.href = `task_form.php?task_id=${taskId}&mode=edit`;
        }

        // Apply filters
        function applyFilters() {
            const statusFilter = document.getElementById('statusFilter').value;
            const priorityFilter = document.getElementById('priorityFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const searchQuery = document.getElementById('searchInput').value;
            
            const currentUrl = new URL(window.location.href);
            
            // Clear existing filters
            currentUrl.searchParams.delete('status');
            currentUrl.searchParams.delete('priority');
            currentUrl.searchParams.delete('date_filter');
            currentUrl.searchParams.delete('search');
            
            // Add new filters if they exist
            if (statusFilter) {
                currentUrl.searchParams.set('status', statusFilter);
                
                // Set the filter parameter based on status
                switch (statusFilter) {
                    case 'Ongoing':
                        currentUrl.searchParams.set('filter', 'ongoing');
                        break;
                    case 'Pending':
                        currentUrl.searchParams.set('filter', 'pending');
                        break;
                    case 'Completed':
                        currentUrl.searchParams.set('filter', 'completed');
                        break;
                }
            }
            
            if (priorityFilter) {
                currentUrl.searchParams.set('priority', priorityFilter);
                
                // Set the filter parameter if priority is High
                if (priorityFilter === 'High') {
                    currentUrl.searchParams.set('filter', 'high_priority');
                }
            }
            
            if (dateFilter) {
                currentUrl.searchParams.set('date_filter', dateFilter);
                
                // Set the filter parameter if date filter is next3days
                if (dateFilter === 'next3days') {
                    currentUrl.searchParams.set('filter', 'due_soon');
                }
            }
            
            if (searchQuery) {
                currentUrl.searchParams.set('search', searchQuery);
            }
            
            // Preserve dark mode parameter
            const isDarkMode = document.documentElement.classList.contains('dark');
            currentUrl.searchParams.set('dark_mode', isDarkMode);
            
            // Navigate to the new URL
            window.location.href = currentUrl.toString();
        }
    </script>
</body>
</html>