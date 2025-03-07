<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
require_once('../../clients/admins/RBACService.php');

// Ensure clean output
ob_start();

// Set JSON headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

try {
    // Validate input
    if (!isset($_POST['user_id']) || !isset($_POST['email']) ||
        !isset($_POST['first_name']) || !isset($_POST['last_name'])) {
        throw new Exception('Missing required fields');
    }

    $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    // department should be the numeric ID
    $department = filter_var($_POST['department'] ?? '', FILTER_VALIDATE_INT);

    if (!$userId || !$email) {
        throw new Exception('Invalid input data');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Fetch user + department_id from user_departments
    $stmt = $pdo->prepare("
        SELECT u.id,
               u.email,
               u.first_name,
               u.last_name,
               ud.department_id
          FROM users AS u
     LEFT JOIN user_departments AS ud ON u.id = ud.user_id
         WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        throw new Exception('User not found');
    }

    // Check if there are any changes
    $hasChanges = false;
    $changes = [];

    if ($currentUser['email'] !== $email) {
        $changes['email'] = true;
        $hasChanges = true;
    }
    if ($currentUser['first_name'] !== $firstName) {
        $changes['first_name'] = true;
        $hasChanges = true;
    }
    if ($currentUser['last_name'] !== $lastName) {
        $changes['last_name'] = true;
        $hasChanges = true;
    }
    if (!empty($_POST['password'])) {
        $changes['password'] = true;
        $hasChanges = true;
    }
    // Compare the integer IDs
    if ($department !== false && $department != $currentUser['department_id']) {
        $changes['department'] = true;
        $hasChanges = true;
    }

    // Set current user for audit log triggers
    $pdo->exec("SET @current_user_id = " . $_SESSION['user_id']);
    $pdo->exec("SET @current_module = 'User Management'");
    // Indicate whether changes exist
    $pdo->exec("SET @has_changes = " . ($hasChanges ? "1" : "0"));

    if ($hasChanges) {
        // Update user table
        $stmt = $pdo->prepare("
            UPDATE users 
               SET email = ?,
                   first_name = ?,
                   last_name = ?
             WHERE id = ?
        ");
        if (!$stmt->execute([$email, $firstName, $lastName, $userId])) {
            throw new Exception('Failed to update user');
        }

        // Update password if provided
        if (!empty($_POST['password'])) {
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
        }

        // Update or insert department
        if (!empty($department) && isset($changes['department'])) {
            // Optional: Validate department ID
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ?");
            $stmt->execute([$department]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception('Invalid department ID');
            }

            // Check if user_departments row exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_departments WHERE user_id = ?");
            $stmt->execute([$userId]);
            $exists = $stmt->fetchColumn() > 0;

            if ($exists) {
                // update
                $stmt = $pdo->prepare("
                    UPDATE user_departments 
                       SET department_id = ?
                     WHERE user_id = ?
                ");
                $stmt->execute([$department, $userId]);
            } else {
                // insert
                $stmt = $pdo->prepare("
                    INSERT INTO user_departments (user_id, department_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$userId, $department]);
            }
        }

        $message = 'User updated successfully';
        $success = true;
    } else {
        // No changes were made
        $message = 'No changes were made to the user';
        $success = false;
    }

    $pdo->commit();

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => [
            'id' => $userId,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'department' => $department,
            'hasChanges' => $hasChanges,
            'changes' => $changes
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Update user error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
