<?php
// Enable error reporting for debugging (Disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start the session
session_start();

// Include the database configuration file
require_once('../../../../config/ims-tmdd.php'); // Adjust the path as necessary

// Verify that the PDO connection is established
if (!isset($pdo)) {
    die("Database connection not established.");
}

// Initialize variables
$error_message = '';
$email = '';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize POST data
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Basic validation
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            try {
                // Prepare a SQL statement to prevent SQL injection
                $stmt = $pdo->prepare("SELECT User_ID, Email, Password, First_Name, Last_Name FROM users WHERE Email = :email AND Status = 'Active'");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();

                if ($user) {
                    // **Security Note:** Currently, passwords are stored in plain text.
                    // It's highly recommended to hash passwords using password_hash() and verify using password_verify().
                    if ($password === $user['Password']) { // Plain text comparison
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);

                        // Set session variables
                        $_SESSION['user'] = [
                            'User_ID'    => $user['User_ID'],
                            'Email'      => $user['Email'],
                            'First_Name' => $user['First_Name'],
                            'Last_Name'  => $user['Last_Name']
                        ];

                        // **New Line Added:** Set user_id session variable
                        $_SESSION['user_id'] = $user['User_ID'];

                        // Redirect to dashboard
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error_message = "Invalid email or password.";
                    }
                } else {
                    $error_message = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                // Handle SQL errors gracefully
                // In production, log the error instead of displaying it
                $error_message = "Something went wrong. Please try again later.";
                // Example of logging:
                // error_log("Database Error: " . $e->getMessage());
            }
        }
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
    <link rel="stylesheet" href="../../styles/css/login.css">
    <link rel="icon" type="image/png" href="../../../../public/assets/img/SLU Logo.png">
</head>

<body>
    <div class="container">
        <div class="left-section">
            <img src="../../../../public/assets/img/SLU Logo.png" alt="Logo">
        </div>
        <div class="right-section">
            <form class="login-form" action="login.php" method="POST">
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