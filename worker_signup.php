<?php
session_start(); // Start the session to handle messages
require_once 'php/db_connect.php'; // Include your database connection file (make sure this path is correct)

// Enable error reporting for debugging (REMOVE IN PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize and retrieve form data
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone_number = htmlspecialchars(trim($_POST['phone_number']));

    $age = (int)$_POST['age']; // Cast to integer for age
    $gender = htmlspecialchars(trim($_POST['gender'])); // Gender will be 'Male', 'Female', or 'Other'

    $country = htmlspecialchars(trim($_POST['country']));
    $state = htmlspecialchars(trim($_POST['state']));

    $profession = htmlspecialchars(trim($_POST['profession']));
    $experience = (int)$_POST['experience']; // Cast to integer
    $skills = htmlspecialchars(trim($_POST['skills']));

    // File upload handling for CV
    $cv_path = null;
    if (isset($_FILES['cv_upload']) && $_FILES['cv_upload']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/cvs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
        }
        $cv_filename = uniqid('cv_') . '_' . basename($_FILES['cv_upload']['name']);
        $target_file = $upload_dir . $cv_filename;
        if (move_uploaded_file($_FILES['cv_upload']['tmp_name'], $target_file)) {
            $cv_path = $target_file;
        } else {
            $errors[] = "Failed to upload CV.";
        }
    }


    // 2. Server-side Validation
    $errors = [];

    if (empty($full_name)) {
        $errors[] = "Full Name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check if email already exists
    $stmt_check_email = $conn->prepare("SELECT id FROM workers WHERE email = ?");
    if ($stmt_check_email) {
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();
        if ($stmt_check_email->num_rows > 0) {
            $errors[] = "Email already registered. Please use a different email or log in.";
        }
        $stmt_check_email->close();
    } else {
        $errors[] = "Database error during email check: " . $conn->error;
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (empty($phone_number)) {
        $errors[] = "Phone Number is required.";
    }
    if (!preg_match("/^[0-9]{10,15}$/", $phone_number)) { // Basic phone number validation (10-15 digits)
        $errors[] = "Invalid phone number format.";
    }
    if ($age < 16) {
        $errors[] = "You must be at least 16 years old to register.";
    }
    if (empty($gender)) {
        $errors[] = "Gender is required.";
    }
    if (empty($country)) {
        $errors[] = "Country is required.";
    }
    if (empty($state)) {
        $errors[] = "State is required.";
    }
    if (empty($profession)) {
        $errors[] = "Profession is required.";
    }
    if ($experience < 0) {
        $errors[] = "Experience cannot be negative.";
    }
    if (empty($skills)) {
        $errors[] = "Skills are required.";
    }


    // If no validation errors, proceed with database insertion
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Prepare and execute SQL INSERT statement
        // Added cv_path to the INSERT statement
        $stmt = $conn->prepare("INSERT INTO workers (full_name, email, password, phone_number, age, gender, country, state, profession, experience, skills, cv_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            // 's' for string, 'i' for integer, 's' for cv_path
            $stmt->bind_param("sssisissisis", $full_name, $email, $hashed_password, $phone_number, $age, $gender, $country, $state, $profession, $experience, $skills, $cv_path);

            if ($stmt->execute()) {
                $_SESSION['worker_message'] = "Registration successful! You can now log in.";
                // Corrected redirection path
                header("Location: worker_login.php");
                exit();
            } else {
                $errors[] = "Error during registration. Please try again. (" . $stmt->error . ")";
            }
            $stmt->close();
        } else {
            $errors[] = "Database error: Could not prepare statement. (" . $conn->error . ")";
        }
    }

    // If there are errors, store them in session and redirect back to signup page
    if (!empty($errors)) {
        $_SESSION['worker_errors'] = $errors;
        // Keep the input values so the user doesn't have to re-enter them
        $_SESSION['form_data'] = $_POST;
        // Corrected redirection path
        header("Location: worker_signup.php");
        exit();
    }

    $conn->close();
}

