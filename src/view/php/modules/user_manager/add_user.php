<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
    $first_name = filter_var($_POST['first_name'], FILTER_SANITIZE_STRING);
    $last_name = filter_var($_POST['last_name'], FILTER_SANITIZE_STRING);
    $department = filter_var($_POST['department'], FILTER_SANITIZE_STRING);
    $custom_department = isset($_POST['custom_department']) ? filter_var($_POST['custom_department'], FILTER_SANITIZE_STRING) : '';
    $roles = isset($_POST['roles']) ? $_POST['roles'] : [];

    // Use custom department if selected
    if ($department === 'custom' && !empty($custom_department)) {
        $department = $custom_department;
    }

    // Validate inputs
    if (empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($department) || empty($roles)) {
        $response = ['success' => false, 'message' => 'All fields are required, including at least one role.'];
    } else {
        try {
            // Insert user into the database
            $stmt = $pdo->prepare("INSERT INTO users (Email, Password, First_Name, Last_Name, Department, Status) VALUES (?, ?, ?, ?, ?, 'Active')");
            $stmt->execute([$email, $password, $first_name, $last_name, $department]);
            $user_id = $pdo->lastInsertId();

            // Insert user roles
            $stmt = $pdo->prepare("INSERT INTO user_roles (User_ID, Role_ID) VALUES (?, ?)");
            foreach ($roles as $role_id) {
                $stmt->execute([$user_id, $role_id]);
            }

            $response = ['success' => true, 'message' => 'User added successfully'];
        } catch (PDOException $e) {
            $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    // Check if it's an AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        // For non-AJAX requests, redirect with a success parameter
        if ($response['success']) {
            header("Location: user_management.php?success=1");
        } else {
            $_SESSION['error'] = $response['message'];
            header("Location: user_management.php");
        }
    }
    exit;
}
?>