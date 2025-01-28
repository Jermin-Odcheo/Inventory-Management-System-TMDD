-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jan 28, 2025 at 08:48 AM
-- Server version: 8.2.0
-- PHP Version: 8.2.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_tmdd`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
CREATE TABLE IF NOT EXISTS `assets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_tag` varchar(50) NOT NULL,
  `asset_description` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `date_acquired` date DEFAULT NULL,
  `receiving_report_id` int DEFAULT NULL,
  `purchase_order_id` int DEFAULT NULL,
  `charge_invoice_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_assets_receivingreport` (`receiving_report_id`),
  KEY `fk_assets_purchaseorder` (`purchase_order_id`),
  KEY `fk_assets_chargeinvoice` (`charge_invoice_id`),
  KEY `fk_assets_location` (`location_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `asset_tag`, `asset_description`, `brand`, `serial_number`, `date_acquired`, `receiving_report_id`, `purchase_order_id`, `charge_invoice_id`, `location_id`, `created_at`, `updated_at`) VALUES
(1, '1001', 'laptop', 'asus', 'SN10001001', '2025-01-27', NULL, NULL, NULL, NULL, '2025-01-28 14:00:59', '2025-01-28 14:00:59');

-- --------------------------------------------------------

--
-- Table structure for table `charge_invoices`
--

DROP TABLE IF EXISTS `charge_invoices`;
CREATE TABLE IF NOT EXISTS `charge_invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `charge_invoice_no` varchar(50) NOT NULL,
  `date_of_purchase` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `charge_invoices`
--

INSERT INTO `charge_invoices` (`id`, `charge_invoice_no`, `date_of_purchase`, `created_at`, `updated_at`) VALUES
(1, '20247', '2024-11-19', '2025-01-28 15:04:41', '2025-01-28 15:04:41');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
CREATE TABLE IF NOT EXISTS `locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `building` varchar(100) DEFAULT NULL,
  `floor_number` varchar(50) DEFAULT NULL,
  `specific_area` varchar(100) DEFAULT NULL,
  `remarks` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `purchase_order_no` varchar(50) NOT NULL,
  `units` int DEFAULT '0',
  `order_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `purchase_order_no`, `units`, `order_date`, `created_at`, `updated_at`) VALUES
(1, '10-25-000664', 3, '2024-12-12', '2025-01-28 15:01:22', '2025-01-28 15:01:22'),
(2, '10-25-000664', 3, '2024-12-12', '2025-01-28 15:02:03', '2025-01-28 15:02:03'),
(8, '10-25-000666', 1, '2025-01-17', '2025-01-28 15:16:36', '2025-01-28 15:16:36'),
(9, '10-25-000698', 12, '2025-01-27', '2025-01-28 15:17:04', '2025-01-28 15:17:04'),
(10, '10-25-00066123', 9, '2025-01-09', '2025-01-28 15:17:27', '2025-01-28 15:17:27');

-- --------------------------------------------------------

--
-- Table structure for table `receiving_reports`
--

DROP TABLE IF EXISTS `receiving_reports`;
CREATE TABLE IF NOT EXISTS `receiving_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receiving_report_no` varchar(50) NOT NULL,
  `accountable_person_id` int DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_receiving_reports_accountable` (`accountable_person_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `can_view_assets` tinyint(1) DEFAULT '0',
  `can_create_assets` tinyint(1) DEFAULT '0',
  `can_edit_assets` tinyint(1) DEFAULT '0',
  `can_delete_assets` tinyint(1) DEFAULT '0',
  `can_manage_invoices` tinyint(1) DEFAULT '0',
  `can_manage_reports` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `can_view_assets`, `can_create_assets`, `can_edit_assets`, `can_delete_assets`, `can_manage_invoices`, `can_manage_reports`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 1, 1, 1, 1, 1, 1, '2025-01-28 13:34:38', '2025-01-28 13:34:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_users_role` (`role_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `role_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$Qc7esWYs2Pw3XCnehnukguKrd3L8OU6wxHVB58JIhMd9UxIYhiiUe', 'admin@example.com', 1, 1, '2025-01-28 13:42:23', '2025-01-28 13:42:23');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
