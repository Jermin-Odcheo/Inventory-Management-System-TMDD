<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['id'];
    $password = $_POST['password'];
    // Check whether this is a permanent deletion or soft deletion.
    $permanent = isset($_POST['permanent']) && $_POST['permanent'] == "1";

    // Assume the current superuser's ID is stored in session
    $currentUserId = $_SESSION['user_id'];

    // Retrieve the current superuser's hashed password from the database
    $stmt = $pdo->prepare("SELECT password FROM users WHERE User_ID = ?");
    $stmt->execute([$currentUserId]);
    $storedHash = $stmt->fetchColumn();

    if (password_verify($password, $storedHash)) {
        if ($permanent) {
            // Permanent deletion: actually delete the record
            $stmtDelete = $pdo->prepare("DELETE FROM users WHERE User_ID = ?");
            $stmtDelete->execute([$userId]);
        } else {
            // Soft deletion: mark the user as deleted
            $stmtDelete = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE User_ID = ?");
            $stmtDelete->execute([$userId]);
        }
        header("Location: user_management.php");
        exit();
    } else {
        // Password incorrect; set an error message or handle as needed
        $_SESSION['delete_error'] = "Incorrect password. Operation aborted.";
        header("Location: user_management.php");
        exit();
    }
}
