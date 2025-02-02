<?php
require 'ims-tmdd.php'; // Your DB connection file

// Fetch all users with plain text passwords (be cautious and test this on a backup first)
$stmt = $pdo->query("SELECT User_ID, Password FROM users");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    // Check if the password is already hashed (you might add a condition if you can distinguish them)
    // For example, if your plain text passwords are known to be shorter than 60 characters (bcrypt hash length)
    if (strlen($user['Password']) < 60) {
        $hashedPassword = password_hash($user['Password'], PASSWORD_DEFAULT);
        
        // Update the user record with the hashed password
        $updateStmt = $pdo->prepare("UPDATE users SET Password = ? WHERE User_ID = ?");
        $updateStmt->execute([$hashedPassword, $user['User_ID']]);
    }
}

echo "Migration complete!";
?>
