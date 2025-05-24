<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
// Removed header include to avoid extra HTML output

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address for logging.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (isset($_POST['id'])) {
    $userId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($userId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
        exit();
    }
    try {
        // Update only if the user is archived (is_disabled = 1)
        $stmt = $pdo->prepare("UPDATE users SET is_disabled = 0 WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$userId]);
        echo json_encode(['status' => 'success', 'message' => 'User restored successfully']);
    } catch (PDOException $e) {
        // Check for the specific duplicate entry error for username unique constraint
        if ($e->getCode() == 23000 && 
            strpos($e->getMessage(), 'Duplicate entry') !== false && 
            strpos($e->getMessage(), 'uq_users_username_active') !== false) {
            
            echo json_encode([
                'status' => 'error', 
                'message' => 'A user with this username is already active in the system. Please check existing users before restoring.'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
} else if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
    $userIds = array_filter(array_map('intval', $_POST['user_ids']));
    if(empty($userIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid user IDs provided']);
        exit();
    }
    try {
        $placeholders = implode(",", array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("UPDATE users SET is_disabled = 0 WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($userIds);
        echo json_encode(['status' => 'success', 'message' => 'Selected users restored successfully']);
    } catch (PDOException $e) {
        // Check for the specific duplicate entry error for username unique constraint
        if ($e->getCode() == 23000 && 
            strpos($e->getMessage(), 'Duplicate entry') !== false && 
            strpos($e->getMessage(), 'uq_users_username_active') !== false) {
            
            echo json_encode([
                'status' => 'error', 
                'message' => 'One or more users cannot be restored because their usernames are already in use by active users.'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No user selected']);
}
?>
