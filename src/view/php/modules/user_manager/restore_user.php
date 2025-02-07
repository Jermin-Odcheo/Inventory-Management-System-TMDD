<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
    $userIds = $_POST['user_ids'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_deleted = 0 WHERE User_ID IN (" . implode(",", array_fill(0, count($userIds), '?')) . ")");
        $stmt->execute($userIds);
        echo "Selected users have been restored.";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "No users selected.";
}
?>
