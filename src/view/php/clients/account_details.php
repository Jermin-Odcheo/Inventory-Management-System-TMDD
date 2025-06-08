<?php
/**
 * Account Details Module
 *
 * This file provides functionality to view and manage detailed account information. It displays comprehensive user profile data, account settings, and related information. The module ensures secure access to account details and proper data presentation.
 *
 * @package    InventoryManagementSystem
 * @subpackage Clients
 * @author     TMDD Interns 25'
 */
session_start();
require '../../../../config/ims-tmdd.php'; // This defines $pdo (PDO connection)

// Handle AJAX requests first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account_details'])) {
    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($is_ajax) {
        try {
            $user_id = $_SESSION['user_id'] ?? null;
            if (!$user_id) {
                throw new Exception("User not logged in.");
            }

            $new_first_name = trim($_POST['first_name']);
            $new_last_name = trim($_POST['last_name']);
            $new_username = trim($_POST['username']);
            $new_email = trim($_POST['email']);
            
            $errors = [];
            
            // Validate input
            if (empty($new_first_name) || empty($new_last_name)) {
                $errors[] = "First name and last name are required.";
            }
            
            if (empty($new_username)) {
                $errors[] = "Username is required.";
            }

            if (empty($new_email)) {
                $errors[] = "Email is required.";
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format.";
            }
            
            // Check if username is already taken
            $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$new_username, $user_id]);
            if ($check_stmt->rowCount() > 0) {
                $errors[] = "Username is already taken.";
            }

            // Check if email is already taken
            $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$new_email, $user_id]);
            if ($check_stmt->rowCount() > 0) {
                $errors[] = "Email is already taken.";
            }
            
            if (empty($errors)) {
                // Start transaction
                $pdo->beginTransaction();
                
                // Update user details
                $update_sql = "UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ? WHERE id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_result = $update_stmt->execute([$new_first_name, $new_last_name, $new_username, $new_email, $user_id]);
                
                if ($update_result) {
                    // Commit transaction
                    $pdo->commit();
                    
                    // Update session variables if needed
                    $_SESSION['username'] = $new_username;
                    
                    // Return JSON response
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit();
                } else {
                    throw new Exception("Failed to update account details.");
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => implode("<br>", $errors)]);
                exit();
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
}

// If not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

include '../general/header.php';

/**
 * @var int $user_id
 * @brief Stores the ID of the currently logged-in user.
 *
 * This variable holds the user ID retrieved from the session.
 */
$user_id = $_SESSION['user_id'];

if ($user_id !== null) {
    /**
     * @var \PDOStatement $stmt
     * @brief Prepared statement for retrieving user profile picture path.
     *
     * This statement queries the database for the user's profile picture path.
     */
    $stmt = $pdo->prepare("SELECT profile_pic_path FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    /**
     * @var array $user
     * @brief Stores user data retrieved from the database.
     *
     * This array contains user information including the profile picture path.
     */
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    print("hello " . $user['profile_pic_path']);

    // Fetch user details and role
    $sql = "
        SELECT u.email, u.username, u.first_name, u.last_name, r.role_name, u.profile_pic_path
        FROM users u
        LEFT JOIN user_department_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.id = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug information
    error_log("User ID: " . $user_id);
    error_log("User Data: " . print_r($user, true));

    if (!$user) {
        die("User not found."); // or redirect
    }

    // Set variables for use throughout the page
    $email = $user['email'] ?? '';
    $username = $user['username'] ?? '';
    $first_name = $user['first_name'] ?? '';
    $last_name = $user['last_name'] ?? '';
    $full_name = trim($first_name . ' ' . $last_name);
    $role = $user['role_name'] ?? 'User'; // Default to 'User' if no role is found
} else {
    die("User not logged in."); // or redirect
}

// Handle email update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
    /**
     * @var string $new_email
     * @brief Stores the new email address provided by the user.
     *
     * This variable holds the trimmed email input from the form.
     */
    $new_email = trim($_POST['email']);
    if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        /**
         * @var string $update_sql
         * @brief SQL query for updating user email.
         *
         * This query updates the user's email in the database.
         */
        $update_sql = "UPDATE users SET email = ? WHERE id = ?";
        /**
         * @var \PDOStatement $update_stmt
         * @brief Prepared statement for updating email.
         *
         * This statement executes the update query for the user's email.
         */
        $update_stmt = $pdo->prepare($update_sql);
        if ($update_stmt->execute([$new_email, $user_id])) {
            $email = $new_email;
            /**
             * @var string $success_message
             * @brief Stores success message for email update.
             *
             * This variable holds the success message displayed to the user.
             */
            $success_message = "Email updated successfully.";
        } else {
            /**
             * @var string $error_message
             * @brief Stores error message for email update failure.
             *
             * This variable holds the error message displayed to the user.
             */
            $error_message = "Failed to update email.";
        }
    } else {
        /**
         * @var string $error_message
         * @brief Stores error message for invalid email format.
         *
         * This variable holds the error message for invalid email input.
         */
        $error_message = "Invalid email format.";
    }
}

