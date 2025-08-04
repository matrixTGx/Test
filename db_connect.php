<?php
// php/db_connect.php

// Database connection details
$servername = "localhost";
$username = "root"; // Your MySQL username (e.g., 'root' for XAMPP/WAMP)
$password = "";     // Your MySQL password (e.g., '' for XAMPP/WAMP default)
$dbname = "jobcraftr"; // Your database name

// --- TEMPORARY: Enable error reporting for debugging connection issues ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ---------------------------------------------------------------------

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // This will now show a detailed error if the connection fails
    die("Database Connection failed: " . $conn->connect_error);
}

// Set character set to UTF-8 for proper handling of special characters
$conn->set_charset("utf8mb4");
?>