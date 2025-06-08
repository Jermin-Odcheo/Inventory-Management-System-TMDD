<?php
/**
 * System Configuration Module
 *
 * This file provides core configuration settings for the system. It defines database connections, system parameters, and environment-specific settings. The module ensures proper system initialization and maintains configuration consistency across the application.
 *
 * @package    InventoryManagementSystem
 * @subpackage Configuration
 * @author     TMDD Interns 25'
 */
/**
 * @file config.php
 * @brief handles the configuration of the application
 *
 * This script handles the configuration of the application. It defines the base URL,
 * the root path, and requires the RBACService class.
 */

// Base URL for links
define('BASE_URL', '/');
 
// ROOT_PATH is your project's root directory (one level up from config/)
define('ROOT_PATH', realpath(__DIR__ . '/..') . '/');

// Autoload RBACService (and any other classes you drop under clients/admins)
require_once ROOT_PATH . 'src/control/RBACService.php';
