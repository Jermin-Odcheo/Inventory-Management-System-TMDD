<?php
session_start();
require '../../../../config/ims-tmdd.php'; // This defines $pdo (PDO connection)

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request for debugging
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST Data: " . print_r($_POST, true));
error_log("Headers: " . print_r(getallheaders(), true));

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not an AJAX request']);
    exit();
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account_details'])) {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            throw new Exception("User not logged in.");
        }

        $new_first_name = trim($_POST['first_name']);
        $new_last_name = trim($_POST['last_name']);
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);
        
        // Log the received data
        error_log("Received data - First Name: $new_first_name, Last Name: $new_last_name, Username: $new_username, Email: $new_email");
        
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
        
        error_log("Error in update_account.php: " . $e->getMessage());
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method or missing update_account_details parameter']);
    exit();
} 