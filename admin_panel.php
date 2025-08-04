<?php
session_start();
require_once 'php/db_connect.php'; // Database connection

// Check if admin is logged in, otherwise redirect to login page
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['admin_error'] = "Access denied. Please login as admin.";
    header("Location: admin_login.php");
    exit();
}

// --- Start of ALL Action Handling Logic (Unified) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $type = $_POST['type'] ?? ''; // Used for worker/company actions
    $message_id = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT); // Used for contact message actions

    // Determine if it's an AJAX request to return JSON
    $is_ajax_request = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Default redirect anchor (for general actions or errors)
    $redirect_anchor = '';

    // Set specific redirect anchors based on action type
    if ($action === 'edit' || in_array($action, ['ban', 'unban', 'delete'])) {
        if ($type === 'worker') {
            $redirect_anchor = '#all-workers-section';
        } elseif ($type === 'company') {
            $redirect_anchor = '#all-companies-section';
        }
    } elseif (in_array($action, ['approve', 'reject'])) {
        // Approving/rejecting companies might best redirect to the pending section or all companies section.
        // For simplicity, let's direct to all companies section after approval/rejection.
        $redirect_anchor = '#all-companies-section';
    } elseif (in_array($action, ['mark_as_read', 'archive_message', 'unarchive_message', 'delete_message'])) {
        $redirect_anchor = '#contact-messages-section';
    }


    if ($is_ajax_request) {
        header('Content-Type: application/json'); // For AJAX responses
        $response = ['success' => false, 'message' => ''];
    } else {
        // For standard form submissions (e.g., approve/reject/ban/unban/delete for users/companies)
        $_SESSION['admin_message_temp'] = ''; // Use a temporary session variable
        $_SESSION['admin_error_temp'] = '';
    }

    try {
        // --- Contact Message Actions ---
        if (in_array($action, ['mark_as_read', 'archive_message', 'unarchive_message', 'delete_message'])) {
            if (!$message_id || $message_id <= 0) {
                if ($is_ajax_request) {
                    $response['message'] = "Invalid message ID.";
                } else {
                    $_SESSION['admin_error_temp'] = "Invalid message ID.";
                }
            } else {
                if ($action === 'mark_as_read') {
                    $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
                    if (!$stmt) throw new Exception("Failed to prepare update statement: " . $conn->error);
                    $stmt->bind_param("i", $message_id);
                    if ($stmt->execute()) {
                        $msg = "Message marked as read.";
                        if ($is_ajax_request) $response['success'] = true;
                    } else {
                        $msg = "Failed to mark message as read: " . $stmt->error;
                    }
                } elseif ($action === 'archive_message') {
                    $stmt = $conn->prepare("UPDATE contact_messages SET status = 'archived' WHERE id = ?");
                    if (!$stmt) throw new Exception("Failed to prepare update statement: " . $conn->error);
                    $stmt->bind_param("i", $message_id);
                    if ($stmt->execute()) {
                        $msg = "Message archived successfully.";
                        if ($is_ajax_request) $response['success'] = true;
                    } else {
                        $msg = "Failed to archive message: " . $stmt->error;
                    }
                } elseif ($action === 'unarchive_message') {
                    $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?"); // Unarchiving sets it back to 'read'
                    if (!$stmt) throw new Exception("Failed to prepare update statement: " . $conn->error);
                    $stmt->bind_param("i", $message_id);
                    if ($stmt->execute()) {
                        $msg = "Message unarchived successfully.";
                        if ($is_ajax_request) $response['success'] = true;
                    } else {
                        $msg = "Failed to unarchive message: " . $stmt->error;
                    }
                } elseif ($action === 'delete_message') {
                    $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
                    if (!$stmt) throw new Exception("Failed to prepare delete statement: " . $conn->error);
                    $stmt->bind_param("i", $message_id);
                    if ($stmt->execute()) {
                        $msg = "Message deleted successfully.";
                        if ($is_ajax_request) $response['success'] = true;
                    } else {
                        $msg = "Failed to delete message: " . $stmt->error;
                    }
                }
                if (isset($stmt) && $stmt) $stmt->close();

                if ($is_ajax_request) {
                    $response['message'] = $msg;
                } else {
                    if ($response['success'] ?? false) { // Check if success is set and true
                        $_SESSION['admin_message_temp'] = $msg;
                    } else {
                        $_SESSION['admin_error_temp'] = $msg;
                    }
                }
            }
        }
        // --- End Contact Message Actions ---

        // --- Worker/Company Management Actions ---
        elseif ($action === 'edit') {
            if (!$id || $id <= 0) {
                 $msg = "Invalid ID for edit.";
                 if ($is_ajax_request) $response['message'] = $msg;
                 else $_SESSION['admin_error_temp'] = $msg;
            } else {
                $table = '';
                if ($type === 'worker') {
                    $table = 'workers';
                } elseif ($type === 'company') {
                    $table = 'companies';
                } else {
                    $msg = "Invalid entity type for edit.";
                    if ($is_ajax_request) $response['message'] = $msg;
                    else $_SESSION['admin_error_temp'] = $msg;
                }

                if ($table) {
                    $fields_to_update = [];
                    $bind_types = '';
                    $bind_values = [];

                    // Dynamically add fields if they are provided and not empty
                    if (isset($_POST['name']) && $_POST['name'] !== '') {
                        $fields_to_update[] = 'name = ?';
                        $bind_types .= 's';
                        $bind_values[] = $_POST['name'];
                    }
                    if (isset($_POST['email']) && $_POST['email'] !== '' && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                        $fields_to_update[] = 'email = ?';
                        $bind_types .= 's';
                        $bind_values[] = $_POST['email'];
                    }

                    if ($type === 'worker') {
                        if (isset($_POST['age']) && $_POST['age'] !== '') {
                            $fields_to_update[] = 'age = ?';
                            $bind_types .= 'i';
                            $bind_values[] = $_POST['age'];
                        }
                        if (isset($_POST['gender']) && $_POST['gender'] !== '') {
                            $fields_to_update[] = 'gender = ?';
                            $bind_types .= 's';
                            $bind_values[] = $_POST['gender'];
                        }
                        if (isset($_POST['mobile']) && $_POST['mobile'] !== '') {
                            $fields_to_update[] = 'mobile = ?';
                            $bind_types .= 's';
                            $bind_values[] = $_POST['mobile'];
                        }
                        if (isset($_POST['country']) && $_POST['country'] !== '') {
                            $fields_to_update[] = 'country = ?';
                            $bind_types .= 's';
                            $bind_values[] = $_POST['country'];
                        }
                        if (isset($_POST['state']) && $_POST['state'] !== '') {
                            $fields_to_update[] = 'state = ?';
                            $bind_types .= 's';
                            $bind_values[] = $_POST['state'];
                        }
                    } elseif ($type === 'company') {
                        if (isset($_POST['number']) && $_POST['number'] !== '') {
                            $fields_to_update[] = 'phone_number = ?'; // DB column name
                            $bind_types .= 's';
                            $bind_values[] = $_POST['number'];
                        }
                        if (isset($_POST['country']) && $_POST['country'] !== '') {
                            $fields_to_update[] = 'country = ?';
                            $bind_types .= 's';
                            $bind_values[] = $_POST['country'];
                        }
                        if (isset($_POST['state']) && $_POST['state'] !== '') {
                            $fields_to_update[] = 'state = ?';
                            $bind_types .= 's';
                            $bind_values[] = $_POST['state'];
                        }
                        if (isset($_POST['location_lat']) && $_POST['location_lat'] !== '') {
                            $fields_to_update[] = 'location_lat = ?';
                            $bind_types .= 'd'; // Assuming double for latitude
                            $bind_values[] = $_POST['location_lat'];
                        }
                        if (isset($_POST['location_lon']) && $_POST['location_lon'] !== '') {
                            $fields_to_update[] = 'location_lon = ?';
                            $bind_types .= 'd'; // Assuming double for longitude
                            $bind_values[] = $_POST['location_lon'];
                        }
                        if (isset($_POST['location_address']) && $_POST['location_address'] !== '') {
                            $fields_to_update[] = 'location_address = ?';
                            $bind_types .= 's';
                            $bind_values[] = $_POST['location_address'];
                        }
                    }

                    // Handle password update separately if provided
                    if (isset($_POST['password']) && $_POST['password'] !== '') {
                        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $fields_to_update[] = 'password = ?';
                        $bind_types .= 's';
                        $bind_values[] = $hashed_password;
                    }

                    if (empty($fields_to_update)) {
                        $msg = "No changes submitted for " . $type . ".";
                        if ($is_ajax_request) $response['message'] = $msg;
                        else $_SESSION['admin_warning'] = $msg; // Use admin_warning for no changes
                    } else {
                        $sql = "UPDATE " . $table . " SET " . implode(', ', $fields_to_update) . " WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        // Add ID type and value to the end for WHERE clause
                        $bind_types .= 'i';
                        $bind_values[] = $id;

                        // Bind parameters using call_user_func_array for dynamic arguments
                        call_user_func_array([$stmt, 'bind_param'], array_merge([$bind_types], $bind_values));

                        if ($stmt->execute()) {
                            $msg = ucfirst($type) . " updated successfully!";
                            if ($is_ajax_request) $response['success'] = true;
                        } else {
                            $msg = "Failed to update " . $type . ": " . $stmt->error;
                        }
                        $stmt->close();
                    }

                    if ($is_ajax_request) {
                        $response['message'] = $msg;
                    } else {
                        if ($response['success'] ?? false) { // Check if success is set and true
                            $_SESSION['admin_message_temp'] = $msg;
                        } else {
                            $_SESSION['admin_error_temp'] = $msg;
                        }
                    }
                }
            }
        } elseif ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE companies SET status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $msg = "Company approved successfully!";
                $_SESSION['admin_message_temp'] = $msg;
            } else {
                $msg = "Failed to approve company: " . $stmt->error;
                $_SESSION['admin_error_temp'] = $msg;
            }
            $stmt->close();
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE companies SET status = 'rejected' WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $msg = "Company rejected successfully!";
                $_SESSION['admin_message_temp'] = $msg;
            } else {
                $msg = "Failed to reject company: " . $stmt->error;
                $_SESSION['admin_error_temp'] = $msg;
            }
            $stmt->close();
        } elseif ($action === 'ban') {
            if ($type === 'worker') {
                $stmt = $conn->prepare("UPDATE workers SET status = 'banned' WHERE id = ?");
            } elseif ($type === 'company') {
                $stmt = $conn->prepare("UPDATE companies SET status = 'banned' WHERE id = ?");
            }
            if (isset($stmt)) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $msg = ucfirst($type) . " banned successfully!";
                    $_SESSION['admin_message_temp'] = $msg;
                } else {
                    $msg = "Failed to ban " . $type . ": " . $stmt->error;
                    $_SESSION['admin_error_temp'] = $msg;
                }
                $stmt->close();
            } else {
                 $_SESSION['admin_error_temp'] = "Invalid type for ban action.";
            }
        } elseif ($action === 'unban') {
            if ($type === 'worker') {
                $stmt = $conn->prepare("UPDATE workers SET status = 'active' WHERE id = ?");
            } elseif ($type === 'company') {
                $stmt = $conn->prepare("UPDATE companies SET status = 'approved' WHERE id = ?"); // Companies go back to 'approved'
            }
            if (isset($stmt)) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $msg = ucfirst($type) . " unbanned successfully!";
                    $_SESSION['admin_message_temp'] = $msg;
                } else {
                    $msg = "Failed to unban " . $type . ": " . $stmt->error;
                    $_SESSION['admin_error_temp'] = $msg;
                }
                $stmt->close();
            } else {
                $_SESSION['admin_error_temp'] = "Invalid type for unban action.";
            }
        } elseif ($action === 'delete') {
            if ($type === 'worker') {
                $stmt = $conn->prepare("DELETE FROM workers WHERE id = ?");
            } elseif ($type === 'company') {
                $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
            }
            if (isset($stmt)) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $msg = ucfirst($type) . " deleted successfully!";
                    $_SESSION['admin_message_temp'] = $msg;
                } else {
                    $msg = "Failed to delete " . $type . ": " . $stmt->error;
                    $_SESSION['admin_error_temp'] = $msg;
                }
                $stmt->close();
            } else {
                $_SESSION['admin_error_temp'] = "Invalid type for delete action.";
            }
        } else {
            if ($is_ajax_request) {
                $response['message'] = "Invalid action.";
            } else {
                $_SESSION['admin_error_temp'] = "Invalid action.";
            }
        }

    } catch (Exception $e) {
        error_log("Admin action error: " . $e->getMessage());
        if ($is_ajax_request) {
            $response['message'] = "An internal server error occurred: " . $e->getMessage();
        } else {
            $_SESSION['admin_error_temp'] = "An internal server error occurred: " . $e->getMessage();
        }
    } finally {
        // Ensure database connection is closed if still open
        if (isset($conn) && $conn->ping()) {
            $conn->close();
        }
    }

    // If it was an AJAX request, echo JSON response and exit
    if ($is_ajax_request) {
        echo json_encode($response);
        exit();
    } else {
        // For standard form submissions, transfer temporary messages to main session and redirect
        if (!empty($_SESSION['admin_message_temp'])) {
            $_SESSION['admin_message'] = $_SESSION['admin_message_temp'];
            unset($_SESSION['admin_message_temp']);
        }
        if (!empty($_SESSION['admin_error_temp'])) {
            $_SESSION['admin_error'] = $_SESSION['admin_error_temp'];
            unset($_SESSION['admin_error_temp']);
        }
        // Redirect to the appropriate section based on the action
        $current_filter_param = isset($_GET['message_status_filter']) ? '?message_status_filter=' . htmlspecialchars($_GET['message_status_filter']) : '';
        header("Location: " . $_SERVER['PHP_SELF'] . $current_filter_param . $redirect_anchor);
        exit();
    }
}
// --- End of ALL Action Handling Logic ---

