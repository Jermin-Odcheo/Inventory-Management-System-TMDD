SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS inventory_tmdd;
USE `inventory_tmdd`;

-- Modify Users Table
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `role_id` int NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` ENUM('online', 'offline') DEFAULT 'offline',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_users_role` (`role_id`)
);

-- Modify Roles Table
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `can_manage_users` tinyint(1) DEFAULT '0',
  `can_manage_equipment` tinyint(1) DEFAULT '0',
  `all_access` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- Modify Purchase Orders Table
DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `purchase_order_no` varchar(50) NOT NULL,
  `units` int DEFAULT 0,
  `order_date` date DEFAULT NULL,
  `items_specification` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- Modify Charge Invoices Table
DROP TABLE IF EXISTS `charge_invoices`;
CREATE TABLE `charge_invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `charge_invoice_no` varchar(50) NOT NULL,
  `date_of_purchase` date DEFAULT NULL,
  `purchase_order_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
);

-- Modify Assets Table to Equipment Details
DROP TABLE IF EXISTS `equipment_details`;
CREATE TABLE `equipment_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_tag` varchar(50) NOT NULL,
  `asset_description` varchar(100) DEFAULT NULL,
  `specification` int NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `date_acquired` date DEFAULT NULL,
  `receiving_report_id` int NOT NULL,
  `accountable_individual_id` int NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`specification`) REFERENCES `charge_invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiving_report_id`) REFERENCES `receiving_reports`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`accountable_individual_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Create Equipment Location Table
DROP TABLE IF EXISTS `equipment_location`;
CREATE TABLE `equipment_location` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_tag` varchar(50) NOT NULL,
  `building_location` varchar(100) DEFAULT NULL,
  `floor_number` varchar(50) DEFAULT NULL,
  `specific_area` varchar(100) DEFAULT NULL,
  `person_responsible` int NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`asset_tag`) REFERENCES `equipment_details`(`asset_tag`) ON DELETE CASCADE,
  FOREIGN KEY (`person_responsible`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Create Receiving Report Form Table
DROP TABLE IF EXISTS `receiving_reports`;
CREATE TABLE `receiving_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receiving_report_no` varchar(50) NOT NULL,
  `accountable_individual_id` int NOT NULL,
  `accountable_individual_location` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`accountable_individual_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

COMMIT;
