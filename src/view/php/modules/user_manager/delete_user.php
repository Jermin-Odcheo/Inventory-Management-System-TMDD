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

// Set IP address for logging.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the user ID and password
    $userId = $_POST['user_id'];
    $password = isset($_POST['password']) ? $_POST['password'] : ''; // Password is optional for soft delete

    // Check if the action is for permanent deletion or soft deletion
    $permanent = isset($_POST['permanent']) && $_POST['permanent'] == "1";

    // Retrieve the current superuser's hashed password from the database (if required for security)
    if (!empty($password)) {
        $currentUserId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT password FROM users WHERE User_ID = ?");
        $stmt->execute([$currentUserId]);
        $storedHash = $stmt->fetchColumn();

        // Verify password if required
        if (!password_verify($password, $storedHash)) {
            $_SESSION['delete_error'] = "Incorrect password. Operation aborted.";
            header("Location: user_management.php");
            exit();
        }
    }

    // Perform the deletion operation
    try {
        if ($permanent) {
            // Permanent deletion: delete the user record
            $stmtDelete = $pdo->prepare("DELETE FROM users WHERE User_ID = ?");
            $stmtDelete->execute([$userId]);
        } else {
            // Soft deletion: mark the user as deleted
            $stmtDelete = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE User_ID = ?");
            $stmtDelete->execute([$userId]);
        }

        // Redirect after operation
        header("Location: user_management.php");
        exit();
    } catch (PDOException $e) {
        // Handle database errors
        $_SESSION['delete_error'] = "Error during deletion: " . $e->getMessage();
        header("Location: user_management.php");
        exit();
    }
}
?>
