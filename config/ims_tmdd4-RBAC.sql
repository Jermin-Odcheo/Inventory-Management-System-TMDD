-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 28, 2025 at 04:15 AM
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
  `action` enum('Modified',' Permanently Deleted','Restored','Remove') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `old_value` text,
  `new_value` text,
  `module_id` int DEFAULT NULL,
  `status` enum('Successful','Failed') NOT NULL,
  `date_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`track_id`),
  KEY `user_id` (`user_id`),
  KEY `module_id` (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `charge_invoice`
--

DROP TABLE IF EXISTS `charge_invoice`;
CREATE TABLE IF NOT EXISTS `charge_invoice` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `date_of_purchase` date NOT NULL,
  `po_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `po_no` (`po_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `department_name` varchar(191) NOT NULL,
  `abbreviation` varchar(50) NOT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_name` (`department_name`),
  UNIQUE KEY `abbreviation` (`abbreviation`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `abbreviation`, `is_disabled`) VALUES
(1, 'Office of the President', 'OP', 1),
(2, 'Office of the Executive Assistant to the President', 'OEAP', 1),
(3, 'Office of the Internal Auditor', 'OIA', 1),
(4, 'Office of the Vice President for Mission and Identity', 'OVPMI', 1),
(5, 'Center for Campus Ministry', 'CCM', 1),
(6, 'Community Extension and Outreach Programs Office', 'CEOP', 1),
(7, 'St. Aloysius Gonzaga Parish Office', 'SAGPO', 1),
(8, 'Sunflower Child and Youth Wellness Center', 'SCYWC', 1),
(9, 'Office of the Vice President for Academic Affairs', 'OVPAA', 1),
(10, 'School of Accountancy, Management, Computing and Information Studies', 'SAMCIS', 1),
(11, 'School of Advanced Studies', 'SAS', 1),
(12, 'School of Engineering and Architecture', 'SEA', 1),
(13, 'School of Law', 'SOL', 1),
(14, 'School of Medicine', 'SOM', 1),
(15, 'School of Nursing, Allied Health, and Biological Sciences Natural Sciences', 'SONAHBS', 1),
(16, 'School of Teacher Education and Liberal Arts', 'STELA', 1),
(17, 'Basic Education School', 'SLU BEdS', 1),
(18, 'Office of Institutional Development and Quality Assurance', 'OIDQA', 1),
(19, 'University Libraries', 'UL', 1),
(20, 'University Registrar’s Office', 'URO', 1),
(21, 'University Research and Innovation Center', 'URIC', 1),
(22, 'Office of the Vice President for Finance', 'OVPF', 1),
(23, 'Asset Management and Inventory Control Office', 'AMICO', 1),
(24, 'Finance Office', 'FO', 1),
(25, 'Printing Operations Office', 'POO', 1),
(26, 'Technology Management and Development Department', 'TMDD', 1),
(27, 'Office of the Vice President for Administration', 'OVPA', 1),
(28, 'Athletics and Fitness Center', 'AFC', 1),
(29, 'Campus Planning, Maintenance, and Security Department', 'CPMSD', 1),
(30, 'Center for Culture and the Arts', 'CCA', 1),
(31, 'Dental Clinic', 'DC', 1),
(32, 'Guidance Center', 'GC', 1),
(33, 'Human Resource Department', 'HRD', 1),
(34, 'Students’ Residence Hall', 'SRH', 1),
(35, 'Medical Clinic', 'MC', 1),
(36, 'Office for Legal Affairs', 'OLA', 1),
(37, 'Office of Student Affairs', 'OSA', 1),
(40, 'TMDD Developers', 'TMDD-Dev', 1);

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
  `invoice_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `rr_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `equipment_location_id` int DEFAULT NULL,
  `equipment_status_id` int DEFAULT NULL,
  `remarks` text,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_tag` (`asset_tag`),
  UNIQUE KEY `equipment_location_id` (`equipment_location_id`) USING BTREE,
  UNIQUE KEY `equipment_status_id` (`equipment_status_id`) USING BTREE,
  KEY `invoice_no` (`invoice_no`),
  KEY `rr_no` (`rr_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_location`
--

DROP TABLE IF EXISTS `equipment_location`;
CREATE TABLE IF NOT EXISTS `equipment_location` (
  `equipment_location_id` int NOT NULL AUTO_INCREMENT,
  `asset_tag` varchar(50) NOT NULL,
  `building_loc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `floor_no` varchar(50) DEFAULT NULL,
  `specific_area` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `person_responsible` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `remarks` text,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`equipment_location_id`) USING BTREE,
  UNIQUE KEY `asset_tag` (`asset_tag`) USING BTREE,
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_status`
--

DROP TABLE IF EXISTS `equipment_status`;
CREATE TABLE IF NOT EXISTS `equipment_status` (
  `equipment_status_id` int NOT NULL AUTO_INCREMENT,
  `asset_tag` varchar(50) NOT NULL,
  `status` enum('Operational','Under Maintenance','Decommissioned','Disposed') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `action` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `remarks` text,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`equipment_status_id`) USING BTREE,
  UNIQUE KEY `asset_tag` (`asset_tag`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `module_name`) VALUES
(1, 'Audit'),
(4, 'Equipment Management'),
(2, 'Roles and Privileges'),
(3, 'User Management');

-- --------------------------------------------------------

--
-- Table structure for table `privileges`
--

DROP TABLE IF EXISTS `privileges`;
CREATE TABLE IF NOT EXISTS `privileges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `priv_name` varchar(191) NOT NULL,
  `is_disabled` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `privileges`