// --- Data Fetching for Display ---
// This part remains largely the same as before, fetching data for the dashboard.
// Fetch counts for dashboard summary
$total_pending_companies = 0;
$sql_count_pending = "SELECT COUNT(*) AS count FROM companies WHERE status = 'pending'";
$result_count_pending = $conn->query($sql_count_pending);
if ($result_count_pending && $result_count_pending->num_rows > 0) {
    $total_pending_companies = $result_count_pending->fetch_assoc()['count'];
}

$total_workers = 0;
$sql_count_workers = "SELECT COUNT(*) AS count FROM workers";
$result_count_workers = $conn->query($sql_count_workers);
if ($result_count_workers && $result_count_workers->num_rows > 0) {
    $total_workers = $result_count_workers->fetch_assoc()['count'];
}

$total_companies = 0;
$sql_count_companies = "SELECT COUNT(*) AS count FROM companies";
$result_count_companies = $conn->query($sql_count_companies);
if ($result_count_companies && $result_count_companies->num_rows > 0) {
    $total_companies = $result_count_companies->fetch_assoc()['count'];
}

// Fetch count of new contact messages
$total_new_contact_messages = 0;
$sql_count_new_messages = "SELECT COUNT(*) AS count FROM contact_messages WHERE status = 'new'";
$result_count_new_messages = $conn->query($sql_count_new_messages);
if ($result_count_new_messages && $result_count_new_messages->num_rows > 0) {
    $total_new_contact_messages = $result_count_new_messages->fetch_assoc()['count'];
}


