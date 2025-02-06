<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');


// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    // Use the logged-in user's ID.
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    // For anonymous actions, you might set a default.
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address; adjust as needed if you use a proxy, etc.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the user ID from the hidden field.
    $userId = $_POST['id'];

    // Perform your restore query (soft restore):
    $stmt = $pdo->prepare("UPDATE users SET is_deleted = 0 WHERE User_ID = ?");
    $stmt->execute([$userId]);

    // Redirect back to the user management page
    header("Location: user_management.php");
    exit();
}
