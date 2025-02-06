<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

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