// Retrieve and clear session messages and form data for display
$success_message = '';
$error_messages = [];
$form_data = [];

if (isset($_SESSION['worker_message'])) {
    $success_message = $_SESSION['worker_message'];
    unset($_SESSION['worker_message']);
}
if (isset($_SESSION['worker_errors'])) {
    $error_messages = $_SESSION['worker_errors'];
    unset($_SESSION['worker_errors']);
}
if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
}


// Populate dropdowns for countries and states
$countries = [
    "India", "United States", "Canada", "United Kingdom", "Australia", "Germany", "France", "Japan", "China",
    "Brazil", "Mexico", "South Africa", "Nigeria", "Egypt", "Argentina", "Italy", "Spain", "South Korea", "Indonesia"
];
sort($countries); // Sort for consistent order

// Sample Indian states (you might fetch this dynamically or from a larger list)
$indian_states = [
    "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", "Haryana",
    "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur",
    "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana",
    "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal"
];
sort($indian_states); // Sort for consistent order

// Populate professions (you might fetch this dynamically)
$professions = [
    "Software Engineer", "Data Scientist", "Web Developer", "Graphic Designer", "Marketing Manager",
    "HR Specialist", "Accountant", "Teacher", "Nurse", "Doctor", "Civil Engineer", "Mechanical Engineer",
    "Financial Analyst", "Sales Representative", "Customer Service", "Project Manager", "Business Analyst",
    "Product Manager", "UI/UX Designer", "DevOps Engineer", "Cloud Engineer", "Network Engineer"
];
sort($professions); // Sort for consistent order

