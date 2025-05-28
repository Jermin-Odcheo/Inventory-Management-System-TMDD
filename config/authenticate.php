<?php
session_start();
require 'ims-tmdd.php'; // your PDO $pdo comes from here

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $_SESSION['error'] = 'Username/email and password are required.';
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    // 1) Fetch the user row
    $stmt = $pdo->prepare("
        SELECT id, username, email, password, is_disabled
          FROM users
         WHERE email = ? OR username = ?
         LIMIT 1
    ");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (! $user) {
        $_SESSION['error'] = 'Invalid username/email or password.';
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    // 2) Check disabled
    if ((int)$user['is_disabled'] === 1) {
        $_SESSION['error'] = 'Your account has been deactivated.';
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    // 3) Verify password
    if (!password_verify($password, $user['password'])) {
        $_SESSION['error'] = 'Invalid username/email or password.';
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    // 4) At this point we know the user exists and the password is correct
    $userId = (int)$user['id'];

    // 5) Pull roles
    $role_stmt = $pdo->prepare("
        SELECT GROUP_CONCAT(r.role_name SEPARATOR ', ') AS roles
          FROM user_department_roles ur
          JOIN roles r ON ur.role_id = r.id
         WHERE ur.user_id = ?
    ");
    $role_stmt->execute([$userId]);
    $role_data = $role_stmt->fetch(PDO::FETCH_ASSOC);

    // 6) Seed the session
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['role']     = $role_data['roles'] ?? '';

    // 7) Mark them online
    $pdo->exec("SET @current_user_id = " . (int)$userId);

    $update_status = $pdo->prepare("
        UPDATE users
        SET status = 'Online'
        WHERE id = ?
    ");
    $update_status->execute([$userId]);

    header('Location: ../src/view/php/clients/dashboard.php');
    exit;
}
?>
