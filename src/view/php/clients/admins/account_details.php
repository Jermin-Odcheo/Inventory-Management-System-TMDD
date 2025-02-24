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

// Fetch user details
$sql = "SELECT email FROM users WHERE User_ID = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$email = $user['email'] ?? '';

// Handle email update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
    $new_email = trim($_POST['email']);
    if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $update_sql = "UPDATE users SET email = ? WHERE User_ID = ?";
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
    $sql = "SELECT password FROM users WHERE User_ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Set the @current_user_id variable to the current user's ID
            $pdo->exec("SET @current_user_id = $user_id;");

            // Update the password
            $update_sql = "UPDATE users SET password = ? WHERE User_ID = ?";
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
    <title>Account Details</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>../src/view/styles/css/account_details.css">
</head>
<body>
<?php include '../../general/sidebar.php'; ?>
<div class="main-content">
    <div class="container">
        <h2>Account Details</h2>

        <form method="POST">
            <label for="email">Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            <button type="submit" name="update_email">Update Email</button>
        </form>

        <form method="POST">
            <label for="current_password">Current Password:</label>
            <input type="password" name="current_password" required>

            <label for="new_password">New Password:</label>
            <input type="password" name="new_password" required>

            <label for="confirm_password">Confirm Password:</label>
            <input type="password" name="confirm_password" required>

            <button type="submit" name="update_password">Update Password</button>
        </form>
    </div>
</div>


</body>
</html>
