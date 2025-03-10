-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 07, 2025 at 08:56 AM
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
CREATE DATABASE IF NOT EXISTS `ims_tmddrbac` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `ims_tmddrbac`;

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `UpdateUserAndDepartment`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateUserAndDepartment` (IN `p_user_id` INT, IN `p_email` VARCHAR(255), IN `p_first_name` VARCHAR(191), IN `p_last_name` VARCHAR(191), IN `p_password` VARCHAR(255), IN `p_status` VARCHAR(50), IN `p_department_id` INT, IN `p_changed_by` INT, IN `p_module` VARCHAR(191))   BEGIN
    DECLARE diffList TEXT DEFAULT '';
    DECLARE old_email VARCHAR(255);
    DECLARE old_first_name VARCHAR(191);
    DECLARE old_last_name VARCHAR(191);
    DECLARE old_password VARCHAR(255);
    DECLARE old_department_id INT;
    DECLARE old_department_name VARCHAR(191);
    DECLARE new_department_name VARCHAR(191);

    SELECT email, first_name, last_name, password INTO old_email, old_first_name, old_last_name, old_password
    FROM users WHERE id = p_user_id;

    SELECT department_id INTO old_department_id FROM user_departments WHERE user_id = p_user_id LIMIT 1;

    SELECT department_name INTO old_department_name FROM departments WHERE id = old_department_id LIMIT 1;
    SELECT department_name INTO new_department_name FROM departments WHERE id = p_department_id LIMIT 1;

    -- Update user details
    UPDATE users SET
        email = p_email,
        first_name = p_first_name,
        last_name = p_last_name
    WHERE id = p_user_id;

    IF old_department_id IS NULL THEN
        INSERT INTO user_departments(user_id, department_id) VALUES (p_user_id, p_department_id);
        SET diffList = CONCAT(diffList, 'department (added), ');
    ELSEIF old_department_id <> p_department_id THEN
        UPDATE user_departments SET department_id = p_department_id WHERE user_id = p_user_id;
        SET diffList = CONCAT(diffList, 'department, ');
    END IF;

    IF old_email <> p_email THEN SET diffList = CONCAT(diffList, 'email, '); END IF;
    IF old_first_name <> p_first_name THEN SET diffList = CONCAT(diffList, 'first_name, '); END IF;
    IF old_last_name <> p_last_name THEN SET diffList = CONCAT(diffList, 'last_name, '); END IF;

    INSERT INTO audit_log (
        UserID,
        EntityID,
        Action,
        Details,
        OldVal,
        NewVal,
        Module,
        Status,
        Date_Time
    )
    VALUES (
        p_changed_by,
        p_user_id,
        'Modified',
        IF(TRIM(diffList) <> '', CONCAT('Updated fields: ', TRIM(TRAILING ', ' FROM diffList)), 'No changes'),
        JSON_OBJECT(
            'email', old_email,
            'first_name', old_first_name,
            'last_name', old_last_name,
            'department', old_department_name
        ),
        JSON_OBJECT(
            'email', p_email,
            'first_name', p_first_name,
            'last_name', p_last_name,
            'department', new_department_name
        ),
        IFNULL(p_module, 'User Management'),
        IF(diffList <> '', 'Successful', 'No Changes'),
        NOW()
    );
END$$

DELIMITER ;

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
) ENGINE=InnoDB AUTO_INCREMENT=182 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`TrackID`, `UserID`, `EntityID`, `Action`, `Details`, `OldVal`, `NewVal`, `Module`, `Status`, `Date_Time`) VALUES
(109, 1, 1, 'Add', 'New equipment added', NULL, '{\"asset_tag\":\"23123\",\"asset_description_1\":\"1231\",\"asset_description_2\":\"123\",\"specifications\":\"123\",\"brand\":\"123\",\"model\":\"123\",\"serial_number\":\"123123\",\"date_created\":\"2025-03-13T16:38\",\"remarks\":\"123123\"}', 'Equipment Details', 'Successful', '2025-03-06 16:38:58'),
(110, 1, 11, 'Create', 'New user added: ttestinglast', NULL, '{\"id\": 11, \"email\": \"testing@gmail.com\", \"status\": \"Offline\", \"username\": \"ttestinglast\", \"last_name\": \"TestingLast\", \"department\": \"Unknown\", \"first_name\": \"TestingFirst\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', 'User Management', 'Successful', '2025-03-07 11:52:51'),
(114, 1, 11, 'Modified', 'Updated fields: email, first_name, last_name', '{\"id\": 11, \"email\": \"testing@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingLast\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingFirst\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', '{\"id\": 11, \"email\": \"testingCase@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', 'User Management', 'Successful', '2025-03-07 12:27:16'),
(116, 1, 11, 'Modified', 'Updated fields: email, first_name, last_name', '{\"id\": 11, \"email\": \"testingCase@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', '{\"id\": 11, \"email\": \"testingCase1@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast1\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst1\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', 'User Management', 'Successful', '2025-03-07 12:45:57'),
(118, 1, 11, 'Modified', 'Updated fields: email, first_name, last_name', '{\"id\": 11, \"email\": \"testingCase1@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast1\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst1\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', '{\"id\": 11, \"email\": \"testingCase11@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast11\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst11\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', 'User Management', 'Successful', '2025-03-07 12:55:32'),
(120, 1, 11, 'Modified', 'Updated fields: email, first_name, last_name', '{\"id\": 11, \"email\": \"testingCase11@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast11\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst11\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', '{\"id\": 11, \"email\": \"testingCase111@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast111\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst111\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', 'User Management', 'Successful', '2025-03-07 13:00:26'),
(122, 1, 11, 'Modified', 'Updated fields: email, first_name, last_name', '{\"id\": 11, \"email\": \"testingCase111@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast111\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst111\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', '{\"id\": 11, \"email\": \"testingCase1111@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast1111\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst1111\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', 'User Management', 'Successful', '2025-03-07 13:01:57'),
(123, 1, 11, 'Modified', 'Updated fields: email, first_name, last_name', '{\"id\": 11, \"email\": \"testingCase1111@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast1111\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst1111\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', '{\"id\": 11, \"email\": \"testingCase11111@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast11111\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst11111\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', 'User Management', 'Successful', '2025-03-07 13:05:24'),
(134, 1, 11, 'Restored', 'User has been restored', '{\"id\": 11, \"email\": \"testingCase11111@gmail.com\", \"status\": \"\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast11111\", \"first_name\": \"TestingCaseFirst11111\", \"is_disabled\": 1, \"date_created\": \"2025-03-07 11:52:51.000000\"}', '', 'User Management', 'Successful', '2025-03-07 13:36:28'),
(135, 1, 11, 'Remove', 'User has been removed', '{\"id\": 11, \"email\": \"testingCase11111@gmail.com\", \"status\": \"\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast11111\", \"first_name\": \"TestingCaseFirst11111\", \"is_disabled\": 0, \"date_created\": \"2025-03-07 11:52:51.000000\"}', '', 'User Management', 'Successful', '2025-03-07 13:37:11'),
(136, 1, 11, 'Restored', 'User has been restored', '{\"id\": 11, \"email\": \"testingCase11111@gmail.com\", \"status\": \"\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast11111\", \"first_name\": \"TestingCaseFirst11111\", \"is_disabled\": 1, \"date_created\": \"2025-03-07 11:52:51.000000\"}', '', 'User Management', 'Successful', '2025-03-07 13:37:22'),
(179, 1, 11, 'Modified', 'Updated fields: department, email, first_name, last_name', '{\"email\": \"testingCase@gmail.com\", \"last_name\": \"TestingCaseLast\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst\"}', '{\"email\": \"testingCase1@gmail.com\", \"last_name\": \"TestingCaseLast1\", \"department\": \"Office of the President\", \"first_name\": \"TestingCaseFirst1\"}', 'User Management', 'Successful', '2025-03-07 16:55:26'),
(180, 1, 11, 'Modified', 'Updated fields: department', '{\"email\": \"testingCase1@gmail.com\", \"last_name\": \"TestingCaseLast1\", \"department\": \"Office of the President\", \"first_name\": \"TestingCaseFirst1\"}', '{\"email\": \"testingCase1@gmail.com\", \"last_name\": \"TestingCaseLast1\", \"department\": \"Office of the Vice President for Mission and Identity\", \"first_name\": \"TestingCaseFirst1\"}', 'User Management', 'Successful', '2025-03-07 16:55:33'),
(181, 1, 11, 'Modified', 'Updated fields: email, first_name, last_name', '{\"email\": \"testingCase1@gmail.com\", \"last_name\": \"TestingCaseLast1\", \"department\": \"Office of the Vice President for Mission and Identity\", \"first_name\": \"TestingCaseFirst1\"}', '{\"email\": \"testingCase123@gmail.com\", \"last_name\": \"TestingCaseLast123\", \"department\": \"Office of the Vice President for Mission and Identity\", \"first_name\": \"TestingCaseFirst123\"}', 'User Management', 'Successful', '2025-03-07 16:55:38');

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
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
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
(1, 'Office of the President', 'OP', 0),
(2, 'Office of the Executive Assistant to the President', 'OEAP', 0),
(3, 'Office of the Internal Auditor', 'OIA', 0),
(4, 'Office of the Vice President for Mission and Identity', 'OVPMI', 0),
(5, 'Center for Campus Ministry', 'CCM', 0),
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
(20, 'University Registrar\'s Office', 'URO', 1),
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
(34, 'Students\' Residence Hall', 'SRH', 1),
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
  `location` varchar(255) DEFAULT NULL,
  `accountable_individual` varchar(255) DEFAULT NULL,
  `remarks` text,
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_tag` (`asset_tag`),
  KEY `invoice_no` (`invoice_no`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipment_details`
--

INSERT INTO `equipment_details` (`id`, `asset_tag`, `asset_description_1`, `asset_description_2`, `specifications`, `brand`, `model`, `serial_number`, `invoice_no`, `rr_no`, `location`, `accountable_individual`, `remarks`, `date_created`, `is_disabled`) VALUES
(1, '23123', '1231', '123', '123', '123', '123', '123123', NULL, NULL, NULL, NULL, '123123', '2025-03-13 16:38:00', 0);

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
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`equipment_location_id`),
  UNIQUE KEY `asset_tag` (`asset_tag`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `equipment_location_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_no` (`po_no`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_order`
--

INSERT INTO `purchase_order` (`id`, `po_no`, `date_of_order`, `no_of_units`, `item_specifications`, `date_created`, `is_disabled`) VALUES
(1, '3246789', '2025-03-05', 3, 'qwe', '2025-03-04 09:50:19', 0);

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
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
-- Table structure for table `role_changes`
--

DROP TABLE IF EXISTS `role_changes`;
CREATE TABLE IF NOT EXISTS `role_changes` (
  `ChangeID` int NOT NULL AUTO_INCREMENT,
  `UserID` int NOT NULL,
  `RoleID` int NOT NULL,
  `Action` enum('Add','Modified','Delete') NOT NULL,
  `OldRoleName` varchar(191) DEFAULT NULL,
  `NewRoleName` varchar(191) DEFAULT NULL,
  `ChangeTimestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `OldPrivileges` text,
  `NewPrivileges` text,
  `IsUndone` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`ChangeID`),
  KEY `UserID` (`UserID`),
  KEY `RoleID` (`RoleID`)
) ENGINE=MyISAM AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_changes`
--

INSERT INTO `role_changes` (`ChangeID`, `UserID`, `RoleID`, `Action`, `OldRoleName`, `NewRoleName`, `ChangeTimestamp`, `OldPrivileges`, `NewPrivileges`, `IsUndone`) VALUES
(31, 10, 4, 'Modified', NULL, 'User Manager', '2025-03-05 10:24:57', '[1,2,3,4,5,6,7,8,9,10,11,12]', '[\"1\",\"4\",\"11\",\"10\",\"5\",\"6\",\"12\",\"8\",\"9\",\"7\",\"2\",\"3\"]', 0),
(32, 10, 8, 'Add', NULL, 'User Management', '2025-03-05 10:25:25', NULL, NULL, 0),
(33, 10, 8, 'Delete', NULL, NULL, '2025-03-05 10:25:30', NULL, NULL, 0),
(34, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-03-06 13:54:56', '[\"1|1\",\"2|1\",\"2|2\",\"2|3\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"2|9\",\"2|10\",\"2|11\",\"2|12\",\"3|1\",\"3|2\",\"3|3\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"3|9\",\"3|10\",\"3|11\",\"3|12\",\"4|1\",\"4|2\",\"4|3\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"4|9\",\"4|10\",\"4|11\",\"4|12\"]', '[\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(35, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-03-06 13:55:05', '[\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', '[\"1|1\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(36, 1, 9, 'Add', NULL, 'test', '2025-03-06 13:55:16', NULL, NULL, 0),
(37, 1, 9, 'Delete', NULL, NULL, '2025-03-06 13:55:22', NULL, NULL, 0),
(38, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-07 10:01:15', '[]', '[]', 0),
(39, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-07 10:01:21', '[]', '[\"1|1\"]', 0),
(40, 1, 4, 'Modified', 'User Manager', 'User Manager', '2025-03-07 10:04:51', '[null,null,null,null,null,null,null,null,null,null,null,null]', '[\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\"]', 0);

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
) ENGINE=InnoDB AUTO_INCREMENT=184 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_module_privileges`
--

INSERT INTO `role_module_privileges` (`id`, `role_id`, `module_id`, `privilege_id`) VALUES
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
(75, 5, 2, 12),
(125, 1, 2, 3),
(126, 1, 2, 11),
(127, 1, 2, 10),
(128, 1, 2, 2),
(129, 1, 2, 5),
(130, 1, 2, 6),
(131, 1, 2, 12),
(132, 1, 2, 4),
(133, 1, 2, 8),
(134, 1, 2, 1),
(135, 1, 2, 9),
(136, 1, 2, 7),
(137, 1, 3, 3),
(138, 1, 3, 11),
(139, 1, 3, 10),
(140, 1, 3, 2),
(141, 1, 3, 5),
(142, 1, 3, 6),
(143, 1, 3, 12),
(144, 1, 3, 4),
(145, 1, 3, 8),
(146, 1, 3, 1),
(147, 1, 3, 9),
(148, 1, 3, 7),
(149, 1, 4, 3),
(150, 1, 4, 11),
(151, 1, 4, 10),
(152, 1, 4, 2),
(153, 1, 4, 5),
(154, 1, 4, 6),
(155, 1, 4, 12),
(156, 1, 4, 4),
(157, 1, 4, 8),
(158, 1, 4, 1),
(159, 1, 4, 9),
(160, 1, 4, 7),
(170, NULL, 1, 1),
(171, 6, 1, 1),
(172, 4, 3, 3),
(173, 4, 3, 11),
(174, 4, 3, 10),
(175, 4, 3, 2),
(176, 4, 3, 5),
(177, 4, 3, 6),
(178, 4, 3, 12),
(179, 4, 3, 4),
(180, 4, 3, 8),
(181, 4, 3, 1),
(182, 4, 3, 9),
(183, 4, 3, 7);

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
  `date_created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('Offline','Online') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `date_created`, `status`, `is_disabled`) VALUES
(1, 'navithebear', 'navi@example.com', '$2y$12$2esj1uaDmbD3K6Fi.C0CiuOye96x8OjARwTc82ViEAPvmx4b1cL0S', 'navi', 'slu', '2025-02-19 01:19:52', 'Offline', 0),
(2, 'userman', 'um@example.com', '$2y$12$wE3B0Dq4z0Bd1AHXf4gumexeObTqWXm7aASm7PnkCrtiL.iIfObS.', 'user', 'manager', '2025-02-19 05:40:35', 'Offline', 0),
(3, 'equipman', 'em@example.com', '$2y$12$J0iy9bwoalbG2/NkqDZchuLU4sWramGpsw1EsSZ6se0CefM/sqpZq', 'equipment', 'manager', '2025-02-19 05:40:35', 'Offline', 0),
(4, 'rpman', 'rp@example.com', '$2y$12$dWnJinU4uO7ETYIKi9cL0uN4wJgjACaF.q0Pbkr5yNUK2q1HUQk8G', 'ropriv', 'manager', '2025-02-19 05:41:59', 'Offline', 0),
(5, 'auds', 'auds@example.com', '$2y$12$VRIJ5Okf3p9fE3Xtq.qyze/t./h30ZsV7y7pg4UFksFiJ8JdMSh/q', 'audi', 'broom broom', '2025-02-19 05:41:59', 'Offline', 0),
(11, 'ttestinglast', 'testingCase123@gmail.com', '$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q', 'TestingCaseFirst123', 'TestingCaseLast123', '2025-03-07 03:52:51', '', 0);

--
-- Triggers `users`
--
DROP TRIGGER IF EXISTS `after_user_disable`;
DELIMITER $$
CREATE TRIGGER `after_user_disable` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    -- Only log if the user is actually being disabled (active to disabled)
    IF OLD.is_disabled = 0 AND NEW.is_disabled = 1 THEN
        INSERT INTO audit_log (
            `UserID`,
            `EntityID`,
            `Action`,
            `Details`,
            `OldVal`,
            `NewVal`,
            `Module`,
            `Status`,
            `Date_Time`
        ) VALUES (
            @current_user_id,
            OLD.id,
            'Remove',
            'User has been removed',
            JSON_OBJECT(
                'id', OLD.id,
                'username', OLD.username,
                'email', OLD.email,
                'first_name', OLD.first_name,
                'last_name', OLD.last_name,
                'status', OLD.status,
                'date_created', OLD.date_created,
                'is_disabled', OLD.is_disabled
            ),
            '',
            IFNULL(@current_module, 'User Management'),
            'Successful',
            NOW()
        );
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `user_after_delete`;
DELIMITER $$
CREATE TRIGGER `user_after_delete` BEFORE DELETE ON `users` FOR EACH ROW BEGIN
    -- Use HEX() to compare BIT(1) value: '01' indicates true
    IF HEX(OLD.is_disabled) = '01' THEN
        INSERT INTO audit_log (
            `UserID`,              
            `EntityID`,            
            `Action`,              
            `Details`,             
            `OldVal`,              
            `NewVal`,              
            `Module`,              
            `Status`,              
            `Date_Time`            
        ) VALUES (
            @current_user_id,      
            OLD.id,                
            'Delete',              
            'User deleted',        
            JSON_OBJECT(           
                'id', OLD.id,
                'username', OLD.username,
                'email', OLD.email,
                'first_name', OLD.first_name,
                'last_name', OLD.last_name,
                'status', OLD.status,
                'date_created', OLD.date_created,
                'is_disabled', OLD.is_disabled
            ),
            NULL,                  
            IFNULL(@current_module, 'User Management'),
            'Successful',          
            NOW()                  
        );
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `user_after_restore`;
DELIMITER $$
CREATE TRIGGER `user_after_restore` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    -- Only log if the user is actually being disabled (active to disabled)
    IF OLD.is_disabled = 1 AND NEW.is_disabled = 0 THEN
        INSERT INTO audit_log (
            `UserID`,
            `EntityID`,
            `Action`,
            `Details`,
            `OldVal`,
            `NewVal`,
            `Module`,
            `Status`,
            `Date_Time`
        ) VALUES (
            @current_user_id,
            OLD.id,
            'Restored',
            'User has been restored',
            JSON_OBJECT(
                'id', OLD.id,
                'username', OLD.username,
                'email', OLD.email,
                'first_name', OLD.first_name,
                'last_name', OLD.last_name,
                'status', OLD.status,
                'date_created', OLD.date_created,
                'is_disabled', OLD.is_disabled
            ),
            '',
            IFNULL(@current_module, 'User Management'),
            'Successful',
            NOW()
        );
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `users_after_create`;
DELIMITER $$
CREATE TRIGGER `users_after_create` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    -- Declare variable to hold department name
    DECLARE dept_name VARCHAR(191);

    -- Set default value in case the query doesn't return results
    SET dept_name = 'Unknown';

    -- Attempt to get department name for the user
    -- This might not work if the department assignment happens after user creation
    SELECT d.department_name INTO dept_name
    FROM departments d
             JOIN user_departments ud ON d.id = ud.department_id
    WHERE ud.user_id = NEW.id
    LIMIT 1;

    INSERT INTO audit_log (
        UserID,
        EntityID,
        Action,
        Details,
        OldVal,
        NewVal,
        Module,
        Status,
        Date_Time
    ) VALUES (
                 @current_user_id,
                 NEW.id,
                 'Create',
                 CONCAT('New user added: ', NEW.username),
                 NULL,
                 JSON_OBJECT(
                         'id', NEW.id,
                         'username', NEW.username,
                         'email', NEW.email,
                         'first_name', NEW.first_name,
                         'last_name', NEW.last_name,
                         'department', dept_name,
                         'status', NEW.status,
                         'date_created', NEW.date_created
                 ),
                 IFNULL(@current_module, 'User Management'),
                 'Successful',
                 NOW()
             );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `users_status_change`;
DELIMITER $$
CREATE TRIGGER `users_status_change` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    DECLARE actionType VARCHAR(50);
    DECLARE detailsText VARCHAR(255);
    DECLARE changesText VARCHAR(255);

    -- Check if the status field has changed
    IF OLD.status <> NEW.status THEN
        IF OLD.status = 'offline' AND NEW.status = 'online' THEN
            SET actionType = 'Login';
            SET detailsText = CONCAT(NEW.username, ' is now online.');
            SET changesText = 'Offline > Online';
        ELSEIF OLD.status = 'online' AND NEW.status = 'offline' THEN
            SET actionType = 'Logout';
            SET detailsText = CONCAT(NEW.username, ' is now offline.');
            SET changesText = 'Online > Offline';
        ELSE
            -- Fallback for any other status change
            SET actionType = 'Status Change';
            SET detailsText = CONCAT('Status changed from ', OLD.status, ' to ', NEW.status);
            SET changesText = CONCAT(OLD.status, ' > ', NEW.status);
        END IF;

        INSERT INTO audit_log (
            UserID,
            EntityID,
            Action,
            Details,
            OldVal,
            NewVal,
            Module,
            Status,
            Date_Time
        )
        VALUES (
            @current_user_id,
            NEW.id,
            actionType,
            detailsText,
            JSON_OBJECT('status', OLD.status),
            JSON_OBJECT('status', NEW.status),
            'users',
            'successful',
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

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
(11, 4),
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
(5, 6),
(11, 6);

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
  ADD CONSTRAINT `equipment_details_ibfk_1` FOREIGN KEY (`invoice_no`) REFERENCES `charge_invoice` (`invoice_no`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `equipment_location`
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
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`equipment_location_id`),
  UNIQUE KEY `asset_tag` (`asset_tag`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `equipment_location_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
