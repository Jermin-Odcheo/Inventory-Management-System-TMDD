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

    $stmt = $conn->prepare("SELECT id, username, email, password, is_disabled FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($user['is_disabled'] === '1') {
            $_SESSION['error'] = "Your account has been disabled.";
            header("Location: ../public/index.php");
            exit();
        }

        if (password_verify($password, $user["password"])) {
            // Set session variables
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["email"] = $user["email"];

            // ✅ **Fetch User Roles and Store in Session**
            $role_stmt = $conn->prepare("
                SELECT GROUP_CONCAT(DISTINCT r.role_name) AS roles 
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = ?
            ");
            $role_stmt->bind_param("i", $user["id"]);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            $role_stmt->close();

            if ($role_data = $role_result->fetch_assoc()) {
                $_SESSION["roles"] = $role_data["roles"]; // Store roles as a comma-separated string
            } else {
                $_SESSION["roles"] = ""; // No roles found, set empty string
            }

            // Update user status to "Online"
            $update_status = $conn->prepare("UPDATE users SET status = 'Online' WHERE id = ?");
            $update_status->bind_param("i", $user["id"]);
            $update_status->execute();
            $update_status->close();

            header("Location: ../src/view/php/clients/dashboard.php");
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
