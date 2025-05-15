<?php
session_start();
require '../../../../config/ims-tmdd.php'; // This defines $pdo (PDO connection)

include '../general/header.php';

// If not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/index.php");
    exit();
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
            // Server-side password validation
            if (strlen($new_password) < 8) {
                $error_message = "Password must be at least 8 characters long.";
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                $error_message = "Password must contain at least one uppercase letter.";
            } elseif (!preg_match('/[a-z]/', $new_password)) {
                $error_message = "Password must contain at least one lowercase letter.";
            } elseif (!preg_match('/\d/', $new_password)) {
                $error_message = "Password must contain at least one number.";
            } elseif (!preg_match('/[@$!%*?&]/', $new_password)) {
                $error_message = "Password must contain at least one special character (@$!%*?&).";
            } else {
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
            }
        } else {
            $error_message = "New passwords do not match.";
        }
    } else {
        $error_message = "Current password is incorrect.";
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $delete_sql = "DELETE FROM users WHERE id = ?";
    $delete_stmt = $pdo->prepare($delete_sql);
    
    if ($delete_stmt->execute([$user_id])) {
        // Destroy session
        session_destroy();
        // Redirect to homepage or login page
        header("Location: " . BASE_URL . "public/index.php?account_deleted=1");
        exit();
    } else {
        $error_message = "Failed to delete account. Please try again later.";
    }
}

//for profile pic uploading
if (isset($_POST['update_profile_pic']) && isset($_FILES['profile_picture'])) {
    $upload_dir = __DIR__ . '/../../../../public/assets/img/user_images/'; // adjust path as needed
    $relative_dir = 'assets/img/user_images/';
    $uploaded_file = $_FILES['profile_picture'];
    
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    $file_ext = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));

    if (in_array($file_ext, $allowed_types) && getimagesize($uploaded_file['tmp_name'])) {
        // Delete old image if it's not default
        if (!empty($user['profile_pic_path']) && file_exists(__DIR__ . '/../public/' . $user['profile_pic_path'])) {
            unlink(__DIR__ . '/../public/' . $user['profile_pic_path']);
        }

        // Create new filename (e.g., user_123.jpg)
        $new_filename = 'user_' . $user_id . '.' . $file_ext;

        $target_file = $upload_dir . $new_filename;
        $relative_path = $relative_dir . $new_filename;

        if (move_uploaded_file($uploaded_file['tmp_name'], $target_file)) {
            // Update the user's profile_pic_path in DB
            $stmt = $pdo->prepare("UPDATE users SET profile_pic_path = ? WHERE id = ?");
            print("the id is : " . $user_id);
            $stmt->execute([$relative_path, $user_id]);

            // Update in session or $user array if needed
            $user['profile_pic_path'] = $relative_path;
            $success_message = "Profile picture updated successfully.";
        } else {
            $error_message = "Failed to upload the image.";
        }
    } else {
        $error_message = "Invalid image file.";
    }
}

if ($user_id !== null) {
    $stmt = $pdo->prepare("SELECT profile_pic_path FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    print($user['profile_pic_path']);
} else {
    die("User not logged in."); // or redirect
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/account_details.css">
    <title>Account Details</title>
    <style>
        html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    overflow-y: auto; /* Add this to enable vertical scrolling */
}

.main-content, .container, .sidebar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.main-content::-webkit-scrollbar,
.container::-webkit-scrollbar,
.sidebar::-webkit-scrollbar,
body::-webkit-scrollbar,
html::-webkit-scrollbar {
    display: none;
}
        
        .main-content {
            margin-left: 230px;
            padding: 30px;
            display: flex;
            justify-content: center;
            min-height: 100vh;
        }
        
        .container {
            box-sizing: border-box;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            padding: 25px;
            width: 88%;
            max-width: 1000px;
            margin: 80px auto 15px auto;
        }
        
        h2 {
            margin-bottom: 20px;
            color: #333;
            font-weight: 600;
        }
        
        h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #444;
        }
        
        .account-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 992px) {
            .account-layout {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .container {
                width: 95%;
            }
        }
        
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .password-field {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #888;
            transition: color 0.2s;
        }
        
        .toggle-password:hover {
            color: #333;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
        }
        
        .btn-secondary {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn-secondary:hover {
            background-color: #e9ecef;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .danger-zone {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .danger-zone h3 {
            color: #dc3545;
        }
        
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
        }
        
        .info-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 500;
            font-size: 16px;
        }
        
        .modal-content {
            border-radius: 10px;
            border: none;
        }
        
        .modal-header {
            border-bottom: 1px solid #eee;
            padding: 18px 22px;
        }
        
        .modal-body {
            padding: 22px;
        }
        
        .modal-footer {
            border-top: 1px solid #eee;
            padding: 18px 22px;
        }
        
        /* Password validation styles */
        #password-requirements {
            list-style-type: none;
            padding-left: 0;
            margin-top: 5px;
        }
        
        #password-requirements li {
            margin-bottom: 3px;
            font-size: 13px;
        }
        
        #password-requirements li:before {
            content: "•";
            margin-right: 8px;
        }
        
        .text-success {
            color: #28a745 !important;
        }
        
        .text-danger {
            color: #dc3545 !important;
        }
        
        .form-text {
            font-size: 13px;
            margin-top: 5px;
        }
        
        #password-match {
            display: none;
        }
    </style>
