<?php
require_once('../config/database.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

function sendResetEmail($email, $token) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tengwei32@gmail.com'; 
        $mail->Password = 'qlbc nakp xhtx lnuz'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('your-email@gmail.com', 'To Do List System');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?email=" . urlencode($email);
        $mail->Body = "Please click the following link to reset your password: <a href='$resetLink'>Reset Password</a>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'forgot_password') {
            $email = trim($_POST['email']);
            
            $sql = "SELECT user_id FROM users WHERE email = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $user_id);
                    mysqli_stmt_fetch($stmt);
                    
                    if (sendResetEmail($email, "")) {
                        $success = "Password reset instructions have been sent to your email.";
                    } else {
                        $error = "Error sending reset email. Please try again later.";
                    }
                } else {
                    $error = "No account found with that email address.";
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($_POST['action'] === 'reset_password') {

        }
        if ($_POST['action'] === 'login') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            
            $sql = "SELECT user_id, username, password FROM users WHERE username = ?";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $username);
                
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    
                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        mysqli_stmt_bind_result($stmt, $user_id, $username, $hashed_password);
                        if (mysqli_stmt_fetch($stmt)) {
                            if (password_verify($password, $hashed_password)) {
                                $_SESSION['user_id'] = $user_id;
                                $_SESSION['username'] = $username;
                                header("location: ../dashboard.php");
                                exit();
                            } else {
                                $error = "Invalid username or password.";
                            }
                        }
                    } else {
                        $error = "Invalid username or password.";
                    }
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($_POST['action'] === 'register') {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($password !== $confirm_password) {
                $error = "Passwords do not match.";
            } else {
                // Check username
                $sql = "SELECT user_id FROM users WHERE username = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "s", $username);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_store_result($stmt);
                    
                    if (mysqli_stmt_num_rows($stmt) > 0) {
                        $error = "This username is already taken.";
                    }
                    mysqli_stmt_close($stmt);
                }
                
                // Check email
                if (empty($error)) {
                    $sql = "SELECT user_id FROM users WHERE email = ?";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt, "s", $email);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_store_result($stmt);
                        
                        if (mysqli_stmt_num_rows($stmt) > 0) {
                            $error = "This email is already registered.";
                        }
                        mysqli_stmt_close($stmt);
                    }
                }
                
                // Register user
                if (empty($error)) {
                    $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        mysqli_stmt_bind_param($stmt, "sss", $username, $email, $hashed_password);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "Registration successful! You can now log in.";
                        } else {
                            $error = "Something went wrong. Please try again later.";
                        }
                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication - Student Task Management System</title>
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
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-800">Welcome to To-Do List System</h2>
                <p class="text-gray-600 mt-2">Sign in or create an account</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $success; ?></span>
                </div>
            <?php endif; ?>

            <!-- Auth Tabs -->
            <div class="flex mb-6" role="tablist">
                <button onclick="switchTab('login')" id="login-tab" class="flex-1 py-2 px-4 text-center border-b-2 focus:outline-none" role="tab">
                    Sign In
                </button>
                <button onclick="switchTab('register')" id="register-tab" class="flex-1 py-2 px-4 text-center border-b-2 focus:outline-none" role="tab">
                    Create Account
                </button>
                <button onclick="switchTab('forgot')" id="forgot-tab" class="flex-1 py-2 px-4 text-center border-b-2 focus:outline-none" role="tab">
                    Reset Password
                </button>
            </div>

            <!-- Login Form -->
            <form id="login-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6">
                <input type="hidden" name="action" value="login">
                
                <div>
                    <label for="login-username" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                    <input type="text" id="login-username" name="username" required
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="login-password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                    <input type="password" id="login-password" name="password" required
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <button type="submit"
                    class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300 soft-shadow">
                    Sign In
                </button>
            </form>

            <!-- Register Form -->
            <form id="register-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6 hidden">
                <input type="hidden" name="action" value="register">
                
                <div>
                    <label for="register-username" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                    <input type="text" id="register-username" name="username" required
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="register-email" class="block text-gray-700 text-sm font-semibold mb-2">Email</label>
                    <input type="email" id="register-email" name="email" required
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="register-password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                    <input type="password" id="register-password" name="password" required
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="register-confirm-password" class="block text-gray-700 text-sm font-semibold mb-2">Confirm Password</label>
                    <input type="password" id="register-confirm-password" name="confirm_password" required
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <button type="submit"
                    class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300 soft-shadow">
                    Create Account
                </button>
            </form>

            <!-- Forgot Password Form -->
            <form id="forgot-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6 hidden">
                <input type="hidden" name="action" value="forgot_password">
                
                <div>
                    <label for="forgot-email" class="block text-gray-700 text-sm font-semibold mb-2">Email Address</label>
                    <input type="email" id="forgot-email" name="email" required
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <button type="submit"
                    class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300 soft-shadow">
                    Send Reset Link
                </button>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const loginTab = document.getElementById('login-tab');
            const registerTab = document.getElementById('register-tab');
            const forgotTab = document.getElementById('forgot-tab');
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');
            const forgotForm = document.getElementById('forgot-form');

            // Hide all forms first
            loginForm.classList.add('hidden');
            registerForm.classList.add('hidden');
            forgotForm.classList.add('hidden');

            // Reset all tabs
            loginTab.classList.remove('border-blue-500', 'text-blue-600');
            registerTab.classList.remove('border-blue-500', 'text-blue-600');
            forgotTab.classList.remove('border-blue-500', 'text-blue-600');

            // Show selected form and highlight tab
            if (tab === 'login') {
                loginForm.classList.remove('hidden');
                loginTab.classList.add('border-blue-500', 'text-blue-600');
            } else if (tab === 'register') {
                registerForm.classList.remove('hidden');
                registerTab.classList.add('border-blue-500', 'text-blue-600');
            } else if (tab === 'forgot') {
                forgotForm.classList.remove('hidden');
                forgotTab.classList.add('border-blue-500', 'text-blue-600');
            }
        }

        // Initialize tabs
        document.addEventListener('DOMContentLoaded', () => {
            switchTab('login');
        });
    </script>
</body>
</html>