// Add this after the email update handling code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $errors = [];

    // Verify current password
    $sql = "SELECT password FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!password_verify($current_password, $user['password'])) {
        $errors[] = "Current password is incorrect.";
    }

    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }

    // Password validation
    if (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Z]/', $new_password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[a-z]/', $new_password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    if (!preg_match('/\d/', $new_password)) {
        $errors[] = "Password must contain at least one number.";
    }
    if (!preg_match('/[@$!%*?&]/', $new_password)) {
        $errors[] = "Password must contain at least one special character (@$!%*?&).";
    }

    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_result = $update_stmt->execute([$hashed_password, $user_id]);

            if ($update_result) {
                // Commit transaction
                $pdo->commit();
                $success_message = "Password updated successfully.";
            } else {
                throw new Exception("Failed to update password.");
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    /**
     * @var string $delete_sql
     * @brief SQL query for deleting user account.
     *
     * This query deletes the user's account from the database.
     */
    $delete_sql = "DELETE FROM users WHERE id = ?";
    /**
     * @var \PDOStatement $delete_stmt
     * @brief Prepared statement for deleting account.
     *
     * This statement executes the delete query for the user's account.
     */
    $delete_stmt = $pdo->prepare($delete_sql);
    
    if ($delete_stmt->execute([$user_id])) {
        // Destroy session
        session_destroy();
        // Redirect to homepage or login page
        header("Location: " . BASE_URL . "index.php?account_deleted=1");
        exit();
    } else {
        /**
         * @var string $error_message
         * @brief Stores error message for account deletion failure.
         *
         * This variable holds the error message displayed to the user.
         */
        $error_message = "Failed to delete account. Please try again later.";
    }
}