</head>
<body>
<?php include '../general/sidebar.php'; ?>
<div class="main-content">
<div style="max-height: 100vh; overflow-y: auto;">
    <div class="container">
        <h2>Account Details</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <div class="info-section">
            <h3>User Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['first_name'] ?? '') . ' ' . htmlspecialchars($user['last_name'] ?? ''); ?></span>
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
        
        <div class="account-layout">
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

            
            
            <div class="form-section">
                <h3>Change Password</h3>
                <form method="POST" id="password-form">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="password-field">
                            <input type="password" id="current_password" name="current_password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-field">
                            <input type="password" id="new_password" name="new_password" required 
                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
                                   title="Must contain at least 8 characters, one uppercase, one lowercase, one number and one special character">
                            <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-text text-muted">
                            Password must contain:
                            <ul id="password-requirements" style="margin-top: 5px; padding-left: 20px;">
                                <li id="req-length" class="text-danger">At least 8 characters</li>
                                <li id="req-uppercase" class="text-danger">One uppercase letter</li>
                                <li id="req-lowercase" class="text-danger">One lowercase letter</li>
                                <li id="req-number" class="text-danger">One number</li>
                                <li id="req-special" class="text-danger">One special character</li>
                            </ul>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="password-field">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small id="password-match" class="form-text text-danger" style="display: none;">
                            Passwords do not match
                        </small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="document.getElementById('password-form').reset()">Cancel</button>
                        <button type="submit" name="update_password" class="btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="form-section">
            <h3>Profile Picture</h3>
            <form method="POST" enctype="multipart/form-data" id="profile-pic-form">
                <div class="form-group">
                    <?php if (!empty($user['profile_pic_path'])): ?>
                        <div class="current-pic">
                            <p>Current Picture:</p>
                            <img 
                                src="<?php echo !empty($user['profile_pic_path']) 
                                    ? '/public/' . htmlspecialchars($user['profile_pic_path']) 
                                    : '/public/assets/img/default_profile.jpg'; ?>" 
                                style="max-width: 150px; height: auto;"
                            >
                        </div>
                    <?php endif; ?>
                    <label for="profile_picture">Upload New Picture</label>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" required>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="document.getElementById('profile-pic-form').reset()">Cancel</button>
                    <button type="submit" name="update_profile_pic" class="btn-primary">Update Picture</button>
                </div>
            </form>
        </div>

        <div class="danger-zone">
    <h3>Danger Zone</h3>
    <p>Permanently delete your account and all associated data. This action cannot be undone.</p>
    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
        Delete My Account
    </button>
</div>
    </div>
    </div>
</div>

<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAccountModalLabel">Delete Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete your account? This action cannot be undone.</p>
                <p>All your data will be permanently removed from our system.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="delete-form">
                    <input type="hidden" name="delete_account" value="1">
                    <button type="submit" class="btn btn-danger">Delete Permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }
    
    // Password validation
    document.addEventListener('DOMContentLoaded', function() {
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (newPassword) {
            newPassword.addEventListener('input', function() {
                validatePassword(this.value);
            });
        }
        
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                validatePasswordMatch();
            });
        }
    });

    function validatePassword(password) {
        // Length requirement
        document.getElementById('req-length').className = password.length >= 8 ? 'text-success' : 'text-danger';
        
        // Uppercase requirement
        document.getElementById('req-uppercase').className = /[A-Z]/.test(password) ? 'text-success' : 'text-danger';
        
        // Lowercase requirement
        document.getElementById('req-lowercase').className = /[a-z]/.test(password) ? 'text-success' : 'text-danger';
        
        // Number requirement
        document.getElementById('req-number').className = /\d/.test(password) ? 'text-success' : 'text-danger';
        
        // Special character requirement
        document.getElementById('req-special').className = /[@$!%*?&]/.test(password) ? 'text-success' : 'text-danger';
    }

    function validatePasswordMatch() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const matchElement = document.getElementById('password-match');
        
        if (confirmPassword.length > 0) {
            if (newPassword === confirmPassword) {
                matchElement.style.display = 'none';
            } else {
                matchElement.style.display = 'block';
            }
        } else {
            matchElement.style.display = 'none';
        }
    }

    document.getElementById('password-form').addEventListener('submit', function(event) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        // Client-side validation
        const isPasswordValid = (
            newPassword.length >= 8 &&
            /[A-Z]/.test(newPassword) &&    
            /[a-z]/.test(newPassword) &&
            /\d/.test(newPassword) &&
            /[@$!%*?&]/.test(newPassword)
        );
        
        if (!isPasswordValid) {
            event.preventDefault();
            alert('Password does not meet all requirements.');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            event.preventDefault();
            alert('New passwords do not match.');
        }
    });
</script>

</body>
</html>
