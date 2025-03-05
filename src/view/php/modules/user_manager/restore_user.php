<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
include '../../general/header.php';

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    // Use the logged-in user's ID.
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    // For anonymous actions, you might set a default.
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address for logging.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

if (isset($_POST['id'])) {
    $userId = $_POST['id'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_disabled = 0 WHERE id = ?");
        $stmt->execute([$userId]);
        echo "User restored.";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
// Also handle bulk restoration if needed
else if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
    $userIds = $_POST['user_ids'];
    try {
        $placeholders = implode(",", array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("UPDATE users SET is_deleted = 0 WHERE User_ID IN ($placeholders)");
        $stmt->execute($userIds);
        echo "Selected users restored.";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "No user selected.";
}
