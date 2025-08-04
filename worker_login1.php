<?php
session_start();
require_once 'php/db_connect.php'; // Make sure this path is correct based on your file structure

if (isset($_SESSION['worker_logged_in']) && $_SESSION['worker_logged_in']) {
    header('Location: worker_home.php'); // Redirect to worker home if already logged in
    exit();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = "Email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, password FROM workers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $name, $hash_password);
            $stmt->fetch();
            if (password_verify($password, $hash_password)) {
                $_SESSION['worker_logged_in'] = true;
                $_SESSION['worker_id'] = $id;
                $_SESSION['worker_name'] = $name;
                header("Location: worker_home.php"); // Redirect on successful login
                exit();
            } else {
                $errors[] = "Invalid email or password.";
            }
        } else {
            $errors[] = "Invalid email or password.";
        }
        $stmt->close();
    }
}
?>

<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Worker Login | Job Board</title>
    <meta name="description" content="Worker login page for the job board.">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="site.webmanifest">
    <link rel="shortcut icon" type="image/x-icon" href="img/ok.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/owl.carousel.min.css">
    <link rel="stylesheet" href="assets/css/flaticon.css">
    <link rel="stylesheet" href="assets/css/price_rangs.css">
    <link rel="stylesheet" href="assets/css/slicknav.css">
    <link rel="stylesheet" href="assets/css/animate.min.css">
    <link rel="stylesheet" href="assets/css/magnific-popup.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="stylesheet" href="assets/css/themify-icons.css">
    <link rel="stylesheet" href="assets/css/slick.css">
    <link rel="stylesheet" href="assets/css/nice-select.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        /* General Body and Font Styles */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body {
            background-image: url('img/man.jpg'); /* Ensure this path is correct */
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            background-color: #f0f2f7;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        /* Login Container and Box Styling */
        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            z-index: 2;
            position: relative;
        }
        .login-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            padding: 45px 35px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            max-width: 400px;
            width: 100%;
            color: #000;
            animation: fadeIn 1s ease;
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 10px;
            font-weight: 700;
            color: #000;
            font-size: 2em;
        }
        .login-box p {
            text-align: center;
            font-size: 16px;
            margin-bottom: 30px;
        }

        /* Form Control (Input Fields) Styling */
        .login-box .form-control {
            padding: 12px 18px;
            border-radius: 12px;
            border: none;
            background-color: rgba(255, 255, 255, 0.15);
            color: #000;
            border: 0.5px solid #0303035b;
            width: 100%; /* Ensure full width */
            box-sizing: border-box; /* Include padding in width */
        }
        .login-box .form-control::placeholder {
            color: #555;
        }
        .login-box .form-control:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 188, 212, 0.4);
            background-color: rgba(255, 255, 255, 0.25);
        }

        /* Submit Button Styling */
        .login-box button {
            background-color: #00bcd4;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 12px;
            font-weight: 600;
            color: #fff;
            font-size: 1.1em;
            transition: 0.3s ease;
            cursor: pointer;
        }
        .login-box button:hover {
            background-color: #0097a7;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 188, 212, 0.4);
        }

        /* Link Styling */
        .login-box .text-link {
            text-align: center;
            margin-top: 25px;
            font-size: 16px;
        }
        .login-box .text-link a {
            color: #0097a7;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .login-box .text-link a:hover {
            color: #006f7a;
            text-decoration: underline;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Error Message Styling */
        .error-message {
            color: #ff4444; /* Red color for errors */
            background-color: rgba(255, 0, 0, 0.1);
            border: 1px solid #ff4444;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: left;
            font-size: 0.9em;
        }

        /* Password Eye Icon Specific Styles */
        .password-input-container {
            position: relative;
            margin-bottom: 20px; /* Provides spacing below the password field */
            width: 100%;
        }

        .password-input-container .form-control {
            padding-right: 40px; /* Creates space for the icon inside the input */
            margin-bottom: 0; /* Remove default margin-bottom from .form-control here */
        }

        .password-input-container #togglePassword {
            position: absolute;
            right: 15px; /* Adjust if needed to align perfectly */
            top: 50%;
            transform: translateY(-50%); /* Vertically centers the icon */
            cursor: pointer;
            color: #555; /* Icon color */
            font-size: 1.2em; /* Icon size */
            z-index: 10; /* Ensures icon is clickable over input */
        }
    </style>
</head>

<body>
    <header>
        <div class="header-area header-transparrent">
            <div class="headder-top header-sticky">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-lg-3 col-md-2">
                            <div class="logo">
                                <a href="index.html"><img src="img/logo.png" style="height: 60px; width: 200px;" alt="Job Board Logo"></a>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mobile_menu d-block d-lg-none"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="login-container">
        <div class="login-box">
            <h2>Worker Login</h2>
            <p>Welcome back! Please login to your account.</p>

            <?php if (!empty($errors)): ?>
                <div class="error-message" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="#" method="POST" novalidate>
                <input type="email" name="email" class="form-control" placeholder="Email Address" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                
                <div class="password-input-container">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                    <i class="far fa-eye" id="togglePassword"></i>
                </div>
                
                <button type="submit">Login</button>
            </form>
            <div class="text-link">
                <p>Don't have an account? <a href="signup.html">Register</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor/modernizr-3.5.0.min.js"></script>
    <script src="assets/js/vendor/jquery-1.12.4.min.js"></script>
    <script src="assets/js/popper.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/js/jquery.slicknav.min.js"></script>
    <script src="assets/js/owl.carousel.min.js"></script>
    <script src="assets/js/slick.min.js"></script>
    <script src="assets/js/wow.min.js"></script>
    <script src="assets/js/animated.headline.js"></script>
    <script src="assets/js/jquery.magnific-popup.js"></script>
    <script src="assets/js/gijgo.min.js"></script>
    <script src="assets/js/jquery.nice-select.min.js"></script>
    <script src="assets/js/jquery.sticky.js"></script>
    <script src="assets/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/plugins.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye slash icon
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>

</html>