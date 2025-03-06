-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 06, 2025 at 12:28 AM
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
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `equipment_location_id` int DEFAULT NULL,
  `equipment_status_id` int DEFAULT NULL,
  `remarks` text,
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
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
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=MyISAM AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_changes`
--

INSERT INTO `role_changes` (`ChangeID`, `UserID`, `RoleID`, `Action`, `OldRoleName`, `NewRoleName`, `ChangeTimestamp`, `OldPrivileges`, `NewPrivileges`, `IsUndone`) VALUES
(31, 10, 4, 'Modified', NULL, 'User Manager', '2025-03-05 10:24:57', '[1,2,3,4,5,6,7,8,9,10,11,12]', '[\"1\",\"4\",\"11\",\"10\",\"5\",\"6\",\"12\",\"8\",\"9\",\"7\",\"2\",\"3\"]', 0),
(32, 10, 8, 'Add', NULL, 'User Management', '2025-03-05 10:25:25', NULL, NULL, 0),
(33, 10, 8, 'Delete', NULL, NULL, '2025-03-05 10:25:30', NULL, NULL, 0);

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
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(76, 4, NULL, 1),
(77, 4, NULL, 4),
(78, 4, NULL, 11),
(79, 4, NULL, 10),
(80, 4, NULL, 5),
(81, 4, NULL, 6),
(82, 4, NULL, 12),
(83, 4, NULL, 8),
(84, 4, NULL, 9),
(85, 4, NULL, 7),
(86, 4, NULL, 2),
(87, 4, NULL, 3);

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `date_created`, `status`, `is_disabled`) VALUES
(1, 'navithebear', 'navi@example.com', '$2y$12$2esj1uaDmbD3K6Fi.C0CiuOye96x8OjARwTc82ViEAPvmx4b1cL0S', 'navi', 'slu', '2025-02-19 01:19:52', 'Online', 0),
(2, 'userman', 'um@example.com', '$2y$12$wE3B0Dq4z0Bd1AHXf4gumexeObTqWXm7aASm7PnkCrtiL.iIfObS.', 'user', 'manager', '2025-02-19 05:40:35', 'Offline', 0),
(3, 'equipman', 'em@example.com', '$2y$12$J0iy9bwoalbG2/NkqDZchuLU4sWramGpsw1EsSZ6se0CefM/sqpZq', 'equipment', 'manager', '2025-02-19 05:40:35', 'Offline', 0),
(4, 'rpman', 'rp@example.com', '$2y$12$dWnJinU4uO7ETYIKi9cL0uN4wJgjACaF.q0Pbkr5yNUK2q1HUQk8G', 'ropriv', 'manager', '2025-02-19 05:41:59', 'Offline', 0),
(5, 'auds', 'auds@example.com', '$2y$12$VRIJ5Okf3p9fE3Xtq.qyze/t./h30ZsV7y7pg4UFksFiJ8JdMSh/q', 'audi', 'broom broom', '2025-02-19 05:41:59', 'Offline', 0);

--
-- Triggers `users`
--
DROP TRIGGER IF EXISTS `after_user_delete`;
DELIMITER $$
CREATE TRIGGER `after_user_delete` AFTER DELETE ON `users` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,              -- The user who performed the action
        `EntityID`,            -- The ID of the deleted user
        `Action`,              -- Action type
        `Details`,             -- Description of the action
        `OldVal`,              -- Old values before deletion
        `NewVal`,              -- New values after deletion (NULL for DELETE)
        `Module`,              -- Module where the action occurred
        `Status`,              -- Status of the action
        `Date_Time`            -- Timestamp of the action
    ) VALUES (
        @current_user_id,      -- Replace with the actual user ID performing the action
        OLD.id,                -- The ID of the deleted user
        'Remove',              -- Action type
        'User deleted',        -- Details
        JSON_OBJECT(           -- Old values in JSON format
            'id', OLD.id,
            'username', OLD.username,
            'email', OLD.email,
            'first_name', OLD.first_name,
            'last_name', OLD.last_name,
            'status', OLD.status,
            'date_created', OLD.date_created,
            'is_disabled', OLD.is_disabled
        ),
        NULL,                  -- No new values for DELETE operation
        IFNULL(@current_module, 'User Management'), -- Default to 'User Management'
        'Successful',          -- Status of the action
        NOW()                  -- Current timestamp
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `after_user_disable`;
DELIMITER $$
CREATE TRIGGER `after_user_disable` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,              -- The user who performed the action
        `EntityID`,            -- The ID of the deleted user
        `Action`,              -- Action type
        `Details`,             -- Description of the action
        `OldVal`,              -- Old values before the delete
        `NewVal`,              -- New values (empty for delete)
        `Module`,              -- Module where the action occurred
        `Status`,              -- Status of the action
        `Date_Time`            -- Timestamp of the action
    ) VALUES (
                 @current_user_id,      -- Replace with the actual user ID performing the action
                 OLD.id,                -- The ID of the deleted user
                 'Delete',              -- Action type
                 'User has been deleted',  -- Details
                 JSON_OBJECT(           -- Old values in JSON format
                         'id', OLD.id,
                         'username', OLD.username,
                         'email', OLD.email,
                         'first_name', OLD.first_name,
                         'last_name', OLD.last_name,
                         'status', OLD.status,
                         'date_created', OLD.date_created,
                         'is_disabled', OLD.is_disabled
                 ),
                 '',                    -- New values are empty for delete
                 IFNULL(@current_module, 'User Management'), -- Default to 'User Management'
                 'Successful',           -- Status of the action
                 NOW()                  -- Current timestamp
             );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `user_after_modify`;
DELIMITER $$
CREATE TRIGGER `user_after_modify` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    DECLARE diffList TEXT DEFAULT '';
    DECLARE dept_name VARCHAR(191);

    -- Get department name for the user
    SELECT d.department_name INTO dept_name
    FROM departments d
             JOIN user_departments ud ON d.id = ud.department_id
    WHERE ud.user_id = NEW.id
    LIMIT 1;

    -- Only log modifications if is_disabled did not change
    IF OLD.is_disabled = NEW.is_disabled THEN
        IF OLD.username <> NEW.username THEN
            SET diffList = CONCAT(diffList, 'username, ');
        END IF;
        IF OLD.email <> NEW.email THEN
            SET diffList = CONCAT(diffList, 'email, ');
        END IF;
        IF OLD.first_name <> NEW.first_name THEN
            SET diffList = CONCAT(diffList, 'first_name, ');
        END IF;
        IF OLD.last_name <> NEW.last_name THEN
            SET diffList = CONCAT(diffList, 'last_name, ');
        END IF;
        IF OLD.status <> NEW.status THEN
            SET diffList = CONCAT(diffList, 'status, ');
        END IF;
        IF OLD.password <> NEW.password THEN
            SET diffList = CONCAT(diffList, 'password, ');
        END IF;

        -- Insert log entry
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
            'Modified',
            CASE 
                WHEN TRIM(TRAILING ', ' FROM diffList) <> '' 
                THEN CONCAT('Updated fields: ', TRIM(TRAILING ', ' FROM diffList))
                ELSE 'No changes made'
            END,
            JSON_OBJECT(
                'id', OLD.id,
                'username', OLD.username,
                'email', OLD.email,
                'first_name', OLD.first_name,
                'last_name', OLD.last_name,
                'password', OLD.password,
                'status', OLD.status,
                'department', dept_name,
                'date_created', OLD.date_created
            ),
            JSON_OBJECT(
                'id', NEW.id,
                'username', NEW.username,
                'email', NEW.email,
                'first_name', NEW.first_name,
                'last_name', NEW.last_name,
                'password', NEW.password,
                'status', NEW.status,
                'department', dept_name,
                'date_created', NEW.date_created
            ),
            IFNULL(@current_module, 'User Management'),
            CASE 
                WHEN TRIM(TRAILING ', ' FROM diffList) <> '' THEN 'Successful'
                ELSE 'Failed'
            END,
            NOW()
        );
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `user_after_restore`;
DELIMITER $$
CREATE TRIGGER `user_after_restore` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    -- Declare variables first, at the beginning of the block
    DECLARE dept_name VARCHAR(191);

    -- Then proceed with the logic
    IF OLD.is_disabled = 1 AND NEW.is_disabled = 0 THEN
        -- Get department name for the user
        SELECT d.department_name INTO dept_name
        FROM departments d
                 JOIN user_departments ud ON d.id = ud.department_id
        WHERE ud.user_id = NEW.id
        LIMIT 1;

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
                     NEW.id,
                     'Restored',
                     'User restored (is_disabled set to 0)',
                     JSON_OBJECT(
                             'id', OLD.id,
                             'username', OLD.username,
                             'email', OLD.email,
                             'first_name', OLD.first_name,
                             'last_name', OLD.last_name,
                             'status', OLD.status,
                             'department', dept_name,
                             'date_created', OLD.date_created,
                             'is_disabled', OLD.is_disabled
                     ),
                     JSON_OBJECT(
                             'id', NEW.id,
                             'username', NEW.username,
                             'email', NEW.email,
                             'first_name', NEW.first_name,
                             'last_name', NEW.last_name,
                             'status', NEW.status,
                             'department', dept_name,
                             'date_created', NEW.date_created,
                             'is_disabled', NEW.is_disabled
                     ),
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