--

INSERT INTO `privileges` (`id`, `priv_name`, `is_disabled`) VALUES
(1, 'Track', 0),
(2, 'Create', 0),
(3, 'Add', 0),
(4, 'Remove', 0),
(5, 'Delete', 0),
(6, 'Modify', 0),
(7, 'View', 0),
(8, 'Restore', 0),
(9, 'Undo', 0),
(10, 'Assign', 0),
(11, 'Approve', 0),
(12, 'Reject', 0);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order`
--

DROP TABLE IF EXISTS `purchase_order`;
CREATE TABLE IF NOT EXISTS `purchase_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `po_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `date_of_order` date NOT NULL,
  `no_of_units` int NOT NULL,
  `item_specifications` text NOT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_no` (`po_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receive_report`
--

DROP TABLE IF EXISTS `receive_report`;
CREATE TABLE IF NOT EXISTS `receive_report` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rr_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `accountable_individual` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `ai_loc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `po_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rr_no` (`rr_no`),
  KEY `po_no` (`po_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `is_disabled`) VALUES
(1, 'TMDD-Dev', 1),
(2, 'Super Admin', 1),
(3, 'Equipment Manager', 1),
(4, 'User Manager', 1),
(5, 'RP Manager', 1),
(6, 'Auditor', 1);

-- --------------------------------------------------------

--
-- Table structure for table `role_module_privileges`
--

