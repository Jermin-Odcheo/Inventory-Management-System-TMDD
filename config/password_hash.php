<?php
include 'ims-tmdd.php'; // Ensure your database connection file is correct

// Fetch all users who have non-hashed passwords
$sql = "SELECT id, password FROM users";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $userId = $row['id'];
        $plainPassword = $row['password'];

        // Check if password is already hashed (skip if it's hashed)
        if (password_needs_rehash($plainPassword, PASSWORD_DEFAULT)) {
            $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

            // Update database with hashed password
            $updateSql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("si", $hashedPassword, $userId);
            $stmt->execute();
        }
    }
    echo "Passwords updated successfully!";
} else {
    echo "No users found!";
}

$conn->close();
?>