//for profile pic uploading
if (isset($_POST['update_profile_pic']) && isset($_FILES['profile_picture'])) {
    /**
     * @var string $upload_dir
     * @brief Stores the absolute path for uploading profile pictures.
     *
     * This variable holds the directory path where profile pictures are uploaded.
     */
    $upload_dir = __DIR__ . '/../../../../public/assets/img/user_images/'; // adjust path as needed
    /**
     * @var string $relative_dir
     * @brief Stores the relative path for profile pictures.
     *
     * This variable holds the relative directory path for storing profile pictures.
     */
    $relative_dir = 'assets/img/user_images/';
    /**
     * @var array $uploaded_file
     * @brief Stores the uploaded file information.
     *
     * This array contains details of the uploaded profile picture file.
     */
    $uploaded_file = $_FILES['profile_picture'];
    
    /**
     * @var array $allowed_types
     * @brief Stores allowed file types for profile pictures.
     *
     * This array lists the permitted file extensions for profile pictures.
     */
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    /**
     * @var string $file_ext
     * @brief Stores the file extension of the uploaded file.
     *
     * This variable holds the lowercase extension of the uploaded file.
     */
    $file_ext = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));

    if (in_array($file_ext, $allowed_types) && getimagesize($uploaded_file['tmp_name'])) {
        // Delete old image if it's not default
        if (!empty($user['profile_pic_path']) && file_exists(__DIR__ . '/../public/' . $user['profile_pic_path'])) {
            unlink(__DIR__ . '/../public/' . $user['profile_pic_path']);
        }

        // Create new filename (e.g., user_123.jpg)
        /**
         * @var string $new_filename
         * @brief Stores the new filename for the uploaded profile picture.
         *
         * This variable creates a unique filename based on user ID and file extension.
         */
        $new_filename = 'user_' . $user_id . '.' . $file_ext;

        /**
         * @var string $target_file
         * @brief Stores the full path for the uploaded file.
         *
         * This variable holds the complete path where the file will be moved.
         */
        $target_file = $upload_dir . $new_filename;
        /**
         * @var string $relative_path
         * @brief Stores the relative path for database storage.
         *
         * This variable holds the relative path to be stored in the database.
         */
        $relative_path = $relative_dir . $new_filename;

        if (move_uploaded_file($uploaded_file['tmp_name'], $target_file)) {
            // Update the user's profile_pic_path in DB
            /**
             * @var \PDOStatement $stmt
             * @brief Prepared statement for updating profile picture path.
             *
             * This statement updates the profile picture path in the database.
             */
            $stmt = $pdo->prepare("UPDATE users SET profile_pic_path = ? WHERE id = ?");
            print("the id is : " . $user_id);
            $stmt->execute([$relative_path, $user_id]);

            // Update in session or $user array if needed
            $user['profile_pic_path'] = $relative_path;
            /**
             * @var string $success_message
             * @brief Stores success message for profile picture update.
             *
             * This variable holds the success message displayed to the user.
             */
            $success_message = "Profile picture updated successfully.";
        } else {
            /**
             * @var string $error_message
             * @brief Stores error message for profile picture upload failure.
             *
             * This variable holds the error message displayed to the user.
             */
            $error_message = "Failed to upload the image.";
        }
    } else {
        /**
         * @var string $error_message
         * @brief Stores error message for invalid image file.
         *
         * This variable holds the error message for invalid image file upload.
         */
        $error_message = "Invalid image file.";
    }
}

