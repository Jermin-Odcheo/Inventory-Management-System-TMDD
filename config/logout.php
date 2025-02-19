<?php
session_start();
require 'ims-tmdd.php';

if (isset($_SESSION["user_id"])) {
    $user_id = $_SESSION["user_id"];

    // Update user status to "Offline"
    $update_status = $conn->prepare("UPDATE users SET status = 'Offline' WHERE id = ?");
    $update_status->bind_param("i", $user_id);
    $update_status->execute();
    $update_status->close();
}

// Destroy session and redirect
session_destroy();
header("Location: ../public/index.php");
exit();
?>
