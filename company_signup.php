<?php
session_start(); // Start the session to manage messages

// Enable error reporting for debugging.
// IMPORTANT: You should set display_errors to 'Off' on a live production server for security.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database connection file.
// Assuming db_connect.php is in a 'php' subdirectory relative to company_signup.php
require_once 'php/db_connect.php'; 

// Check if the database connection was successful
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// Initialize messages for display
$error_message = '';
$warning_message = '';
$success_message = '';

// Retrieve and clear session messages from previous attempts (e.g., from login or other pages)
if (isset($_SESSION['error_message_company'])) {
    $error_message = $_SESSION['error_message_company'];
    unset($_SESSION['error_message_company']);
}
if (isset($_SESSION['warning_message_company'])) {
    $warning_message = $_SESSION['warning_message_company'];
    unset($_SESSION['warning_message_company']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// --- Handle POST request for company registration ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and retrieve input data
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone_number = htmlspecialchars(trim($_POST['phone_number'] ?? ''));
    $country = htmlspecialchars(trim($_POST['country'] ?? ''));
    $state = htmlspecialchars(trim($_POST['state'] ?? '')); 
    $address = htmlspecialchars(trim($_POST['address'] ?? ''));
    $city = htmlspecialchars(trim($_POST['city'] ?? ''));
    $website = htmlspecialchars(trim($_POST['website'] ?? ''));
    $contact_person = htmlspecialchars(trim($_POST['contact_person'] ?? ''));
    $contact_email = filter_var(trim($_POST['contact_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Location data from map
    $location_lat = filter_var(trim($_POST['location_lat'] ?? ''), FILTER_VALIDATE_FLOAT);
    $location_lon = filter_var(trim($_POST['location_lon'] ?? ''), FILTER_VALIDATE_FLOAT);
    $location_address = htmlspecialchars(trim($_POST['location_address'] ?? ''));

    // Default values for fields not directly on signup form (or handled later)
    $company_logo_path = 'uploads/default_company_logo.png'; // Default logo path
    $description = ''; // Company description, can be updated later in profile

    // Internal errors array for this POST request
    $internal_errors = [];

    // Server-side Validation
    if (empty($name)) { $internal_errors[] = "Company Name is required."; }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $internal_errors[] = "Valid email is required."; }
    if (empty($phone_number)) { $internal_errors[] = "Phone Number is required."; }
    if (empty($country)) { $internal_errors[] = "Country is required."; }
    if (empty($address)) { $internal_errors[] = "Address is required."; }
    if (empty($city)) { $internal_errors[] = "City is required."; }
    if (empty($contact_person)) { $internal_errors[] = "Contact Person is required."; }
    if (empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) { $internal_errors[] = "Valid Contact Email is required."; }
    if (empty($password)) { $internal_errors[] = "Password is required."; }
    if (strlen($password) < 6) { $internal_errors[] = "Password must be at least 6 characters long."; }
    if ($password !== $confirm_password) { $internal_errors[] = "Passwords do not match."; }
    
    // Validate location data
    if ($location_lat === false || $location_lon === false || empty($location_address)) {
        $internal_errors[] = "Please select your company's location on the map.";
    }

    // State validation (now required if a state list exists for the country)
    // This server-side check needs to align with the JS logic for which countries have states.
    // We'll check if the selected country has states defined in our PHP array and if the state is empty.
    global $country_states; // Make sure $country_states is accessible here
    if (!empty($country) && isset($country_states[$country]) && count($country_states[$country]) > 1 && empty($state)) {
        $internal_errors[] = "State is required for the selected country.";
    }


    // Check if email or phone number already exists
    if (empty($internal_errors)) {
        $stmt_check = $conn->prepare("SELECT id FROM companies WHERE email = ? OR phone_number = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("ss", $email, $phone_number);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $internal_errors[] = "Email or Phone Number already registered.";
            }
            $stmt_check->close();
        } else {
            $internal_errors[] = "Database error during email/phone check: " . $conn->error;
            error_log("Database error during email/phone check (company_signup.php): " . $conn->error);
        }
    }

    // Attempt to register company if no errors
    if (empty($internal_errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $status = 'pending'; // Default status for new registrations

        $stmt_insert = $conn->prepare(
            "INSERT INTO companies (name, email, phone_number, country, state, address, city, website, contact_person, contact_email, password, company_logo_path, description, location_lat, location_lon, location_address, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if ($stmt_insert) {
            $stmt_insert->bind_param(
                "sssssssssssssddss",
                $name, $email, $phone_number, $country, $state, $address, $city, $website, $contact_person, $contact_email, $hashed_password, $company_logo_path, $description, $location_lat, $location_lon, $location_address, $status
            );

            if ($stmt_insert->execute()) {
                $_SESSION['success_message'] = "Company registered successfully! Your account is pending admin approval.";
                header("Location: company_login.php"); // Redirect to login page
                exit();
            } else {
                $internal_errors[] = "Error registering company: " . $stmt_insert->error;
                error_log("Error registering company (company_signup.php): " . $stmt_insert->error);
            }
            $stmt_insert->close();
        } else {
            $internal_errors[] = "Database error preparing registration statement: " . $conn->error;
            error_log("Database error preparing registration statement (company_signup.php): " . $conn->error);
        }
    }

    // If there were any errors, set the session error message for display
    if (!empty($internal_errors)) {
        $error_message = implode("<br>", $internal_errors);
    }
    $conn->close(); // Close the database connection after all operations
}

// Data for dynamic country/state dropdowns
$country_states = [
    "" => [""], // Default empty option for "Select Country"
    "India" => [
        "", // Empty option for "Select State"
        "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal",
        "Andaman and Nicobar Islands", "Chandigarh", "Dadra and Nagar Haveli and Daman and Diu", "Delhi", "Jammu and Kashmir", "Ladakh", "Lakshadweep", "Puducherry"
    ],
    "United States" => [
        "", // Empty option
        "Alabama", "Alaska", "Arizona", "Arkansas", "California", "Colorado", "Connecticut", "Delaware", "Florida", "Georgia", "Hawaii", "Idaho", "Illinois", "Indiana", "Iowa", "Kansas", "Kentucky", "Louisiana", "Maine", "Maryland", "Massachusetts", "Michigan", "Minnesota", "Mississippi", "Missouri", "Montana", "Nebraska", "Nevada", "New Hampshire", "New Jersey", "New Mexico", "New York", "North Carolina", "North Dakota", "Ohio", "Oklahoma", "Oregon", "Pennsylvania", "Rhode Island", "South Carolina", "South Dakota", "Tennessee", "Texas", "Utah", "Vermont", "Virginia", "Washington", "West Virginia", "Wisconsin", "Wyoming"
    ],
    "Canada" => [
        "", // Empty option
        "Alberta", "British Columbia", "Manitoba", "New Brunswick", "Newfoundland and Labrador", "Nova Scotia", "Ontario", "Prince Edward Island", "Quebec", "Saskatchewan", "Northwest Territories", "Nunavut", "Yukon"
    ],
    "United Kingdom" => [
        "", // Empty option
        "England", "Scotland", "Wales", "Northern Ireland"
    ],
    "Australia" => [
        "", // Empty option
        "New South Wales", "Queensland", "South Australia", "Tasmania", "Victoria", "Western Australia", "Australian Capital Territory", "Northern Territory"
    ],
    "Germany" => [
        "", // Empty option
        "Baden-Württemberg", "Bavaria", "Berlin", "Brandenburg", "Bremen", "Hamburg", "Hesse", "Lower Saxony", "Mecklenburg-Vorpommern", "North Rhine-Westphalia", "Rhineland-Palatinate", "Saarland", "Saxony", "Saxony-Anhalt", "Schleswig-Holstein", "Thuringia"
    ],
    "France" => [
        "", // Empty option
        "Auvergne-Rhône-Alpes", "Bourgogne-Franche-Comté", "Brittany", "Centre-Val de Loire", "Corsica", "Grand Est", "Hauts-de-France", "Île-de-France", "Normandy", "Nouvelle-Aquitaine", "Occitanie", "Pays de la Loire", "Provence-Alpes-Côte d'Azur"
    ],
    "Japan" => [
        "", // Empty option
        "Hokkaido", "Tohoku", "Kanto", "Chubu", "Kansai", "Chugoku", "Shikoku", "Kyushu"
    ],
    "China" => [
        "", // Empty option
        "Anhui", "Fujian", "Gansu", "Guangdong", "Guizhou", "Hainan", "Hebei", "Heilongjiang", "Henan", "Hubei", "Hunan", "Jiangsu", "Jiangxi", "Jilin", "Liaoning", "Qinghai", "Shaanxi", "Shandong", "Shanxi", "Sichuan", "Yunnan", "Zhejiang", "Taiwan", "Chongqing", "Beijing", "Shanghai", "Tianjin", "Guangxi", "Inner Mongolia", "Ningxia", "Xinjiang", "Tibet"
    ],
    "Brazil" => [
        "", // Empty option
        "Acre", "Alagoas", "Amapá", "Amazonas", "Bahia", "Ceará", "Distrito Federal", "Espírito Santo", "Goiás", "Maranhão", "Mato Grosso", "Mato Grosso do Sul", "Minas Gerais", "Pará", "Paraíba", "Paraná", "Pernambuco", "Piauí", "Rio de Janeiro", "Rio Grande do Norte", "Rio Grande do Sul", "Rondônia", "Roraima", "Santa Catarina", "São Paulo", "Sergipe", "Tocantins"
    ]
    // Add more countries and their states/provinces as needed
];

// Generate a simple list of all countries for the main dropdown
$countries = array_keys($country_states);
sort($countries); // Sort the country names alphabetically
// Ensure the initial empty option is at the very beginning
if (($key = array_search("", $countries)) !== false) {
    unset($countries[$key]);
}
array_unshift($countries, ""); // Add an empty option at the very beginning
?>
<!DOCTYPE html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Company Signup - Job Board Platform</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="site.webmanifest">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.ico">

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <!-- Removed nice-select.css to avoid conflicts with Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- CSS here -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/owl.carousel.min.css">
    <link rel="stylesheet" href="assets/css/flaticon.css">
    <link rel="stylesheet" href="assets/css/price_rangs.css">
    <link rel="stylesheet" href="assets/css/slicknav.css">
    <link rel="stylesheet" href="assets/css/animate.min.css">
    <link rel="stylesheet" href="assets/css/magnific-popup.css">
    <link rel="stylesheet" href="assets/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="assets/css/themify-icons.css">
    <link rel="stylesheet" href="assets/css/slick.css">
    <!-- <link rel="stylesheet" href="assets/css/nice-select.css"> --> <!-- Removed -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Ensure html and body take full height */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden; /* Prevent horizontal scroll */
        }

        /* Set the background image on the body */
        body {
            background-image: url('assets/img/hero/h1_hero.jpg'); /* The desired background image */
            background-size: cover; /* Ensures the image covers the entire body */
            background-position: center center; /* Centers the image */
            background-repeat: no-repeat; /* Prevents repetition */
            background-attachment: fixed; /* Keeps the background image fixed while content scrolls */
            background-color: #f4f4f4; /* Fallback background color */
        }

        /* Form container to center the signup box */
        .form-container {
            position: relative;
            z-index: 1; /* Ensure content is above background */
            width: 100%;
            display: flex; /* Use flexbox to center content */
            align-items: center; /* Vertically center content */
            justify-content: center; /* Horizontally center content */
            padding: 50px 0; /* Add some padding for spacing */
            min-height: calc(100vh - 150px); /* Adjust based on header/footer */
        }

        .signup-container {
            /* Glassmorphism effect */
            background-color: rgba(255, 255, 255, 0.2); /* Translucent white background */
            backdrop-filter: blur(10px); /* Blur effect for the glass look */
            -webkit-backdrop-filter: blur(10px); /* Safari support */
            border: 1px solid rgba(255, 255, 255, 0.3); /* Lighter border for glass effect */
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2); /* Stronger shadow for contrast */
            width: 100%;
            max-width: 700px; /* Wider for more fields */
            z-index: 2; /* Ensure it's above the overlay */
        }
        .form-group label {
            font-weight: 500;
            color: #000; /* Changed label color to BLACK */
            text-shadow: none; /* Remove text shadow */
        }
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #000; /* Explicitly 2px solid BLACK border for input boxes */
            border-radius: 5px;
            margin-bottom: 15px;
            background-color: #fff; /* Make input fields WHITE */
            color: #000; /* Ensure text in inputs is BLACK */
        }
        .form-control::placeholder { /* Style placeholder text */
            color: #333; /* Darker placeholder text */
            opacity: 1; /* Ensures placeholder is not too transparent */
        }
        .btn-custom-submit {
            background-color: #28a745;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        .btn-custom-submit:hover {
            background-color: #218838;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #000; /* Adjusted link text color to BLACK */
            text-shadow: none; /* Remove text shadow */
        }
        .login-link a {
            color: #007bff; /* Keep blue for the link itself */
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #badbcc;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        /* Make map container visible and styled */
        #map {
            height: 400px;
            width: 100%;
            border: 1px solid #000; /* BLACK border for map */
            border-radius: 5px;
        }
        .map-controls {
            margin-top: 10px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .map-controls input[type="text"] {
            flex-grow: 1;
            margin-bottom: 0; /* Override default form-control margin */
            background-color: #fff; /* Ensure map search input is WHITE */
            color: #000; /* Ensure map search input text is BLACK */
            border: 2px solid #000; /* Explicitly 2px solid BLACK border for map search input */
        }
        .map-controls input[type="text"]::placeholder {
            color: #333;
        }
        /* Hide Leaflet attribution control */
        .leaflet-control-attribution {
            display: none !important;
        }

        /* Custom Select2 dropdown styling for fixed height and scrolling */
        .select2-container--default .select2-results__options {
            max-height: 200px; /* Adjust this value to show approximately 10 items */
            overflow-y: auto;
            background-color: #fff; /* White background for dropdown options */
            color: #000; /* Black text for dropdown options */
            border: 1px solid #000; /* BLACK border for dropdown options container */
        }
        /* Style for individual options */
        .select2-container--default .select2-results__option {
            color: #000; /* Black text for options */
        }
        /* Style for hovered option */
        .select2-container--default .select2-results__option--highlighted {
            background-color: #007bff !important; /* Highlight color */
            color: #fff !important;
        }

        /* Ensure Select2 inputs match form-control styling */
        .select2-container .select2-selection--single {
            height: 46px; /* Match Bootstrap form-control height */
            border: 2px solid #000 !important; /* Explicitly 2px solid BLACK border for Select2 */
            border-radius: 5px;
            padding-top: 8px; /* Adjust padding to vertically align text */
            background-color: #fff !important; /* Make Select2 background WHITE */
            color: #000; /* Ensure text in select2 is BLACK */
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 44px; /* Match height of select box */
            /* border-left: 1px solid #555; */ /* Removed as not needed with full black border */
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px; /* Adjust line height to vertically align text */
            color: #000; /* Ensure rendered text is BLACK */
        }
        /* Style for the initial prompt in the modal */
        #selectLocationPrompt {
            padding: 20px;
            font-size: 1.1em;
            color: #555;
            text-align: center;
        }

        /* Adjust section title for better visibility on blurred background */
        .section-tittle h2 {
            color: #000; /* Black title */
            text-shadow: none; /* Remove text shadow */
            border-bottom-color: rgba(0,0,0,0.1) !important; /* Lighter border for title */
        }

        /* Ensure modal content also has opaque background */
        .modal-content {
            background-color: #fefefe; /* Opaque white for modal content */
        }
    </style>