// Fetch pending companies
$pending_companies = [];
$sql_pending = "SELECT id, name, email, phone_number, country, state, location_address, created_at FROM companies WHERE status = 'pending' ORDER BY created_at DESC";
$result_pending = $conn->query($sql_pending);
if ($result_pending && $result_pending->num_rows > 0) {
    while ($row = $result_pending->fetch_assoc()) {
        $pending_companies[] = $row;
    }
}
// Fetch all workers
$all_workers = [];
$sql_workers = "SELECT id, name, age, gender, mobile, email, country, state, status, created_at FROM workers ORDER BY created_at DESC";
$result_workers = $conn->query($sql_workers);
if ($result_workers && $result_workers->num_rows > 0) {
    while ($row = $result_workers->fetch_assoc()) {
        $all_workers[] = $row;
    }
}

// Fetch all companies (approved, banned, rejected)
$all_companies = [];
$sql_companies = "SELECT id, name, email, phone_number, country, state, location_address, status, created_at FROM companies ORDER BY created_at DESC";
$result_companies = $conn->query($sql_companies);
if ($result_companies && $result_companies->num_rows > 0) {
    while ($row = $result_companies->fetch_assoc()) {
        $all_companies[] = $row;
    }
}

// Fetch all contact messages based on filter
$contact_messages = [];
$current_message_filter = htmlspecialchars(trim($_GET['message_status_filter'] ?? 'all')); // Default to 'all'

