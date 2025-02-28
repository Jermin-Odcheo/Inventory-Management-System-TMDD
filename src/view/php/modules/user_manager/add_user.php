<?php
// add_user.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address; adjust as needed if you use a proxy.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// If editing, load user data.
$isEditing = isset($_GET['id']);
$userData = [];
if ($isEditing) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE User_ID = ?");
    $stmt->execute([$_GET['id']]);
    $userData = $stmt->fetch();
}

// Fetch available roles.
$stmt = $pdo->prepare("SELECT * FROM roles");
$stmt->execute();
$roles = $stmt->fetchAll();

// Departments array
$departments = [
    'SAS'      => 'School of Advanced Studies',
    'SOM'      => 'School of Medicine',
    'SOL'      => 'School of Law',
    'STELA'    => 'School of Teacher Education and Liberal Arts',
    'SONAHBS'  => 'School of Nursing, Allied Health, and Biological Sciences',
    'SEA'      => 'School of Engineering and Architecture',
    'SAMCIS'   => 'School of Accountancy, Management, Computing, and Information Studies'
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Retrieve and trim form values
        $email      = trim($_POST['email']);
        $firstName  = trim($_POST['first_name']);
        $lastName   = trim($_POST['last_name']);
        // Use custom department if provided, otherwise the selected one
        $department = isset($_POST['custom_department']) && !empty($_POST['custom_department'])
            ? trim($_POST['custom_department'])
            : trim($_POST['department']);
        $roleIDs    = isset($_POST['roles']) ? $_POST['roles'] : [];
        $password   = $_POST['password'];

        // Validate required fields
        if (empty($email) || empty($firstName) || empty($lastName) || empty($department) || (!$isEditing && empty($password))) {
            throw new Exception("Please fill in all required fields.");
        }

        if (empty($roleIDs)) {
            throw new Exception("Please assign at least one role to the user.");
        }

        // Check for duplicate email
        if ($isEditing) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Email = ? AND User_ID != ?");
            $stmt->execute([$email, $userData['User_ID']]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Email = ?");
            $stmt->execute([$email]);
        }
        $emailCount = $stmt->fetchColumn();

        if ($emailCount > 0) {
            throw new Exception("The email address is already taken. Please choose a different email.");
        }

        // Begin transaction
        $pdo->beginTransaction();

        if (!$isEditing) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (Email, Password, First_Name, Last_Name, Department) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt->execute([$email, $hashedPassword, $firstName, $lastName, $department])) {
                throw new Exception("Failed to insert user data.");
            }
            $userID = $pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare("UPDATE users SET Email = ?, First_Name = ?, Last_Name = ?, Department = ? WHERE User_ID = ?");
            $stmt->execute([$email, $firstName, $lastName, $department, $userData['User_ID']]);
            $userID = $userData['User_ID'];
        }

        // Update user roles: first delete existing roles, then insert new ones.
        $stmt = $pdo->prepare("DELETE FROM user_roles WHERE User_ID = ?");
        $stmt->execute([$userID]);
        $stmt = $pdo->prepare("INSERT INTO user_roles (User_ID, Role_ID) VALUES (?, ?)");
        foreach ($roleIDs as $roleID) {
            $stmt->execute([$userID, $roleID]);
        }

        $pdo->commit();
        $successMessage = $isEditing ? "User updated successfully!" : "User added successfully!";

        // If AJAX request, return JSON; otherwise, redirect.
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $successMessage]);
        } else {
            header("Location: user_management.php?success=1");
        }
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = $e->getMessage();
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMessage]);
        } else {
            $_SESSION['errors'] = [$errorMessage];
            header("Location: user_management.php");
        }
        exit();
    }
}
?>