// Combine country and state data for JavaScript (as in testing.php)
$countryStateData = [
    "United States" => [
        "Alabama", "Alaska", "Arizona", "Arkansas", "California", "Colorado", "Connecticut", "Delaware", "Florida",
        "Georgia", "Hawaii", "Idaho", "Illinois", "Indiana", "Iowa", "Kansas", "Kentucky", "Louisiana", "Maine",
        "Maryland", "Massachusetts", "Michigan", "Minnesota", "Mississippi", "Missouri", "Montana", "Nebraska",
        "Nevada", "New Hampshire", "New Jersey", "New Mexico", "New York", "North Carolina", "North Dakota",
        "Ohio", "Oklahoma", "Oregon", "Pennsylvania", "Rhode Island", "South Carolina", "South Dakota",
        "Tennessee", "Texas", "Utah", "Vermont", "Virginia", "Washington", "West Virginia", "Wisconsin", "Wyoming"
    ],
    "Canada" => [
        "Alberta", "British Columbia", "Manitoba", "New Brunswick", "Newfoundland and Labrador", "Nova Scotia",
        "Ontario", "Prince Edward Island", "Quebec", "Saskatchewan"
    ],
    "India" => $indian_states, // Use the dynamically sorted Indian states
    "United Kingdom" => [
        "England", "Scotland", "Wales", "Northern Ireland"
    ],
    "Australia" => [
        "New South Wales", "Victoria", "Queensland", "Western Australia", "South Australia", "Tasmania",
        "Northern Territory", "Australian Capital Territory"
    ],
    "Germany" => [
        "Baden-Württemberg", "Bavaria", "Berlin", "Brandenburg", "Bremen", "Hamburg", "Hesse",
        "Lower Saxony", "Mecklenburg-Vorpommern", "North Rhine-Westphalia", "Rhineland-Palatinate",
        "Saarland", "Saxony", "Saxony-Anhalt", "Schleswig-Holstein", "Thuringia"
    ],
    "France" => [
        "Auvergne-Rhône-Alpes", "Bourgogne-Franche-Comté", "Brittany", "Centre-Val de Loire", "Corsica",
        "Grand Est", "Hauts-de-France", "Île-de-France", "Normandy", "Nouvelle-Aquitaine", "Occitania",
        "Pays de la Loire", "Provence-Alpes-Côte d'Azur"
    ],
    "Japan" => [
        "Hokkaido", "Tohoku", "Kanto", "Chubu", "Kansai", "Chugoku", "Shikoku", "Kyushu"
    ],
    "China" => [
        "Anhui", "Fujian", "Gansu", "Guangdong", "Guizhou", "Hainan", "Hebei", "Heilongjiang", "Henan",
        "Hubei", "Hunan", "Jiangsu", "Jiangxi", "Jilin", "Liaoning", "Qinghai", "Shaanxi", "Shandong",
        "Shanxi", "Sichuan", "Yunnan", "Zhejiang", "Taiwan", "Chongqing", "Beijing", "Shanghai", "Tianjin",
        "Guangxi", "Inner Mongolia", "Ningxia", "Tibet", "Xinjiang", "Hong Kong", "Macau"
    ],
    "Brazil" => [
        "Acre", "Alagoas", "Amapá", "Amazonas", "Bahia", "Ceará", "Distrito Federal", "Espírito Santo",
        "Goiás", "Maranhão", "Mato Grosso", "Mato Grosso do Sul", "Minas Gerais", "Pará", "Paraíba",
        "Paraná", "Pernambuco", "Piauí", "Rio de Janeiro", "Rio Grande do Norte", "Rio Grande do Sul",
        "Rondônia", "Roraima", "Santa Catarina", "São Paulo", "Sergipe", "Tocantins"
    ],
    "Mexico" => [
        "Aguascalientes", "Baja California", "Baja California Sur", "Campeche", "Chiapas", "Chihuahua",
        "Coahuila", "Colima", "Durango", "Guanajuato", "Guerrero", "Hidalgo", "Jalisco", "México", "Michoacán",
        "Morelos", "Nayarit", "Nuevo León", "Oaxaca", "Puebla", "Querétaro", "Quintana Roo", "San Luis Potosí",
        "Sinaloa", "Sonora", "Tabasco", "Tamaulipas", "Tlaxcala", "Veracruz", "Yucatán", "Zacatecas"
    ],
    "South Africa" => [
        "Eastern Cape", "Free State", "Gauteng", "KwaZulu-Natal", "Limpopo", "Mpumalanga", "North West",
        "Northern Cape", "Western Cape"
    ],
    "Nigeria" => [
        "Abia", "Adamawa", "Akwa Ibom", "Anambra", "Bauchi", "Bayelsa", "Benue", "Borno", "Cross River",
        "Delta", "Ebonyi", "Edo", "Ekiti", "Enugu", "Gombe", "Imo", "Jigawa", "Kaduna", "Kano", "Katsina",
        "Kebbi", "Kogi", "Kwara", "Lagos", "Nasarawa", "Niger", "Ogun", "Ondo", "Osun", "Oyo", "Plateau",
        "Rivers", "Sokoto", "Taraba", "Yobe", "Zamfara", "Federal Capital Territory"
    ],
    "Egypt" => [
        "Alexandria", "Aswan", "Asyut", "Beheira", "Beni Suef", "Cairo", "Dakahlia", "Damietta", "Faiyum",
        "Gharbia", "Giza", "Ismailia", "Kafr El Sheikh", "Luxor", "Matruh", "Minya", "Monufia", "New Valley",
        "North Sinai", "Port Said", "Qalyubia", "Qena", "Red Sea", "Sharqia", "Sohag", "South Sinai", "Suez"
    ],
    "Argentina" => [
        "Buenos Aires", "Catamarca", "Chaco", "Chubut", "Córdoba", "Corrientes", "Entre Ríos", "Formosa",
        "Jujuy", "La Pampa", "La Rioja", "Mendoza", "Misiones", "Neuquén", "Río Negro", "Salta", "San Juan",
        "San Luis", "Santa Cruz", "Santa Fe", "Santiago del Estero", "Tierra del Fuego", "Tucumán"
    ],
    "Italy" => [
        "Abruzzo", "Aosta Valley", "Apulia", "Basilicata", "Calabria", "Campania", "Emilia-Romagna", "Friuli-Venezia Giulia",
        "Lazio", "Liguria", "Lombardy", "Marche", "Molise", "Piedmont", "Sardinia", "Sicily", "Tuscany", "Trentino-Alto Adige",
        "Umbria", "Veneto"
    ],
    "Spain" => [
        "Andalusia", "Aragon", "Asturias", "Balearic Islands", "Basque Country", "Canary Islands", "Cantabria",
        "Castile and León", "Castile-La Mancha", "Catalonia", "Extremadura", "Galicia", "La Rioja", "Community of Madrid",
        "Region of Murcia", "Navarre", "Valencian Community"
    ],
    "South Korea" => [
        "Seoul", "Busan", "Daegu", "Incheon", "Gwangju", "Daejeon", "Ulsan", "Sejong", "Gyeonggi", "Gangwon",
        "North Chungcheong", "South Chungcheong", "North Jeolla", "South Jeolla", "North Gyeongsang",
        "South Gyeongsang", "Jeju"
    ],
    "Indonesia" => [
        "Aceh", "Bali", "Bangka Belitung Islands", "Banten", "Bengkulu", "Central Java", "Central Kalimantan",
        "Central Sulawesi", "East Java", "East Kalimantan", "East Nusa Tenggara", "Gorontalo", "Jakarta",
        "Jambi", "Lampung", "Maluku", "North Kalimantan", "North Maluku", "North Sulawesi", "North Sumatra",
        "Papua", "Riau", "Riau Islands", "South Kalimantan", "South Sulawesi", "South Sumatra", "West Java",
        "West Kalimantan", "West Nusa Tenggara", "West Papua", "West Sulawesi", "West Sumatra", "Yogyakarta"
    ]
];


