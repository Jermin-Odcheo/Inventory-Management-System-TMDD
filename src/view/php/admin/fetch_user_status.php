<?php
session_start();
require '../../../../config/ims-tmdd.php'; // adjust path

// Make sure only logged-in users can fetch this data
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT User_ID, Email, First_Name, Last_Name, Department, last_active
        FROM users
        ORDER BY First_Name ASC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Define the online threshold in seconds (e.g., 120 = 2 minutes)
$onlineThreshold = 120;
$now = new DateTime();

foreach ($users as &$user) {
    $isOnline = false;
    if (!empty($user['last_active'])) {
        $lastActive = new DateTime($user['last_active']);
        $diffInSeconds = $now->getTimestamp() - $lastActive->getTimestamp();
        if ($diffInSeconds <= $onlineThreshold) {
            $isOnline = true;
        }
    }
    // Attach isOnline to the user array
    $user['isOnline'] = $isOnline;
}

// Send JSON to the front end
header('Content-Type: application/json');
echo json_encode($users);
