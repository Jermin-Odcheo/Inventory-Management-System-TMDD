<?php
session_start();
require '../../../../../config/ims-tmdd.php'; // This defines $pdo (PDO connection)

include '../../general/header.php';

// If not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/index.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    die("Error: User not logged in. Please log in first.");
}
$user_id = $_SESSION['user_id'];

// Fetch user details and role
$sql = "
    SELECT u.email, u.username, u.first_name, u.last_name, r.role_name 
    FROM users u 
    LEFT JOIN user_roles ur ON u.id = ur.user_id 
    LEFT JOIN roles r ON ur.role_id = r.id 
    WHERE u.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$email = $user['email'] ?? '';
$username = $user['username'] ?? '';
$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role = $user['role_name'] ?? 'User'; // Default to 'User' if no role is found

// Handle email update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
    $new_email = trim($_POST['email']);
    if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $update_sql = "UPDATE users SET email = ? WHERE id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        if ($update_stmt->execute([$new_email, $user_id])) {
            $email = $new_email;
            $success_message = "Email updated successfully.";
        } else {
            $error_message = "Failed to update email.";
        }
    } else {
        $error_message = "Invalid email format.";
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Verify current password
    $sql = "SELECT password FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Set the @current_user_id variable (e.g., for auditing in a trigger)
            $pdo->exec("SET @current_user_id = $user_id;");

            // Update the password
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            if ($update_stmt->execute([$hashed_password, $user_id])) {
                $success_message = "Password updated successfully.";
            } else {
                $error_message = "Failed to update password.";
            }
        } else {
            $error_message = "New passwords do not match.";
        }
    } else {
        $error_message = "Current password is incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Roboto+Mono:wght@300;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/account_details.css">
    <title>Account Details</title>

</head>
<body>
<?php include '../../general/sidebar.php'; ?>
<div class="main-content">
    <div class="container">
        <h2>Account Details</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <!-- User Information (Non-editable) -->
        <div class="info-section">
            <h3>User Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['first_name']) . ' ' . htmlspecialchars($user['last_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Username</span>
                    <span class="info-value"><?php echo htmlspecialchars($username); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Role</span>
                    <span class="info-value"><?php echo htmlspecialchars($role); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Email Update Form -->
        <div class="form-section">
            <h3>Email Address</h3>
            <form method="POST" id="email-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="document.getElementById('email-form').reset()">Cancel</button>
                    <button type="submit" name="update_email" class="btn-primary">Update Email</button>
                </div>
            </form>
        </div>
        
        <!-- Password Update Form -->
        <div class="form-section">
            <h3>Change Password</h3>
            <form method="POST" id="password-form">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <div class="password-field">
                        <input type="password" id="current_password" name="current_password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="password-field">
                        <input type="password" id="new_password" name="new_password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-field">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="document.getElementById('password-form').reset()">Cancel</button>
                    <button type="submit" name="update_password" class="btn-primary">Update Password</button>
                </div>
            </form>

        </div>
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
            Delete My Account
        </button>
    </div>

</div>
<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAccountModalLabel">Delete Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete your account? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteAccount">Delete</button>
            </div>
        </div>
    </div>
</div>
<script>
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }
</script>

</body>
</html>