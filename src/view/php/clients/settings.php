<?php
session_start();
require_once(__DIR__ . '/../../../../config/config.php');

//If not logged in redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/index.php"); // Redirect to login page
    exit();
}
?>




