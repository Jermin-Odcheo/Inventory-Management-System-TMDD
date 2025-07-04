<?php
/**
 * Logout Module
 *
 * This file provides the logout functionality for the system. It handles user session termination, cleanup of session data, and secure redirection to the login page. The module ensures proper security measures are taken during the logout process.
 *
 * @package    InventoryManagementSystem
 * @subpackage General
 * @author     TMDD Interns 25'
 */
session_start();
require '../../../../config/ims-tmdd.php';

/**
 * Set MySQL session variable for use in triggers
 */
if (isset($_SESSION['user_id'])) {
    $currentUserId = intval($_SESSION['user_id']);
    $pdo->query("SET @current_user_id = {$currentUserId}");

    // Update the user's status to 'Offline'
    $stmt = $pdo->prepare("UPDATE users SET status = 'Offline' WHERE id = ?");
    $stmt->execute([$currentUserId]);

}
/**
 * Now proceed with logout
 */
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Location: ../../../../index.php");
exit();
?>
