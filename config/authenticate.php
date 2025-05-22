<?php
session_start();
require 'ims-tmdd.php'; // Ensure this connects to your MySQL database

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = trim($_POST["login"]);  // input name "email" from form, but can be username or email
    $password = trim($_POST["password"]);

    if (empty($login) || empty($password)) {
        $_SESSION['error'] = "Username/email and password are required.";
        header("Location: " . BASE_URL . "index.php");
        exit();
    }

    // Prepare statement to find user by email OR username
    $stmt = $pdo->prepare("SELECT id, username, email, password, is_disabled FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // No user found
        $_SESSION['error'] = "Invalid username/email or password.";
        header("Location: " . BASE_URL . "index.php");
        exit();
    }

    // Check if user is disabled
    if ($user['is_disabled'] == 1) {
        $_SESSION['error'] = "Your account has been deactivated.";
        header("Location: " . BASE_URL . "index.php");
        exit();
    }

    // Verify password
    if (!password_verify($password, $user["password"])) {
        $_SESSION['error'] = "Invalid username/email or password.";
        header("Location: " . BASE_URL . "index.php");
        exit();
    }

    // At this point, user exists and password is correct
    $userId = $user['id'];

    // Fetch User Roles and Store in Session
    $role_stmt = $pdo->prepare("
        SELECT GROUP_CONCAT(r.role_name SEPARATOR ', ') as roles
        FROM user_department_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ?
    ");
    $role_stmt->execute([$userId]);
    $role_data = $role_stmt->fetch(PDO::FETCH_ASSOC);

    // Set session variables
    $_SESSION["user_id"] = $userId;
    $_SESSION["username"] = $user["username"];
    $_SESSION["email"] = $user["email"];
    $_SESSION["role"] = $role_data ? $role_data["roles"] : "";

    // Update user status to "Online"
    $update_status = $pdo->prepare("UPDATE users SET status = 'Online' WHERE id = ?");
    $update_status->execute([$userId]);

    header("Location: ../src/view/php/clients/dashboard.php");
    exit();
}
?>
