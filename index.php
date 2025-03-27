<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>To Do List System</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Soft UI Dependencies -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f8f9fa;
        }
        .soft-shadow {
            box-shadow: 6px 6px 12px #b8b9be, -6px -6px 12px #ffffff;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white soft-shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-2xl font-semibold text-gray-800">To-Do List System</span>
                </div>
                <div class="flex items-center">
                    <a href="auth/auth.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg soft-shadow transition duration-300">
                        Get Started
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">
                To-Do List System
            </h1>
            <p class="text-xl text-gray-600 mb-8">
                Organize your academic life efficiently with our powerful task management tools
            </p>
        </div>

        <!-- Feature Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mt-12">
            <!-- Task Organization -->
            <div class="bg-white p-6 rounded-xl soft-shadow feature-card">
                <div class="text-blue-500 text-3xl mb-4">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Task Organization</h3>
                <p class="text-gray-600">
                    Categorize and prioritize your tasks effectively with our intuitive organization system
                </p>
            </div>

            <!-- Reminders -->
            <div class="bg-white p-6 rounded-xl soft-shadow feature-card">
                <div class="text-blue-500 text-3xl mb-4">
                    <i class="fas fa-bell"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Smart Reminders</h3>
                <p class="text-gray-600">
                    Never miss a deadline with our intelligent reminder system
                </p>
            </div>

            <!-- Progress Tracking -->
            <div class="bg-white p-6 rounded-xl soft-shadow feature-card">
                <div class="text-blue-500 text-3xl mb-4">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Progress Tracking</h3>
                <p class="text-gray-600">
                    Monitor your progress with detailed analytics and visual dashboards
                </p>
            </div>

            <!-- Filtering & Sorting -->
            <div class="bg-white p-6 rounded-xl soft-shadow feature-card">
                <div class="text-blue-500 text-3xl mb-4">
                    <i class="fas fa-filter"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Advanced Filtering</h3>
                <p class="text-gray-600">
                    Find tasks quickly with powerful filtering and sorting options
                </p>
            </div>

            <!-- Calendar Integration -->
            <div class="bg-white p-6 rounded-xl soft-shadow feature-card">
                <div class="text-blue-500 text-3xl mb-4">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Calendar View</h3>
                <p class="text-gray-600">
                    Visualize your tasks in an intuitive calendar interface
                </p>
            </div>

            <!-- Get Started -->
            <div class="bg-white p-6 rounded-xl soft-shadow feature-card">
                <div class="text-blue-500 text-3xl mb-4">
                    <i class="fas fa-rocket"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Get Started Now</h3>
                <p class="text-gray-600">
                    Join now and take control of your academic tasks
                </p>
                <a href="auth/auth.php" class="inline-block mt-4 bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg soft-shadow transition duration-300">
                    Sign Up
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white soft-shadow mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="text-center text-gray-600">
                © 2025 To-Do List System. All rights reserved.
            </div>
        </div>
    </footer>
</body>
</html>