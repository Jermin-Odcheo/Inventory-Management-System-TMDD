<?php
/**
 * @file authenticate.php
 * @brief handles the authentication process
 *
 * This script handles the authentication process. It checks if the user is logged in,
 * fetches the user's information, and displays it in the header.
 */
session_start();
require 'ims-tmdd.php'; // your PDO $pdo comes from here
/**
 * @var string $login The login username or email.
 * @var string $password The password entered by the user.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $_SESSION['error'] = 'Username/email and password are required.';
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    /**
     * @var PDOStatement $stmt The prepared statement to fetch the user's information.
     */
    $stmt = $pdo->prepare("
        SELECT id, username, email, password, is_disabled
          FROM users
         WHERE email = ? OR username = ?
         LIMIT 1
    ");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
/**
 * @var bool $user The user's information.
 */
    if (! $user) {
        $_SESSION['error'] = 'User does not exist. Please check your username/email and try again.';
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    /**
     * @var bool $user The user's information.
     */
    if ((int)$user['is_disabled'] === 1) {
        $_SESSION['error'] = 'Your account has been deactivated.';
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    /**
     * @var bool $user The user's information.
     */
    if (!password_verify($password, $user['password'])) {
        $_SESSION['error'] = 'Invalid username/email or password.';
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    /**
     * @var int $userId The user's ID.
     */
    $userId = (int)$user['id'];

    /**
     * @var PDOStatement $role_stmt The prepared statement to fetch the user's roles.
     */
    $role_stmt = $pdo->prepare("
        SELECT GROUP_CONCAT(r.role_name SEPARATOR ', ') AS roles
          FROM user_department_roles ur
          JOIN roles r ON ur.role_id = r.id
         WHERE ur.user_id = ?
    ");
    $role_stmt->execute([$userId]);
    $role_data = $role_stmt->fetch(PDO::FETCH_ASSOC);

    /**
     * @var array $user The user's information.
     */
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['role']     = $role_data['roles'] ?? '';

    /**
     * @var PDOStatement $update_status The prepared statement to update the user's status.
     */
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
