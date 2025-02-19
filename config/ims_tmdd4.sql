-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 19, 2025 at 05:55 AM
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
  `is_disabled` tinyint(1) DEFAULT '0',
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_name` (`department_name`),
  UNIQUE KEY `abbreviation` (`abbreviation`)
) ENGINE=MyISAM AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `abbreviation`) VALUES
(1, 'Office of the President', 'OP'),
(2, 'Office of the Executive Assistant to the President', 'OEAP'),
(3, 'Office of the Internal Auditor', 'OIA'),
(4, 'Office of the Vice President for Mission and Identity', 'OVPMI'),
(5, 'Center for Campus Ministry', 'CCM'),
(6, 'Community Extension and Outreach Programs Office', 'CEOP'),
(7, 'St. Aloysius Gonzaga Parish Office', 'SAGPO'),
(8, 'Sunflower Child and Youth Wellness Center', 'SCYWC'),
(9, 'Office of the Vice President for Academic Affairs', 'OVPAA'),
(10, 'School of Accountancy, Management, Computing and Information Studies', 'SAMCIS'),
(11, 'School of Advanced Studies', 'SAS'),
(12, 'School of Engineering and Architecture', 'SEA'),
(13, 'School of Law', 'SOL'),
(14, 'School of Medicine', 'SOM'),
(15, 'School of Nursing, Allied Health, and Biological Sciences Natural Sciences', 'SONAHBS'),
(16, 'School of Teacher Education and Liberal Arts', 'STELA'),
(17, 'Basic Education School', 'SLU BEdS'),
(18, 'Office of Institutional Development and Quality Assurance', 'OIDQA'),
(19, 'University Libraries', 'UL'),
(20, 'University Registrar’s Office', 'URO'),
(21, 'University Research and Innovation Center', 'URIC'),
(22, 'Office of the Vice President for Finance', 'OVPF'),
(23, 'Asset Management and Inventory Control Office', 'AMICO'),
(24, 'Finance Office', 'FO'),
(25, 'Printing Operations Office', 'POO'),
(26, 'Technology Management and Development Department', 'TMDD'),
(27, 'Office of the Vice President for Administration', 'OVPA'),
(28, 'Athletics and Fitness Center', 'AFC'),
(29, 'Campus Planning, Maintenance, and Security Department', 'CPMSD'),
(30, 'Center for Culture and the Arts', 'CCA'),
(31, 'Dental Clinic', 'DC'),
(32, 'Guidance Center', 'GC'),
(33, 'Human Resource Department', 'HRD'),
(34, 'Students’ Residence Hall', 'SRH'),
(35, 'Medical Clinic', 'MC'),
(36, 'Office for Legal Affairs', 'OLA'),
(37, 'Office of Student Affairs', 'OSA'),
(40, 'TMDD Developers', 'TMDD-Dev');

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
  `is_disabled` tinyint(1) DEFAULT '0',
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
  `is_disabled` tinyint(1) DEFAULT '0',
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
  `is_disabled` tinyint(1) DEFAULT '0',
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
  `is_disabled` tinyint(1) DEFAULT '0',
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
  `is_disabled` tinyint(1) DEFAULT '0',
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'TMDD-Dev'),
(2, 'Super Admin'),
(4, 'Equipment Manager'),
(5, 'User Manager'),
(6, 'RP Manager'),
(7, 'Auditor');

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
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_module_privileges`
--

INSERT INTO `role_module_privileges` (`id`, `role_id`, `module_id`, `can_track`, `can_create`, `can_view`, `can_edit`, `can_undo`, `can_delete`) VALUES
(1, 1, 1, 1, 1, 1, 1, 1, 1),
(2, 1, 2, 1, 1, 1, 1, 1, 1),
(3, 1, 3, 1, 1, 1, 1, 1, 1),
(4, 1, 4, 1, 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `created_at`) VALUES
(1, 'navithebear', 'navi@example.com', 'navi123', 'navi', 'slu', '2025-02-19 01:19:52'),
(2, 'userman', 'usermanager@example.com', 'userman123', 'user', 'manager', '2025-02-19 05:40:35'),
(3, 'equipman', 'equipmentmanager@example.com', 'equipment123', 'equipment', 'manager', '2025-02-19 05:40:35'),
(4, 'rpman', 'rolesandprivieleges@example.com', 'rp123', 'roles', 'Privileges-manager', '2025-02-19 05:41:59'),
(5, 'auds', 'auditor@example.com', 'auds123', 'audi', 'tor', '2025-02-19 05:41:59');

-- --------------------------------------------------------

--
-- Table structure for table `user_departments`
--

DROP TABLE IF EXISTS `user_departments`;
CREATE TABLE IF NOT EXISTS `user_departments` (
  `user_id` int NOT NULL,
  `department_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`department_id`),
  KEY `department_id` (`department_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_departments`
--

INSERT INTO `user_departments` (`user_id`, `department_id`) VALUES
(1, 40);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(2, 5),
(3, 4),
(4, 6),
(5, 0);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
