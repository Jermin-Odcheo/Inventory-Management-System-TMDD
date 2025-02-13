<?php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust path as needed

// Optional: Check if the logged-in user has permission to manage roles.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if the role ID is provided in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Optionally, you can set an error message in the session
    $_SESSION['error'] = "No role ID specified.";
    header("Location: manage_roles.php");
    exit();
}

$role_id = intval($_GET['id']); // Convert to integer to sanitize input

try {
    // First, verify that the role exists
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE Role_ID = ?");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        $_SESSION['error'] = "Role not found.";
        header("Location: manage_roles.php");
        exit();
    }

    // Delete the role from the roles table.
    // If you have foreign key constraints with ON DELETE CASCADE (as in your SQL dump), 
    // the associated records in role_privileges (and possibly other related tables) will be automatically removed.
    $stmt = $pdo->prepare("DELETE FROM roles WHERE Role_ID = ?");
    if ($stmt->execute([$role_id])) {
        $_SESSION['success'] = "Role deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete the role. Please try again.";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

// Redirect back to the manage roles page
header("Location: manage_roles.php");
exit();
