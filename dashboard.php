<?php
require_once('config/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/auth.php");
    exit();
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
    }
    
    echo json_encode($response);
    exit();
}

// Check for dark mode preference in URL parameter
$dark_mode = isset($_GET['dark_mode']) && $_GET['dark_mode'] === 'true' ? 'true' : 'false';

// Get task statistics for dashboard cards
$user_id = $_SESSION['user_id'];
$stats = [];

// Total Tasks
$sql = "SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND is_archived = 0";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['total'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
}

// High Priority Tasks
$sql = "SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND priority = 'High' AND status != 'Completed' AND is_archived = 0";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['high_priority'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
}

// Due in 3 Days
$three_days_later = date('Y-m-d H:i:s', strtotime('+3 days'));
$sql = "SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND due_date <= ? AND status != 'Completed' AND is_archived = 0";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "is", $user_id, $three_days_later);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['due_soon'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
}

// Ongoing Tasks
$sql = "SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND status = 'Ongoing' AND is_archived = 0";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['ongoing'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
}

// Pending Tasks
$sql = "SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND status = 'Pending' AND is_archived = 0";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['pending'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
}

// Completed Tasks
$sql = "SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND status = 'Completed' AND is_archived = 0";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['completed'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
}

// Get tasks due in 3 days for reminder notification
$tasks_due_soon = [];
$sql = "SELECT task_id, title, due_date, reminder_time FROM tasks 
        WHERE user_id = ? AND due_date <= ? AND status != 'Completed' AND is_archived = 0 
        ORDER BY due_date ASC LIMIT 5";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "is", $user_id, $three_days_later);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $tasks_due_soon[] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo $dark_mode === 'true' ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - To-Do List System</title>
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
        
        /* Notification styles */
        .notification-bell {
            position: relative;
            cursor: pointer;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 320px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.3s ease;
            opacity: 0;
            z-index: 50;
        }
        .notification-dropdown.show {
            max-height: 400px;
            opacity: 1;
        }
        
        /* Slide-in notification */
        .slide-in-notification {
            position: fixed;
            top: 20px;
            right: -400px;
            width: 350px;
            transition: right 0.5s ease;
            z-index: 100;
        }
        .slide-in-notification.show {
            right: 20px;
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
                        <h1 class="text-xl font-semibold text-gray-800 dark:text-dark-text">Dashboard</h1>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Notification Bell -->
                        <div class="notification-bell relative">
                            <button id="notificationButton" class="text-gray-600 dark:text-white hover:text-gray-900 dark:hover:text-white focus:outline-none relative p-2 rounded-lg">
                                <i class="fas fa-bell text-xl"></i>
                                <?php if (count($tasks_due_soon) > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 flex items-center justify-center rounded-full"><?php echo count($tasks_due_soon); ?></span>
                                <?php endif; ?>
                            </button>
                        </div>
                        
                        <!-- User Profile -->
                        <div class="relative">
                            <button id="userDropdownButton" class="flex items-center space-x-2 focus:outline-none p-2 rounded-lg">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-white">
                                    <span class="text-sm font-semibold">
                                        <?php echo isset($_SESSION['username']) ? substr(htmlspecialchars($_SESSION['username']), 0, 1) : 'U'; ?>
                                    </span>
                                </div>
                                <span class="hidden md:inline-block text-gray-700 dark:text-dark-text"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></span>
                                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                            </button>
                            
                            <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-dark-card rounded-lg shadow-lg z-50 border border-gray-200 dark:border-dark-border">
                                <div class="px-4 py-3 border-b border-gray-200 dark:border-dark-border">
                                    <p class="text-sm text-gray-500 dark:text-dark-text">Signed in as</p>
                                    <p class="font-medium text-gray-800 dark:text-white"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></p>
                                </div>
                                <a href="#" onclick="openProfileModal()" class="block px-4 py-2 text-gray-700 dark:text-dark-text hover:bg-gray-100 dark:hover:bg-dark-border">
                                    <i class="fas fa-user mr-2"></i> Edit Profile
                                </a>
                                <button onclick="logout()" class="block w-full text-left px-4 py-2 text-gray-700 dark:text-dark-text hover:bg-gray-100 dark:hover:bg-dark-border rounded-b-lg">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </button>
                            </div>
                        </div>
                            
                        <!-- Notification Dropdown -->
                        <div id="notificationDropdown" class="notification-dropdown bg-white dark:bg-dark-card soft-shadow rounded-lg mt-2">
                            <div class="p-4 border-b border-gray-200 dark:border-dark-border">
                                <h3 class="font-semibold text-gray-800 dark:text-dark-text">Tasks Due Soon</h3>
                            </div>
                            <div class="p-2 max-h-60 overflow-y-auto">
                                <?php if (count($tasks_due_soon) > 0): ?>
                                    <?php foreach ($tasks_due_soon as $task): ?>
                                        <div class="p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg">
                                            <div class="font-medium text-gray-800 dark:text-dark-text"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <span><i class="fas fa-calendar-alt mr-1"></i> Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?></span>
                                                <?php if ($task['reminder_time']): ?>
                                                <span class="ml-2"><i class="fas fa-bell mr-1"></i> Reminder: <?php echo date('M d, g:i A', strtotime($task['reminder_time'])); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                                        No tasks due soon
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Slide-in Notification -->
        <div id="slideInNotification" class="slide-in-notification bg-white dark:bg-dark-card soft-shadow rounded-lg p-4">
            <div class="flex justify-between items-start mb-2">
                <h3 class="font-semibold text-gray-800 dark:text-dark-text">Reminder</h3>
                <button id="closeNotification" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="notificationContent">
                <!-- Content will be dynamically inserted here -->
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="content-container">
            <!-- Welcome Section -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-dark-text mb-2">Welcome to your Dashboard</h2>
                <p class="text-gray-600 dark:text-gray-400">Here's an overview of your tasks and activities</p>
            </div>

            <!-- Dashboard Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- High Priority Tasks Card -->
                <div class="gradient-border rounded-xl hover-scale">
                    <div class="bg-white dark:bg-dark-card h-full rounded-xl p-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-red-100 to-transparent opacity-50 rounded-bl-full"></div>
                        <div class="relative z-10">
                            <div class="text-red-500 text-4xl mb-4 flex items-center justify-center w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full mx-auto">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <h3 class="text-xl font-semibold mb-2 text-center dark:text-white">High Priority Tasks</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-center mb-4">Tasks marked as High Priority</p>
                            <div class="text-3xl font-bold text-gray-800 dark:text-dark-text mb-2 text-center"><?php echo $stats['high_priority']; ?></div>
                            <div class="flex justify-center">
                                <a href="task.php?priority=High&filter=high_priority" class="text-white bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 px-6 py-2 rounded-lg transition-all duration-300 inline-flex items-center group">
                                    <span>View Tasks</span>
                                    <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                                </a>
                        </div>
                    </div>
                        </div>
                    </div>

                <!-- Due Soon Tasks Card -->
                <div class="gradient-border rounded-xl hover-scale">
                    <div class="bg-white dark:bg-dark-card h-full rounded-xl p-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-amber-100 to-transparent opacity-50 rounded-bl-full"></div>
                        <div class="relative z-10">
                            <div class="text-amber-500 text-4xl mb-4 flex items-center justify-center w-16 h-16 bg-amber-100 dark:bg-amber-900/30 rounded-full mx-auto">
                            <i class="fas fa-hourglass-half"></i>
                            </div>
                            <h3 class="text-xl font-semibold mb-2 text-center dark:text-white">Due in 3 Days</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-center mb-4">Tasks due within the next 3 days</p>
                            <div class="text-3xl font-bold text-gray-800 dark:text-dark-text mb-2 text-center"><?php echo $stats['due_soon']; ?></div>
                            <div class="flex justify-center">
                                <a href="task.php?date_filter=next3days&filter=due_soon" class="text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 px-6 py-2 rounded-lg transition-all duration-300 inline-flex items-center group">
                                    <span>View Tasks</span>
                                    <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                                </a>
                            </div>
                        </div>
                        </div>
                    </div>

                <!-- Ongoing Tasks Card -->
                <div class="gradient-border rounded-xl hover-scale">
                    <div class="bg-white dark:bg-dark-card h-full rounded-xl p-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-100 to-transparent opacity-50 rounded-bl-full"></div>
                        <div class="relative z-10">
                            <div class="text-blue-500 text-4xl mb-4 flex items-center justify-center w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded-full mx-auto">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <h3 class="text-xl font-semibold mb-2 text-center dark:text-white">Ongoing Tasks</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-center mb-4">Tasks currently in progress</p>
                            <div class="text-3xl font-bold text-gray-800 dark:text-dark-text mb-2 text-center"><?php echo $stats['ongoing']; ?></div>
                            <div class="flex justify-center">
                                <a href="task.php?status=Ongoing&filter=ongoing" class="text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 px-6 py-2 rounded-lg transition-all duration-300 inline-flex items-center group">
                                    <span>View Tasks</span>
                                    <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                                </a>
                            </div>
                        </div>
                        </div>
                    </div>

                <!-- Pending Tasks Card -->
                <div class="gradient-border rounded-xl hover-scale">
                    <div class="bg-white dark:bg-dark-card h-full rounded-xl p-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-purple-100 to-transparent opacity-50 rounded-bl-full"></div>
                        <div class="relative z-10">
                            <div class="text-purple-500 text-4xl mb-4 flex items-center justify-center w-16 h-16 bg-purple-100 dark:bg-purple-900/30 rounded-full mx-auto">
                                <i class="fas fa-folder-open"></i>
                            </div>
                            <h3 class="text-xl font-semibold mb-2 text-center dark:text-white">Pending Tasks</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-center mb-4">All unfinished tasks</p>
                            <div class="text-3xl font-bold text-gray-800 dark:text-dark-text mb-2 text-center"><?php echo $stats['pending']; ?></div>
                            <div class="flex justify-center">
                                <a href="task.php?status=Pending&filter=pending" class="text-white bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 px-6 py-2 rounded-lg transition-all duration-300 inline-flex items-center group">
                                    <span>View Tasks</span>
                                    <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                                </a>
                            </div>
                        </div>
                        </div>
                    </div>

                <!-- Completed Tasks Card -->
                <div class="gradient-border rounded-xl hover-scale">
                    <div class="bg-white dark:bg-dark-card h-full rounded-xl p-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-green-100 to-transparent opacity-50 rounded-bl-full"></div>
                        <div class="relative z-10">
                            <div class="text-green-500 text-4xl mb-4 flex items-center justify-center w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full mx-auto">
                            <i class="fas fa-check-circle"></i>
                        </div>
                            <h3 class="text-xl font-semibold mb-2 text-center dark:text-white">Completed Tasks</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-center mb-4">Tasks marked as completed</p>
                            <div class="text-3xl font-bold text-gray-800 dark:text-dark-text mb-2 text-center"><?php echo $stats['completed']; ?></div>
                            <div class="flex justify-center">
                                <a href="task.php?status=Completed&filter=completed" class="text-white bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 px-6 py-2 rounded-lg transition-all duration-300 inline-flex items-center group">
                                    <span>View Tasks</span>
                                    <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                                </a>
                            </div>
                    </div>
                        </div>
                    </div>

                <!-- Total Tasks Card -->
                <div class="gradient-border rounded-xl hover-scale">
                    <div class="bg-white dark:bg-dark-card h-full rounded-xl p-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-gray-100 to-transparent opacity-50 rounded-bl-full"></div>
                        <div class="relative z-10">
                            <div class="text-gray-700 dark:text-gray-300 text-4xl mb-4 flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full mx-auto">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h3 class="text-xl font-semibold mb-2 text-center dark:text-white">Total Tasks</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-center mb-4">Total number of tasks in the system</p>
                            <div class="text-3xl font-bold text-gray-800 dark:text-dark-text mb-2 text-center"><?php echo $stats['total']; ?></div>
                            <div class="flex justify-center">
                                <a href="task.php?filter=all" class="text-white bg-gradient-to-r from-gray-700 to-gray-800 hover:from-gray-800 hover:to-gray-900 px-6 py-2 rounded-lg transition-all duration-300 inline-flex items-center group">
                                    <span>View Tasks</span>
                                    <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Task Timeline -->
            <div class="gradient-border rounded-xl mt-8">
                <div class="bg-white dark:bg-dark-card rounded-xl p-6">
                    <h3 class="text-2xl font-bold mb-6 text-gray-800 dark:text-white flex items-center">
                        <i class="fas fa-calendar-alt text-blue-500 mr-3"></i>
                        Upcoming Tasks Timeline
                </h3>
                <div class="relative">
                    <!-- Timeline line -->
                        <div class="absolute h-full w-0.5 bg-gradient-to-b from-blue-500 via-purple-500 to-pink-500 left-7 top-0"></div>
                    
                    <!-- Timeline items -->
                    <div class="space-y-6">
                        <?php if (count($tasks_due_soon) > 0): ?>
                                <?php foreach ($tasks_due_soon as $index => $task): 
                                    // Determine time difference for label
                                    $dueDate = new DateTime($task['due_date']);
                                    $today = new DateTime('today');
                                    $interval = $today->diff($dueDate);
                                    $daysRemaining = $interval->days;
                                    
                                    if ($dueDate->format('Y-m-d') === $today->format('Y-m-d')) {
                                        $timeLabel = '<span class="text-red-600 dark:text-red-400 font-medium">Today</span>';
                                        $dotColor = 'bg-red-500';
                                    } elseif ($dueDate->format('Y-m-d') === $today->modify('+1 day')->format('Y-m-d')) {
                                        $timeLabel = '<span class="text-orange-600 dark:text-orange-400 font-medium">Tomorrow</span>';
                                        $dotColor = 'bg-orange-500';
                                    } elseif ($daysRemaining <= 3) {
                                        $timeLabel = '<span class="text-blue-600 dark:text-blue-400 font-medium">In ' . $daysRemaining . ' days</span>';
                                        $dotColor = 'bg-blue-500';
                                    } else {
                                        $timeLabel = '<span class="text-gray-600 dark:text-gray-300 font-medium">' . $dueDate->format('M j, Y') . '</span>';
                                        $dotColor = 'bg-gray-500';
                                    }
                                ?>
                                <div class="flex items-start ml-7 pl-6 relative">
                                    <!-- Timeline dot -->
                                    <div class="absolute w-4 h-4 rounded-full <?php echo $dotColor; ?> left-0 top-1.5 transform -translate-x-2 z-10"></div>
                                    <!-- Task card -->
                                    <div class="bg-white dark:bg-dark-card p-4 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300 border-l-4 border-<?php echo strtotime($task['due_date']) < time() ? 'red' : 'blue'; ?>-400 w-full">
                                        <div class="flex justify-between items-start">
                                            <h4 class="text-lg font-semibold text-gray-800 dark:text-white"><?php echo htmlspecialchars($task['title']); ?></h4>
                                            <div class="flex items-center">
                                                <?php echo $timeLabel; ?>
                                        </div>
                                        </div>
                                        <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                        <?php if ($task['reminder_time']): ?>
                                            <div class="mt-2 text-sm">
                                                <i class="fas fa-bell mr-1 text-blue-500"></i> Reminder: <?php echo date('g:i A', strtotime($task['reminder_time'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        </div>
                                        <div class="mt-3 flex justify-end items-center">
                                            <a href="task.php?filter=task_view&task_id=<?php echo $task['task_id']; ?>" class="text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-800/30 p-1.5 rounded-lg transition-colors duration-200 mr-1" title="View Task">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="includes/pages/edit_task.php?id=<?php echo $task['task_id']; ?>" class="text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-800/30 p-1.5 rounded-lg transition-colors duration-200 mr-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                                <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-calendar-check text-4xl mb-3 text-gray-300 dark:text-gray-600"></i>
                                    <p>No upcoming tasks found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                        
                        <?php if (count($tasks_due_soon) > 0): ?>
                        <div class="mt-6 text-center">
                        <a href="task.php" class="text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300 inline-flex items-center">
                                <span>View All Tasks</span>
                                <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions Section -->
            <div class="bg-white dark:bg-dark-card soft-shadow rounded-xl p-6 mb-8 mt-8">
                <h3 class="text-xl font-semibold text-gray-800 dark:text-dark-text mb-4">
                    <i class="fas fa-bolt text-yellow-500 mr-2"></i> Quick Actions
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="task_form.php" class="flex items-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                        <div class="text-blue-500 dark:text-blue-400 text-xl mr-3">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-800 dark:text-dark-text">Create Task</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Add a new task to your list</p>
                        </div>
                    </a>
                    <a href="calendar.php" class="flex items-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors">
                        <div class="text-purple-500 dark:text-purple-400 text-xl mr-3">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-800 dark:text-dark-text">View Calendar</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">See your tasks in calendar view</p>
                        </div>
                    </a>
                    <a href="task.php?priority=High" class="flex items-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors">
                        <div class="text-red-500 dark:text-red-400 text-xl mr-3">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-800 dark:text-dark-text">High Priority</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Focus on important tasks</p>
                        </div>
                    </a>
                    <a href="archive.php" class="flex items-center p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <div class="text-gray-500 dark:text-gray-400 text-xl mr-3">
                            <i class="fas fa-archive"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-800 dark:text-dark-text">Archives</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">View your archived tasks</p>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Floating Notification Panel -->
            <div id="floatingNotification" class="<?php echo count($tasks_due_soon) > 0 ? '' : 'hidden'; ?> fixed right-4 bottom-24 bg-white dark:bg-dark-card p-4 rounded-xl shadow-lg z-50 w-80 border-l-4 border-blue-500 soft-shadow">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                        <i class="fas fa-bell text-blue-500 dark:text-blue-400 mr-2"></i>
                        Tasks Due in 3 Days
                    </h4>
                    <button id="dismissNotification" class="text-gray-400 hover:text-gray-600 dark:text-gray-300 dark:hover:text-white focus:outline-none rounded-full h-8 w-8 flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <ul id="notificationList" class="space-y-2 max-h-60 overflow-y-auto">
                    <?php if (count($tasks_due_soon) > 0): ?>
                        <?php foreach ($tasks_due_soon as $task): ?>
                            <?php 
                                $dueDate = new DateTime($task['due_date']);
                                $today = new DateTime();
                                $interval = $today->diff($dueDate);
                                $daysRemaining = $interval->days;
                                
                                if ($dueDate->format('Y-m-d') === $today->format('Y-m-d')) {
                                    $dueDateText = '<span class="text-red-600 dark:text-red-400 font-medium">Due today</span>';
                                    $borderClass = 'border-l-3 border-red-500 pl-3';
                                } elseif ($dueDate->format('Y-m-d') === $today->modify('+1 day')->format('Y-m-d')) {
                                    $dueDateText = '<span class="text-orange-600 dark:text-orange-400 font-medium">Due tomorrow</span>';
                                    $borderClass = 'border-l-3 border-orange-500 pl-3';
                                } else {
                                    $dueDateText = '<span class="text-blue-600 dark:text-blue-400 font-medium">Due in ' . $daysRemaining . ' days</span>';
                                    $borderClass = 'border-l-3 border-blue-500 pl-3';
                                }
                            ?>
                            <li class="text-sm text-gray-600 dark:text-gray-300 p-3 border border-gray-100 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200 <?php echo $borderClass; ?>">
                                <div class="font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($task['title']); ?></div>
                                <div class="flex justify-between items-center mt-1">
                                    <?php echo $dueDateText; ?>
                                    <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo date('M j, g:i A', strtotime($task['due_date'])); ?></span>
                                </div>
                                <div class="mt-2 flex justify-end">
                                    <a href="task.php?filter=task_view&task_id=<?php echo $task['task_id']; ?>" class="text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300 text-xs">
                                        View task <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="text-sm text-gray-600 p-2">No tasks due in the next 3 days</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Quick Add Task Button -->
    <a href="task_form.php" class="fixed bottom-8 right-8 group z-40">
        <div class="relative">
            <div class="absolute -inset-0.5 bg-gradient-to-r from-pink-600 to-purple-600 rounded-full blur opacity-75 group-hover:opacity-100 transition duration-1000 group-hover:duration-200"></div>
            <button class="relative flex items-center gap-2 bg-white dark:bg-dark-card px-6 py-3 rounded-full leading-none">
                <i class="fas fa-plus text-lg text-pink-600 dark:text-purple-400 group-hover:rotate-180 transition-transform duration-300"></i>
                <span class="font-semibold bg-gradient-to-r from-pink-600 to-purple-600 bg-clip-text text-transparent">Add New Task</span>
            </button>
        </div>
    </a>

    <!-- Add padding at the bottom to avoid the floating button overlapping content -->
    <div class="pb-24"></div>

    <!-- Profile Edit Modal -->
    <div id="profileModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white dark:bg-dark-card dark:text-dark-text rounded-xl shadow-lg max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-dark-border flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Edit Profile</h3>
                <button onclick="closeProfileModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="profileForm" class="px-6 py-4 space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-dark-text">Username</label>
                    <input type="text" id="username" name="username" class="mt-1 block w-full rounded-md border-gray-300 dark:border-dark-border dark:bg-dark-bg dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>">
                </div>
                <div>
                    <label for="currentPassword" class="block text-sm font-medium text-gray-700 dark:text-dark-text">Current Password</label>
                    <input type="password" id="currentPassword" name="currentPassword" class="mt-1 block w-full rounded-md border-gray-300 dark:border-dark-border dark:bg-dark-bg dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="newPassword" class="block text-sm font-medium text-gray-700 dark:text-dark-text">New Password (leave blank to keep current)</label>
                    <input type="password" id="newPassword" name="newPassword" class="mt-1 block w-full rounded-md border-gray-300 dark:border-dark-border dark:bg-dark-bg dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="confirmPassword" class="block text-sm font-medium text-gray-700 dark:text-dark-text">Confirm New Password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" class="mt-1 block w-full rounded-md border-gray-300 dark:border-dark-border dark:bg-dark-bg dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeProfileModal()" class="px-4 py-2 bg-gray-100 dark:bg-dark-border text-gray-700 dark:text-dark-text rounded hover:bg-gray-200 dark:hover:bg-opacity-70">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        Save Changes
                    </button>
                </div>
            </form>
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

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu toggle
            const menuButton = document.getElementById('menuButton');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (menuButton && sidebar) {
                menuButton.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
                    sidebar.classList.toggle('translate-x-0');
                });
            }

            // Check window size on load and resize
            function checkWindowSize() {
                if (window.innerWidth >= 1024) { // lg breakpoint
                    sidebar.classList.remove('-translate-x-full');
                    sidebar.classList.add('translate-x-0');
                } else {
                    sidebar.classList.add('-translate-x-full');
                    sidebar.classList.remove('translate-x-0');
                }
            }

            // Initial check
            checkWindowSize();

            // Listen for window resize
            window.addEventListener('resize', checkWindowSize);

            // User dropdown toggle
            const userDropdownButton = document.getElementById('userDropdownButton');
            const userDropdown = document.getElementById('userDropdown');
            
            if (userDropdownButton && userDropdown) {
                userDropdownButton.addEventListener('click', () => {
                    userDropdown.classList.toggle('hidden');
                });
            
                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    if (!userDropdownButton.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.classList.add('hidden');
                    }
                });
            }

            // Dark mode initialization
            initDarkMode();
            
            // Profile form submission
            const profileForm = document.getElementById('profileForm');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    updateProfile();
                });
            }

            // Initialize notification visibility
            const dismissNotification = document.getElementById('dismissNotification');
            const floatingNotification = document.getElementById('floatingNotification');
            const notificationBell = document.getElementById('notificationButton');
            
            if (dismissNotification && floatingNotification) {
                dismissNotification.addEventListener('click', () => {
                    floatingNotification.classList.add('hidden');
                });
            }
            
            if (notificationBell && floatingNotification) {
                notificationBell.addEventListener('click', () => {
                    floatingNotification.classList.toggle('hidden');
                });
            }
            
            // Setup confirmation dialog for task actions
            setupTaskActions();
            
            // Update remain_time for today's reminders
            updateRemainTime();
            
            // Initial check for reminders
            checkReminders();
            
            // Set intervals
            setInterval(checkReminders, 30000); // Check every 30 seconds
            setInterval(updateRemainTime, 60000); // Update remain_time every minute
            
            // Dark mode toggle button event
            document.getElementById('darkModeToggle').addEventListener('click', toggleDarkMode);
        });

        // Setup task actions
        function setupTaskActions() {
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

        // Dark mode functions
        function initDarkMode() {
            const darkMode = localStorage.getItem('darkMode') === 'true';
            const html = document.documentElement;
            
            if (darkMode) {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
        }
        
        function toggleDarkMode() {
            const html = document.documentElement;
            const isDark = html.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark);
            
            // Reload page with dark mode parameter
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('dark_mode', isDark);
            window.location.href = currentUrl.toString();
        }
        
        // Profile modal functions
        function openProfileModal() {
            document.getElementById('profileModal').classList.remove('hidden');
            document.getElementById('profileModal').classList.add('flex');
            document.getElementById('userDropdown').classList.add('hidden');
        }
        
        function closeProfileModal() {
            document.getElementById('profileModal').classList.add('hidden');
            document.getElementById('profileModal').classList.remove('flex');
        }
        
        function updateProfile() {
            const username = document.getElementById('username').value;
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // Validate input
            if (!username) {
                alert('Username is required');
                return;
            }
            
            if (!currentPassword) {
                alert('Current password is required');
                return;
            }
            
            if (newPassword && newPassword !== confirmPassword) {
                alert('New passwords do not match');
                return;
            }
            
            // Send update request
            const formData = new FormData();
            formData.append('ajax_action', 'update_profile');
            formData.append('username', username);
            formData.append('current_password', currentPassword);
            
            if (newPassword) {
                formData.append('new_password', newPassword);
            }
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Profile updated successfully');
                    closeProfileModal();
                    // Reload to reflect changes
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to update profile');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Notification functions
        function showNotification(title, dueDate, reminderTime) {
            const notification = document.getElementById('slideInNotification');
            const content = document.getElementById('notificationContent');
            
            content.innerHTML = `
                <div class="font-medium text-gray-800 dark:text-dark-text">${title}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <span><i class="fas fa-calendar-alt mr-1"></i> Due: ${dueDate}</span>
                    ${reminderTime ? `<span class="ml-2"><i class="fas fa-bell mr-1"></i> Reminder: ${reminderTime}</span>` : ''}
                </div>
            `;
            
            notification.classList.add('show');
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
            }, 5000);
        }

        // Logout function
        function logout() {
            // Send AJAX request to logout
            fetch('dashboard.php', {
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

        // Update remain_time for today's reminders
        function updateRemainTime() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'api/update_remain_time.php', true);
            xhr.onload = function() {
                if (this.status === 200) {
                    try {
                        const response = JSON.parse(this.responseText);
                        console.log('Updated remain_time for ' + response.updated_count + ' reminders');
                    } catch (e) {
                        console.error('Error updating remain_time:', e);
                    }
                }
            };
            xhr.send();
        }

        // Check for reminders every 30 seconds
        function checkReminders() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'api/check_reminders.php', true);
            xhr.onload = function() {
                if (this.status === 200) {
                    try {
                        const response = JSON.parse(this.responseText);
                        if (response.success && response.reminders && response.reminders.length > 0) {
                            // Show the first reminder as a slide-in notification
                            const reminder = response.reminders[0];
                            const dueDate = new Date(reminder.due_date).toLocaleDateString('en-US', {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric'
                            });
                            const reminderTime = reminder.reminder_time_formatted ? 
                                new Date(reminder.reminder_datetime).toLocaleTimeString('en-US', {
                                    hour: 'numeric',
                                    minute: '2-digit',
                                    hour12: true
                                }) : null;
                            
                            showNotification(reminder.title, dueDate, reminderTime);
                        }
                    } catch (e) {
                        console.error('Error parsing reminder response:', e);
                    }
                }
            };
            xhr.send();
        }
    </script>
</body>
</html>