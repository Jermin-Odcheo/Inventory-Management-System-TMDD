<?php
session_start();
require_once '../config/ims-tmdd.php';

// If the user is already logged in, redirect to dashboard
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // JOIN the roles table so we can grab the privileges
    $stmt = $pdo->prepare("
        SELECT u.*, 
               r.can_view_assets, 
               r.can_create_assets, 
               r.can_edit_assets, 
               r.can_delete_assets,
               r.can_manage_invoices,
               r.can_manage_reports
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.username = :username
          AND u.is_active = 1
        LIMIT 1
    ");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Store user data + role privileges in session
        $_SESSION['user'] = [
            'id'         => $user['id'],
            'username'   => $user['username'],
            'role_id'    => $user['role_id'],
            'privileges' => [
                'can_view_assets'     => $user['can_view_assets'],
                'can_create_assets'   => $user['can_create_assets'],
                'can_edit_assets'     => $user['can_edit_assets'],
                'can_delete_assets'   => $user['can_delete_assets'],
                'can_manage_invoices' => $user['can_manage_invoices'],
                'can_manage_reports'  => $user['can_manage_reports'],
            ]
        ];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .login-container h1 {
            margin-bottom: 1.5rem;
            font-size: 2rem;
            text-align: center;
            color: #333;
        }

        .form-label {
            font-weight: 500;
        }

        .btn-login {
            width: 100%;
            padding: 0.5rem;
            font-size: 1.1rem;
        }

        .error-message {
            color: #dc3545;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1>Login</h1>
        <?php if ($error) echo "<p class='error-message'>$error</p>"; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-login">Login</button>
        </form>
    </div>

    <!-- Bootstrap 5 JS (optional, for certain components) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>