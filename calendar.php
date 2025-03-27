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

// Fetch all tasks for the calendar
$tasks = [];
$sql = "SELECT * FROM tasks WHERE user_id = ? AND is_archived = 0";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $tasks[] = [
            'id' => $row['task_id'],
            'title' => $row['title'],
            'start' => $row['due_date'],
            'description' => $row['description'],
            'priority' => $row['priority'],
            'category' => $row['category'],
            'status' => $row['status'],
            'backgroundColor' => $row['priority'] === 'High' ? '#FCA5A5' : 
                               ($row['priority'] === 'Medium' ? '#FCD34D' : '#86EFAC'),
            'borderColor' => $row['priority'] === 'High' ? '#DC2626' : 
                           ($row['priority'] === 'Medium' ? '#D97706' : '#059669'),
            'textColor' => '#1F2937'
        ];
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo $dark_mode === 'true' ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - To-Do List System</title>
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
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <!-- Add SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        
        /* Calendar Customization */
        .fc {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .dark .fc {
            background: #161626;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
        }
        .fc .fc-toolbar-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1F2937;
        }
        .dark .fc .fc-toolbar-title {
            color: #e2e8f0;
        }
        .fc .fc-button {
            background-color: #3B82F6;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .fc .fc-button:hover {
            background-color: #2563EB;
            transform: translateY(-1px);
        }
        .fc .fc-button:active {
            transform: translateY(0);
        }
        .fc .fc-day-today {
            background-color: #EFF6FF !important;
        }
        .dark .fc .fc-day-today {
            background-color: #1e3a8a !important;
        }
        .fc .fc-event {
            border-radius: 6px;
            padding: 2px 4px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        .fc .fc-event:hover {
            transform: scale(1.02);
        }
        .fc .fc-daygrid-day-number {
            color: #4B5563;
            font-weight: 500;
        }
        .dark .fc .fc-daygrid-day-number {
            color: #cbd5e1;
        }
        .fc .fc-col-header-cell-cushion {
            color: #1F2937;
            font-weight: 600;
        }
        .dark .fc .fc-col-header-cell-cushion {
            color: #e2e8f0;
        }
        /* Custom navigation buttons */
        .fc .fc-prev-button, .fc .fc-next-button {
            padding: 8px 16px;
            font-weight: 500;
            font-size: 14px;
            min-width: 120px;
            text-transform: none;
        }
        .fc .fc-button-primary {
            background-color: #3B82F6;
            border-color: #3B82F6;
        }
        .dark .fc .fc-button-primary {
            background-color: #2563eb;
            border-color: #2563eb;
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: #2563EB;
            border-color: #2563EB;
        }
        .dark .fc .fc-button-primary:not(:disabled).fc-button-active,
        .dark .fc .fc-button-primary:not(:disabled):active {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
        }
        /* Override the icon-only buttons with text */
        .fc-direction-ltr .fc-button-group > .fc-button:not(:last-child) {
            margin-right: 8px;
        }
        .fc-prev-button .fc-icon,
        .fc-next-button .fc-icon {
            display: none;
        }
        .fc-prev-button::before {
            content: "← ";
            margin-right: 4px;
        }
        .fc-prev-button::after {
            content: "Last Month";
            font-weight: 500;
        }
        .fc-next-button::before {
            content: "Next Month";
            font-weight: 500;
        }
        .fc-next-button::after {
            content: " →";
            margin-left: 4px;
        }
        .fc .fc-today-button {
            border-radius: 0;
            margin: 0 !important;
        }
        
        /* Responsive styles for calendar buttons */
        @media (max-width: 640px) {
            .fc .fc-toolbar {
                flex-direction: column;
                gap: 8px;
            }
            .fc .fc-toolbar-chunk {
                display: flex;
                justify-content: center;
                width: 100%;
            }
            .fc .fc-prev-button, .fc .fc-next-button {
                padding: 4px 8px;
                min-width: auto;
                width: 120px;
            }
            .fc-prev-button::before {
                content: "←";
                margin-right: 4px;
            }
            .fc-prev-button::after {
                content: "Last";
                font-weight: 500;
            }
            .fc-next-button::before {
                content: "Next";
                font-weight: 500;
            }
            .fc-next-button::after {
                content: "→";
                margin-left: 4px;
            }
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
                    <span class="ml-3 font-medium dark:hidden">Dark Mode</span>
                    <span class="ml-3 font-medium hidden dark:block">Light Mode</span>
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
    <div class="lg:ml-64 p-8">
        <!-- Mobile Menu Button -->
        <button id="menuButton" class="lg:hidden fixed top-4 left-4 z-50 bg-white dark:bg-gray-800 p-2 rounded-lg shadow-lg">
            <i class="fas fa-bars text-xl dark:text-white"></i>
        </button>

        <!-- Calendar Container -->
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Calendar View</h1>
                <div class="flex flex-wrap gap-4">
                    <div id="calendarViewControls" class="bg-white dark:bg-gray-800 rounded-lg flex shadow-sm">
                        <button id="monthViewBtn" class="px-4 py-2 text-gray-700 dark:text-gray-200 font-medium rounded-l-lg bg-blue-100 dark:bg-blue-900" onclick="changeCalendarView('dayGridMonth')">
                            Month
                        </button>
                        <button id="weekViewBtn" class="px-4 py-2 text-gray-700 dark:text-gray-200 font-medium" onclick="changeCalendarView('timeGridWeek')">
                            Week
                        </button>
                        <button id="dayViewBtn" class="px-4 py-2 text-gray-700 dark:text-gray-200 font-medium rounded-r-lg" onclick="changeCalendarView('timeGridDay')">
                            Day
                        </button>
                    </div>
                    <button onclick="showQuickAddTask()" class="bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white px-4 py-2 rounded-lg transition-colors duration-300 flex items-center gap-2 shadow-md">
                        <i class="fas fa-plus"></i>
                        <span>Quick Add Task</span>
                    </button>
                </div>
            </div>
            <div id="calendar" class="gradient-border"></div>
        </div>
    </div>

    <!-- Task Details Modal -->
    <div id="taskModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 dark:bg-gray-900 dark:bg-opacity-60 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white dark:bg-gray-800 dark:border-gray-700">
            <div class="mt-3">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white" id="modalTitle"></h3>
                <div class="mt-2 space-y-3 dark:text-gray-300" id="modalContent"></div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button onclick="editTask()" class="bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white px-4 py-2 rounded-lg transition-colors duration-300 shadow-md">
                        Edit Task
                    </button>
                    <button onclick="closeTaskModal()" class="bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Add Task Modal -->
    <div id="quickAddModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 dark:bg-gray-900 dark:bg-opacity-60 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white dark:bg-gray-800 dark:border-gray-700">
            <div class="mt-3">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Quick Add Task</h3>
                <form id="quickAddForm" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                        <input type="text" id="quickTitle" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea id="quickDescription" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" rows="3"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Due Date</label>
                        <input type="datetime-local" id="quickDueDate" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Priority</label>
                        <select id="quickPriority" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                        <select id="quickCategory" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="Assignments">Assignments</option>
                            <option value="Discussions">Discussions</option>
                            <option value="Club Activities">Club Activities</option>
                            <option value="Examinations">Examinations</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="submit" class="bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white px-4 py-2 rounded-lg transition-colors duration-300 shadow-md">
                            Add Task
                        </button>
                        <button type="button" onclick="closeQuickAddModal()" class="bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                    </div>
                </form>
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
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dark mode
            initializeDarkMode();
            
            // Add event listener for dark mode toggle
            document.getElementById('darkModeToggle').addEventListener('click', toggleDarkMode);
            
            // Initialize Calendar
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev',
                    center: 'title',
                    right: 'next'
                },
                events: <?php echo json_encode($tasks); ?>,
                eventClick: function(info) {
                    showTaskDetails(info.event);
                },
                dateClick: function(info) {
                    showQuickAddTask(info.dateStr);
                },
                eventDidMount: function(info) {
                    // Add tooltip
                    info.el.title = `${info.event.title}\nPriority: ${info.event.extendedProps.priority}\nCategory: ${info.event.extendedProps.category}\nStatus: ${info.event.extendedProps.status}`;
                }
            });
            calendar.render();

            // Save calendar instance to window for access from other functions
            window.calendarInstance = calendar;

            // Mobile menu toggle
            const menuButton = document.getElementById('menuButton');
            const sidebar = document.getElementById('sidebar');
            
            menuButton.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth < 1024 && 
                    !sidebar.contains(e.target) && 
                    !menuButton.contains(e.target) && 
                    !sidebar.classList.contains('-translate-x-full')) {
                    sidebar.classList.add('-translate-x-full');
                }
            });

            // Quick Add Form Submit
            document.getElementById('quickAddForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitQuickAddTask();
            });
        });

        function showTaskDetails(event) {
            const modal = document.getElementById('taskModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');

            title.textContent = event.title;
            content.innerHTML = `
                <p class="text-gray-600 dark:text-gray-300"><i class="fas fa-align-left mr-2"></i>${event.extendedProps.description || 'No description'}</p>
                <p class="text-gray-600 dark:text-gray-300"><i class="fas fa-calendar mr-2"></i>${new Date(event.start).toLocaleString()}</p>
                <p class="text-gray-600 dark:text-gray-300"><i class="fas fa-flag mr-2"></i>Priority: ${event.extendedProps.priority}</p>
                <p class="text-gray-600 dark:text-gray-300"><i class="fas fa-folder mr-2"></i>Category: ${event.extendedProps.category}</p>
                <p class="text-gray-600 dark:text-gray-300"><i class="fas fa-info-circle mr-2"></i>Status: ${event.extendedProps.status}</p>
            `;

            modal.classList.remove('hidden');
            window.currentTaskId = event.id;
        }

        function closeTaskModal() {
            document.getElementById('taskModal').classList.add('hidden');
        }

        function editTask() {
            if (window.currentTaskId) {
                window.location.href = `includes/pages/edit_task.php?id=${window.currentTaskId}`;
            }
        }

        function showQuickAddTask(dateStr = '') {
            const modal = document.getElementById('quickAddModal');
            const dueDateInput = document.getElementById('quickDueDate');
            
            if (dateStr) {
                // If a date was clicked, set it as the due date
                dueDateInput.value = dateStr + 'T12:00';
            } else {
                // Otherwise, set current date/time
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                dueDateInput.value = now.toISOString().slice(0,16);
            }
            
            modal.classList.remove('hidden');
        }

        function closeQuickAddModal() {
            document.getElementById('quickAddModal').classList.add('hidden');
        }

        function submitQuickAddTask() {
            const formData = new FormData();
            formData.append('title', document.getElementById('quickTitle').value);
            formData.append('description', document.getElementById('quickDescription').value);
            formData.append('due_date', document.getElementById('quickDueDate').value);
            formData.append('priority', document.getElementById('quickPriority').value);
            formData.append('category', document.getElementById('quickCategory').value);
            formData.append('ajax_action', 'quick_add_task');

            fetch('includes/pages/task_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Task Added!',
                        text: 'The task has been successfully added.',
                        showConfirmButton: false,
                        timer: 1500,
                        background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#ffffff',
                        color: document.documentElement.classList.contains('dark') ? '#f3f4f6' : '#1f2937'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: data.message || 'Something went wrong!',
                        background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#ffffff',
                        color: document.documentElement.classList.contains('dark') ? '#f3f4f6' : '#1f2937'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Something went wrong!',
                    background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#ffffff',
                    color: document.documentElement.classList.contains('dark') ? '#f3f4f6' : '#1f2937'
                });
            });
        }

        function logout() {
            // Destroy session serverside
            fetch('calendar.php', {
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
        
        function changeCalendarView(viewName) {
            // Change calendar view
            if (window.calendarInstance) {
                window.calendarInstance.changeView(viewName);
                
                // Update button active states
                const buttons = {
                    'dayGridMonth': document.getElementById('monthViewBtn'),
                    'timeGridWeek': document.getElementById('weekViewBtn'),
                    'timeGridDay': document.getElementById('dayViewBtn')
                };
                
                // Remove active class from all buttons
                Object.values(buttons).forEach(btn => {
                    btn.classList.remove('bg-blue-100', 'dark:bg-blue-900');
                });
                
                // Add active class to selected button
                if (buttons[viewName]) {
                    buttons[viewName].classList.add('bg-blue-100', 'dark:bg-blue-900');
                }
            }
        }
    </script>
</body>
</html>