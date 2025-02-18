<?php
    session_start();
    require_once('../config/ims-tmdd.php'); // Database connection

    $email = '';  // Initialize the variable

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8');

        try {
            // Fetch user and role from the database
            $stmt = $pdo->prepare("
            SELECT u.User_ID, u.Email, u.Password, u.First_Name, u.Last_Name, u.Department, u.Status, r.Role_Name
            FROM users u
            JOIN roles r ON u.Role = r.Role_ID
            WHERE u.Email = ?
        ");

            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Verify password
                if (password_verify($password, $user['Password'])) {
                    // Store user details and role in session
                    $_SESSION['user_id'] = $user['User_ID'];
                    $_SESSION['email']   = $user['Email'];
                    $_SESSION['role']    = $user['Role_Name']; // Correctly setting role

                    // Debugging: Print Role (Remove this in production)
                    error_log("User Logged in: " . $_SESSION['email'] . " | Role: " . $_SESSION['role']);

                    // Redirect to dashboard
                    header("Location: ../src/view/php/clients/admins/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid credentials. Please try again.";
                }
            } else {
                $error = "User not found.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
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

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
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
                    <a href="../src/view/php/general/login_regis/forgot_password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" name="submit">Log In</button>

                <div class="signup-container">
                    <span>Don't have an account?</span>
                    <a href="../src/view/php/general/login_regis/registration.php">Create an Account</a>
                </div>
            </form>
        </div>
    </div>
    <footer>
        <?php include '../src/view/php/general/footer.php';?>
    </footer>

    <script>
        document.getElementById('showPassword').addEventListener('change', function() {
            const passwordInput = document.getElementById('password');
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    </script>
</body>

</html>