if ($user_id !== null) {
    /**
     * @var \PDOStatement $stmt
     * @brief Prepared statement for retrieving updated profile picture path.
     *
     * This statement queries the database for the updated profile picture path.
     */
    $stmt = $pdo->prepare("SELECT profile_pic_path FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    /**
     * @var array $user
     * @brief Stores updated user data.
     *
     * This array contains the updated user information including profile picture path.
     */
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
            overflow-x: hidden;
        }

        .main-content {
            margin-left: 270px;
            padding: 20px;
            min-height: calc(100vh - 60px);
            width: calc(100% - 230px);
            margin-top: 60px;
            display: flex;
            justify-content: center;
        }
        
        .container {
            background-color: #fff;
            border-radius: 10px;
            border: 1px solid #eee;
            padding: 12px;
            width: 90%;
            max-width: 800px;
            margin: 0 auto;
            margin-bottom: 20px;
        }

        .account-layout {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            width: 100%;
            justify-content: center;
        }

        .info-section,
        .form-section,
        .danger-zone {
            background-color: #fff;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            width: 100%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px;
        }

        .info-item {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #eee;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
            display: block;
        }

        .info-value {
            font-weight: 500;
            font-size: 12px;
            word-break: break-word;
        }

        h2 {
            margin-bottom: 15px;
            color: #333;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #444;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 10px;
            }
            
            .container {
                width: 95%;
                padding: 10px;
            }
            
            .account-layout {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .container {
                width: 98%;
                padding: 8px;
            }

            .info-section,
            .form-section,
            .danger-zone {
                padding: 12px;
            }

            h2 {
                font-size: 1.3rem;
                margin-bottom: 12px;
            }

            h3 {
                font-size: 15px;
                margin-bottom: 8px;
            }
        }

        /* Basic button styles without transitions */
        .btn-primary {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-secondary {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        /* Simple form styles */
        .form-group {
            margin-bottom: 16px;
        }
        
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        /* Simple section styles */
        .form-section {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 10px;
            background-color: #fff;
            width: 100%;
            max-width: 800px; /* Limit width */
        }

        .info-section {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 10px;
            background-color: #fff;
            width: 100%;
            max-width: 800px; /* Limit width */
        }

        .danger-zone {
            margin-top: 30px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 10px;
            background-color: #fff;
            margin-bottom: 20px;
            width: 100%;
            max-width: 800px; /* Limit width */
        }

        .form-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Style the danger zone button */
        .danger-zone .btn-danger {
            margin-top: 15px;
        }

        /* Style the profile picture section */
        .profile-pic-form {
            margin-top: 15px;
        }

        .profile-pic-form .form-group {
            margin-bottom: 20px;
        }

        .profile-pic-form input[type="file"] {
            margin-top: 10px;
        }

        /* Simple modal styles */
        .modal-content {
            border-radius: 10px;
            border: 1px solid #eee;
        }

        .modal-header {
            border-bottom: 1px solid #eee;
            padding: 15px;
        }

        .modal-body {
            padding: 15px;
        }

        .modal-footer {
            border-top: 1px solid #eee;
            padding: 15px;
        }

        /* Responsive styles */
        @media (min-width: 2000px) {
            .container {
                max-width: 1400px;
            }
            
            .account-layout {
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                gap: 25px;
            }
        }

        @media (min-width: 1600px) and (max-width: 1999px) {
            .container {
                max-width: 1200px;
            }
            
            .account-layout {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }
        }

        @media (min-width: 1200px) and (max-width: 1599px) {
            .container {
                max-width: 1000px;
            }
            
            .account-layout {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 15px;
            }
        }

        @media (max-width: 1199px) {
            .container {
                max-width: 800px;
            }
            
            .account-layout {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }
            
            .container {
                width: 100%;
                padding: 15px;
            }
            
            .account-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            .container {
                padding: 10px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
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
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
        }
        
        .btn-secondary:hover {
            background-color: #e9ecef;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
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
        
        .info-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
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

        .inline-form {
            margin-top: 5px;
        }

        .inline-form .form-group {
            margin-bottom: 0;
        }

        .inline-form input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .inline-form .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 5px;
        }

        .inline-form .btn-primary {
            padding: 4px 12px;
            font-size: 12px;
        }

        .info-item {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
        }

        .info-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            display: block;
        }

        .info-value {
            font-weight: 500;
            font-size: 14px;
            word-break: break-word;
        }
    </style>
</head>
<body>
<?php include '../general/sidebar.php'; ?>
<div class="main-content">
    <div class="container">
        <h2>Account Details</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <div class="account-layout">
            <div class="info-section">
                <h3>User Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($full_name); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                </div>
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="button" class="btn-primary" data-bs-toggle="modal" data-bs-target="#updateAccountModal">
                        Update Account Details
                    </button>
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

<!-- Update Account Modal -->
<div class="modal fade" id="updateAccountModal" tabindex="-1" aria-labelledby="updateAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateAccountModalLabel">Update Account Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="account-details-form">
                <div class="modal-body">
                    <?php
                    // Debug information
                    error_log("Modal Values - First Name: " . $first_name);
                    error_log("Modal Values - Last Name: " . $last_name);
                    ?>
                    <div class="form-group mb-3">
                        <label for="first_name">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" 
                               value="<?php echo htmlspecialchars($first_name); ?>" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="last_name">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                               value="<?php echo htmlspecialchars($last_name); ?>" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_account_details" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
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

    // Add this script to help debug the modal values
    document.addEventListener('DOMContentLoaded', function() {
        const accountDetailsForm = document.getElementById('account-details-form');
        if (accountDetailsForm) {
            accountDetailsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('update_account_details', '1');
                
                fetch('update_account.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Close the modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('updateAccountModal'));
                        modal.hide();
                        
                        // Reload the page
                        window.location.reload();
                    } else {
                        // Show error message
                        alert(data.error || 'An error occurred while updating account details.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating account details. Please try again.');
                });
            });
        }

        // Add this script to help debug the modal values
        const updateAccountModal = document.getElementById('updateAccountModal');
        if (updateAccountModal) {
            updateAccountModal.addEventListener('show.bs.modal', function () {
                console.log('Modal Opening - First Name:', document.getElementById('first_name').value);
                console.log('Modal Opening - Last Name:', document.getElementById('last_name').value);
            });
        }
    });
</script>

</body>
</html>
