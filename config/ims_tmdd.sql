-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 28, 2025 at 07:57 AM
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
-- Database: `ims_tmdd`
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
-- Table structure for table `chargeinvoice`
--

DROP TABLE IF EXISTS `chargeinvoice`;
CREATE TABLE IF NOT EXISTS `chargeinvoice` (
  `ChargeInvoiceID` int NOT NULL AUTO_INCREMENT,
  `ChargeInvoiceNo` varchar(50) NOT NULL,
  `DateOfChargeInvoice` date NOT NULL,
  `CreatedDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `ModifiedDate` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `PurchaseOrderNumber` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`ChargeInvoiceID`),
  KEY `PurchaseOrderNumber` (`PurchaseOrderNumber`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers for Charge Invoice
--
DROP TRIGGER IF EXISTS `chargeinvoice_after_insert`;
DELIMITER $$
CREATE TRIGGER `chargeinvoice_after_insert` AFTER INSERT ON `chargeinvoice` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,
        `EntityID`,
        `Action`,
        `Details`,
        `OldVal`,
        `NewVal`,
        `Module`,
        `Status`
    ) VALUES (
        @current_user_id,
        NEW.ChargeInvoiceID,
        'Add',
        'New Charge Invoice added',
        '',
        JSON_OBJECT(
            'ChargeInvoiceID', NEW.ChargeInvoiceID,
            'ChargeInvoiceNo', NEW.ChargeInvoiceNo,
            'DateOfChargeInvoice', NEW.DateOfChargeInvoice,
            'PurchaseOrderNumber', NEW.PurchaseOrderNumber
        ),
        'Charge Invoice',
        'Successful'
    );
END
$$
DELIMITER ;

DROP TRIGGER IF EXISTS `chargeinvoice_after_update`;
DELIMITER $$
CREATE TRIGGER `chargeinvoice_after_update` AFTER UPDATE ON `chargeinvoice` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,
        `EntityID`,
        `Action`,
        `Details`,
        `OldVal`,
        `NewVal`,
        `Module`,
        `Status`
    ) VALUES (
        @current_user_id,
        NEW.ChargeInvoiceID,
        'Modified',
        'Charge Invoice updated',
        JSON_OBJECT(
            'ChargeInvoiceID', OLD.ChargeInvoiceID,
            'ChargeInvoiceNo', OLD.ChargeInvoiceNo,
            'DateOfChargeInvoice', OLD.DateOfChargeInvoice,
            'PurchaseOrderNumber', OLD.PurchaseOrderNumber
        ),
        JSON_OBJECT(
            'ChargeInvoiceID', NEW.ChargeInvoiceID,
            'ChargeInvoiceNo', NEW.ChargeInvoiceNo,
            'DateOfChargeInvoice', NEW.DateOfChargeInvoice,
            'PurchaseOrderNumber', NEW.PurchaseOrderNumber
        ),
        'Charge Invoice',
        'Successful'
    );
END
$$
DELIMITER ;

DROP TRIGGER IF EXISTS `chargeinvoice_after_delete`;
DELIMITER $$
CREATE TRIGGER `chargeinvoice_after_delete` AFTER DELETE ON `chargeinvoice` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,
        `EntityID`,
        `Action`,
        `Details`,
        `OldVal`,
        `NewVal`,
        `Module`,
        `Status`
    ) VALUES (
        @current_user_id,
        OLD.ChargeInvoiceID,
        'Delete',
        'Charge Invoice deleted',
        JSON_OBJECT(
            'ChargeInvoiceID', OLD.ChargeInvoiceID,
            'ChargeInvoiceNo', OLD.ChargeInvoiceNo,
            'DateOfChargeInvoice', OLD.DateOfChargeInvoice,
            'PurchaseOrderNumber', OLD.PurchaseOrderNumber
        ),
        '',
        'Charge Invoice',
        'Successful'
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `equipmentdetails`
--

DROP TABLE IF EXISTS `equipmentdetails`;
CREATE TABLE IF NOT EXISTS `equipmentdetails` (
  `EquipmentDetailsID` int NOT NULL AUTO_INCREMENT,
  `AssetTag` varchar(50) NOT NULL,
  `AssetDescription1` varchar(255) DEFAULT NULL,
  `AssetDescription2` varchar(255) DEFAULT NULL,
  `Specification` varchar(255) DEFAULT NULL,
  `Brand` varchar(100) DEFAULT NULL,
  `Model` varchar(100) DEFAULT NULL,
  `SerialNumber` varchar(100) DEFAULT NULL,
  `DateAcquired` date DEFAULT NULL,
  `CreatedDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `ModifiedDate` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ReceivingReportFormNumber` varchar(50) DEFAULT NULL,
  `AccountableIndividualLocation` varchar(255) DEFAULT NULL,
  `AccountableIndividual` varchar(100) DEFAULT NULL,
  `Remarks` text,
  PRIMARY KEY (`EquipmentDetailsID`),
  UNIQUE KEY `AssetTag` (`AssetTag`),
  KEY `Specification` (`Specification`(250)),
  KEY `ReceivingReportFormNumber` (`ReceivingReportFormNumber`),
  KEY `AccountableIndividualLocation` (`AccountableIndividualLocation`(250)),
  KEY `AccountableIndividual` (`AccountableIndividual`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipmentdetails`
--

INSERT INTO `equipmentdetails` (`EquipmentDetailsID`, `AssetTag`, `AssetDescription1`, `AssetDescription2`, `Specification`, `Brand`, `Model`, `SerialNumber`, `DateAcquired`, `CreatedDate`, `ModifiedDate`, `ReceivingReportFormNumber`, `AccountableIndividualLocation`, `AccountableIndividual`, `Remarks`) VALUES
(1, 'AT001', 'Laptop', 'Dell XPS 13', 'Laptops', 'Dell', 'XPS 13', 'SN12345', '2024-02-06', '2024-02-06 10:00:00', '2024-02-06 10:00:00', 'RR001', 'IT Department', 'John Doe', 'Assigned to IT Department'),
(2, 'AT002', 'Printer', 'HP LaserJet', 'Printers', 'HP', 'LaserJet 400', 'SN67890', '2024-02-07', '2024-02-07 14:30:00', '2024-02-07 14:30:00', 'RR002', 'Admin Office', 'Jane Smith', 'Assigned to Admin Office');

-- --------------------------------------------------------

--
-- Table structure for table `equipmentlocation`
--

DROP TABLE IF EXISTS `equipmentlocation`;
CREATE TABLE IF NOT EXISTS `equipmentlocation` (
  `EquipmentLocationID` int NOT NULL AUTO_INCREMENT,
  `AssetTag` varchar(50) DEFAULT NULL,
  `BuildingLocation` varchar(255) DEFAULT NULL,
  `FloorNumber` int DEFAULT NULL,
  `SpecificArea` varchar(255) DEFAULT NULL,
  `PersonResponsible` varchar(100) DEFAULT NULL,
  `Remarks` text,
  `CreatedDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `ModifiedDate` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`EquipmentLocationID`),
  KEY `AssetTag` (`AssetTag`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipmentlocation`
--

INSERT INTO `equipmentlocation` (`EquipmentLocationID`, `AssetTag`, `BuildingLocation`, `FloorNumber`, `SpecificArea`, `PersonResponsible`, `Remarks`, `CreatedDate`, `ModifiedDate`) VALUES
(1, 'AT001', 'Main Building', 3, 'IT Room', 'John Doe', 'Setup Completed', '2024-02-01 10:00:00', '2024-02-01 10:00:00'),
(2, 'AT002', 'Admin Block', 1, 'Office', 'Jane Smith', 'In Use', '2024-02-02 14:30:00', '2024-02-02 14:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `equipmentstatus`
--

DROP TABLE IF EXISTS `equipmentstatus`;
CREATE TABLE IF NOT EXISTS `equipmentstatus` (
  `EquipmentStatusID` int NOT NULL AUTO_INCREMENT,
  `AssetTag` varchar(50) DEFAULT NULL,
  `Status` varchar(100) DEFAULT NULL,
  `Remarks` text,
  `CreatedDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `ModifiedDate` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `AccountableIndividual` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`EquipmentStatusID`),
  KEY `AssetTag` (`AssetTag`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipmentstatus`
--

INSERT INTO `equipmentstatus` (`EquipmentStatusID`, `AssetTag`, `Status`, `Remarks`, `CreatedDate`, `ModifiedDate`, `AccountableIndividual`) VALUES
(1, 'AT001', 'Operational', 'Working well', '2024-02-01 10:00:00', '2024-02-01 10:00:00', 'John Doe'),
(2, 'AT002', 'Operational', 'No issues reported', '2024-02-02 14:30:00', '2024-02-02 14:30:00', 'Jane Smith');

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
CREATE TABLE IF NOT EXISTS `modules` (
  `Module_ID` int NOT NULL AUTO_INCREMENT,
  `Module_Name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`Module_ID`),
  UNIQUE KEY `Module_Name` (`Module_Name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`Module_ID`, `Module_Name`) VALUES
(8, 'Audit Trail'),
(7, 'Block Section'),
(6, 'Faculty Designation'),
(5, 'Faculty Schedule'),
(4, 'Roles and Permissions'),
(3, 'Schedule'),
(2, 'Schedule (Request)'),
(1, 'User Accounts');

-- --------------------------------------------------------

--
-- Table structure for table `privileges`
--

DROP TABLE IF EXISTS `privileges`;
CREATE TABLE IF NOT EXISTS `privileges` (
  `Privilege_ID` int NOT NULL AUTO_INCREMENT,
  `Privilege_Name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Module_ID` int DEFAULT NULL,
  PRIMARY KEY (`Privilege_ID`),
  KEY `Module_ID` (`Module_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `privileges`
--

INSERT INTO `privileges` (`Privilege_ID`, `Privilege_Name`, `Module_ID`) VALUES
(1, 'Trace', 8),
(2, 'View', 7),
(3, 'Add', 7),
(4, 'Edit', 7),
(5, 'Delete', 7),
(6, 'View', 6),
(7, 'Add', 6),
(8, 'Edit', 6),
(9, 'Delete', 6),
(10, 'View', 5),
(11, 'Add', 5),
(12, 'Edit', 5),
(13, 'Delete', 5),
(14, 'View', 4),
(15, 'Add', 4),
(16, 'Edit', 4),
(17, 'Delete', 4),
(18, 'View', 3),
(19, 'Add', 3),
(20, 'Edit', 3),
(21, 'Delete', 3),
(22, 'View', 2),
(23, 'Approve', 2),
(24, 'Reject', 2),
(25, 'Undo', 2),
(26, 'View', 1),
(27, 'Add', 1),
(28, 'Edit', 1),
(29, 'Delete', 1);

-- --------------------------------------------------------

--
-- Table structure for table `purchaseorder`
--

DROP TABLE IF EXISTS `purchaseorder`;
CREATE TABLE IF NOT EXISTS `purchaseorder` (
  `PurchaseOrderID` int NOT NULL AUTO_INCREMENT,
  `PurchaseOrderNumber` varchar(50) NOT NULL,
  `NumberOfUnits` int DEFAULT NULL,
  `DateOfPurchaseOrder` date NOT NULL,
  `CreatedDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `ModifiedDate` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ItemsSpecification` text,
  PRIMARY KEY (`PurchaseOrderID`),
  UNIQUE KEY `PurchaseOrderNumber` (`PurchaseOrderNumber`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchaseorder`
--

INSERT INTO `purchaseorder` (`PurchaseOrderID`, `PurchaseOrderNumber`, `NumberOfUnits`, `DateOfPurchaseOrder`, `CreatedDate`, `ModifiedDate`, `ItemsSpecification`) VALUES
(1, 'PO12345', 10, '2024-02-01', '2025-02-27 08:02:06', '2025-02-27 08:02:06', 'Laptops'),
(2, 'PO12346', 5, '2024-02-02', '2025-02-27 08:02:06', '2025-02-27 08:02:06', 'Printers'),
(3, '1234567890', 12, '2025-02-13', '2025-02-27 08:02:06', '2025-02-27 08:02:06', 'AMD'),
(4, '1111', 11, '2025-02-14', '2025-02-27 08:02:06', '2025-02-27 08:02:06', '123'),
(5, '123', 11, '2025-02-03', '2025-02-27 08:02:06', '2025-02-27 08:02:06', '123');

--
-- Triggers `purchaseorder`
--
DROP TRIGGER IF EXISTS `purchaseorder_after_insert`;
DELIMITER $$
CREATE TRIGGER `purchaseorder_after_insert` AFTER INSERT ON `purchaseorder` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,
        `EntityID`,
        `Action`,
        `Details`,
        `OldVal`,
        `NewVal`,
        `Module`,
        `Status`
    ) VALUES (
        @current_user_id,
        NEW.PurchaseOrderID,
        'Add',
        'New Purchase Order added',
        '',
        JSON_OBJECT(
            'PurchaseOrderID', NEW.PurchaseOrderID,
            'PurchaseOrderNumber', NEW.PurchaseOrderNumber,
            'NumberOfUnits', NEW.NumberOfUnits,
            'DateOfPurchaseOrder', NEW.DateOfPurchaseOrder,
            'ItemsSpecification', NEW.ItemsSpecification
        ),
        'Purchase Order',
        'Successful'
    );
END
$$
DELIMITER ;

DROP TRIGGER IF EXISTS `purchaseorder_after_update`;
DELIMITER $$
CREATE TRIGGER `purchaseorder_after_update` AFTER UPDATE ON `purchaseorder` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,
        `EntityID`,
        `Action`,
        `Details`,
        `OldVal`,
        `NewVal`,
        `Module`,
        `Status`
    ) VALUES (
        @current_user_id,
        NEW.PurchaseOrderID,
        'Modified',
        'Purchase Order updated',
        JSON_OBJECT(
            'PurchaseOrderID', OLD.PurchaseOrderID,
            'PurchaseOrderNumber', OLD.PurchaseOrderNumber,
            'NumberOfUnits', OLD.NumberOfUnits,
            'DateOfPurchaseOrder', OLD.DateOfPurchaseOrder,
            'ItemsSpecification', OLD.ItemsSpecification
        ),
        JSON_OBJECT(
            'PurchaseOrderID', NEW.PurchaseOrderID,
            'PurchaseOrderNumber', NEW.PurchaseOrderNumber,
            'NumberOfUnits', NEW.NumberOfUnits,
            'DateOfPurchaseOrder', NEW.DateOfPurchaseOrder,
            'ItemsSpecification', NEW.ItemsSpecification
        ),
        'Purchase Order',
        'Successful'
    );
END
$$
DELIMITER ;

DROP TRIGGER IF EXISTS `purchaseorder_after_delete`;
DELIMITER $$
CREATE TRIGGER `purchaseorder_after_delete` AFTER DELETE ON `purchaseorder` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,
        `EntityID`,
        `Action`,
        `Details`,
        `OldVal`,
        `NewVal`,
        `Module`,
        `Status`
    ) VALUES (
        @current_user_id,
        OLD.PurchaseOrderID,
        'Delete',
        'Purchase Order deleted',
        JSON_OBJECT(
            'PurchaseOrderID', OLD.PurchaseOrderID,
            'PurchaseOrderNumber', OLD.PurchaseOrderNumber,
            'NumberOfUnits', OLD.NumberOfUnits,
            'DateOfPurchaseOrder', OLD.DateOfPurchaseOrder,
            'ItemsSpecification', OLD.ItemsSpecification
        ),
        '',
        'Purchase Order',
        'Successful'
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `receivingreportform`
--

DROP TABLE IF EXISTS `receivingreportform`;
CREATE TABLE IF NOT EXISTS `receivingreportform` (
  `ReceivingReportFormID` int NOT NULL AUTO_INCREMENT,
  `ReceivingReportNumber` varchar(50) NOT NULL,
  `AccountableIndividual` varchar(100) DEFAULT NULL,
  `PurchaseOrderNumber` varchar(50) DEFAULT NULL,
  `AccountableIndividualLocation` varchar(255) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `ModifiedDate` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ReceivingReportFormID`),
  UNIQUE KEY `ReceivingReportNumber` (`ReceivingReportNumber`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `receivingreportform`
--

INSERT INTO `receivingreportform` (`ReceivingReportFormID`, `ReceivingReportNumber`, `AccountableIndividual`, `PurchaseOrderNumber`, `AccountableIndividualLocation`, `CreatedDate`, `ModifiedDate`) VALUES
(1, 'RR001', 'John Doe', 'PO12345', 'IT Department', '2025-02-27 08:02:06', '2025-02-27 08:02:06'),
(2, 'RR002', 'Jane Smith', 'PO12346', 'Admin Office', '2025-02-27 08:02:06', '2025-02-27 08:02:06');

--
-- Triggers `receivingreportform`
--
DROP TRIGGER IF EXISTS `receivingreportform_after_insert`;
DELIMITER $$
CREATE TRIGGER `receivingreportform_after_insert` AFTER INSERT ON `receivingreportform` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,
        `EntityID`,
        `Action`,
        `Details`,
        `OldVal`,
        `NewVal`,
        `Module`,
        `Status`
    ) VALUES (
        @current_user_id,
        NEW.ReceivingReportFormID,
        'Add',
        'New Receiving Report added',
        '',
        JSON_OBJECT(
            'ReceivingReportFormID', NEW.ReceivingReportFormID,
            'ReceivingReportNumber', NEW.ReceivingReportNumber,
            'AccountableIndividual', NEW.AccountableIndividual,
            'PurchaseOrderNumber', NEW.PurchaseOrderNumber,
            'AccountableIndividualLocation', NEW.AccountableIndividualLocation
        ),
        'Receiving Report',
        'Successful'
    );
END
$$
DELIMITER ;

DROP TRIGGER IF EXISTS `receivingreportform_after_update`;
DELIMITER $$
CREATE TRIGGER `receivingreportform_after_update` AFTER UPDATE ON `receivingreportform` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,
        `EntityID`,
        `Action`,
        `Details`,
        `OldVal`,
        `NewVal`,
        `Module`,
        `Status`
    ) VALUES (
        @current_user_id,
        NEW.ReceivingReportFormID,
        'Modified',
        'Receiving Report updated',
        JSON_OBJECT(
            'ReceivingReportFormID', OLD.ReceivingReportFormID,
            'ReceivingReportNumber', OLD.ReceivingReportNumber,
            'AccountableIndividual', OLD.AccountableIndividual,
            'PurchaseOrderNumber', OLD.PurchaseOrderNumber,
            'AccountableIndividualLocation', OLD.AccountableIndividualLocation
        ),
        JSON_OBJECT(
            'ReceivingReportFormID', NEW.ReceivingReportFormID,
            'ReceivingReportNumber', NEW.ReceivingReportNumber,
            'AccountableIndividual', NEW.AccountableIndividual,
            'PurchaseOrderNumber', NEW.PurchaseOrderNumber,
            'AccountableIndividualLocation', NEW.AccountableIndividualLocation
        ),
        'Receiving Report',
        'Successful'
    );
END
$$
DELIMITER ;

DROP TRIGGER IF EXISTS `receivingreportform_after_delete`;
DELIMITER $$
CREATE TRIGGER `receivingreportform_after_delete` AFTER DELETE ON `receivingreportform` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,
        `EntityID`,
        `Action`,
        `Details`,
        `OldVal`,
        `NewVal`,
        `Module`,
        `Status`
    ) VALUES (
        @current_user_id,
        OLD.ReceivingReportFormID,
        'Delete',
        'Receiving Report deleted',
        JSON_OBJECT(
            'ReceivingReportFormID', OLD.ReceivingReportFormID,
            'ReceivingReportNumber', OLD.ReceivingReportNumber,
            'AccountableIndividual', OLD.AccountableIndividual,
            'PurchaseOrderNumber', OLD.PurchaseOrderNumber,
            'AccountableIndividualLocation', OLD.AccountableIndividualLocation
        ),
        '',
        'Receiving Report',
        'Successful'
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `Role_ID` int NOT NULL AUTO_INCREMENT,
  `Role_Name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`Role_ID`),
  UNIQUE KEY `Role_Name` (`Role_Name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`Role_ID`, `Role_Name`) VALUES
(2, 'Administrator'),
(4, 'Regular User'),
(1, 'Super Admin'),
(3, 'Super User');

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
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_privileges`
--

DROP TABLE IF EXISTS `role_privileges`;
CREATE TABLE IF NOT EXISTS `role_privileges` (
  `Role_Privilege_ID` int NOT NULL AUTO_INCREMENT,
  `Role_ID` int DEFAULT NULL,
  `Privilege_ID` int DEFAULT NULL,
  PRIMARY KEY (`Role_Privilege_ID`),
  KEY `Role_ID` (`Role_ID`),
  KEY `Privilege_ID` (`Privilege_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_privileges`
--

INSERT INTO `role_privileges` (`Role_Privilege_ID`, `Role_ID`, `Privilege_ID`) VALUES
(22, 2, 1),
(23, 2, 2),
(24, 2, 3),
(25, 2, 4),
(26, 2, 5),
(27, 2, 6),
(28, 2, 7),
(29, 2, 8),
(30, 2, 9),
(31, 2, 10),
(32, 2, 11),
(33, 2, 12),
(34, 2, 13),
(35, 2, 14),
(36, 2, 15),
(37, 2, 16),
(38, 2, 17),
(39, 2, 18),
(40, 2, 19),
(41, 2, 20),
(42, 2, 21),
(43, 2, 22),
(44, 2, 23),
(45, 2, 24),
(46, 2, 25),
(47, 2, 26),
(48, 2, 27),
(49, 2, 28),
(50, 2, 29),
(84, 1, 1),
(85, 1, 3),
(86, 1, 5),
(87, 1, 4),
(88, 1, 2),
(89, 1, 7),
(90, 1, 9),
(91, 1, 8),
(92, 1, 6),
(93, 1, 11),
(94, 1, 13),
(95, 1, 12),
(96, 1, 10),
(97, 1, 15),
(98, 1, 17),
(99, 1, 16),
(100, 1, 14),
(101, 1, 19),
(102, 1, 21),
(103, 1, 20),
(104, 1, 18),
(105, 1, 23),
(106, 1, 24),
(107, 1, 25),
(108, 1, 22),
(109, 1, 27),
(110, 1, 29),
(111, 1, 28),
(112, 1, 26),
(113, 3, 14),
(114, 3, 27),
(115, 3, 29),
(116, 3, 28),
(117, 3, 26),
(118, 4, 28),
(119, 4, 26);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `User_ID` int NOT NULL AUTO_INCREMENT,
  `Email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `First_Name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Last_Name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Department` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Status` enum('Online','Offline') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Offline',
  `last_active` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `reset_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`User_ID`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Email`, `Password`, `First_Name`, `Last_Name`, `Department`, `Status`, `last_active`, `is_deleted`, `reset_token`, `reset_token_expires`) VALUES
(1, 'superadmin@example.com', '$2y$10$i4ei.yjO/RWfQmsCjZrbUO8PdC8YKip8/JRf8yXYC17AU7RbHt8vq', 'Super1', 'Admin1', 'Office of the President', '', '2025-02-05 11:08:09', 0, NULL, NULL),
(2, 'administrator@example.com', '$2y$10$Pf0uRneLsEex.szkU8vJS.P27ttwz9EtZkND8w4ttadglX6AVQFpe', 'Admin', 'Inistrator', 'Department', '', NULL, 0, NULL, NULL),
(139, 'asd@gmail.com', '$2y$10$XZI2H62333wGltLe2lnD3umbO8v5h6m0mX4dF3FDwt1rBOCQgO3/u', 'asd', 'asd', 'SOL', 'Offline', NULL, 0, NULL, NULL),
(141, 'superuseasdr@example.com', '$2y$10$jvRMVO9L7B2pv0TelQDaZeilhdSdwHLZpnAUIqLT7y0i5vYNxozEW', 'asd', 'asd', 'SOM', 'Offline', NULL, 0, NULL, NULL),
(142, 'testtest@example.com', '$2y$10$VeW7q1lJ7kzlBuMhxk.48OcfEEbVucx2/j5EDNSlAhGGXUnmBLWcq', 'adf', 'adf', 'SOL', 'Offline', NULL, 0, NULL, NULL),
(143, 'asdasdasdas123d@example.com', '$2y$10$Dd/KgFonELaE92kMGbU0OuTqwWwYYRaIDshRO/6IR5wCxyNBM9Ecu', 'asd', 'asd', 'SOL', 'Offline', NULL, 0, NULL, NULL),
(145, 'testtest123@example.com', '$2y$10$2dQ47SHsBGEhLS9x6bIqLeuLCpr3ZwgP3kEZ0XVYMD.2/CIvpVCZK', '123123', '123123', 'SOM', 'Offline', NULL, 0, NULL, NULL),
(146, '1233333@example.com', '$2y$10$pfVCnoMV/kZ39xk9Zk34ceZ7hXrCaRllfKaTnfrlNm3h5o0Wu7952', '312', '123', 'SOM', 'Offline', NULL, 0, NULL, NULL),
(147, 'superuse12312312r@example.com', '$2y$10$TlZ6hEyAl4819wKYjGKftOSpubm9aAPmiqJ5PfrpF4YBuklmqJMzW', '123123', '123123', 'SOL', 'Offline', NULL, 0, NULL, NULL);

--
-- Triggers `users`
--
DROP TRIGGER IF EXISTS `after_user_delete`;
DELIMITER $$
CREATE TRIGGER `after_user_delete` AFTER DELETE ON `users` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,              -- The user who performed the action
        `EntityID`,            -- The ID of the permanently deleted user
        `Action`,              -- Action type
        `Details`,             -- Description of the action
        `OldVal`,              -- Old values before the permanent delete
        `NewVal`,              -- New values (empty for permanent delete)
        `Module`,              -- Module where the action occurred
        `Status`,              -- Status of the action
        `Date_Time`            -- Timestamp of the action
    ) VALUES (
                 @current_user_id,      -- Replace with the actual user ID performing the action
                 OLD.User_ID,           -- The ID of the permanently deleted user
                 'Delete',    -- Action type
                 'User has been deleted', -- Details
                 JSON_OBJECT(           -- Old values in JSON format
                         'User_ID', OLD.User_ID,
                         'Email', OLD.Email,
                         'First_Name', OLD.First_Name,
                         'Last_Name', OLD.Last_Name,
                         'Department', OLD.Department,
                         'Status', OLD.Status,
                         'Last_Active', OLD.last_active,
                         'is_deleted', OLD.is_deleted
                 ),
                 '',                    -- New values are empty for permanent delete
                 IFNULL(@current_module, 'User Management'), -- Default to 'User Management'
                 'Successful',           -- Status of the action
                 NOW()                  -- Current timestamp
             );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `after_user_remove`;
DELIMITER $$
CREATE TRIGGER `after_user_remove` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    -- Check if the update is a soft delete (is_deleted changed from 0 to 1)
    IF OLD.is_deleted = 0 AND NEW.is_deleted = 1 THEN
        INSERT INTO audit_log (
            `UserID`,              -- The user who performed the action
            `EntityID`,            -- The ID of the soft-deleted user
            `Action`,              -- Action type
            `Details`,             -- Description of the action
            `OldVal`,              -- Old values before the soft delete
            `NewVal`,              -- New values after the soft delete
            `Module`,              -- Module where the action occurred
            `Status`,              -- Status of the action
            `Date_Time`            -- Timestamp of the action
        ) VALUES (
                     @current_user_id,      -- Replace with the actual user ID performing the action
                     OLD.User_ID,           -- The ID of the soft-deleted user
                     'Remove',         -- Action type
                     'User soft deleted (is_deleted set to 1)', -- Details
                     JSON_OBJECT(           -- Old values in JSON format
                             'User_ID', OLD.User_ID,
                             'Email', OLD.Email,
                             'First_Name', OLD.First_Name,
                             'Last_Name', OLD.Last_Name,
                             'Department', OLD.Department,
                             'Status', OLD.Status,
                             'Last_Active', OLD.last_active,
                             'is_deleted', OLD.is_deleted
                     ),
                     JSON_OBJECT(           -- New values in JSON format
                             'User_ID', NEW.User_ID,
                             'Email', NEW.Email,
                             'First_Name', NEW.First_Name,
                             'Last_Name', NEW.Last_Name,
                             'Department', NEW.Department,
                             'Status', NEW.Status,
                             'Last_Active', NEW.last_active,
                             'is_deleted', NEW.is_deleted
                     ),
                     IFNULL(@current_module, 'User Management'), -- Default to 'User Management'
                     'Successful',           -- Status of the action
                     NOW()                  -- Current timestamp
                 );
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `user_after_modify`;
DELIMITER $$
CREATE TRIGGER `user_after_modify` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    DECLARE diffList TEXT DEFAULT '';
    
    -- Only log modifications if is_deleted did not change.
    IF OLD.is_deleted = NEW.is_deleted THEN
        IF OLD.Email <> NEW.Email THEN 
            SET diffList = CONCAT(diffList, 'Email, ');
        END IF;
        IF OLD.First_Name <> NEW.First_Name THEN 
            SET diffList = CONCAT(diffList, 'First_Name, ');
        END IF;
        IF OLD.Last_Name <> NEW.Last_Name THEN 
            SET diffList = CONCAT(diffList, 'Last_Name, ');
        END IF;
        IF OLD.Department <> NEW.Department THEN 
            SET diffList = CONCAT(diffList, 'Department, ');
        END IF;
        IF OLD.Status <> NEW.Status THEN 
            SET diffList = CONCAT(diffList, 'Status, ');
        END IF;
        IF OLD.last_active <> NEW.last_active THEN 
            SET diffList = CONCAT(diffList, 'Last_Active, ');
        END IF;
        IF OLD.Password <> NEW.Password THEN 
            SET diffList = CONCAT(diffList, 'Password, ');
        END IF;

        -- Only insert a log if at least one field (other than is_deleted) changed.
        IF TRIM(TRAILING ', ' FROM diffList) <> '' THEN
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
                NEW.User_ID,
                'Modified',
                CONCAT('Updated fields: ', TRIM(TRAILING ', ' FROM diffList)),
                JSON_OBJECT(
                    'User_ID', OLD.User_ID,
                    'Email', OLD.Email,
                    'First_Name', OLD.First_Name,
                    'Last_Name', OLD.Last_Name,
                    'Department', OLD.Department,
                    'Password', OLD.Password,
                    'Status', OLD.Status,
                    'Last_Active', OLD.last_active
                ),
                JSON_OBJECT(
                    'User_ID', NEW.User_ID,
                    'Email', NEW.Email,
                    'First_Name', NEW.First_Name,
                    'Last_Name', NEW.Last_Name,
                    'Department', NEW.Department,
                    'Password', NEW.Password,
                    'Status', NEW.Status,
                    'Last_Active', NEW.last_active
                ),
                IFNULL(@current_module, 'User Management'),
                'Successful',
                NOW()
            );
        END IF;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `user_after_restore`;
DELIMITER $$
CREATE TRIGGER `user_after_restore` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    -- Check if the update is a restore (is_deleted changed from 1 to 0)
    IF OLD.is_deleted = 1 AND NEW.is_deleted = 0 THEN
        INSERT INTO audit_log (
            `UserID`,              -- The user who performed the action
            `EntityID`,            -- The ID of the restored user
            `Action`,              -- Action type
            `Details`,             -- Description of the action
            `OldVal`,              -- Old values before the restore
            `NewVal`,              -- New values after the restore
            `Module`,              -- Module where the action occurred
            `Status`,              -- Status of the action
            `Date_Time`            -- Timestamp of the action
        ) VALUES (
            @current_user_id,      -- The user performing the action
            NEW.User_ID,           -- The ID of the restored user
            'Restored',            -- Action type
            'User restored (is_deleted set to 0)',  -- Details
            JSON_OBJECT(           -- Old values in JSON format
                'User_ID', OLD.User_ID,
                'Email', OLD.Email,
                'First_Name', OLD.First_Name,
                'Last_Name', OLD.Last_Name,
                'Department', OLD.Department,
                'Status', OLD.Status,
                'Last_Active', OLD.last_active,
                'is_deleted', OLD.is_deleted
            ),
            JSON_OBJECT(           -- New values in JSON format
                'User_ID', NEW.User_ID,
                'Email', NEW.Email,
                'First_Name', NEW.First_Name,
                'Last_Name', NEW.Last_Name,
                'Department', NEW.Department,
                'Status', NEW.Status,
                'Last_Active', NEW.last_active,
                'is_deleted', NEW.is_deleted
            ),
            IFNULL(@current_module, 'User Management'),
            'Successful',
            NOW()                  -- Current timestamp
        );
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `users_after_create`;
DELIMITER $$
CREATE TRIGGER `users_after_create` AFTER INSERT ON `users` FOR EACH ROW INSERT INTO audit_log (
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
    NEW.User_ID,
    'Create',  -- Ensure this is correctly set
    CONCAT('New user added: ', NEW.Email),
    NULL,
    JSON_OBJECT(
        'User_ID', NEW.User_ID,
        'Email', NEW.Email,
        'First_Name', NEW.First_Name,
        'Last_Name', NEW.Last_Name,
        'Department', NEW.Department
    ),
    IFNULL(@current_module, 'User Management'),
    'Successful',
    NOW()
)
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE IF NOT EXISTS `user_roles` (
  `User_Role_ID` int NOT NULL AUTO_INCREMENT,
  `User_ID` int DEFAULT NULL,
  `Role_ID` int DEFAULT NULL,
  PRIMARY KEY (`User_Role_ID`),
  KEY `User_ID` (`User_ID`),
  KEY `Role_ID` (`Role_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`User_Role_ID`, `User_ID`, `Role_ID`) VALUES
(1, 1, 1),
(2, 2, 2),
(49, 139, 4),
(50, 141, 4),
(51, 142, 3),
(52, 143, 4),
(54, 145, 4),
(55, 146, 4),
(56, 147, 4);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `privileges`
--
ALTER TABLE `privileges`
  ADD CONSTRAINT `privileges_ibfk_1` FOREIGN KEY (`Module_ID`) REFERENCES `modules` (`Module_ID`) ON DELETE CASCADE;

--
-- Constraints for table `role_privileges`
--
ALTER TABLE `role_privileges`
  ADD CONSTRAINT `role_privileges_ibfk_1` FOREIGN KEY (`Role_ID`) REFERENCES `roles` (`Role_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_privileges_ibfk_2` FOREIGN KEY (`Privilege_ID`) REFERENCES `privileges` (`Privilege_ID`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`Role_ID`) REFERENCES `roles` (`Role_ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
