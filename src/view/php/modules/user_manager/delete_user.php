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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine if the deletion is permanent or soft.
    $permanent = isset($_POST['permanent']) && $_POST['permanent'] == "1";

    // If a password is provided, verify it.
    if (!empty($_POST['password'])) {
        $currentUserId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT password FROM users WHERE User_ID = ?");
        $stmt->execute([$currentUserId]);
        $storedHash = $stmt->fetchColumn();

        // Verify password if required.
        if (!password_verify($_POST['password'], $storedHash)) {
            $_SESSION['delete_error'] = "Incorrect password. Operation aborted.";
            header("Location: user_management.php");
            exit();
        }
    }

    // Check if multiple user IDs have been provided.
    if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
        $ids = $_POST['user_ids'];
        // Create placeholders for the query.
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($permanent) {
            // Permanent delete for multiple users.
            $stmt = $pdo->prepare("DELETE FROM users WHERE User_ID IN ($placeholders)");
            $stmt->execute($ids);
            echo "Selected users permanently deleted.";
        } else {
            // Soft delete for multiple users.
            $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE User_ID IN ($placeholders)");
            $stmt->execute($ids);
            echo "Selected users have been soft-deleted.";
        }
    }
    // Else, handle single user deletion.
    elseif (isset($_POST['user_id'])) {
        $userId = $_POST['user_id'];

        if ($permanent) {
            // Permanent delete for single user.
            $stmt = $pdo->prepare("DELETE FROM users WHERE User_ID = ?");
            $stmt->execute([$userId]);
            echo "User permanently deleted from database.";
        } else {
            // Soft delete for single user.
            $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE User_ID = ?");
            $stmt->execute([$userId]);
            echo "User has been soft-deleted.";
        }
    } else {
        echo "No users selected.";
    }
}
?>
