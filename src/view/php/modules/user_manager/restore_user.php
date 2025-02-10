<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

if (isset($_POST['id'])) {
    $userId = $_POST['id'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_deleted = 0 WHERE User_ID = ?");
        $stmt->execute([$userId]);
        echo "User restored.";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
// Also handle bulk restoration if needed
else if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
    $userIds = $_POST['user_ids'];
    try {
        $placeholders = implode(",", array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("UPDATE users SET is_deleted = 0 WHERE User_ID IN ($placeholders)");
        $stmt->execute($userIds);
        echo "Selected users restored.";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "No user selected.";
}
