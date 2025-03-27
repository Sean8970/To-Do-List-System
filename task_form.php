<?php
require_once('config/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/auth.php");
    exit();
}

$task = null;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'add';
$categories = ['Assignments', 'Discussions', 'Club Activities', 'Examinations', 'Others'];

// If task ID is provided, fetch task data for editing
if (isset($_GET['id']) || isset($_GET['task_id'])) {
    $task_id = isset($_GET['id']) ? $_GET['id'] : $_GET['task_id'];
    
    $sql = "SELECT * FROM tasks WHERE task_id = ? AND user_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $task_id, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $task = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$task) {
            header("Location: dashboard.php");
            exit();
        }
    }
}

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    $response = ['success' => false, 'message' => '', 'redirect' => ''];
    
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $priority = $_POST['priority'];
    $category = trim($_POST['category']);
    $reminder_time = !empty($_POST['reminder_time']) ? $_POST['reminder_time'] : null;
    
    if (empty($title)) {
        $response['message'] = "Title is required";
    } else {
        if ($_POST['ajax_action'] === 'add') {
            $sql = "INSERT INTO tasks (user_id, title, description, due_date, priority, category, reminder_time, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "issssss", $_SESSION['user_id'], $title, $description, $due_date, $priority, $category, $reminder_time);
                
                if (mysqli_stmt_execute($stmt)) {
                    $task_id = mysqli_insert_id($conn);
                    
                    // Add reminder if specified
                    if ($reminder_time) {
                        $reminder_sql = "INSERT INTO reminders (task_id, reminder_time) VALUES (?, ?)";
                        if ($reminder_stmt = mysqli_prepare($conn, $reminder_sql)) {
                            mysqli_stmt_bind_param($reminder_stmt, "is", $task_id, $reminder_time);
                            mysqli_stmt_execute($reminder_stmt);
                            mysqli_stmt_close($reminder_stmt);
                        }
                    }
                    
                    $response['success'] = true;
                    $response['message'] = "Task added successfully!";
                    $response['redirect'] = "dashboard.php";
                }
                mysqli_stmt_close($stmt);
            }
        } else if ($_POST['ajax_action'] === 'edit') {
            $task_id = $_POST['task_id'];
            $sql = "UPDATE tasks SET title = ?, description = ?, due_date = ?, priority = ?, category = ?, reminder_time = ? 
                    WHERE task_id = ? AND user_id = ?";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssssssii", $title, $description, $due_date, $priority, $category, $reminder_time, $task_id, $_SESSION['user_id']);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Update or create reminder
                    if ($reminder_time) {
                        $reminder_sql = "INSERT INTO reminders (task_id, reminder_time) VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE reminder_time = VALUES(reminder_time)";
                        if ($reminder_stmt = mysqli_prepare($conn, $reminder_sql)) {
                            mysqli_stmt_bind_param($reminder_stmt, "is", $task_id, $reminder_time);
                            mysqli_stmt_execute($reminder_stmt);
                            mysqli_stmt_close($reminder_stmt);
                        }
                    }
                    
                    $response['success'] = true;
                    $response['message'] = "Task updated successfully!";
                    $response['redirect'] = "dashboard.php";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
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
    
    // If no errors, insert the task
    if (empty($errors)) {
        // Combine date and time
        $due_datetime = $due_date . ' ' . $due_time;
        
        $sql = "INSERT INTO tasks (user_id, title, description, due_date, priority, category, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
                
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "issssss", 
                $_SESSION['user_id'], 
                $title, 
                $description, 
                $due_datetime, 
                $priority, 
                $category,
                $status
            );
            
            if (mysqli_stmt_execute($stmt)) {
                // Redirect to tasks page after successful submission
                header("Location: task.php");
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

// Get categories for dropdown
// REMOVED: The database query that was fetching categories from existing tasks
// We're now using only the predefined list that was set at the top of the file:
// $categories = ['Assignments', 'Discussions', 'Club Activities', 'Examinations', 'Others'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode === 'add' ? 'Add' : 'Edit'; ?> Task - To-Do List System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f8f9fa;
        }
        .soft-shadow {
            box-shadow: 6px 6px 12px #b8b9be, -6px -6px 12px #ffffff;
        }
        /* Toast position styles */
        .swal2-toast {
            margin-top: 1rem !important;
        }
    </style>
