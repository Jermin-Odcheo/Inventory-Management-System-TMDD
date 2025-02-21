<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include $_SERVER['DOCUMENT_ROOT'] . '/IMS-TMDD RABAC Tester/config/ims-tmdd.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /IMS-TMDD RABAC Tester/public/index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch username
$query = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>

<div class="container-fluid d-flex justify-content-between align-items-center p-3 bg-dark text-white">
    <a class="navbar-brand text-white" href="/IMS-TMDD RABAC Tester/src/view/php/clients/dashboard.php">
        Dashboard
    </a>
    <span class="navbar-text">Welcome, <strong><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></strong>!</span>
    <a href="/IMS-TMDD RABAC Tester/config/logout.php" class="btn btn-danger">Logout</a>
</div>
