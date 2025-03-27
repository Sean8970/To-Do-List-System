<?php
require_once('../config/database.php');

// Only start session if one isn't already active
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

// Check if user is coming from email link with email parameter
if (!isset($_GET['email'])) {
    header("Location: ../index.php");
    exit;
}

$email = $_GET['email'];

// Validate email exists in database
$sql = "SELECT user_id, username FROM users WHERE email = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        // Email not found in database
        $error = "Invalid email address. Please request a new password reset.";
    } else {
        $user_id = $row['user_id'];
        $username = $row['username'];
    }
    mysqli_stmt_close($stmt);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_POST['email'];
    
    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Validate email again
        $sql = "SELECT user_id FROM users WHERE email = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                $user_id = $row['user_id'];
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
                if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $user_id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        $success = "Password has been reset successfully. You can now login.";
                    } else {
                        $error = "Error updating password. Please try again.";
                    }
                    mysqli_stmt_close($update_stmt);
                }
            } else {
                $error = "Invalid email address. Please request a new password reset.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - To-Do List System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-image: url('https://images.unsplash.com/photo-1432821596592-e2c18b78144f?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .auth-container {
            backdrop-filter: blur(8px);
            background-color: rgba(255, 255, 255, 0.9);
        }
        .soft-shadow {
            box-shadow: 6px 6px 12px rgba(0, 0, 0, 0.1), -6px -6px 12px rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <!-- Back to Index Button -->
    <div class="fixed top-4 left-4">
        <a href="../index.php" class="flex items-center gap-2 bg-white text-blue-600 px-4 py-2 rounded-lg soft-shadow hover:bg-blue-50 transition duration-300">
            <i class="fas fa-arrow-left"></i>
            Back to Home
        </a>
    </div>

    <div class="w-full max-w-md">
        <div class="auth-container rounded-xl p-8 soft-shadow">
            <h2 class="text-2xl font-bold text-center mb-6">Reset Your Password</h2>
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $success; ?></span>
                    <div class="mt-4 text-center">
                        <a href="auth.php" class="text-blue-600 hover:underline">Go to Login</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if (isset($username)): ?>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?email=" . urlencode($email)); ?>" class="space-y-6">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($username); ?>" readonly
                            class="w-full px-4 py-2 rounded-lg border border-gray-300 bg-gray-100 cursor-not-allowed">
                    </div>

                    <div>
                        <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">New Password</label>
                        <input type="password" id="password" name="password" required
                            class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-gray-700 text-sm font-semibold mb-2">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                            class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <button type="submit"
                        class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300 soft-shadow">
                        Reset Password
                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>