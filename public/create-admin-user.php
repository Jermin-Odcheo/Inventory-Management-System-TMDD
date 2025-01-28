<?php
// create_admin_user.php
require_once '../config/ims-tmdd.php'; // or the path to your PDO connection file

// 1. First, find the "Admin" role ID.
$roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'Admin' LIMIT 1");
$roleStmt->execute();
$role = $roleStmt->fetch();

if (!$role) {
    // If you reach here, there is no 'Admin' role in the DB.
    // You can either stop or create one:
    // Stop:
    // die("No Admin role found. Please insert an Admin role in the 'roles' table first.");

    // Or create one automatically:
    $insertRole = $pdo->prepare("INSERT INTO roles (role_name, can_view_assets, can_create_assets, can_edit_assets, can_delete_assets, can_manage_invoices, can_manage_reports)
                                 VALUES ('Admin', 1, 1, 1, 1, 1, 1)");
    $insertRole->execute();
    $adminRoleId = $pdo->lastInsertId();
} else {
    $adminRoleId = $role['id'];
}

// 2. Define admin credentials
$adminUsername = 'admin';
$adminPassword = 'admin123';
$adminEmail    = 'admin@example.com';

// 3. Check if an admin user already exists with this username
$userCheckStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
$userCheckStmt->execute(['username' => $adminUsername]);
$existingUser = $userCheckStmt->fetch();

if ($existingUser) {
    die("User 'admin' already exists. Please choose another username or remove the existing one.");
}

// 4. Hash the password
$passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);

// 5. Insert the new admin user
try {
    $insertUserStmt = $pdo->prepare("
        INSERT INTO users (username, password_hash, email, role_id, is_active) 
        VALUES (:username, :password_hash, :email, :role_id, 1)
    ");
    $insertUserStmt->execute([
        'username'      => $adminUsername,
        'password_hash' => $passwordHash,
        'email'         => $adminEmail,
        'role_id'       => $adminRoleId
    ]);

    echo "Admin user created successfully!";
} catch (PDOException $e) {
    echo "Error creating admin user: " . $e->getMessage();
}
?>