<?php
session_start();
require_once 'php/db_connect.php'; // Make sure this path is correct based on your file structure

// If company is already logged in, redirect to company home
if (isset($_SESSION['company_logged_in']) && $_SESSION['company_logged_in']) {
    header('Location: company_home.php'); // Redirect to company home if already logged in
    exit();
}

$errors = [];
$success_message = ''; // For displaying success messages

// Retrieve and clear any session-based error messages
if (isset($_SESSION['company_error'])) {
    $errors[] = $_SESSION['company_error'];
    unset($_SESSION['company_error']); // Clear the error after displaying
}

// Retrieve and clear any session-based success messages (e.g., after registration)
if (isset($_SESSION['company_message'])) {
    $success_message = $_SESSION['company_message'];
    unset($_SESSION['company_message']); // Clear the message after displaying
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = "Email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    } else {
        // Prepare SQL statement to fetch company by email
        // Ensure your 'companies' table has 'id', 'name', 'email', and 'password' columns
        $stmt = $conn->prepare("SELECT id, name, email, password, status FROM companies WHERE email = ?"); // Added status
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $name, $fetched_email, $hash_password, $status); // Bind status
                $stmt->fetch();

                // Verify the password
                if (password_verify($password, $hash_password)) {
                    // Check company status
                    if ($status === 'approved') { // Only allow login if approved
                        $_SESSION['company_logged_in'] = true;
                        $_SESSION['company_id'] = $id;
                        $_SESSION['company_name'] = $name;
                        $_SESSION['company_email'] = $fetched_email; // Store email in session

                        // Redirect to company home page after successful login
                        header("Location: company_home.php");
                        exit();
                    } elseif ($status === 'pending') {
                        $errors[] = "Your account is pending admin approval. Please wait.";
                    } elseif ($status === 'banned') {
                        $errors[] = "Your account has been banned. Please contact administration.";
                    } else {
                        $errors[] = "Account status unknown. Please contact support.";
                    }
                } else {
                    $errors[] = "Invalid email or password.";
                }
            } else {
                $errors[] = "Invalid email or password.";
            }
            $stmt->close();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
    }
}
$conn->close();
?>

<!doctype html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Company Login | Job Board</title>
    <meta name="description" content="Company login page for the job board.">
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
            <div class="header-btn flex items-center space-x-4">
                <a href="company_signup.php" class="btn-header btn-login">Sign up</a>
            </div>
            <button class="md:hidden text-gray-700 focus:outline-none">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </nav>

        <section class="login-section login-container">
            <div class="login-box">
                <h2>Company Login</h2>
                <?php if (!empty($errors)): ?>
                    <div class="alert-danger" role="alert">
                        <ul class="list-disc list-inside">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <div class="alert-success" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>
                <p>Login to your company account.</p>

                <form action="company_login.php" method="POST">
                    <input type="email" class="form-control" id="email" name="email" placeholder="Company Email Address" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <div class="password-container">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <span toggle="#password" class="fa fa-eye field-icon toggle-password" id="togglePassword"></span>
                    </div>
                    
                    <button type="submit" class="btn-form-submit">Login</button>
                </form>

                <div class="text-link mt-4">
                    <p>Don't have a company account? <a href="company_signup.php">Register Here</a></p>
                    <p><a href="forgot_password_company.php">Forgot Password?</a></p>
                </div>
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