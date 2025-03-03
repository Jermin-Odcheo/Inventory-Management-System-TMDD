-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 03, 2025 at 03:54 AM
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
-- Database: `ims_tmddrbac`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE IF NOT EXISTS `audit_log` (
  `TrackID` int NOT NULL AUTO_INCREMENT,
  `UserID` int NOT NULL,
  `EntityID` int DEFAULT NULL,
  `Action` enum('View','Modified','Delete','Add','Undo','Update','Create','Remove','Restored') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `Details` text,
  `OldVal` text,
  `NewVal` text,
  `Module` varchar(50) NOT NULL,
  `Status` enum('Successful','Failed') NOT NULL,
  `Date_Time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`TrackID`),
  KEY `idx_module` (`Module`),
  KEY `idx_action` (`Action`)
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`TrackID`, `UserID`, `EntityID`, `Action`, `Details`, `OldVal`, `NewVal`, `Module`, `Status`, `Date_Time`) VALUES
(78, 1, 1, 'Modified', 'Updated fields: First_Name, Last_Name, Department', '{\"Email\": \"superadmin@example.com\", \"Status\": \"\", \"User_ID\": 1, \"Password\": \"$2y$10$i4ei.yjO/RWfQmsCjZrbUO8PdC8YKip8/JRf8yXYC17AU7RbHt8vq\", \"Last_Name\": \"Admin1\", \"Department\": \"TMDD1\", \"First_Name\": \"Super1\", \"Last_Active\": \"2025-02-05 11:08:09.000000\"}', '{\"Email\": \"superadmin@example.com\", \"Status\": \"\", \"User_ID\": 1, \"Password\": \"$2y$10$i4ei.yjO/RWfQmsCjZrbUO8PdC8YKip8/JRf8yXYC17AU7RbHt8vq\", \"Last_Name\": \"Admin1adf\", \"Department\": \"Office of the President\", \"First_Name\": \"Super1asdf\", \"Last_Active\": \"2025-02-05 11:08:09.000000\"}', 'User Management', 'Successful', '2025-02-28 09:18:53'),
(79, 1, 1, 'Modified', 'Updated fields: First_Name, Last_Name', '{\"Email\": \"superadmin@example.com\", \"Status\": \"\", \"User_ID\": 1, \"Password\": \"$2y$10$i4ei.yjO/RWfQmsCjZrbUO8PdC8YKip8/JRf8yXYC17AU7RbHt8vq\", \"Last_Name\": \"Admin1adf\", \"Department\": \"Office of the President\", \"First_Name\": \"Super1asdf\", \"Last_Active\": \"2025-02-05 11:08:09.000000\"}', '{\"Email\": \"superadmin@example.com\", \"Status\": \"\", \"User_ID\": 1, \"Password\": \"$2y$10$i4ei.yjO/RWfQmsCjZrbUO8PdC8YKip8/JRf8yXYC17AU7RbHt8vq\", \"Last_Name\": \"Admin\", \"Department\": \"Office of the President\", \"First_Name\": \"Super\", \"Last_Active\": \"2025-02-05 11:08:09.000000\"}', 'User Management', 'Successful', '2025-02-28 09:19:07'),
(80, 1, 1, 'Modified', 'Updated fields: First_Name, Last_Name', '{\"Email\": \"superadmin@example.com\", \"Status\": \"\", \"User_ID\": 1, \"Password\": \"$2y$10$i4ei.yjO/RWfQmsCjZrbUO8PdC8YKip8/JRf8yXYC17AU7RbHt8vq\", \"Last_Name\": \"Admin\", \"Department\": \"Office of the President\", \"First_Name\": \"Super\", \"Last_Active\": \"2025-02-05 11:08:09.000000\"}', '{\"Email\": \"superadmin@example.com\", \"Status\": \"\", \"User_ID\": 1, \"Password\": \"$2y$10$i4ei.yjO/RWfQmsCjZrbUO8PdC8YKip8/JRf8yXYC17AU7RbHt8vq\", \"Last_Name\": \"Admin1\", \"Department\": \"Office of the President\", \"First_Name\": \"Super1\", \"Last_Active\": \"2025-02-05 11:08:09.000000\"}', 'User Management', 'Successful', '2025-02-28 09:21:13'),
(89, 1, 147, 'Create', 'New user added: superuse12312312r@example.com', NULL, '{\"Email\": \"superuse12312312r@example.com\", \"User_ID\": 147, \"Last_Name\": \"123123\", \"Department\": \"SOL\", \"First_Name\": \"123123\"}', 'User Management', 'Successful', '2025-02-28 13:44:37'),
(90, 1, 146, 'Remove', 'User soft deleted (is_deleted set to 1)', '{\"Email\": \"1233333@example.com\", \"Status\": \"Offline\", \"User_ID\": 146, \"Last_Name\": \"123\", \"Department\": \"SOM\", \"First_Name\": \"312\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"1233333@example.com\", \"Status\": \"Offline\", \"User_ID\": 146, \"Last_Name\": \"123\", \"Department\": \"SOM\", \"First_Name\": \"312\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-28 14:12:15'),
(91, 1, 147, 'Remove', 'User soft deleted (is_deleted set to 1)', '{\"Email\": \"superuse12312312r@example.com\", \"Status\": \"Offline\", \"User_ID\": 147, \"Last_Name\": \"123123\", \"Department\": \"SOL\", \"First_Name\": \"123123\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"superuse12312312r@example.com\", \"Status\": \"Offline\", \"User_ID\": 147, \"Last_Name\": \"123123\", \"Department\": \"SOL\", \"First_Name\": \"123123\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-28 14:12:15'),
(92, 1, 144, 'Remove', 'User soft deleted (is_deleted set to 1)', '{\"Email\": \"superadmin123123@example.com\", \"Status\": \"Offline\", \"User_ID\": 144, \"Last_Name\": \"123\", \"Department\": \"SOL\", \"First_Name\": \"123\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"superadmin123123@example.com\", \"Status\": \"Offline\", \"User_ID\": 144, \"Last_Name\": \"123\", \"Department\": \"SOL\", \"First_Name\": \"123\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-28 14:44:59'),
(93, 1, 145, 'Remove', 'User soft deleted (is_deleted set to 1)', '{\"Email\": \"testtest123@example.com\", \"Status\": \"Offline\", \"User_ID\": 145, \"Last_Name\": \"123\", \"Department\": \"SOM\", \"First_Name\": \"123\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"testtest123@example.com\", \"Status\": \"Offline\", \"User_ID\": 145, \"Last_Name\": \"123\", \"Department\": \"SOM\", \"First_Name\": \"123\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-28 14:44:59'),
(94, 1, 144, 'Delete', 'User has been deleted', '{\"Email\": \"superadmin123123@example.com\", \"Status\": \"Offline\", \"User_ID\": 144, \"Last_Name\": \"123\", \"Department\": \"SOL\", \"First_Name\": \"123\", \"is_deleted\": 1, \"Last_Active\": null}', '', 'User Management', 'Successful', '2025-02-28 15:21:12'),
(96, 1, 145, 'Modified', 'Updated fields: First_Name, Last_Name', '{\"Email\": \"testtest123@example.com\", \"Status\": \"Offline\", \"User_ID\": 145, \"Password\": \"$2y$10$2dQ47SHsBGEhLS9x6bIqLeuLCpr3ZwgP3kEZ0XVYMD.2/CIvpVCZK\", \"Last_Name\": \"123\", \"Department\": \"SOM\", \"First_Name\": \"123\", \"Last_Active\": null}', '{\"Email\": \"testtest123@example.com\", \"Status\": \"Offline\", \"User_ID\": 145, \"Password\": \"$2y$10$2dQ47SHsBGEhLS9x6bIqLeuLCpr3ZwgP3kEZ0XVYMD.2/CIvpVCZK\", \"Last_Name\": \"123123\", \"Department\": \"SOM\", \"First_Name\": \"123123\", \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-28 15:52:59'),
(97, 1, 146, 'Restored', '1233333@example.com has been restored', '{\"is_deleted\": 1}', '{\"is_deleted\": 0}', 'User Management', 'Successful', '2025-02-28 15:53:35'),
(98, 1, 147, 'Restored', 'User restored (is_deleted set to 0)', '{\"Email\": \"superuse12312312r@example.com\", \"Status\": \"Offline\", \"User_ID\": 147, \"Last_Name\": \"123123\", \"Department\": \"SOL\", \"First_Name\": \"123123\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"superuse12312312r@example.com\", \"Status\": \"Offline\", \"User_ID\": 147, \"Last_Name\": \"123123\", \"Department\": \"SOL\", \"First_Name\": \"123123\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-28 15:57:09');

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
(20, 'University Registrar's Office', 'URO', 1),
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
(34, 'Students' Residence Hall', 'SRH', 1),
(35, 'Medical Clinic', 'MC', 1),
(36, 'Office for Legal Affairs', 'OLA', 1),
(37, 'Office of Student Affairs', 'OSA', 1),
(40, 'TMDD Developers', 'TMDD-Dev', 0);

-- --------------------------------------------------------

--
-- Table structure for table `equipment_details`
--

DROP TABLE IF EXISTS `equipment_details`;
CREATE TABLE IF NOT EXISTS `equipment_details` (
  `EquipmentDetailsID` int NOT NULL AUTO_INCREMENT,
  `AssetTag` varchar(50) NOT NULL,
  `AssetDescription1` text NOT NULL,
  `AssetDescription2` text NOT NULL,
  `Specification` text NOT NULL,
  `Brand` varchar(100) DEFAULT NULL,
  `Model` varchar(100) DEFAULT NULL,
  `SerialNumber` varchar(100) DEFAULT NULL,
  `InvoiceNo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `RRNo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `EquipmentLocationID` int DEFAULT NULL,
  `EquipmentStatusID` int DEFAULT NULL,
  `Remarks` text,
  `DateAcquired` datetime DEFAULT NULL,
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`EquipmentDetailsID`),
  UNIQUE KEY `AssetTag` (`AssetTag`),
  KEY `EquipmentLocationID` (`EquipmentLocationID`),
  KEY `EquipmentStatusID` (`EquipmentStatusID`),
  KEY `InvoiceNo` (`InvoiceNo`),
  KEY `RRNo` (`RRNo`)
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
(1, 'TMDD-Dev', 0),
(2, 'Super Admin', 0),
(3, 'Equipment Manager', 0),
(4, 'User Manager', 0),
(5, 'RP Manager', 0),
(6, 'Auditor', 0);

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