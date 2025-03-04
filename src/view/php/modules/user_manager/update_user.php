<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
include '../../general/header.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Access denied.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit();
}

// Retrieve and sanitize input data
$user_id    = $_POST['user_id'] ?? '';
$email      = trim($_POST['email'] ?? '');
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$department = trim($_POST['department'] ?? ''); // Assumed to be department_id
$password   = $_POST['password'] ?? '';

if (empty($user_id) || empty($email) || empty($first_name) || empty($last_name)) {
    http_response_code(400);
    echo "Missing required fields.";
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "Invalid email format.";
    exit();
}

try {
    // Set user-defined variables for auditing triggers
    $pdo->query("SET @current_user_id = " . (int)$_SESSION['user_id']);
    $pdo->query("SET @current_module = 'User Management'");

    // Update the users table
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users 
                SET email = :email,
                    first_name = :first_name,
                    last_name = :last_name,
                    password = :password
                WHERE id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':email'      => $email,
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':password'   => $hashedPassword,
            ':user_id'    => $user_id,
        ]);
    } else {
        $sql = "UPDATE users 
                SET email = :email,
                    first_name = :first_name,
                    last_name = :last_name
                WHERE id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':email'      => $email,
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':user_id'    => $user_id,
        ]);
    }

    // Handle department update if provided
    if (!empty($department)) {
        // Check if the user already has a department assigned
        $checkSql = "SELECT COUNT(*) FROM user_departments WHERE user_id = :user_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':user_id' => $user_id]);
        $count = $checkStmt->fetchColumn();

        if ($count > 0) {
            // Update existing department assignment
            $updateDeptSql = "UPDATE user_departments 
                              SET department_id = :department_id 
                              WHERE user_id = :user_id";
            $updateDeptStmt = $pdo->prepare($updateDeptSql);
            $updateDeptStmt->execute([
                ':department_id' => $department,
                ':user_id'       => $user_id,
            ]);
        } else {
            // Insert new department assignment
            $insertDeptSql = "INSERT INTO user_departments (user_id, department_id) 
                              VALUES (:user_id, :department_id)";
            $insertDeptStmt = $pdo->prepare($insertDeptSql);
            $insertDeptStmt->execute([
                ':user_id'       => $user_id,
                ':department_id' => $department,
            ]);
        }
    }

    echo "User updated successfully.";
} catch (PDOException $e) {
    http_response_code(500);
    echo "Error updating user: " . $e->getMessage();
    exit();
}
?>