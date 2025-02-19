-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 19, 2025 at 08:44 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ims_tmdd4`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `track_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `action` enum('Modified','Deleted','Restored','Soft Deleted','Permanently Deleted') NOT NULL,
  `old_value` text,
  `new_value` text,
  `module_id` int DEFAULT NULL,
  `status` enum('Successful','Failed') NOT NULL,
  `date_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`track_id`),
  KEY `user_id` (`user_id`),
  KEY `module_id` (`module_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `charge_invoice`
--

DROP TABLE IF EXISTS `charge_invoice`;
CREATE TABLE IF NOT EXISTS `charge_invoice` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(20) NOT NULL,
  `date_of_purchase` date NOT NULL,
  `po_no` varchar(20) NOT NULL,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `po_no` (`po_no`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `department_name` varchar(191) NOT NULL,
  `abbreviation` varchar(50) NOT NULL,
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_name` (`department_name`),
  UNIQUE KEY `abbreviation` (`abbreviation`)
) ENGINE=MyISAM AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `abbreviation`, `is_disabled`) VALUES
(1, 'Office of the President', 'OP', '0'),
(2, 'Office of the Executive Assistant to the President', 'OEAP', '0'),
(3, 'Office of the Internal Auditor', 'OIA', '0'),
(4, 'Office of the Vice President for Mission and Identity', 'OVPMI', '0'),
(5, 'Center for Campus Ministry', 'CCM', '0'),
(6, 'Community Extension and Outreach Programs Office', 'CEOP', '0'),
(7, 'St. Aloysius Gonzaga Parish Office', 'SAGPO', '0'),
(8, 'Sunflower Child and Youth Wellness Center', 'SCYWC', '0'),
(9, 'Office of the Vice President for Academic Affairs', 'OVPAA', '0'),
(10, 'School of Accountancy, Management, Computing and Information Studies', 'SAMCIS', '0'),
(11, 'School of Advanced Studies', 'SAS', '0'),
(12, 'School of Engineering and Architecture', 'SEA', '0'),
(13, 'School of Law', 'SOL', '0'),
(14, 'School of Medicine', 'SOM', '0'),
(15, 'School of Nursing, Allied Health, and Biological Sciences Natural Sciences', 'SONAHBS', '0'),
(16, 'School of Teacher Education and Liberal Arts', 'STELA', '0'),
(17, 'Basic Education School', 'SLU BEdS', '0'),
(18, 'Office of Institutional Development and Quality Assurance', 'OIDQA', '0'),
(19, 'University Libraries', 'UL', '0'),
(20, 'University Registrar’s Office', 'URO', '0'),
(21, 'University Research and Innovation Center', 'URIC', '0'),
(22, 'Office of the Vice President for Finance', 'OVPF', '0'),
(23, 'Asset Management and Inventory Control Office', 'AMICO', '0'),
(24, 'Finance Office', 'FO', '0'),
(25, 'Printing Operations Office', 'POO', '0'),
(26, 'Technology Management and Development Department', 'TMDD', '0'),
(27, 'Office of the Vice President for Administration', 'OVPA', '0'),
(28, 'Athletics and Fitness Center', 'AFC', '0'),
(29, 'Campus Planning, Maintenance, and Security Department', 'CPMSD', '0'),
(30, 'Center for Culture and the Arts', 'CCA', '0'),
(31, 'Dental Clinic', 'DC', '0'),
(32, 'Guidance Center', 'GC', '0'),
(33, 'Human Resource Department', 'HRD', '0'),
(34, 'Students’ Residence Hall', 'SRH', '0'),
(35, 'Medical Clinic', 'MC', '0'),
(36, 'Office for Legal Affairs', 'OLA', '0'),
(37, 'Office of Student Affairs', 'OSA', '0'),
(40, 'TMDD Developers', 'TMDD-Dev', '0');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_details`
--

DROP TABLE IF EXISTS `equipment_details`;
CREATE TABLE IF NOT EXISTS `equipment_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_tag` varchar(50) NOT NULL,
  `asset_description_1` text NOT NULL,
  `asset_description_2` text NOT NULL,
  `specifications` text NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `invoice_no` varchar(20) NOT NULL,
  `rr_no` varchar(20) NOT NULL,
  `equipment_location_id` int NOT NULL,
  `equipment_status_id` int NOT NULL,
  `remarks` text,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_tag` (`asset_tag`),
  KEY `invoice_no` (`invoice_no`),
  KEY `rr_no` (`rr_no`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_location`
--

DROP TABLE IF EXISTS `equipment_location`;
CREATE TABLE IF NOT EXISTS `equipment_location` (
  `id` int NOT NULL AUTO_INCREMENT,
  `equipment_location_id` int NOT NULL,
  `asset_tag` varchar(50) NOT NULL,
  `building_loc` varchar(255) NOT NULL,
  `floor_no` varchar(50) DEFAULT NULL,
  `specific_area` text NOT NULL,
  `person_responsible` varchar(255) NOT NULL,
  `department_id` int NOT NULL,
  `remarks` text,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipment_location_id` (`equipment_location_id`),
  KEY `asset_tag` (`asset_tag`),
  KEY `department_id` (`department_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_status`
--

DROP TABLE IF EXISTS `equipment_status`;
CREATE TABLE IF NOT EXISTS `equipment_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `equipment_status_id` int NOT NULL,
  `asset_tag` varchar(50) NOT NULL,
  `status` enum('Operational','Under Maintenance','Decommissioned','Disposed') NOT NULL,
  `action` text NOT NULL,
  `remarks` text,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipment_status_id` (`equipment_status_id`),
  KEY `asset_tag` (`asset_tag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
CREATE TABLE IF NOT EXISTS `modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_name` (`module_name`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `module_name`) VALUES
(3, 'User Management'),
(2, 'Roles and Privileges'),
(1, 'Audit'),
(4, 'Equipment Management');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order`
--

DROP TABLE IF EXISTS `purchase_order`;
CREATE TABLE IF NOT EXISTS `purchase_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `po_no` varchar(20) NOT NULL,
  `date_of_order` date NOT NULL,
  `no_of_units` int NOT NULL,
  `item_specifications` text NOT NULL,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_no` (`po_no`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receive_report`
--

DROP TABLE IF EXISTS `receive_report`;
CREATE TABLE IF NOT EXISTS `receive_report` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rr_no` varchar(20) NOT NULL,
  `accountable_individual` varchar(255) NOT NULL,
  `ai_loc` text NOT NULL,
  `po_no` varchar(20) NOT NULL,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rr_no` (`rr_no`),
  KEY `po_no` (`po_no`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `is_disabled`) VALUES
(1, 'TMDD-Dev', '0'),
(2, 'Super Admin', '0'),
(3, 'Equipment Manager', '0'),
(4, 'User Manager', '0'),
(5, 'RP Manager', '0'),
(6, 'Auditor', '0');

-- --------------------------------------------------------

--
-- Table structure for table `role_module_privileges`
--

DROP TABLE IF EXISTS `role_module_privileges`;
CREATE TABLE IF NOT EXISTS `role_module_privileges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_id` int DEFAULT NULL,
  `module_id` int DEFAULT NULL,
  `can_track` tinyint(1) DEFAULT '0',
  `can_create` tinyint(1) DEFAULT '0',
  `can_view` tinyint(1) DEFAULT '0',
  `can_edit` tinyint(1) DEFAULT '0',
  `can_undo` tinyint(1) DEFAULT '0',
  `can_delete` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  KEY `module_id` (`module_id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_module_privileges`
--

INSERT INTO `role_module_privileges` (`id`, `role_id`, `module_id`, `can_track`, `can_create`, `can_view`, `can_edit`, `can_undo`, `can_delete`) VALUES
(1, 1, 1, 1, 1, 1, 1, 1, 1),
(2, 1, 2, 1, 1, 1, 1, 1, 1),
(3, 1, 3, 1, 1, 1, 1, 1, 1),
(4, 1, 4, 1, 1, 1, 1, 1, 1),
(5, 4, 3, 0, 1, 1, 1, 0, 0),
(6, 5, 2, 0, 1, 1, 1, 1, 0),
(7, 4, 3, 0, 1, 1, 1, 0, 0),
(8, 5, 2, 0, 1, 1, 1, 1, 0),
(9, 5, 4, 0, 0, 0, 0, 0, 0),
(10, 1, 6, 0, 1, 1, 0, 0, 0),
(11, 5, 4, 0, 0, 0, 0, 0, 0),
(12, 1, 6, 0, 1, 1, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('Offline','Online') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `created_at`, `status`, `is_disabled`) VALUES
(1, 'navithebear', 'navi@example.com', '$2y$12$kEvIhLW4m0MArA1EZDBtuuq29Oida7.m8SpyC1xWR4V9TnFP/dijq', 'navi', 'slu', '2025-02-19 01:19:52', 'Offline', '0'),
(2, 'userman', 'um@example.com', '$2y$12$wE3B0Dq4z0Bd1AHXf4gumexeObTqWXm7aASm7PnkCrtiL.iIfObS.', 'user', 'manager', '2025-02-19 05:40:35', 'Offline', '0'),
(3, 'equipman', 'em@example.com', '$2y$12$J0iy9bwoalbG2/NkqDZchuLU4sWramGpsw1EsSZ6se0CefM/sqpZq', 'equipment', 'manager', '2025-02-19 05:40:35', 'Offline', '0'),
(4, 'rpman', 'rp@example.com', '$2y$12$dWnJinU4uO7ETYIKi9cL0uN4wJgjACaF.q0Pbkr5yNUK2q1HUQk8G', 'roles', 'Privileges-manager', '2025-02-19 05:41:59', 'Offline', '0'),
(5, 'auds', 'auds@example.com', '$2y$12$VRIJ5Okf3p9fE3Xtq.qyze/t./h30ZsV7y7pg4UFksFiJ8JdMSh/q', 'audi', 'tor', '2025-02-19 05:41:59', 'Offline', '0');

-- --------------------------------------------------------

--
-- Table structure for table `user_departments`
--

DROP TABLE IF EXISTS `user_departments`;
CREATE TABLE IF NOT EXISTS `user_departments` (
  `user_id` int NOT NULL,
  `department_id` int NOT NULL,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`department_id`),
  KEY `department_id` (`department_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_departments`
--

INSERT INTO `user_departments` (`user_id`, `department_id`, `is_disabled`) VALUES
(1, 40, '0');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`, `is_disabled`) VALUES
(1, 1, '0'),
(2, 4, '0'),
(3, 3, '0'),
(4, 5, '0'),
(5, 6, '0');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