</head>
<body>
    <!-- Preloader Start -->
    <div id="preloader-active">
        <div class="preloader d-flex align-items-center justify-content-center">
            <div class="preloader-inner position-relative">
                <div class="preloader-circle"></div>
                <div class="preloader-img pere-text">
                    <img src="assets/img/logo/logo.png" alt="">
                </div>
            </div>
        </div>
    </div>
    <!-- Preloader Start -->
    <header>
        <!-- Header Start -->
        <div class="header-area header-transparrent">
           <div class="headder-top header-sticky">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-lg-3 col-md-2">
                            <!-- Logo -->
                            <div class="logo">
                                <a href="index.html"><img src="assets/img/logo/logo.png" style="height: 60px; width: 200px;" alt="JobCrafter Logo"></a>
                            </div>  
                        </div>
                        <div class="col-lg-9 col-md-9">
                            <div class="menu-wrapper">
                                <!-- Main-menu -->
                                <div class="main-menu">
                                    <nav class="d-none d-lg-block">
                                        <ul id="navigation">
                                            <li><a href="index.html">Home</a></li>
                                            <li><a href="about.html">About</a></li>
                                            <li><a href="contact.html">Contact</a></li>
                                        </ul>
                                    </nav>
                                </div>          
                                <!-- Header-btn -->
                                <div class="header-btn d-none f-right d-lg-block">
                                    <a href="company_login.php" class="btn head-btn2">Login</a>
                                </div>
                            </div>
                        </div>
                        <!-- Mobile Menu -->
                        <div class="col-12">
                            <div class="mobile_menu d-block d-lg-none"></div>
                        </div>
                    </div>
                </div>
           </div>
       </div>
        <!-- Header End -->
    </header>
    <main>
        <!-- Company Registration Form Start -->
        <section class="form-container">
            <div class="signup-container">
                <!-- Adjusted section title color for better visibility on blurred background -->
                <div class="section-tittle text-center mb-40">
                    <h2>Register Your Company</h2>
                </div>

                <?php
                // Display messages
                if (!empty($error_message)) {
                    echo '<div class="alert alert-danger">' . htmlspecialchars($error_message) . '</div>';
                }
                if (!empty($warning_message)) {
                    echo '<div class="alert alert-warning">' . htmlspecialchars($warning_message) . '</div>';
                }
                if (!empty($success_message)) {
                    echo '<div class="alert alert-success">' . htmlspecialchars($success_message) . '</div>';
                }
                ?>
                <form action="company_signup.php" method="POST" class="form-contact contact_form">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="name">Company Name:</label>
                                <input type="text" id="name" name="name" class="form-control valid" onfocus="this.placeholder = ''" onblur="this.placeholder = 'Enter company name'" placeholder="Company Name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="email">Company Email:</label>
                                <input type="email" id="email" name="email" class="form-control valid" onfocus="this.placeholder = ''" onblur="this.placeholder = 'Enter company email'" placeholder="Company Email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="phone_number">Phone Number:</label>
                                <input type="text" id="phone_number" name="phone_number" class="form-control" onfocus="this.placeholder = ''" onblur="this.placeholder = 'Enter phone number'" placeholder="Phone Number" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="website">Website (Optional):</label>
                                <input type="url" id="website" name="website" class="form-control" onfocus="this.placeholder = ''" onblur="this.placeholder = 'Enter website URL (optional)'" placeholder="Website (Optional)" value="<?php echo htmlspecialchars($_POST['website'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="contact_person">Contact Person Name:</label>
                                <input type="text" id="contact_person" name="contact_person" class="form-control" onfocus="this.placeholder = ''" onblur="this.placeholder = 'Enter contact person name'" placeholder="Contact Person Name" value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="contact_email">Contact Person Email:</label>
                                <input type="email" id="contact_email" name="contact_email" class="form-control" onfocus="this.placeholder = ''" onblur="this.placeholder = 'Enter contact person email'" placeholder="Contact Person Email" value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="country">Country:</label>
                                <!-- Removed nice-select class here -->
                                <select class="form-control" name="country" id="country" required>
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country_option): ?>
                                        <option value="<?php echo htmlspecialchars($country_option); ?>" <?php echo (($_POST['country'] ?? '') == $country_option) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($country_option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="state">State:</label>
                                <!-- Removed nice-select class here -->
                                <select class="form-control" name="state" id="state" required>
                                    <option value="">Select State</option>
                                    <?php 
                                    // Populate initial state options if a country was previously selected via POST
                                    $selected_country_post = $_POST['country'] ?? '';
                                    if (!empty($selected_country_post) && isset($country_states[$selected_country_post])) {
                                        foreach ($country_states[$selected_country_post] as $state_option):
                                            if ($state_option === '') continue; // Skip the initial empty option if it exists in sub-array
                                    ?>
                                            <option value="<?php echo htmlspecialchars($state_option); ?>" <?php echo (($_POST['state'] ?? '') == $state_option) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($state_option); ?>
                                            </option>
                                    <?php 
                                        endforeach;
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Address:</label>
                        <input type="text" id="address" name="address" class="form-control" onfocus="this.placeholder = ''" onblur="this.placeholder = 'Enter street address, building name, etc.'" placeholder="Address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="city">City:</label>
                        <input type="text" id="city" name="city" class="form-control" onfocus="this.placeholder = ''" onblur="this.placeholder = 'Enter city'" placeholder="City" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Company Location (from Map):</label>
                        <input type="text" id="location_address" name="location_address" class="form-control" placeholder="Select location on map" value="<?php echo htmlspecialchars($_POST['location_address'] ?? ''); ?>" readonly required>
                        <input type="hidden" id="location_lat" name="location_lat" value="<?php echo htmlspecialchars($_POST['location_lat'] ?? ''); ?>">
                        <input type="hidden" id="location_lon" name="location_lon" value="<?php echo htmlspecialchars($_POST['location_lon'] ?? ''); ?>">
                        <button type="button" class="btn btn-info mt-2" data-toggle="modal" data-target="#locationModal" id="openLocationModalBtn">Select Company Location</button>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="password">Password:</label>
                                <input type="password" id="password" name="password" class="form-control" onfocus="this.placeholder = ''" onblur="this.placeholder = 'Enter password'" placeholder="Password" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password:</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" onfocus="this.placeholder = ''" onblur="this.placeholder = 'Confirm password'" placeholder="Confirm Password" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mt-3">
                        <button type="submit" class="button button-contactForm boxed-btn">Register Company</button>
                    </div>
                </form>
                <div class="login-link">
                    Already have an account? <a href="company_login.php">Login here</a>
                </div>
            </div>
        </section>
        <!-- Company Registration Form End -->
    </main>

    <!-- Location Modal -->
    <div class="modal fade" id="locationModal" tabindex="-1" role="dialog" aria-labelledby="locationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="locationModalLabel">Select Company Location</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- This div will be hidden/shown based on 'Select Company Location' button click -->
                    <div id="mapContentContainer" style="display: none;"> 
                        <div class="map-controls mb-3">
                            <button type="button" class="btn btn-primary" id="gpsLocationBtn"><i class="fas fa-crosshairs"></i> Use Current Location (GPS)</button>
                            <input type="text" id="searchLocationInput" class="form-control" placeholder="Search location by name...">
                            <button type="button" class="btn btn-secondary" id="searchLocationBtn"><i class="fas fa-search"></i> Search</button>
                        </div>
                        <div id="map"></div>
                    </div>
                    <div id="selectLocationPrompt" class="text-center p-3">
                        <p>Click "Confirm Location" button after selecting your location on the map.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="confirmLocationBtn">Confirm Location</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- End Location Modal -->

    <footer>
        <!-- Footer Start-->
        <div class="footer-area footer-bg footer-padding">
            <div class="container">
                <div class="row d-flex justify-content-between">
                    <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6">
                       <div class="single-footer-caption mb-50">
                         <div class="single-footer-caption mb-30">
                             <div class="footer-tittle">
                                 <h4>About Us</h4>
                                 <div class="footer-pera">
                                     <p>JobCrafter helps connect job seekers with opportunities and companies with talent.</p>
                                </div>
                             </div>
                         </div>

                       </div>
                    </div>
                    <div class="col-xl-3 col-lg-3 col-md-4 col-sm-5">
                        <div class="single-footer-caption mb-50">
                            <div class="footer-tittle">
                                <h4>Contact Info</h4>
                                <ul>
                                    <li>
                                    <p>Address :Your address goes
                                        here, your demo address.</p>
                                    </li>
                                    <li><a href="#">Phone : +8880 44338899</a></li>
                                    <li><a href="#">Email : info@jobcrafter.com</a></li>
                                </ul>
                            </div>

                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-3 col-md-4 col-sm-5">
                        <div class="single-footer-caption mb-50">
                            <div class="footer-tittle">
                                <h4>Important Link</h4>
                                <ul>
                                    <li><a href="#"> View Project</a></li>
                                    <li><a href="#">Contact Us</a></li>
                                    <li><a href="#">Testimonial</a></li>
                                    <li><a href="#">Properties</a></li>
                                    <li><a href="#">Support</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-3 col-md-4 col-sm-5">
                        <div class="single-footer-caption mb-50">
                            <div class="footer-tittle">
                                <h4>Newsletter</h4>
                                <div class="footer-pera footer-pera2">
                                 <p>Stay updated with the latest job opportunities.</p>
                             </div>
                             <div class="footer-form" >
                                 <div id="mc_embed_signup">
                                     <form target="_blank" action="https://spondonit.us12.list-manage.com/subscribe/post?u=1462626880ade1ac87bd9c93a&amp;id=92a4423d01"
                                     method="get" class="subscribe_form relative mail_part">
                                         <input type="email" name="email" id="newsletter-form-email" placeholder="Email Address"
                                         class="placeholder hide-on-focus" onfocus="this.placeholder = ''"
                                         onblur="this.placeholder = ' Email Address '">
                                         <div class="form-icon">
                                             <button type="submit" name="submit" id="newsletter-submit"
                                             class="email_icon newsletter-submit button-contactForm"><img src="assets/img/icon/form.png" alt=""></button>
                                         </div>
                                         <div class="mt-10 info"></div>
                                     </form>
                                 </div>
                             </div>
                            </div>
                        </div>
                    </div>
                </div>
               <!--  -->
               <div class="row footer-wejed justify-content-between">
                       <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6">
                          <!-- footer-change-logo -->
                          <div class="footer-logo mb-20">
                            <a href="index.html"><img src="assets/img/logo/logo2_footer.png" alt=""></a>
                          </div>
                       </div>
                       <div class="col-xl-3 col-lg-3 col-md-4 col-sm-5">
                        <div class="footer-tittle-bottom">
                            <span>5000+</span>
                            <p>Talented Hunter</p>
                        </div>
                       </div>
                       <div class="col-xl-3 col-lg-3 col-md-4 col-sm-5">
                            <div class="footer-tittle-bottom">
                                <span>451</span>
                                <p>Talented Hunter</p>
                            </div>
                       </div>
                       <div class="col-xl-3 col-lg-3 col-md-4 col-sm-5">
                            <div class="footer-tittle-bottom">
                                <span>568</span>
                                <p>Talented Hunter</p>
                            </div>
                       </div>
               </div>
            </div>
        </div>
        <!-- footer-bottom area -->
        <div class="footer-bottom-area footer-bg">
            <div class="container">
                <div class="footer-border">
                     <div class="row d-flex justify-content-between align-items-center">
                         <div class="col-xl-10 col-lg-10 ">
                             <div class="footer-copy-right">
                                 <p><!-- Link back to Colorlib can't be removed. Template is licensed under CC BY 3.0. -->
  Copyright &copy;<script>document.write(new Date().getFullYear());</script> All rights reserved | This template is made with <i class="fa fa-heart" aria-hidden="true"></i> by <a href="https://colorlib.com" target="_blank">Colorlib</a>
  <!-- Link back to Colorlib can't be removed. Template is licensed under CC BY 3.0. --></p>
                             </div>
                         </div>
                         <div class="col-xl-2 col-lg-2">
                             <div class="footer-social f-right">
                                 <a href="#"><i class="fab fa-facebook-f"></i></a>
                                 <a href="#"><i class="fab fa-twitter"></i></a>
                                 <a href="#"><i class="fas fa-globe"></i></a>
                                 <a href="#"><i class="fab fa-behance"></i></a>
                             </div>
                         </div>
                     </div>
                </div>
            </div>
        </div>
        <!-- Footer End-->
    </footer>

    <!-- All JS Custom Plugins Link Here here -->
    <script src="./assets/js/vendor/modernizr-3.5.0.min.js"></script>
    <script src="./assets/js/vendor/jquery-1.12.4.min.js"></script>
    <script src="./assets/js/popper.min.js"></script>
    <script src="./assets/js/bootstrap.min.js"></script>
    <!-- Jquery Mobile Menu -->
    <script src="./assets/js/jquery.slicknav.min.js"></script>

    <!-- Jquery Slick , Owl-Carousel Plugins -->
    <script src="./assets/js/owl.carousel.min.js"></script>
    <script src="./assets/js/slick.min.js"></script>
    <script src="./assets/js/price_rangs.js"></script>
    
    <!-- One Page, Animated-HeadLin -->
    <script src="./assets/js/wow.min.js"></script>
    <script src="./assets/js/animated.headline.js"></script>
    <script src="./assets/js/jquery.magnific-popup.js"></script>

    <!-- Scrollup, nice-select, sticky -->
    <script src="./assets/js/jquery.scrollUp.min.js"></script>
    <!-- Removed nice-select.js -->
    <!-- <script src="./assets/js/jquery.nice-select.min.js"></script> --> 
    <script src="./assets/js/jquery.sticky.js"></script>
    
    <!-- contact js -->
    <script src="./assets/js/contact.js"></script>
    <script src="./assets/js/jquery.form.js"></script>
    <script src="./assets/js/jquery.validate.min.js"></script>
    <script src="./assets/js/mail-script.js"></script>
    <script src="./assets/js/jquery.ajaxchimp.min.js"></script>
    
    <!-- Jquery Plugins, main Jquery -->	
    <script src="./assets/js/plugins.js"></script>
    <script src="./assets/js/main.js"></script>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Dynamically update the copyright year
        document.addEventListener('DOMContentLoaded', function() {
            const currentYear = new Date().getFullYear();
            const copyrightElement = document.querySelector('.footer-copy-right p');
            if (copyrightElement) {
                // Ensure to only update the dynamic year part, preserving Colorlib attribution
                let copyrightText = copyrightElement.textContent;
                copyrightElement.textContent = copyrightText.replace(/Copyright ©\d{4}/, `Copyright ©${currentYear}`);
            }

            // Store the PHP generated country_states map in a JS variable
            const allCountryStates = <?php echo json_encode($country_states); ?>;

            // Initialize Select2 for country dropdown
            $('#country').select2({
                placeholder: "Select Country",
                allowClear: true,
                width: '100%', // Ensure it takes the full width
                dropdownParent: $('#country').parent(), // Important for modal compatibility
                theme: 'default' // Use default theme, which is customizable
            });

            // Initialize Select2 for state dropdown
            $('#state').select2({
                placeholder: "Select State",
                allowClear: true,
                width: '100%',
                dropdownParent: $('#state').parent(), // Important for modal compatibility
                theme: 'default' // Use default theme, which is customizable
            });

            // Event listener for country change to update state dropdown
            $('#country').on('change', function() {
                var selectedCountry = $(this).val();
                var stateSelect = $('#state');
                var currentSelectedState = stateSelect.val(); // Preserve current selection if possible

                stateSelect.empty(); // Clear existing options

                // Add default "Select State" option
                stateSelect.append(new Option('Select State', '', true, true)); // Default selected option

                // Check if the selected country has specific states defined in our data
                if (allCountryStates[selectedCountry]) {
                    // Populate states for the selected country, skipping the initial empty option if it exists in the sub-array
                    $.each(allCountryStates[selectedCountry], function(index, value) {
                        if (value !== '') { // Avoid adding duplicate empty option if it exists
                            stateSelect.append(new Option(value, value));
                        }
                    });
                }

                // Attempt to re-select the previously selected state if it still exists
                if (currentSelectedState && stateSelect.find('option[value="' + currentSelectedState + '"]').length) {
                    stateSelect.val(currentSelectedState).trigger('change'); // Trigger change for Select2 to update
                } else {
                    stateSelect.val('').trigger('change'); // Clear selection if old state is not available
                }
            }).trigger('change'); // Trigger on load to set initial state options based on default or POST data
        });


        // --- Leaflet Map Integration ---
        let map;
        let marker;

        // Function to show/hide map content within the modal
        function toggleMapContent(show) {
            if (show) {
                $('#mapContentContainer').show();
                $('#selectLocationPrompt').hide();
                // Ensure map is initialized and invalidates size to display correctly
                if (map) { 
                    map.invalidateSize();
                }
            } else {
                $('#mapContentContainer').hide();
                $('#selectLocationPrompt').show();
            }
        }

        // Initial state: hide map content when the page loads
        toggleMapContent(false);

        // Initialize map when the modal is shown
        $('#locationModal').on('shown.bs.modal', function () {
            toggleMapContent(true); // Show map content when modal is opened

            if (!map) { // Initialize map only once
                // Default to a central location (e.g., India)
                const defaultLocation = [20.5937, 78.9629]; 

                map = L.map('map', {attributionControl: false}).setView(defaultLocation, 5); // Set view to default location and zoom, disable default attribution

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    // attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' // Removed or set to empty
                }).addTo(map);

                marker = L.marker(defaultLocation, { draggable: true }).addTo(map);

                // Update marker position and get address on drag end
                marker.on('dragend', function(event) {
                    const latLng = marker.getLatLng();
                    updateLocationFields(latLng.lat, latLng.lng);
                });

                // Update marker position and get address on map click
                map.on('click', function(event) {
                    marker.setLatLng(event.latlng);
                    updateLocationFields(event.latlng.lat, event.latlng.lng);
                });

                // If location_lat and location_lon are already set (e.g., from a previous submission attempt)
                // Set the map view and marker to that location
                const initialLat = parseFloat(document.getElementById('location_lat').value);
                const initialLon = parseFloat(document.getElementById('location_lon').value);
                if (!isNaN(initialLat) && !isNaN(initialLon) && initialLat !== 0 && initialLon !== 0) {
                    const initialLatLng = [initialLat, initialLon];
                    map.setView(initialLatLng, 12);
                    marker.setLatLng(initialLatLng);
                    // Also update the address field for the initial marker position
                    updateLocationFields(initialLat, initialLon); 
                }
            }
            map.invalidateSize(); // Invalidate map size to ensure it renders correctly after modal opens
        });

        // Add a specific listener for modal hidden event to reset map content state
        $('#locationModal').on('hidden.bs.modal', function () {
            toggleMapContent(false); // Hide map content when modal is closed
        });


        // Function to update form fields with lat, lon, and address using Nominatim reverse geocoding
        function updateLocationFields(lat, lon) {
            document.getElementById('location_lat').value = lat.toFixed(6);
            document.getElementById('location_lon').value = lon.toFixed(6);

            const nominatimUrl = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}`;
            
            fetch(nominatimUrl)
                .then(response => response.json())
                .then(data => {
                    if (data && data.display_name) {
                        document.getElementById('location_address').value = data.display_name;
                    } else {
                        document.getElementById('location_address').value = 'Address not found';
                    }
                })
                .catch(error => {
                    console.error('Error during reverse geocoding:', error);
                    document.getElementById('location_address').value = 'Error fetching address';
                });
        }

        // Event listener for "Use Current Location (GPS)" button
        document.getElementById('gpsLocationBtn').addEventListener('click', function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const latLng = [position.coords.latitude, position.coords.longitude];
                    map.setView(latLng, 15); // Zoom in closer for current location
                    marker.setLatLng(latLng);
                    updateLocationFields(latLng[0], latLng[1]);
                }, function(error) {
                    // More specific error messages based on GeolocationPositionError.code
                    let errorMessage = 'Error getting GPS location: ';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += "User denied the request for Geolocation. Please allow location access in your browser settings.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += "Location information is unavailable. This might be due to network issues or inability to retrieve location.";
                            break;
                        case error.TIMEOUT:
                            errorMessage += "The request to get user location timed out. Try again with a better signal.";
                            break;
                        case error.UNKNOWN_ERROR:
                            errorMessage += "An unknown error occurred.";
                            break;
                    }
                    alert(errorMessage); // Using alert as per existing pattern; consider a custom modal in production
                    console.error('Error getting GPS location:', error);
                }, {
                    enableHighAccuracy: true, // Request high accuracy
                    timeout: 10000,          // 10 seconds timeout
                    maximumAge: 0            // Don't use cached position
                });
            } else {
                alert('Geolocation is not supported by this browser.'); // Using alert as per existing pattern
            }
        });

        // Event listener for "Search" button
        document.getElementById('searchLocationBtn').addEventListener('click', function() {
            const address = document.getElementById('searchLocationInput').value;
            if (address) {
                const nominatimSearchUrl = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(address)}`;
                
                fetch(nominatimSearchUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.length > 0) {
                            const lat = parseFloat(data[0].lat);
                            const lon = parseFloat(data[0].lon);
                            const latLng = [lat, lon];
                            map.setView(latLng, 12); // Zoom to searched location
                            marker.setLatLng(latLng);
                            updateLocationFields(lat, lon);
                        } else {
                            alert('Location not found for: ' + address); // Using alert as per existing pattern
                        }
                    })
                    .catch(error => {
                        console.error('Error during geocoding search:', error);
                        alert('Error searching for location.'); // Using alert as per existing pattern
                    });
            } else {
                alert('Please enter a location to search.'); // Using alert as per existing pattern
            }
        });

        // Event listener for "Confirm Location" button in the modal
        document.getElementById('confirmLocationBtn').addEventListener('click', function() {
            // The location_lat, location_lon, and location_address fields are already updated
            // by updateLocationFields function when marker is dragged or map is clicked.
            // Just close the modal.
            $('#locationModal').modal('hide');
        });
    </script>
</body>
</html>