$sql_contact_messages = "SELECT id, name, email, subject, phone_number, message, status, received_at FROM contact_messages";
$params_contact_messages = [];
$types_contact_messages = "";

if ($current_message_filter !== 'all') {
    $sql_contact_messages .= " WHERE status = ?";
    $params_contact_messages[] = $current_message_filter;
    $types_contact_messages .= "s";
}
$sql_contact_messages .= " ORDER BY received_at DESC";

$stmt_contact_messages = $conn->prepare($sql_contact_messages);
if ($stmt_contact_messages) {
    if (!empty($params_contact_messages)) {
        $stmt_contact_messages->bind_param($types_contact_messages, ...$params_contact_messages);
    }
    $stmt_contact_messages->execute();
    $result_contact_messages = $stmt_contact_messages->get_result();
    while ($row = $result_contact_messages->fetch_assoc()) {
        $contact_messages[] = $row;
    }
    $stmt_contact_messages->close();
} else {
    error_log("Failed to prepare statement for contact messages: " . $conn->error);
}

// IMPORTANT: Close the connection here after all data fetching is done
// This was already present, just re-iterating its importance.
if (isset($conn) && $conn->ping()) { // Ensure connection is still valid before closing
    $conn->close();
}
?>
<!DOCTYPE html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Admin Panel - Job Board Platform</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="site.webmanifest">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.ico">

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
    <link rel="stylesheet" href="assets/css/nice-select.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">

    <style>
        .dashboard-container {
            padding: 20px;
            max-width: 1200px;
            margin: 20px auto;
            background-color: white;
            border-radius: 8px; /* Consistent border-radius */
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); /* Consistent box-shadow */
        }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #badbcc; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .section { margin-bottom: 40px; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; background-color: #fcfcfc; }
        .section h3 { color: #007bff; margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #e9ecef; color: #495057; }
        .actions button {
            padding: 6px 12px;
            margin: 2px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .approve-btn { background-color: #28a745; color: white; }
        .approve-btn:hover { background-color: #218838; }
        .reject-btn, .delete-btn { background-color: #dc3545; color: white; }
        .reject-btn:hover, .delete-btn:hover { background-color: #c82333; }
        .ban-btn { background-color: #ffc107; color: #333; }
        .ban-btn:hover { background-color: #e0a800; }
        .unban-btn { background-color: #17a2b8; color: white; }
        .unban-btn:hover { background-color: #138496; }
        .edit-btn { background-color: #6c757d; color: white; }
        .edit-btn:hover { background-color: #5a6268; }
        .read-btn { background-color: #4CAF50; color: white; } /* Green for Mark as Read */
        .read-btn:hover { background-color: #45a049; }
        .archive-btn { background-color: #ff8c00; color: white; } /* Orange for Archive */
        .archive-btn:hover { background-color: #cc7000; }
        .unarchive-btn { background-color: #663399; color: white; } /* Purple for Unarchive */
        .unarchive-btn:hover { background-color: #552288; }
        .view-msg-btn { background-color: #007bff; color: white; } /* Blue for View Message */
        .view-msg-btn:hover { background-color: #0056b3; }

        /* Status badges */
        .status-new { background-color: #f0ad4e; color: white; padding: 3px 8px; border-radius: 4px; } /* Orange for new */
        .status-read { background-color: #5cb85c; color: white; padding: 3px 8px; border-radius: 4px; } /* Green for read */
        .status-archived { background-color: #5bc0de; color: white; padding: 3px 8px; border-radius: 4px; } /* Light blue for archived */

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            position: relative;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2); /* Added shadow to modal */
        }
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .modal-form .form-group {
            margin-bottom: 15px;
        }
        .modal-form label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        .modal-form input[type="text"],
        .modal-form input[type="number"],
        .modal-form input[type="email"],
        .modal-form input[type="password"],
        .modal-form select {
            width: calc(100% - 22px); /* Account for padding and border */
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .modal-form input[type="radio"] {
            margin-right: 5px;
        }
        .modal-form button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        .modal-form button:hover {
            background-color: #0056b3;
        }

        /* Dashboard Summary Cards */
        .summary-cards {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 40px;
        }
        .summary-card {
            background-color: #e9f5ff; /* Light blue background */
            border: 1px solid #cce5ff;
            border-left: 5px solid #007bff; /* Blue left border */
            border-radius: 8px;
            padding: 20px;
            flex: 1;
            min-width: 250px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .summary-card h4 {
            color: #007bff;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        .summary-card p {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        /* Message modal specific styles */
        #messageDetailsModal .modal-content {
            max-width: 700px;
        }
        #messageDetailsModal .message-content-box {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            white-space: pre-wrap; /* Preserves whitespace and line breaks */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin-top: 10px;
        }
        #messageDetailsModal .message-meta-info {
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        #messageDetailsModal .message-meta-info strong {
            color: #343a40;
        }
        /* Message filter buttons */
        .message-filters-buttons {
            margin-bottom: 20px;
        }
        .message-filters-buttons .btn {
            margin-right: 10px;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
        }
        .message-filters-buttons .btn.active {
            background-color: #0056b3;
            color: white;
            font-weight: bold;
        }
        /* Modal action buttons */
        .modal-footer-actions {
            text-align: right;
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .modal-footer-actions button {
            margin-left: 10px;
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
                                <a href="index.html"><img src="img/logo.png" style="height: 60px; width: 200px;" alt="JobCrafter Logo"></a>
                            </div>  
                        </div>
                        <div class="col-lg-9 col-md-9">
                            <div class="menu-wrapper">
                                <div class="main-menu">
                                    <nav class="d-none d-lg-block">
                                        <ul id="navigation">
                                            <li><a href="index.html">Home</a></li>
                                            <li><a href="about.html">About</a></li>
                                            <li><a href="contact.php">Contact</a></li>
                                            <li><a href="php/admin_logout.php">Logout</a></li> </ul>
                                    </nav>
                                </div>          
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
    <main>
        <div class="dashboard-container">
            <h2>Admin Dashboard</h2>

            <?php
            // Display messages transferred from temporary session variables
            if (isset($_SESSION['admin_message'])) {
                echo '<div class="message success">' . htmlspecialchars($_SESSION['admin_message']) . '</div>';
                unset($_SESSION['admin_message']);
            }
            if (isset($_SESSION['admin_error'])) {
                echo '<div class="message error">' . htmlspecialchars($_SESSION['admin_error']) . '</div>';
                unset($_SESSION['admin_error']);
            }
            if (isset($_SESSION['admin_warning'])) {
                echo '<div class="message warning">' . htmlspecialchars($_SESSION['admin_warning']) . '</div>';
                unset($_SESSION['admin_warning']);
            }
            ?>

            <div class="summary-cards">
                <div class="summary-card">
                    <h4>Pending Companies</h4>
                    <p><?php echo $total_pending_companies; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Total Workers</h4>
                    <p><?php echo $total_workers; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Total Companies</h4>
                    <p><?php echo $total_companies; ?></p>
                </div>
                <div class="summary-card">
                    <h4>New Contact Messages</h4>
                    <p><?php echo $total_new_contact_messages; ?></p>
                </div>
            </div>

            <div class="section">
                <h3>Pending Company Registrations</h3>
                <?php if (empty($pending_companies)): ?>
                    <p>No pending company registrations.</p>
                <?php else: ?>
                    <table id="pendingCompaniesTable" class="display">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Company Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Location</th>
                                <th>Registered On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_companies as $company): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($company['id']); ?></td>
                                    <td><?php echo htmlspecialchars($company['name']); ?></td>
                                    <td><?php echo htmlspecialchars($company['email']); ?></td>
                                    <td><?php echo htmlspecialchars($company['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($company['location_address']); ?></td>
                                    <td colspan=""><?php echo htmlspecialchars($company['created_at']); ?></td>
                                    <td class="actions">
                                        <form action="admin_panel.php" method="POST" style="display:inline-block;">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($company['id']); ?>">
                                            <input type="hidden" name="type" value="company">
                                            <button type="submit" name="action" value="approve" class="approve-btn">Approve</button>
                                        </form>
                                        <form action="admin_panel.php" method="POST" style="display:inline-block;">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($company['id']); ?>">
                                            <input type="hidden" name="type" value="company">
                                            <button type="submit" name="action" value="reject" class="reject-btn">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="section" id="all-workers-section">
                <h3>All Workers</h3>
                <?php if (empty($all_workers)): ?>
                    <p>No workers registered yet.</p>
                <?php else: ?>
                    <table id="allWorkersTable" class="display">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Mobile</th>
                                <th>Email</th>
                                <th>Country</th>
                                <th>State</th>
                                <th>Status</th>
                                <th>Registered On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_workers as $worker): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($worker['id']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['name']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['age']); ?></td>
                                    <td><?php htmlspecialchars($worker['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['mobile']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['email']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['country']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['state']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['status']); ?></td>
                                    <td><?php echo htmlspecialchars($worker['created_at']); ?></td>
                                    <td class="actions">
                                        <button class="edit-btn" onclick="openEditModal('worker', <?php echo htmlspecialchars(json_encode($worker)); ?>)">Edit</button>
                                        <?php if ($worker['status'] === 'active'): ?>
                                            <form action="admin_panel.php" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($worker['id']); ?>">
                                                <input type="hidden" name="type" value="worker">
                                                <button type="submit" name="action" value="ban" class="ban-btn">Ban</button>
                                            </form>
                                        <?php elseif ($worker['status'] === 'banned'): ?>
                                            <form action="admin_panel.php" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($worker['id']); ?>">
                                                <input type="hidden" name="type" value="worker">
                                                <button type="submit" name="action" value="unban" class="unban-btn">Unban</button>
                                            </form>
                                        <?php endif; ?>
                                        <form action="admin_panel.php" method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to permanently delete this worker?');">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($worker['id']); ?>">
                                            <input type="hidden" name="type" value="worker">
                                            <button type="submit" name="action" value="delete" class="delete-btn">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="section" id="all-companies-section">
                <h3>All Companies</h3>
                <?php if (empty($all_companies)): ?>
                    <p>No companies registered yet.</p>
                <?php else: ?>
                    <table id="allCompaniesTable" class="display">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Company Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Registered On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_companies as $company): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($company['id']); ?></td>
                                    <td><?php echo htmlspecialchars($company['name']); ?></td>
                                    <td><?php echo htmlspecialchars($company['email']); ?></td>
                                    <td><?php echo htmlspecialchars($company['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($company['location_address']); ?></td>
                                    <td><?php echo htmlspecialchars($company['status']); ?></td>
                                    <td><?php echo htmlspecialchars($company['created_at']); ?></td>
                                    <td class="actions">
                                        <button class="edit-btn" onclick="openEditModal('company', <?php echo htmlspecialchars(json_encode($company)); ?>)">Edit</button>
                                        <?php if ($company['status'] === 'approved'): ?>
                                            <form action="admin_panel.php" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($company['id']); ?>">
                                                <input type="hidden" name="type" value="company">
                                                <button type="submit" name="action" value="ban" class="ban-btn">Ban</button>
                                            </form>
                                        <?php elseif ($company['status'] === 'banned'): ?>
                                            <form action="admin_panel.php" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($company['id']); ?>">
                                                <input type="hidden" name="type" value="company">
                                                <button type="submit" name="action" value="unban" class="unban-btn">Unban</button>
                                            </form>
                                        <?php elseif ($company['status'] === 'pending'): ?>
                                            <form action="admin_panel.php" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($company['id']); ?>">
                                                <input type="hidden" name="type" value="company">
                                                <button type="submit" name="action" value="approve" class="approve-btn">Approve</button>
                                            </form>
                                            <form action="admin_panel.php" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($company['id']); ?>">
                                                <input type="hidden" name="type" value="company">
                                                <button type="submit" name="action" value="reject" class="reject-btn">Reject</button>
                                            </form>
                                        <?php endif; ?>
                                        <form action="admin_panel.php" method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to permanently delete this company?');">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($company['id']); ?>">
                                            <input type="hidden" name="type" value="company">
                                            <button type="submit" name="action" value="delete" class="delete-btn">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Contact Messages Section -->
            <div class="section" id="contact-messages-section">
                <h3>Contact Messages</h3>
                <div class="message-filters-buttons">
                    <a href="?message_status_filter=new#contact-messages-section" class="btn btn-primary <?php echo ($current_message_filter === 'new') ? 'active' : ''; ?>">New Messages</a>
                    <a href="?message_status_filter=read#contact-messages-section" class="btn btn-primary <?php echo ($current_message_filter === 'read') ? 'active' : ''; ?>">Read Messages</a>
                    <a href="?message_status_filter=archived#contact-messages-section" class="btn btn-primary <?php echo ($current_message_filter === 'archived') ? 'active' : ''; ?>">Archived Messages</a>
                    <a href="?message_status_filter=all#contact-messages-section" class="btn btn-primary <?php echo ($current_message_filter === 'all') ? 'active' : ''; ?>">All Messages</a>
                </div>

                <?php if (empty($contact_messages)): ?>
                    <p>No contact messages to display for the current filter: <?php echo htmlspecialchars(ucfirst($current_message_filter)); ?>.</p>
                <?php else: ?>
                    <table id="contactMessagesTable" class="display">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Phone</th>
                                <th>Received On</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contact_messages as $message): ?>
                                <tr data-message-id="<?php echo htmlspecialchars($message['id']); ?>">
                                    <td><?php echo htmlspecialchars($message['id']); ?></td>
                                    <td><?php echo htmlspecialchars($message['name']); ?></td>
                                    <td><?php echo htmlspecialchars($message['email']); ?></td>
                                    <td><?php echo htmlspecialchars($message['subject'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($message['phone_number'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($message['received_at']); ?></td>
                                    <td><span class="message-status-text status-<?php echo htmlspecialchars($message['status']); ?>"><?php echo htmlspecialchars(ucfirst($message['status'])); ?></span></td>
                                    <td class="actions">
                                        <button class="view-msg-btn" onclick="openMessageDetailsModal(<?php echo htmlspecialchars(json_encode($message)); ?>)">View Message</button>
                                        <?php if ($message['status'] === 'new'): ?>
                                            <button class="read-btn" data-action="mark_as_read">Mark as Read</button>
                                        <?php endif; ?>
                                        <?php if ($message['status'] === 'new' || $message['status'] === 'read'): ?>
                                            <button class="archive-btn" data-action="archive_message">Archive</button>
                                        <?php elseif ($message['status'] === 'archived'): ?>
                                            <button class="unarchive-btn" data-action="unarchive_message">Unarchive</button>
                                        <?php endif; ?>
                                        <button class="delete-btn" data-action="delete_message">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>


        </div>

        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="closeEditModal()">&times;</span>
                <h3 id="modalTitle">Edit User</h3>
                <form id="editForm" class="modal-form" action="admin_panel.php" method="POST">
                    <input type="hidden" id="edit_id" name="id">
                    <input type="hidden" id="edit_type" name="type"> <!-- This will be 'worker' or 'company' -->
                    <input type="hidden" name="action" value="edit">

                    <div class="form-group">
                        <label for="edit_name">Name:</label>
                        <!-- Removed 'required' attribute, server-side will handle if empty -->
                        <input type="text" id="edit_name" name="name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email:</label>
                        <!-- Removed 'required' attribute, server-side will handle if empty -->
                        <input type="email" id="edit_email" name="email" class="form-control">
                    </div>

                    <div id="workerFields" style="display:none;">
                        <div class="form-group">
                            <label for="edit_age">Age:</label>
                            <input type="number" id="edit_age" name="age" class="form-control" min="16" max="99">
                        </div>
                        <div class="form-group">
                            <label>Gender:</label><br>
                            <input type="radio" id="edit_male" name="gender" value="Male">
                            <label for="edit_male">Male</label>
                            <input type="radio" id="edit_female" name="gender" value="Female">
                            <label for="edit_female">Female</label>
                            <input type="radio" id="edit_other" name="gender" value="Other">
                            <label for="edit_other">Other</label>
                        </div>
                        <div class="form-group">
                            <label for="edit_mobile">Mobile Number:</label>
                            <input type="text" id="edit_mobile" name="mobile" class="form-control" pattern="[0-9]{10}" title="Please enter a 10-digit mobile number">
                        </div>
                        <div class="form-group">
                            <label for="edit_worker_country">Country:</label>
                            <input type="text" id="edit_worker_country" name="country" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_worker_state">State:</label>
                            <input type="text" id="edit_worker_state" name="state" class="form-control">
                        </div>
                    </div>

                    <div id="companyFields" style="display:none;">
                        <div class="form-group">
                            <label for="edit_number">Phone Number:</label>
                            <input type="text" id="edit_number" name="number" class="form-control" pattern="[0-9]{10}" title="Please enter a 10-digit phone number">
                        </div>
                        <div class="form-group">
                            <label for="edit_company_country">Country:</label>
                            <input type="text" id="edit_company_country" name="country" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_company_state">State:</label>
                            <input type="text" id="edit_company_state" name="state" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_location_lat">Location Latitude:</label>
                            <input type="text" id="edit_location_lat" name="location_lat" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_location_lon">Location Longitude:</label>
                            <input type="text" id="edit_location_lon" name="location_lon" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_location_address">Address:</label>
                            <input type="text" id="edit_location_address" name="location_address" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_password">New Password (leave blank to keep current):</label>
                        <input type="password" id="edit_password" name="password" class="form-control" minlength="8">
                    </div>

                    <button type="submit" class="btn btn-custom-submit">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Message Details Modal -->
        <div id="messageDetailsModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="closeMessageDetailsModal()">&times;</span>
                <h3>Contact Message Details</h3>
                <div class="message-meta-info">
                    <p><strong>From:</strong> <span id="msgDetailName"></span> (<span id="msgDetailEmail"></span>)</p>
                    <p><strong>Phone:</strong> <span id="msgDetailPhone"></span></p>
                    <p><strong>Subject:</strong> <span id="msgDetailSubject"></span></p>
                    <p><strong>Received On:</strong> <span id="msgDetailDate"></span></p>
                    <p><strong>Status:</strong> <span id="msgDetailStatus"></span></p>
                </div>
                <div class="message-content-box" id="msgDetailMessage">
                    <!-- Message content will be loaded here -->
                </div>
                <div class="modal-footer-actions">
                    <button id="markAsReadModalBtn" class="btn read-btn" data-action="mark_as_read">Mark as Read</button>
                    <button id="archiveModalBtn" class="btn archive-btn" data-action="archive_message">Archive</button>
                    <button id="unarchiveModalBtn" class="btn unarchive-btn" data-action="unarchive_message" style="display:none;">Unarchive</button>
                    <button class="btn delete-btn" data-action="delete_message">Delete</button>
                    <button class="btn btn-secondary" onclick="closeMessageDetailsModal()">Close</button>
                </div>
            </div>
        </div>


        <script>
            var editModal = document.getElementById("editModal");
            var span = document.getElementsByClassName("close-btn")[0]; // For editModal close button

            var messageDetailsModal = document.getElementById("messageDetailsModal");
            var closeMessageDetailsBtn = messageDetailsModal.querySelector(".close-btn");


            function openEditModal(type, data) {
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_type').value = type;
                document.getElementById('modalTitle').innerText = 'Edit ' + type.charAt(0).toUpperCase() + type.slice(1);

                // Common fields
                document.getElementById('edit_name').value = data.name;
                document.getElementById('edit_email').value = data.email;

                // Hide all type-specific fields initially
                document.getElementById('workerFields').style.display = 'none';
                document.getElementById('companyFields').style.display = 'none';

                if (type === 'worker') {
                    document.getElementById('workerFields').style.display = 'block';
                    document.getElementById('edit_age').value = data.age;
                    // Handle gender radio buttons
                    document.getElementById('edit_male').checked = false;
                    document.getElementById('edit_female').checked = false;
                    document.getElementById('edit_other').checked = false;
                    if (data.gender === 'Male') {
                        document.getElementById('edit_male').checked = true;
                    } else if (data.gender === 'Female') {
                        document.getElementById('edit_female').checked = true;
                    } else if (data.gender === 'Other') {
                        document.getElementById('edit_other').checked = true;
                    }
                    document.getElementById('edit_mobile').value = data.mobile;
                    document.getElementById('edit_worker_country').value = data.country;
                    document.getElementById('edit_worker_state').value = data.state;
                } else if (type === 'company') {
                    document.getElementById('companyFields').style.display = 'block';
                    document.getElementById('edit_number').value = data.phone_number;
                    document.getElementById('edit_company_country').value = data.country;
                    document.getElementById('edit_company_state').value = data.state;
                    document.getElementById('edit_location_lat').value = data.location_lat;
                    document.getElementById('edit_location_lon').value = data.location_lon;
                    document.getElementById('edit_location_address').value = data.location_address;
                }

                // Clear password field on opening
                document.getElementById('edit_password').value = '';

                editModal.style.display = "block";
            }

            function closeEditModal() {
                editModal.style.display = "none";
            }

            function openMessageDetailsModal(messageData) {
                document.getElementById('msgDetailName').innerText = messageData.name || 'N/A';
                document.getElementById('msgDetailEmail').innerText = messageData.email || 'N/A';
                document.getElementById('msgDetailPhone').innerText = messageData.phone_number || 'N/A';
                document.getElementById('msgDetailSubject').innerText = messageData.subject || 'No Subject';
                document.getElementById('msgDetailDate').innerText = messageData.received_at || 'N/A';
                document.getElementById('msgDetailStatus').innerText = messageData.status ? capitalizeFirstLetter(messageData.status) : 'N/A';
                document.getElementById('msgDetailMessage').innerText = messageData.message || 'No message content.';

                // Set data-message-id for modal action buttons
                // Important: Ensure the delete button also has this data-message-id if you want it to work from here
                $('#markAsReadModalBtn, #archiveModalBtn, #unarchiveModalBtn, .modal-footer-actions .delete-btn').data('message-id', messageData.id);

                // Conditionally display modal action buttons based on message status
                if (messageData.status === 'new') {
                    $('#markAsReadModalBtn').show();
                    $('#archiveModalBtn').show();
                    $('#unarchiveModalBtn').hide();
                } else if (messageData.status === 'read') {
                    $('#markAsReadModalBtn').hide(); // Already read, so hide this option
                    $('#archiveModalBtn').show();
                    $('#unarchiveModalBtn').hide();
                } else if (messageData.status === 'archived') {
                    $('#markAsReadModalBtn').hide();
                    $('#archiveModalBtn').hide();
                    $('#unarchiveModalBtn').show();
                }

                messageDetailsModal.style.display = "block";
            }

            function closeMessageDetailsModal() {
                messageDetailsModal.style.display = "none";
            }

            function capitalizeFirstLetter(string) {
                return string.charAt(0).toUpperCase() + string.slice(1);
            }

            // Close any modal when the user clicks anywhere outside of the modal content
            window.onclick = function(event) {
                if (event.target == editModal) {
                    editModal.style.display = "none";
                }
                if (event.target == messageDetailsModal) {
                    closeMessageDetailsModal();
                }
            }
        </script>
    </main>

    <footer>
        <div class="footer-bottom-area footer-bg">
            <div class="container">
                <div class="footer-border">
                     <div class="row d-flex justify-content-between align-items-center">
                         <div class="col-xl-12">
                             <div class="footer-copy-right text-center">
                                 <p id="copyright-year">Copyright reserved © <span id="current-year-footer"></span></p>
                             </div>
                         </div>
                     </div>
                </div>
            </div>
        </div>
        </footer>

    <script src="./assets/js/vendor/modernizr-3.5.0.min.js"></script>
    <script src="./assets/js/vendor/jquery-1.12.4.min.js"></script>
    <script src="./assets/js/popper.min.js"></script>
    <script src="./assets/js/bootstrap.min.js"></script>
    <script src="./assets/js/jquery.slicknav.min.js"></script>
    <script src="./assets/js/owl.carousel.min.js"></script>
    <script src="./assets/js/slick.min.js"></script>
    <script src="./assets/js/price_rangs.js"></script>
    <script src="./assets/js/wow.min.js"></script>
    <script src="./assets/js/jquery.magnific-popup.js"></script>
    <script src="./assets/js/jquery.scrollUp.min.js"></script>
    <script src="./assets/js/jquery.nice-select.min.js"></script>
    <script src="./assets/js/jquery.sticky.js"></script>
    <script src="http://cdnjs.cloudflare.com/ajax/libs/waypoints/2.0.3/waypoints.min.js"></script>
    <script src="./assets/js/jquery.counterup.min.js"></script>
    <script src="./assets/js/plugins.js"></script>
    <script src="./assets/js/main.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currentYear = new Date().getFullYear();
            const copyrightElement = document.getElementById('copyright-year');
            if (copyrightElement) {
                copyrightElement.textContent = `Copyright reserved © ${currentYear}`;
            }

            $('#pendingCompaniesTable').DataTable({ "paging": true, "ordering": true, "info": true, "searching": true, "pageLength": 10 });
            $('#allWorkersTable').DataTable({ "paging": true, "ordering": true, "info": true, "searching": true, "pageLength": 10 });
            $('#allCompaniesTable').DataTable({ "paging": true, "ordering": true, "info": true, "searching": true, "pageLength": 10 });
            $('#contactMessagesTable').DataTable({ "paging": true, "ordering": true, "info": true, "searching": true, "pageLength": 10 });

            // Handle actions for contact messages and modal buttons using AJAX
            $(document).on('click', '.read-btn, .archive-btn, .unarchive-btn, .delete-btn, #markAsReadModalBtn, #archiveModalBtn, #unarchiveModalBtn, .modal-footer-actions .delete-btn', function() {
                const button = $(this);
                const messageId = button.data('message-id');
                const action = button.data('action');
                let confirmMessage = '';

                if (action === 'mark_as_read') {
                    confirmMessage = 'Are you sure you want to mark this message as read?';
                } else if (action === 'archive_message') {
                    confirmMessage = 'Are you sure you want to archive this message?';
                } else if (action === 'unarchive_message') {
                    confirmMessage = 'Are you sure you want to unarchive this message? It will be moved to "Read" status.';
                } else if (action === 'delete_message') {
                    confirmMessage = 'Are you sure you want to permanently delete this message? This action cannot be undone.';
                }

                if (confirm(confirmMessage)) { // Consider replacing with a custom modal for better UX
                    $.ajax({
                        url: 'admin_panel.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: action,
                            message_id: messageId
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.message);
                                // Reload to the contact messages section after action
                                window.location.href = 'admin_panel.php?message_status_filter=<?php echo $current_message_filter; ?>#contact-messages-section';
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("AJAX Error: ", status, error, xhr.responseText);
                            alert('An error occurred during the action. Please try again.');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
