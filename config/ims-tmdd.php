<?php
/**
 * IMS-TMDD Configuration Module
 *
 * This file provides specific configuration settings for the TMDD Inventory Management System. It defines system-wide parameters, custom settings, and integration configurations. The module ensures proper system initialization and maintains configuration consistency across the application.
 *
 * @package    InventoryManagementSystem
 * @subpackage Configuration
 * @author     TMDD Interns 25'
 */
/**
 * @file ims-tmdd.php
 * @brief handles the database connection
 *
 * This script handles the database connection. It includes the main config file,
 * defines the database credentials, and creates a PDO object.
 */
// Include the main config file first
require_once __DIR__ . '/config.php';
/**
 * @var string $host The host name of the database.
 * @var string $db The database name.
 * @var string $user The username of the database.
 * @var string $pass The password of the database.
 * @var string $charset The charset of the database.
 */
$host = 'localhost';
$db   = 'ims_tmddrbac';
$user = 'root'; // default username for localhost
$pass = ''; // default password for localhost
$charset = 'utf8mb4';
/**
 * @var string $dsn The data source name of the database.
 * @var array $options The options of the database.
 */
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Include the RBACService class
require_once __DIR__ . '/../src/control/RBACService.php';