?>
<!doctype html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Worker Signup | Job Board</title>
    <meta name="description" content="Worker signup page for the job board.">
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

        .signup-container {
            flex-grow: 1; /* Allows it to take available space */
            display: flex;
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically */
            padding: 20px 0;
            position: relative;
            z-index: 2; /* Above the man image and other elements */
        }

        .signup-box {
            background: rgba(255, 255, 255, 0.1); /* Transparent background */
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 45px 35px;
            border-radius: 20px;
            box-shadow: none; /* Removed box shadow */
            max-width: 600px;
            width: 100%;
            animation: fadeIn 1s ease;
            color: #000000;
            margin: 20px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            z-index: 3;
        }

        .signup-box h2 {
            text-align: center;
            margin-bottom: 10px;
            font-weight: 700;
            color: #000000;
            font-size: 2.5em;
        }

        .signup-box p {
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
        }

        .form-control:not(textarea) {
            height: 50px;
            border: 0.5px solid #0303035b;
        }
        
        .select-wrapper {
            position: relative;
        }

        .select-wrapper::after {
            content: '\25BC'; /* Down arrow unicode character */
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            color: #555;
            pointer-events: none; /* Ensures clicks go through to the select */
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

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
            border: 0.5px solid #0303035b;
        }

        .signup-file-input-area {
            display: flex;
            align-items: center;
            padding: 5px;
            border-radius: 12px;
            background-color: rgba(255, 255, 255, 0.15);
            margin-bottom: 20px;
            height: 50px;
            overflow: hidden;
            box-sizing: border-box;
            border: 0.5px solid #0303035b;
            transition: background-color 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }

        .signup-file-input-hidden {
            display: none;
        }

        .signup-file-label {
            display: flex;
            align-items: center;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .signup-file-button {
            background-color: #f7a451;
            color: #fff;
            padding: 8px 15px;
            border-radius: 9px;
            font-size: 0.9em;
            font-weight: 600;
            white-space: nowrap;
            transition: background-color 0.3s ease;
        }

        .signup-file-button:hover {
            background-color: #e69042;
        }

        .signup-file-text {
            flex-grow: 1;
            padding: 0 15px;
            color: #555;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        /* Focus style for file input area */
        .signup-file-input-hidden:focus + .signup-file-label .signup-file-text {
            border-color: var(--blue-primary); /* Apply focus style to the visible text */
            box-shadow: 0 0 0 3px rgba(0, 188, 212, 0.4);
        }
         .signup-file-input-hidden:focus + .signup-file-label + .signup-file-input-area {
            box-shadow: 0 0 0 3px rgba(0, 188, 212, 0.4);
            border-color: #00bcd4;
        }


        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-check-inputt {
            padding: 0;
            margin-right: 8px;
            width: 18px;
            height: 18px;
            accent-color: var(--blue-primary);
            cursor: pointer;
        }

        .form-check-label {
            font-size: 14px;
            color: #000000;
            display: flex;
            align-items: center;
        }

        .form-check-label a {
            color: var(--blue-primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
            margin-left: 4px;
        }

        .form-check-label a:hover {
            color: var(--blue-hover);
            text-decoration: underline;
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

        @media (max-width: 768px) {
            .signup-box {
                padding: 35px 25px;
                max-width: 90%;
            }
            .signup-box h2 {
                font-size: 2em;
            }
            .signup-box p {
                font-size: 15px;
            }
            /* Keep man-image visible on larger screens but hide on small */
            .man-image {
                display: block; /* Ensure it's shown for wider screens */
            }
        }

        @media (max-width: 576px) {
            .signup-box {
                padding: 25px 18px;
                max-width: 95%;
            }
            .signup-box h2 {
                font-size: 1.8em;
            }
            .form-control {
                padding: 10px 15px;
                margin-bottom: 15px;
            }
            .signup-box button {
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
                <a href="worker_login1.php" class="btn-header btn-login">Login</a>
            </div>
            <button class="md:hidden text-gray-700 focus:outline-none">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </nav>

        <section class="signup-section signup-container">
            <div class="signup-box">
                <h2>Worker Sign Up</h2>
                <?php if (!empty($success_message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error_messages)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <ul class="list-disc list-inside">
                            <?php foreach ($error_messages as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <p>Create your profile and start finding jobs!</p>

                <form action="worker_signup.php" method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Full Name" required value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>">
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" required value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" placeholder="Phone Number" value="<?php echo htmlspecialchars($form_data['phone_number'] ?? ''); ?>">
                        <div class="select-wrapper">
                            <select class="form-control" id="profession" name="profession" required>
                                <option value="">Profession/Desired Role</option>
                                <?php
                                foreach ($professions as $profession_option) {
                                    $selected = (($form_data['profession'] ?? '') == $profession_option) ? 'selected' : '';
                                    echo "<option value=\"{$profession_option}\" {$selected}>{$profession_option}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="number" class="form-control" id="age" name="age" min="16" placeholder="Age" required value="<?php echo htmlspecialchars($form_data['age'] ?? ''); ?>">
                        <div class="select-wrapper">
                            <select class="form-control" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (($form_data['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($form_data['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (($form_data['gender'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="select-wrapper">
                            <select class="form-control" id="countrySelect" name="country" required>
                                <option value="">Select Country</option>
                                <?php
                                // Ensure country is initialized for options to work
                                if (empty($form_data['country'])) {
                                    echo "<option value=\"\" selected disabled>Select Country</option>";
                                }
                                foreach ($countries as $country_option) {
                                    $selected = (($form_data['country'] ?? '') == $country_option) ? 'selected' : '';
                                    echo "<option value=\"{$country_option}\" {$selected}>{$country_option}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="select-wrapper">
                            <select class="form-control" id="stateSelect" name="state" required <?php echo empty($form_data['country']) ? 'disabled' : ''; ?>>
                                <option value="">Select State</option>
                                <?php
                                if (!empty($form_data['country']) && isset($countryStateData[$form_data['country']])) {
                                    foreach ($countryStateData[$form_data['country']] as $state_option) {
                                        $selected = (($form_data['state'] ?? '') == $state_option) ? 'selected' : '';
                                        echo "<option value=\"{$state_option}\" {$selected}>{$state_option}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <input type="number" class="form-control" id="experience" name="experience" min="0" placeholder="Years of Experience" value="<?php echo htmlspecialchars($form_data['experience'] ?? ''); ?>">

                    <textarea class="form-control" id="skills" name="skills" rows="4" placeholder="Key Skills (comma-separated)"><?php echo htmlspecialchars($form_data['skills'] ?? ''); ?></textarea>
                    
                    <p class="mb-2 text-sm text-gray-700">Upload CV/Resume</p>
                    <div class="signup-file-input-area">
                        <input type="file" class="signup-file-input-hidden" id="cv_upload" name="cv_upload" accept=".pdf,.doc,.docx">
                        <label for="cv_upload" class="signup-file-label">
                            <span class="signup-file-button">Choose File</span>
                            <span class="signup-file-text" id="cv_upload_filename">
                                <?php echo !empty($form_data['cv_upload_filename']) ? htmlspecialchars($form_data['cv_upload_filename']) : 'No file chosen'; ?>
                            </span>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-inputt" id="terms_agree" name="terms_agree" required <?php echo (isset($form_data['terms_agree'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="terms_agree">I agree to the <a href="terms_and_conditions.html">Terms & Conditions</a></label>
                    </div>
                    <button type="submit" class="btn-form-submit">Register Now</button>
                </form>

                <div class="text-link mt-4">
                    <p>Already have an account? <a href="worker_login1.php">Login</a></p>
                </div>
            </div>
        </section>
    </div>
    
    <!-- Jquery, Popper, Bootstrap JS (placed at the end for faster page load) -->
    <!-- Note: Bootstrap JS is still needed for other components if used elsewhere in your project -->
    <script src="./assets/js/vendor/jquery-1.12.4.min.js"></script>
    <script src="./assets/js/popper.min.js"></script>
    <script src="./assets/js/bootstrap.min.js"></script>
    
    <script>
        // PHP-generated JavaScript data for countries and states
        const countryStateData = <?php echo json_encode($countryStateData); ?>;

        const countrySelect = document.getElementById('countrySelect');
        const stateSelect = document.getElementById('stateSelect');
        const cvUploadInput = document.getElementById('cv_upload');
        const cvUploadFilename = document.getElementById('cv_upload_filename');

        function populateCountries() {
            // Clear existing options except the first placeholder
            countrySelect.innerHTML = '<option value="">Select Country</option>';
            // Sort keys of countryStateData for consistent order in dropdown
            Object.keys(countryStateData).sort().forEach(country => {
                const option = document.createElement('option');
                option.value = country;
                option.textContent = country;
                countrySelect.appendChild(option);
            });
            // Set selected country if form_data is present (after validation error)
            const initialCountry = "<?php echo htmlspecialchars($form_data['country'] ?? ''); ?>";
            if (initialCountry) {
                countrySelect.value = initialCountry;
                populateStates(); // Populate states immediately if country is pre-selected
            }
        }

        function populateStates() {
            const selectedCountry = countrySelect.value;
            stateSelect.innerHTML = '<option value="">Select State</option>'; // Clear states
            stateSelect.disabled = true; // Disable state until country is selected

            if (selectedCountry && countryStateData[selectedCountry]) {
                countryStateData[selectedCountry].forEach(state => {
                    const option = document.createElement('option');
                    option.value = state;
                    option.textContent = state;
                    stateSelect.appendChild(option);
                });
                stateSelect.disabled = false;
                // Set selected state if form_data is present (after validation error)
                const initialState = "<?php echo htmlspecialchars($form_data['state'] ?? ''); ?>";
                if (initialState) {
                    stateSelect.value = initialState;
                }
            }
        }

        // Event listener for country change to update states
        countrySelect.addEventListener('change', populateStates);

        // Event listener for CV file input change
        cvUploadInput.addEventListener('change', function(e) {
            var fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
            cvUploadFilename.textContent = fileName;
        });

        // Populate countries on DOM content loaded
        document.addEventListener('DOMContentLoaded', () => {
            populateCountries();
        });

        // Hide preloader on window load
        window.addEventListener('load', function() {
            const preloader = document.getElementById('preloader-active');
            if (preloader) {
                preloader.style.display = 'none';
            }
        });
    </script>
</body>
</html>