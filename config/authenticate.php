<?php
session_start();
include 'ims-tmdd.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password);
        $stmt->fetch();
        
        if (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['email'] = $email;

            // Correct absolute path for successful login redirection
            header("Location: ../src/view/php/clients/admins/dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid email or password.";
            // Correct path for failed login redirection
            header("Location: ../public/index.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "No user found.";
        header("Location: ../public/index.php");
        exit();
    }
    $stmt->close();
}
?>
