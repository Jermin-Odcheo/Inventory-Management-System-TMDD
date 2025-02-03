<?php
// Enable error reporting for debugging (Disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start the session
session_start();

// Include the database configuration file
require_once('../config/ims-tmdd.php'); // Adjust the path as necessary

$error_message = '';
$email = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Prepare and execute the SQL query
    $stmt = $pdo->prepare("SELECT * FROM users WHERE Email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verify the password using password_verify()
    if ($user && password_verify($password, $user['Password'])) {
        // If the password matches, set session variables and redirect
        $_SESSION['user_id'] = $user['User_ID'];
        $_SESSION['email'] = $user['Email'];
        header("Location: ../src/view/php/admin/admin_dashboard.php");
        exit();
    } else {
        // If authentication fails, set an error message
        $error_message = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TMDD | Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="./index.css">
    <link rel="icon" type="image/png" href="./assets/img/SLU Logo.png">
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
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>" required>
                </div>

                <div class="form-group password-group">
                    <input type="password" name="password" id="password" placeholder="Password" required>
                </div>

                <div class="form-options">
                    <div class="show-password">
                        <input type="checkbox" id="showPassword" class="form-check-input">
                        <label for="showPassword">Show Password</label>
                    </div>
                    <a href="../src/view/php/general/login_regis/forget_password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit">Log In</button>

                <div class="signup-container">
                    <span>Don't have an account?</span>
                    <a href="../src/view/php/general/login_regis/registration.php">Create an Account</a>
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
