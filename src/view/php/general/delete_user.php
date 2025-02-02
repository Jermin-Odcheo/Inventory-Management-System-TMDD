<?php
session_start();
require_once('../../../../config/ims-tmdd.php');

// Check for proper privileges here.

if (isset($_GET['id'])) {
    $userID = $_GET['id'];
    // Delete user roles first because of foreign key constraints.
    $stmt = $pdo->prepare("DELETE FROM user_roles WHERE User_ID = ?");
    $stmt->execute([$userID]);

    // Then delete the user.
    $stmt = $pdo->prepare("DELETE FROM users WHERE User_ID = ?");
    $stmt->execute([$userID]);
}

header("Location: manage_users.php");
exit();
?>
