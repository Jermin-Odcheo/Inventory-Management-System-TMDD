<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    // Use the logged-in user's ID.
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id         = $_POST['id'];
    $email      = $_POST['email'];
    $firstName  = $_POST['first_name'];
    $lastName   = $_POST['last_name'];
    $department = $_POST['department'];
    $status     = ''; // Force status to be blank
    $password   = $_POST['password'];

    try {
        // Update query including password if provided.
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $query = "UPDATE users 
                      SET Email = ?, First_Name = ?, Last_Name = ?, Department = ?, Status = ?, Password = ? 
                      WHERE User_ID = ?";
            $params = [$email, $firstName, $lastName, $department, $status, $hashedPassword, $id];
        } else {
            $query = "UPDATE users 
                      SET Email = ?, First_Name = ?, Last_Name = ?, Department = ?, Status = ? 
                      WHERE User_ID = ?";
            $params = [$email, $firstName, $lastName, $department, $status, $id];
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        echo "User updated successfully!";
    } catch (PDOException $e) {
        echo "Error updating user: " . $e->getMessage();
    }
}
