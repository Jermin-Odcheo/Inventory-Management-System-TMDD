<?php
session_start();
$error_message = '';
$password_class = '';
$confirm_password_class = '';
$form_valid = true;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include '../../../../model/db_connection.php';

    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if the token is valid and has not expired
    $sql = "SELECT * FROM users WHERE reset_token = '$token'";
    $result = $db->query($sql);

    if ($result->num_rows > 0) {
        // Token found, now check expiration
        $user = $result->fetch_assoc();
        $token_expiration = $user['reset_token_expires'];

        // Check if the token has expired
        if (strtotime($token_expiration) > time()) {
            // Token is valid and not expired
            if ($password === $confirm_password) {
                if (strlen($password) >= 8 && strlen($password) <= 16) {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    // Update the password and clear the token and expiration fields
                    $update_sql = "UPDATE users SET password = '$hashed_password', reset_token = NULL, reset_token_expires = NULL WHERE reset_token = '$token'";
                    if ($db->query($update_sql)) {
                        $_SESSION['password_reset_success'] = true;
                        header("Location: password_reset_success.php");
                        exit();
                    } else {
                        $error_message = "Failed to reset password. Please try again.";
                    }
                } else {
                    $error_message = "Password must be between 8 and 16 characters.";
                    $password_class = 'is-invalid';
                    $confirm_password_class = 'is-invalid';
                }
            } else {
                $error_message = "Passwords do not match.";
                $password_class = 'is-invalid';
                $confirm_password_class = 'is-invalid';
            }
        } else {
            $error_message = "Token has expired. Please request a new password reset.";
        }
    } else {
        $error_message = "Invalid token.";
    }

    $db->close();
} else {
    $token = $_GET['token'] ?? '';
    if (empty($token)) {
        header("Location: forgot_password.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="/src/view/styles/css/forgot_password.css?v=1.0"> <!-- Using the same CSS file -->
    <link rel="icon" type="png" href="../../../../../public/assets/img/SLU Logo.png">
</head>

<body>

    <div class="container py-5 mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card shadow custom-card-size">
                    <div class="card-body">
                        <h1 class="text-center mb-4">Reset Password</h1>
                        <p class="text-center">Enter your new password below.</p>

                        <form action="" method="post">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="form-floating position-relative">
                                    <input type="password" class="form-control <?php echo $password_class; ?>" id="password" name="password" required>
                                    <label for="password">Enter your new password</label>
                                    <div class="invalid-feedback">
                                        <?php echo $error_message; ?>
                                    </div>
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password', this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="form-floating position-relative">
                                    <input type="password" class="form-control <?php echo $confirm_password_class; ?>" id="confirm_password" name="confirm_password" required>
                                    <label for="confirm_password">Confirm your new password</label>
                                    <div class="invalid-feedback">
                                        <?php echo $error_message; ?>
                                    </div>
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password', this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary w-100 py-2">Reset Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bottom">
        <?php include "../footer.php" ?>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>

</html>
