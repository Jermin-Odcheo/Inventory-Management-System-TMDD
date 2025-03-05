<?php
session_start();
require 'ims-tmdd.php'; // Ensure this connects to your MySQL database

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Email and password are required.";
        header("Location: ../public/index.php");
        exit();
    }

    // Use PDO to prepare and execute the query
    $stmt = $pdo->prepare("SELECT id, username, email, password, is_disabled FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['is_disabled'] === 1) {
            $_SESSION['error'] = "Your account has been disabled.";
            header("Location: ../public/index.php");
            exit();
        }

        if (password_verify($password, $user["password"])) {
            // Set session variables
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["email"] = $user["email"];

            // Fetch User Roles and Store in Session
// In authenticate.php
            $role_stmt = $pdo->prepare("
            SELECT GROUP_CONCAT(r.role_name SEPARATOR ', ') as roles
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ");
                    $role_stmt->execute([$user["id"]]);
                    $role_data = $role_stmt->fetch();

                    $_SESSION["role"] = $role_data ? $role_data["roles"] : "";

            // Update user status to "Online"
            $update_status = $pdo->prepare("UPDATE users SET status = 'Online' WHERE id = ?");
            $update_status->execute([$user["user_id"]]);

            header("Location: ../src/view/php/clients/admins/dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid email or password.";
            header("Location: ../public/index.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Invalid email or password.";
        header("Location: ../public/index.php");
        exit();
    }
}
?>
