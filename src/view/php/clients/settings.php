<?php
/**
 * Settings Module
 *
 * This file provides functionality for managing system and user settings. It handles the configuration of various system parameters, user preferences, and display options. The module ensures proper validation and persistence of settings changes.
 *
 * @package    InventoryManagementSystem
 * @subpackage Clients
 * @author     TMDD Interns 25'
 */
session_start();
require_once(__DIR__ . '/../../../../config/config.php');

//If not logged in redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php"); // Redirect to login page
    exit();
}
?>




