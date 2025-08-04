<?php
session_start(); // Start session for admin login state

// Enable error reporting for debugging (REMOVE IN PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the file with admin credentials.
// Assuming var.php is in a 'php' subdirectory relative to admin_login.php
require_once 'php/var.php'; 

// Check if the admin is already logged in, redirect to admin panel if so
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: admin_panel.php'); // Redirect to admin panel if already logged in
    exit();
}

// Initialize error message for display
$error_message = '';

// Retrieve and clear session error message from previous attempts
if (isset($_SESSION['error_message_admin'])) {
    $error_message = $_SESSION['error_message_admin'];
    unset($_SESSION['error_message_admin']);
}

// --- Handle POST request for admin login ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Collect and sanitize input
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // 2. Authenticate against the stored admin credentials from var.php
    // In a real application, you'd fetch the hashed password from a database 'admins' table
    if ($username === $admin_username && password_verify($password, $admin_password_hash)) {
        // Login successful
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;

        // Redirect to the admin control panel
        header("Location: admin_panel.php");
        exit();
    } else {
        // Invalid credentials
        $error_message = "Invalid username or password.";
        $_SESSION['error_message_admin'] = $error_message; // Store error in session for display
        // No redirection needed here, the page will simply reload and display the error
    }
}
?>
<!DOCTYPE html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Admin Login | Job Board</title>
    <meta name="description" content="Admin login page for the job board.">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="site.webmanifest">
    <link rel="shortcut icon" type="image/x-xicon" href="assets/img/jobfavicon.png">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Poppins Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        /* Custom CSS to override/enhance Tailwind */
        :root {
            --primary-pink: #FF3B81;
            --hover-pink: #E03372;
            --blue-primary: #00bcd4;
            --blue-hover: #0097a7;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            /* No background-image here, as the <img> will handle it */
            min-height: 100vh; /* Ensure body takes full viewport height */
            display: flex; /* Use flexbox for centering content */
            flex-direction: column; /* Stack header, main, footer vertically */
            overflow-y: auto; /* Allow scrolling for the entire page if content overflows */
        }
        
        .main-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        nav {
            background-color: #fff; /* Ensure nav has a background */
        }

        /* Styling for the man image to make it full screen background */
        .man-image {
            position: fixed; /* Fixes it to the viewport */
            top: 0;
            left: 0;
            width: 100vw; /* Covers full viewport width */
            height: 100vh; /* Covers full viewport height */
            object-fit: cover; /* Ensures it covers the area without distortion */
            z-index: -1; /* Places it behind all other content */
            background-position: center center; /* Centers the image within its bounds */
            background-repeat: no-repeat; /* Prevents repeating if image is small */
        }

        .login-container {
            flex-grow: 1; /* Allows it to take available space */
            display: flex;
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically */
            padding: 20px 0;
            position: relative;
            z-index: 2; /* Above the man image and other elements */
        }

        .login-box {
            background: rgba(255, 255, 255, 0.1); /* Transparent background */
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 45px 35px;
            border-radius: 20px;
            box-shadow: none; /* Removed box shadow */
            max-width: 500px; /* Adjusted max-width for login form */
            width: 100%;
            animation: fadeIn 1s ease;
            color: #000000;
            margin: 20px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            z-index: 3;
        }

        .login-box h2 {
            text-align: center;
            margin-bottom: 10px;
            font-weight: 700;
            color: #000000;
            font-size: 2.5em;
        }

        .login-box p {
            text-align: center;
            font-size: 16px;
            color: #000000;
            margin-bottom: 30px;
        }

        .form-control {
            padding: 12px 18px;
            border-radius: 12px;
            border: none;
            margin-bottom: 20px;
            background-color: rgba(255, 255, 255, 0.15);
            color: #000000;
            transition: background-color 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            width: 100%;
            box-sizing: border-box;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            height: 50px; /* Consistent height for inputs */
            border: 0.5px solid #0303035b;
        }

        .form-control::placeholder {
            color: #555;
        }

        .form-control:focus {
            border-color: var(--blue-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 188, 212, 0.4);
            background-color: rgba(255, 255, 255, 0.25);
        }

        .password-container {
            position: relative;
            width: 100%;
            margin-bottom: 20px; /* Add margin to match other inputs */
        }
        .password-container .form-control {
            margin-bottom: 0; /* Remove default margin from input inside container */
        }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            font-size: 1.2em; /* Make icon slightly larger */
            transition: color 0.3s ease;
        }
        .toggle-password:hover {
            color: #555;
        }

        .btn-header {
            padding: 10px 24px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-register {
            background-color: var(--primary-pink);
            color: #fff;
            border: 2px solid transparent;
        }

        .btn-register:hover {
            background-color: var(--hover-pink);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }

        .btn-login {
            background-color: transparent;
            color: var(--primary-pink);
            border: 2px solid var(--primary-pink);
        }

        .btn-login:hover {
            background-color: rgba(255, 59, 129, 0.05);
            color: var(--hover-pink);
            border-color: var(--hover-pink);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }

        .btn-form-submit {
            background-color: var(--blue-primary);
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 12px;
            font-weight: 600;
            color: #fff;
            transition: 0.3s ease;
            font-size: 1.1em;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 188, 212, 0.2);
        }

        .btn-form-submit:hover {
            background-color: var(--blue-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 188, 212, 0.4);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Alert styling from worker_signup.php */
        .alert-success {
            background-color: #d4edda; /* green-100 */
            color: #155724; /* green-700 */
            border: 1px solid #badbcc; /* green-400 */
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert-danger {
            background-color: #f8d7da; /* red-100 */
            color: #721c24; /* red-700 */
            border: 1px solid #f5c6cb; /* red-400 */
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert-warning {
            background-color: #fff3cd; /* yellow-100 */
            color: #856404; /* yellow-700 */
            border: 1px solid #ffeeba; /* yellow-400 */
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .login-box {
                padding: 35px 25px;
                max-width: 90%;
            }
            .login-box h2 {
                font-size: 2em;
            }
            .login-box p {
                font-size: 15px;
            }
            .man-image {
                display: block; /* Ensure it's shown for wider screens */
            }
        }

        @media (max-width: 576px) {
            .login-box {
                padding: 25px 18px;
                max-width: 95%;
            }
            .login-box h2 {
                font-size: 1.8em;
            }
            .form-control {
                padding: 10px 15px;
                margin-bottom: 15px;
            }
            .login-box button {
                padding: 10px;
                font-size: 1em;
            }
            .text-link {
                margin-top: 20px;
            }
            .man-image {
                display: none; /* Hide the man image on very small screens to prioritize content */
            }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- Man image for background (now full screen) -->
        <img src="img/man.jpg" alt="Man holding tablet" class="man-image">

        <!-- Navigation Bar (Header) -->
        <nav class="relative z-10 flex items-center justify-between px-6 py-4 bg-white shadow-md">
            <div class="logo">
                <a href="index.html">
                    <img src="img/logo.png" style="height: 50px; width: auto;" alt="JobCrafter Logo">
                </a>
            </div>
            <div class="hidden md:flex items-center space-x-6 text-gray-700 font-medium">
                <a href="index.html" class="hover:text-gray-900 transition-colors">Home</a>
                <a href="about.html" class="hover:text-gray-900 transition-colors">About</a>
                <a href="contact.html" class="hover:text-gray-900 transition-colors">Contact</a>
            </div>

        </nav>

        <section class="login-section login-container">
            <div class="login-box">
                <h2>Admin Login</h2>
                <?php if (!empty($error_message)): ?>
                    <div class="alert-danger" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>
                <p>Login to your admin panel.</p>

                <form action="admin_login.php" method="POST">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <div class="password-container">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <span toggle="#password" class="fa fa-eye field-icon toggle-password" id="togglePassword"></span>
                    </div>
                    
                    <button type="submit" class="btn-form-submit">Login</button>
                </form>
            </div>
        </section>
    </div>

    <!-- Jquery, Popper, Bootstrap JS (placed at the end for faster page load) -->
    <script src="./assets/js/vendor/jquery-1.12.4.min.js"></script>
    <script src="./assets/js/popper.min.js"></script>
    <script src="./assets/js/bootstrap.min.js"></script>
    
    <script>
        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');

        if (togglePassword && passwordField) {
            togglePassword.addEventListener('click', function () {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                this.classList.toggle('fa-eye-slash');
            });
        }

        // Set current year for copyright
        const currentYear = new Date().getFullYear();
        const copyrightElement = document.getElementById('copyright-year');
        if (copyrightElement) {
            copyrightElement.textContent = `Copyright reserved © ${currentYear}`;
        }

        // Hide preloader if you have one (copied from worker_signup.php)
        window.addEventListener('load', function() {
            const preloader = document.getElementById('preloader-active');
            if (preloader) {
                preloader.style.display = 'none';
            }
        });
    </script>
</body>
</html>