</head>
<body class="min-h-screen py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl p-8 soft-shadow">
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-2xl font-bold text-gray-800">
                    <?php echo $mode === 'add' ? 'Add New Task' : 'Edit Task'; ?>
                </h1>
                <div class="flex items-center gap-4">
                    <?php if ($mode === 'add'): ?>
                        <button type="button" id="quickAddBtn" 
                            class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition duration-300">
                            <i class="fas fa-magic mr-2"></i> Quick Fill
                        </button>
                    <?php endif; ?>
                    <a href="dashboard.php" class="text-blue-500 hover:text-blue-600">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($mode === 'add'): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                    <button type="button" onclick="applyTemplate('assignment')"
                        class="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-300 text-left">
                        <div class="flex items-center gap-3 mb-2">
                            <i class="fas fa-book text-blue-500"></i>
                            <span class="font-semibold">Assignment Template</span>
                        </div>
                        <p class="text-sm text-gray-600">For homework, projects, and assignments</p>
                    </button>

                    <button type="button" onclick="applyTemplate('discussion')"
                        class="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-300 text-left">
                        <div class="flex items-center gap-3 mb-2">
                            <i class="fas fa-comments text-green-500"></i>
                            <span class="font-semibold">Discussion Template</span>
                        </div>
                        <p class="text-sm text-gray-600">For forum posts and group discussions</p>
                    </button>

                    <button type="button" onclick="applyTemplate('exam')"
                        class="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-300 text-left">
                        <div class="flex items-center gap-3 mb-2">
                            <i class="fas fa-file-alt text-red-500"></i>
                            <span class="font-semibold">Exam Template</span>
                        </div>
                        <p class="text-sm text-gray-600">For tests, quizzes, and examinations</p>
                    </button>

                    <button type="button" onclick="applyTemplate('club')"
                        class="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-300 text-left">
                        <div class="flex items-center gap-3 mb-2">
                            <i class="fas fa-users text-purple-500"></i>
                            <span class="font-semibold">Club Activity Template</span>
                        </div>
                        <p class="text-sm text-gray-600">For club meetings and activities</p>
                    </button>

                    <button type="button" onclick="applyTemplate('presentation')"
                        class="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-300 text-left">
                        <div class="flex items-center gap-3 mb-2">
                            <i class="fas fa-presentation text-orange-500"></i>
                            <span class="font-semibold">Presentation Template</span>
                        </div>
                        <p class="text-sm text-gray-600">For class presentations and speeches</p>
                    </button>
                </div>
            <?php endif; ?>

            <form id="taskForm" class="space-y-6">
                <input type="hidden" name="ajax_action" value="<?php echo $mode; ?>">
                <?php if ($mode === 'edit'): ?>
                    <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                <?php endif; ?>

                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                    <input type="text" id="title" name="title" required
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Enter task title"
                        value="<?php echo $mode === 'edit' ? htmlspecialchars($task['title']) : ''; ?>">
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="description" name="description" rows="4"
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Enter task description"><?php echo $mode === 'edit' ? htmlspecialchars($task['description']) : ''; ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                        <input type="datetime-local" id="due_date" name="due_date" required
                            class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $mode === 'edit' ? date('Y-m-d\TH:i', strtotime($task['due_date'])) : ''; ?>">
                    </div>

                    <div>
                        <label for="reminder_time" class="block text-sm font-medium text-gray-700 mb-2">Custom Reminder Time</label>
                        <input type="datetime-local" id="reminder_time" name="reminder_time"
                            class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?php echo ($mode === 'edit' && $task['reminder_time']) ? date('Y-m-d\TH:i', strtotime($task['reminder_time'])) : ''; ?>">
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select id="category" name="category"
                            class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>" 
                                    <?php echo ($mode === 'edit' && $task['category'] === $cat) ? 'selected' : ''; ?>>
                                    <?php echo $cat; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                        <div class="flex gap-4">
                            <?php foreach (['Low', 'Medium', 'High'] as $priority): ?>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="priority" value="<?php echo $priority; ?>"
                                        <?php echo ($mode === 'edit' && $task['priority'] === $priority) || 
                                                  ($mode === 'add' && $priority === 'Medium') ? 'checked' : ''; ?>>
                                    <span><?php echo $priority; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-4">
                    <a href="dashboard.php" 
                        class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition duration-300">
                        Cancel
                    </a>
                    <button type="submit"
                        class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-300 soft-shadow">
                        <?php echo $mode === 'add' ? 'Add Task' : 'Save Changes'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Set minimum date for due date and reminder time to today
        const today = new Date().toISOString().slice(0, 16);
        document.getElementById('due_date').min = today;
        document.getElementById('reminder_time').min = today;

        // Quick Add button functionality
        const quickAddBtn = document.getElementById('quickAddBtn');
        if (quickAddBtn) {
            quickAddBtn.addEventListener('click', () => {
                // Get current date/time
                const now = new Date();
                
                // Set due date to tomorrow at current time
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                
                // Fill the form with test data
                document.getElementById('title').value = 'Test Task ' + now.toLocaleTimeString();
                document.getElementById('description').value = 'This is a test task created on ' + now.toLocaleString() + '\n\nTest Details:\n- Priority: High\n- Category: Assignments\n- Due: Tomorrow';
                document.getElementById('due_date').value = tomorrow.toISOString().slice(0, 16);
                document.getElementById('category').value = 'Assignments';
                document.querySelector('input[value="High"]').checked = true;
            });
        }

        // Form submission with AJAX and SweetAlert2
        document.getElementById('taskForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            try {
                const formData = new FormData(e.target);
                const response = await fetch('task_form.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer)
                            toast.addEventListener('mouseleave', Swal.resumeTimer)
                        }
                    });

                    Toast.fire({
                        icon: 'success',
                        title: data.message
                    });
                    
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: data.message || "An error occurred",
                        toast: true,
                        position: 'top',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: "An error occurred while saving the task",
                    toast: true,
                    position: 'top',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        });

        // Validate reminder time is before due date
        document.getElementById('reminder_time').addEventListener('change', (e) => {
            const reminderTime = new Date(e.target.value);
            const dueDate = new Date(document.getElementById('due_date').value);
            
            if (reminderTime >= dueDate) {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: "Reminder time must be before the due date",
                    toast: true,
                    position: 'top',
                    showConfirmButton: false,
                    timer: 3000
                });
                e.target.value = '';
            }
        });

        // Update reminder time min when due date changes
        document.getElementById('due_date').addEventListener('change', (e) => {
            const reminderInput = document.getElementById('reminder_time');
            const reminderTime = new Date(reminderInput.value);
            const dueDate = new Date(e.target.value);
            
            if (reminderInput.value && reminderTime >= dueDate) {
                reminderInput.value = '';
            }
        });

        // Template functionality
        function applyTemplate(type) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const tomorrowStr = tomorrow.toISOString().slice(0, 16);
            
            const templates = {
                assignment: {
                    title: 'New Assignment',
                    description: 'Assignment Details:\n- Due Date:\n- Requirements:\n- Resources needed:\n- Submission format:',
                    category: 'Assignments',
                    priority: 'High',
                    dueDate: tomorrowStr
                },
                discussion: {
                    title: 'Discussion Post',
                    description: 'Discussion Topic:\n- Main points to cover:\n- References needed:\n- Word count requirement:',
                    category: 'Discussions',
                    priority: 'Medium',
                    dueDate: tomorrowStr
                },
                exam: {
                    title: 'Upcoming Exam',
                    description: 'Exam Details:\n- Topics covered:\n- Study materials:\n- Exam format:\n- Location:',
                    category: 'Examinations',
                    priority: 'High',
                    dueDate: tomorrowStr
                },
                club: {
                    title: 'Club Meeting',
                    description: 'Meeting Details:\n- Agenda:\n- Location:\n- Required materials:\n- Notes:',
                    category: 'Club Activities',
                    priority: 'Medium',
                    dueDate: tomorrowStr
                },
                presentation: {
                    title: 'Class Presentation',
                    description: 'Presentation Details:\n- Topic:\n- Duration:\n- Required visuals:\n- Key points to cover:',
                    category: 'Assignments',
                    priority: 'High',
                    dueDate: tomorrowStr
                }
            };

            const template = templates[type];
            document.getElementById('title').value = template.title;
            document.getElementById('description').value = template.description;
            
            // Fix for category dropdown - use querySelector to find the correct option
            const categoryDropdown = document.getElementById('category');
            
            // First try to find an exact match
            let categoryOption = Array.from(categoryDropdown.options).find(option => 
                option.value === template.category);
                
            // If no exact match, try case-insensitive match
            if (!categoryOption) {
                categoryOption = Array.from(categoryDropdown.options).find(option => 
                    option.value.toLowerCase() === template.category.toLowerCase());
            }
            
            // If we found a matching option, select it
            if (categoryOption) {
                categoryDropdown.value = categoryOption.value;
            } else {
                // If no match found, select the first option as fallback
                if (categoryDropdown.options.length > 0) {
                    categoryDropdown.selectedIndex = 0;
                }
            }
            
            document.getElementById('due_date').value = template.dueDate;
            document.querySelector(`input[value="${template.priority}"]`).checked = true;
        }
    </script>
</body>
</html>