DROP TABLE IF EXISTS `role_module_privileges`;
CREATE TABLE IF NOT EXISTS `role_module_privileges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_id` int DEFAULT NULL,
  `module_id` int DEFAULT NULL,
  `privilege_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  KEY `module_id` (`module_id`),
  KEY `fk_rmp_privilege` (`privilege_id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_module_privileges`
--

INSERT INTO `role_module_privileges` (`id`, `role_id`, `module_id`, `privilege_id`) VALUES
(1, 6, 1, 1),
(2, 1, 1, 1),
(3, 1, 2, 1),
(4, 1, 2, 2),
(5, 1, 2, 3),
(6, 1, 2, 4),
(7, 1, 2, 5),
(8, 1, 2, 6),
(9, 1, 2, 7),
(10, 1, 2, 8),
(11, 1, 2, 9),
(12, 1, 2, 10),
(13, 1, 2, 11),
(14, 1, 2, 12),
(15, 1, 3, 1),
(16, 1, 3, 2),
(17, 1, 3, 3),
(18, 1, 3, 4),
(19, 1, 3, 5),
(20, 1, 3, 6),
(21, 1, 3, 7),
(22, 1, 3, 8),
(23, 1, 3, 9),
(24, 1, 3, 10),
(25, 1, 3, 11),
(26, 1, 3, 12),
(27, 1, 4, 1),
(28, 1, 4, 2),
(29, 1, 4, 3),
(30, 1, 4, 4),
(31, 1, 4, 5),
(32, 1, 4, 6),
(33, 1, 4, 7),
(34, 1, 4, 8),
(35, 1, 4, 9),
(36, 1, 4, 10),
(37, 1, 4, 11),
(38, 1, 4, 12),
(40, 3, 4, 1),
(41, 3, 4, 2),
(42, 3, 4, 3),
(43, 3, 4, 4),
(44, 3, 4, 5),
(45, 3, 4, 6),
(46, 3, 4, 7),
(47, 3, 4, 8),
(48, 3, 4, 9),
(49, 3, 4, 10),
(50, 3, 4, 11),
(51, 3, 4, 12),
(52, 4, 3, 1),
(53, 4, 3, 2),
(54, 4, 3, 3),
(55, 4, 3, 4),
(56, 4, 3, 5),
(57, 4, 3, 6),
(58, 4, 3, 7),
(59, 4, 3, 8),
(60, 4, 3, 9),
(61, 4, 3, 10),
(62, 4, 3, 11),
(63, 4, 3, 12),
(64, 5, 2, 1),
(65, 5, 2, 2),
(66, 5, 2, 3),
(67, 5, 2, 4),
(68, 5, 2, 5),
(69, 5, 2, 6),
(70, 5, 2, 7),
(71, 5, 2, 8),
(72, 5, 2, 9),
(73, 5, 2, 10),
(74, 5, 2, 11),
(75, 5, 2, 12);

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
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `created_at`, `status`, `is_disabled`) VALUES
(1, 'navithebear', 'navi@example.com', '$2y$12$2esj1uaDmbD3K6Fi.C0CiuOye96x8OjARwTc82ViEAPvmx4b1cL0S', 'navi', 'slu', '2025-02-19 01:19:52', 'Online', 1),
(2, 'userman', 'um@example.com', '$2y$12$wE3B0Dq4z0Bd1AHXf4gumexeObTqWXm7aASm7PnkCrtiL.iIfObS.', 'user', 'manager', '2025-02-19 05:40:35', 'Offline', 1),
(3, 'equipman', 'em@example.com', '$2y$12$J0iy9bwoalbG2/NkqDZchuLU4sWramGpsw1EsSZ6se0CefM/sqpZq', 'equipment', 'manager', '2025-02-19 05:40:35', 'Offline', 1),
(4, 'rpman', 'rp@example.com', '$2y$12$dWnJinU4uO7ETYIKi9cL0uN4wJgjACaF.q0Pbkr5yNUK2q1HUQk8G', 'ropriv', 'manager', '2025-02-19 05:41:59', 'Offline', 1),
(5, 'auds', 'auds@example.com', '$2y$12$VRIJ5Okf3p9fE3Xtq.qyze/t./h30ZsV7y7pg4UFksFiJ8JdMSh/q', 'audi', 'broom broom', '2025-02-19 05:41:59', 'Offline', 1);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(3, 3),
(2, 4),
(4, 5),
(5, 6);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `charge_invoice`
--
ALTER TABLE `charge_invoice`
  ADD CONSTRAINT `charge_invoice_ibfk_1` FOREIGN KEY (`po_no`) REFERENCES `purchase_order` (`po_no`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `equipment_details`
--
ALTER TABLE `equipment_details`
  ADD CONSTRAINT `equipment_details_ibfk_1` FOREIGN KEY (`invoice_no`) REFERENCES `charge_invoice` (`invoice_no`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `equipment_details_ibfk_2` FOREIGN KEY (`rr_no`) REFERENCES `receive_report` (`rr_no`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `equipment_details_ibfk_3` FOREIGN KEY (`equipment_location_id`) REFERENCES `equipment_location` (`equipment_location_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `equipment_details_ibfk_4` FOREIGN KEY (`equipment_status_id`) REFERENCES `equipment_status` (`equipment_status_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `equipment_location`
--
ALTER TABLE `equipment_location`
  ADD CONSTRAINT `equipment_location_ibfk_1` FOREIGN KEY (`asset_tag`) REFERENCES `equipment_details` (`asset_tag`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `equipment_status`
--
ALTER TABLE `equipment_status`
  ADD CONSTRAINT `equipment_status_ibfk_1` FOREIGN KEY (`asset_tag`) REFERENCES `equipment_details` (`asset_tag`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `receive_report`
--
ALTER TABLE `receive_report`
  ADD CONSTRAINT `receive_report_ibfk_1` FOREIGN KEY (`po_no`) REFERENCES `purchase_order` (`po_no`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `role_module_privileges`
--
ALTER TABLE `role_module_privileges`
  ADD CONSTRAINT `fk_rmp_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rmp_privilege` FOREIGN KEY (`privilege_id`) REFERENCES `privileges` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rmp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_departments`
--
ALTER TABLE `user_departments`
  ADD CONSTRAINT `user_departments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_departments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
