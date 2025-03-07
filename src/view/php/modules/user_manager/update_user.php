<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
require_once('../../clients/admins/RBACService.php');

ob_start();

// JSON Response Headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

try {
    // Validate inputs
    if (!isset($_POST['user_id'], $_POST['email'], $_POST['first_name'], $_POST['last_name'])) {
        throw new Exception('Missing required fields');
    }

    $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $department = filter_var($_POST['department'] ?? '', FILTER_VALIDATE_INT);
    $password = $_POST['password'] ?? '';

    if (!$userId || !$email || !$firstName || !$lastName) {
        throw new Exception('Invalid input data');
    }

    // Optional: Check if provided department ID exists
    if ($department) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ?");
        $stmt->execute([$department]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Invalid department ID');
        }
    } else {
        throw new Exception('Department ID is required.');
    }

    // Hash the password only if provided
    $hashedPassword = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : '';

    // Begin transaction
    $pdo->beginTransaction();

    // Set user and module variables for audit
    $pdo->exec("SET @current_user_id = " . intval($_SESSION['user_id']));
    $pdo->exec("SET @current_module = 'User Management'");

    // Execute Stored Procedure
    $stmt = $pdo->prepare("CALL UpdateUserAndDepartment(?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $userId,
        $email,
        $firstName,
        $lastName,
        $hashedPassword,
        'active', // You can modify this based on your actual status logic
        $department,
        $_SESSION['user_id'],
        'User Management'
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully',
        'data' => [
            'id' => $userId,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'department' => $department
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

ob_end_flush();
