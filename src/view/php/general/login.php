<?php
session_start();
include('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $login_query = "SELECT user_id, email, password, account_type, online_status FROM users WHERE email = ?";
    if ($stmt = $db->prepare($login_query)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($user_id, $db_email, $db_password, $account_type, $online_status);

        if ($stmt->num_rows > 0) {
            $stmt->fetch();
            
            if (password_verify($password, $db_password)) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $db_email;
                $_SESSION['account_type'] = $account_type;
                $_SESSION['online_status'] = $online_status;

                if ($account_type == 'Reviewer') {
                    header("Location: php/reviewer/rev_dashboard.php");
                } elseif ($account_type == 'Uploader') {
                    header("Location: php/uploader/upld_dashboard.php");
                } elseif ($account_type == 'Admin') {
                    header("Location: php/admin/admin_dashboard.php");
                } 
                exit;
            } else {
                $error_message = "Incorrect email or password.";
            }
        } else {
            $error_message = "Incorrect email or password.";
        }
        $stmt->close();
    } else {
        $error_message = "Database query failed.";
    }
    $db->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TMDD| Inventory System </title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/styles/index.css">
    <link rel="icon" type="png" href="./assets/img/SLU Logo.png">
</head>
<body>
    <div class="container">
        <div class="left-section">
            <img src="./assets/img/SLU Logo.png" alt="Logo">
        </div>
        <div class="right-section">
            <form class="login-form" action="index.php" method="POST">
                <h2 class="welcome-message">Welcome Back!</h2>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES); ?>" required>
                </div>

                <div class="form-group password-group">
                    <input type="password" name="password" id="password" placeholder="Password" required>
                </div>

                <div class="form-options">
                    <div class="show-password">
                        <input type="checkbox" id="showPassword" class="form-check-input">
                        <label for="showPassword">Show Password</label>
                    </div>
                    <a href="./php/general/confirmations/forget_password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit">Log In</button>

                <div class="signup-container">
                    <span>Don't have an account?</span>
                    <a href="./php/general/registration.php">Create an Account</a>
                </div>
            </form>
        </div>
    </div>
    <footer>
        <p class="mb-0">&copy; 2025 TMDD Interns | Alagad ni SLU</p>
    </footer>

    <script>
        document.getElementById('showPassword').addEventListener('change', function() {
            const passwordInput = document.getElementById('password');
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    </script>
</body>
</html>
