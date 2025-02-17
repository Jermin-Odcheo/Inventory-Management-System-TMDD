-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 17, 2025 at 02:05 AM
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
  `Action` enum('View','Modified','Delete','Add','Undo') NOT NULL,
  `Details` text,
  `OldVal` text,
  `NewVal` text,
  `Module` varchar(50) NOT NULL,
  `Status` enum('Successful','Failed') NOT NULL,
  `Date_Time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`TrackID`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`TrackID`, `UserID`, `EntityID`, `Action`, `Details`, `OldVal`, `NewVal`, `Module`, `Status`, `Date_Time`) VALUES
(54, 1, 51, 'Modified', 'Updated fields: ...', '{\"Email\": \"testcase4@example.com\", \"Status\": \"Online\", \"User_ID\": 51, \"Last_Name\": \"123\", \"Department\": \"123\", \"First_Name\": \"123\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"testcase44@example.com\", \"Status\": \"Online\", \"User_ID\": 51, \"Last_Name\": \"1234\", \"Department\": \"1234\", \"First_Name\": \"1234\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-14 16:03:53'),
(55, 1, 51, '', 'User soft deleted (is_deleted set to 1)', '{\"Email\": \"testcase44@example.com\", \"Status\": \"Online\", \"User_ID\": 51, \"Last_Name\": \"1234\", \"Department\": \"1234\", \"First_Name\": \"1234\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"testcase44@example.com\", \"Status\": \"Online\", \"User_ID\": 51, \"Last_Name\": \"1234\", \"Department\": \"1234\", \"First_Name\": \"1234\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-14 16:04:22'),
(56, 1, 51, '', 'User restored (is_deleted set to 0)', '{\"Email\": \"testcase44@example.com\", \"Status\": \"Online\", \"User_ID\": 51, \"Last_Name\": \"1234\", \"Department\": \"1234\", \"First_Name\": \"1234\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"testcase44@example.com\", \"Status\": \"Online\", \"User_ID\": 51, \"Last_Name\": \"1234\", \"Department\": \"1234\", \"First_Name\": \"1234\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-14 16:04:27'),
(57, 1, 51, '', 'User soft deleted (is_deleted set to 1)', '{\"Email\": \"testcase44@example.com\", \"Status\": \"Online\", \"User_ID\": 51, \"Last_Name\": \"1234\", \"Department\": \"1234\", \"First_Name\": \"1234\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"testcase44@example.com\", \"Status\": \"Online\", \"User_ID\": 51, \"Last_Name\": \"1234\", \"Department\": \"1234\", \"First_Name\": \"1234\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-14 16:09:46'),
(58, 1, 51, '', 'User permanently deleted from the database', '{\"Email\": \"testcase44@example.com\", \"Status\": \"Online\", \"User_ID\": 51, \"Last_Name\": \"1234\", \"Department\": \"1234\", \"First_Name\": \"1234\", \"is_deleted\": 1, \"Last_Active\": null}', '', 'User Management', 'Successful', '2025-02-14 16:09:51'),
(59, 1, 49, '', 'User soft deleted (is_deleted set to 1)', '{\"Email\": \"test123123@example.com\", \"Status\": \"Online\", \"User_ID\": 49, \"Last_Name\": \"test123123\", \"Department\": \"123123\", \"First_Name\": \"test123123\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"test123123@example.com\", \"Status\": \"Online\", \"User_ID\": 49, \"Last_Name\": \"test123123\", \"Department\": \"123123\", \"First_Name\": \"test123123\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-14 16:33:17'),
(60, 1, 53, 'Add', 'New user added', '', NULL, 'User Management', 'Successful', '2025-02-17 08:12:29'),
(61, 1, 47, '', 'User soft deleted (is_deleted set to 1)', '{\"Email\": \"asdasd@gmail.com\", \"Status\": \"Online\", \"User_ID\": 47, \"Last_Name\": \"asdasd\", \"Department\": \"asdasd\", \"First_Name\": \"asdasd\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"asdasd@gmail.com\", \"Status\": \"Online\", \"User_ID\": 47, \"Last_Name\": \"asdasd\", \"Department\": \"asdasd\", \"First_Name\": \"asdasd\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-17 08:12:33'),
(62, 1, 53, '', 'User soft deleted (is_deleted set to 1)', '{\"Email\": \"testtest@example.com\", \"Status\": \"Online\", \"User_ID\": 53, \"Last_Name\": \"test\", \"Department\": \"test\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"testtest@example.com\", \"Status\": \"Online\", \"User_ID\": 53, \"Last_Name\": \"test\", \"Department\": \"test\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-17 08:12:33'),
(63, 1, 47, '', 'User restored (is_deleted set to 0)', '{\"Email\": \"asdasd@gmail.com\", \"Status\": \"Online\", \"User_ID\": 47, \"Last_Name\": \"asdasd\", \"Department\": \"asdasd\", \"First_Name\": \"asdasd\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"asdasd@gmail.com\", \"Status\": \"Online\", \"User_ID\": 47, \"Last_Name\": \"asdasd\", \"Department\": \"asdasd\", \"First_Name\": \"asdasd\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-17 08:14:10'),
(64, 1, 49, '', 'User restored (is_deleted set to 0)', '{\"Email\": \"test123123@example.com\", \"Status\": \"Online\", \"User_ID\": 49, \"Last_Name\": \"test123123\", \"Department\": \"123123\", \"First_Name\": \"test123123\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"test123123@example.com\", \"Status\": \"Online\", \"User_ID\": 49, \"Last_Name\": \"test123123\", \"Department\": \"123123\", \"First_Name\": \"test123123\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-17 08:14:10'),
(65, 1, 53, '', 'User restored (is_deleted set to 0)', '{\"Email\": \"testtest@example.com\", \"Status\": \"Online\", \"User_ID\": 53, \"Last_Name\": \"test\", \"Department\": \"test\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"testtest@example.com\", \"Status\": \"Online\", \"User_ID\": 53, \"Last_Name\": \"test\", \"Department\": \"test\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-17 08:14:10'),
(66, 1, 49, '', 'User soft deleted (is_deleted set to 1)', '{\"Email\": \"test123123@example.com\", \"Status\": \"Online\", \"User_ID\": 49, \"Last_Name\": \"test123123\", \"Department\": \"123123\", \"First_Name\": \"test123123\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"test123123@example.com\", \"Status\": \"Online\", \"User_ID\": 49, \"Last_Name\": \"test123123\", \"Department\": \"123123\", \"First_Name\": \"test123123\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-17 08:14:54'),
(67, 1, 53, '', 'User soft deleted (is_deleted set to 1)', '{\"Email\": \"testtest@example.com\", \"Status\": \"Online\", \"User_ID\": 53, \"Last_Name\": \"test\", \"Department\": \"test\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"testtest@example.com\", \"Status\": \"Online\", \"User_ID\": 53, \"Last_Name\": \"test\", \"Department\": \"test\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-17 08:14:54'),
(68, 1, 49, '', 'User permanently deleted from the database', '{\"Email\": \"test123123@example.com\", \"Status\": \"Online\", \"User_ID\": 49, \"Last_Name\": \"test123123\", \"Department\": \"123123\", \"First_Name\": \"test123123\", \"is_deleted\": 1, \"Last_Active\": null}', '', 'User Management', 'Successful', '2025-02-17 08:15:28'),
(69, 1, 53, '', 'User permanently deleted from the database', '{\"Email\": \"testtest@example.com\", \"Status\": \"Online\", \"User_ID\": 53, \"Last_Name\": \"test\", \"Department\": \"test\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '', 'User Management', 'Successful', '2025-02-17 08:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `chargeinvoice`
--

DROP TABLE IF EXISTS `chargeinvoice`;
CREATE TABLE IF NOT EXISTS `chargeinvoice` (
  `ChargeInvoiceID` int NOT NULL AUTO_INCREMENT,
  `ChargeInvoiceNo` varchar(50) NOT NULL,
  `DateOfChargeInvoice` date NOT NULL,
  `PurchaseOrderNumber` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`ChargeInvoiceID`),
  KEY `PurchaseOrderNumber` (`PurchaseOrderNumber`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `chargeinvoice`
--

INSERT INTO `chargeinvoice` (`ChargeInvoiceID`, `ChargeInvoiceNo`, `DateOfChargeInvoice`, `PurchaseOrderNumber`) VALUES
(1, 'CI6789', '2024-02-05', 'PO12345'),
(2, 'CI6790', '2024-02-06', 'PO12346');

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

INSERT INTO `equipmentdetails` (`EquipmentDetailsID`, `AssetTag`, `AssetDescription1`, `AssetDescription2`, `Specification`, `Brand`, `Model`, `SerialNumber`, `DateAcquired`, `ReceivingReportFormNumber`, `AccountableIndividualLocation`, `AccountableIndividual`, `Remarks`) VALUES
(1, 'AT001', 'Laptop', 'Dell XPS 13', 'Laptops', 'Dell', 'XPS 13', 'SN12345', '2024-02-06', 'RR001', 'IT Department', 'John Doe', 'Assigned to IT Department'),
(2, 'AT002', 'Printer', 'HP LaserJet', 'Printers', 'HP', 'LaserJet 400', 'SN67890', '2024-02-07', 'RR002', 'Admin Office', 'Jane Smith', 'Assigned to Admin Office');

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
  PRIMARY KEY (`EquipmentLocationID`),
  KEY `AssetTag` (`AssetTag`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipmentlocation`
--

INSERT INTO `equipmentlocation` (`EquipmentLocationID`, `AssetTag`, `BuildingLocation`, `FloorNumber`, `SpecificArea`, `PersonResponsible`, `Remarks`) VALUES
(1, 'AT001', 'Main Building', 3, 'IT Room', 'John Doe', 'Setup Completed'),
(2, 'AT002', 'Admin Block', 1, 'Office', 'Jane Smith', 'In Use');

-- --------------------------------------------------------

--
-- Table structure for table `equipmentstatus`
--

DROP TABLE IF EXISTS `equipmentstatus`;
CREATE TABLE IF NOT EXISTS `equipmentstatus` (
  `EquipmentStatusID` int NOT NULL AUTO_INCREMENT,
  `AssetTag` varchar(50) DEFAULT NULL,
  `Status` varchar(100) DEFAULT NULL,
  `Action` varchar(255) DEFAULT NULL,
  `Remarks` text,
  PRIMARY KEY (`EquipmentStatusID`),
  KEY `AssetTag` (`AssetTag`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipmentstatus`
--

INSERT INTO `equipmentstatus` (`EquipmentStatusID`, `AssetTag`, `Status`, `Action`, `Remarks`) VALUES
(1, 'AT001', 'Operational', 'Deployed', 'Working well'),
(2, 'AT002', 'Operational', 'Deployed', 'No issues reported');

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
CREATE TABLE IF NOT EXISTS `modules` (
  `Module_ID` int NOT NULL AUTO_INCREMENT,
  `Module_Name` varchar(191) NOT NULL,
  PRIMARY KEY (`Module_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`Module_ID`, `Module_Name`) VALUES
(1, 'User Management'),
(2, 'Roles and Privileges'),
(3, 'Equipment Management'),
(4, 'Audit');

-- --------------------------------------------------------

--
-- Table structure for table `privileges`
--

DROP TABLE IF EXISTS `privileges`;
CREATE TABLE IF NOT EXISTS `privileges` (
  `Privilege_ID` int NOT NULL AUTO_INCREMENT,
  `Privilege_Name` varchar(191) NOT NULL,
  PRIMARY KEY (`Privilege_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `privileges`
--

INSERT INTO `privileges` (`Privilege_ID`, `Privilege_Name`) VALUES
(1, 'Track'),
(2, 'View'),
(3, 'Edit/Modify'),
(4, 'Delete'),
(5, 'Create/Add'),
(6, 'Undo/Restore');

-- --------------------------------------------------------

--
-- Table structure for table `purchaseorder`
--

DROP TABLE IF EXISTS `purchaseorder`;
CREATE TABLE IF NOT EXISTS `purchaseorder` (
  `PurchaseOrderID` int NOT NULL AUTO_INCREMENT,
  `PurchaseOrderNumber` varchar(50) NOT NULL,
  `NumberOfUnits` int NOT NULL,
  `DateOfPurchaseOrder` date NOT NULL,
  `ItemsSpecification` text,
  PRIMARY KEY (`PurchaseOrderID`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchaseorder`
--

INSERT INTO `purchaseorder` (`PurchaseOrderID`, `PurchaseOrderNumber`, `NumberOfUnits`, `DateOfPurchaseOrder`, `ItemsSpecification`) VALUES
(1, 'PO12345', 10, '2024-02-01', 'Laptops'),
(2, 'PO12346', 5, '2024-02-02', 'Printers'),
(3, '1234567890', 12, '2025-02-13', 'AMD'),
(4, '1111', 11, '2025-02-14', '123'),
(5, '123', 11, '2025-02-03', '123');

-- --------------------------------------------------------

--
-- Table structure for table `receivingreportform`
--

DROP TABLE IF EXISTS `receivingreportform`;
CREATE TABLE IF NOT EXISTS `receivingreportform` (
  `ReceivingReportFormID` int NOT NULL AUTO_INCREMENT,
  `ReceivingReportNumber` varchar(50) NOT NULL,
  `AccountableIndividual` varchar(100) NOT NULL,
  `PurchaseOrderNumber` varchar(50) DEFAULT NULL,
  `AccountableIndividualLocation` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ReceivingReportFormID`),
  KEY `PurchaseOrderNumber` (`PurchaseOrderNumber`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `receivingreportform`
--

INSERT INTO `receivingreportform` (`ReceivingReportFormID`, `ReceivingReportNumber`, `AccountableIndividual`, `PurchaseOrderNumber`, `AccountableIndividualLocation`) VALUES
(1, 'RR001', 'John Doe', 'PO12345', 'IT Department'),
(2, 'RR002', 'Jane Smith', 'PO12346', 'Admin Office');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `Role_ID` int NOT NULL AUTO_INCREMENT,
  `Role_Name` varchar(191) NOT NULL,
  PRIMARY KEY (`Role_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`Role_ID`, `Role_Name`) VALUES
(1, 'Super Admin'),
(2, 'Administrator'),
(3, 'Regular User');

-- --------------------------------------------------------

--
-- Table structure for table `role_privileges`
--

DROP TABLE IF EXISTS `role_privileges`;
CREATE TABLE IF NOT EXISTS `role_privileges` (
  `Role_Privilege_ID` int NOT NULL AUTO_INCREMENT,
  `Role_ID` int NOT NULL,
  `Module_ID` int NOT NULL,
  `Privilege_ID` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`Role_Privilege_ID`),
  KEY `fk_rp_role` (`Role_ID`),
  KEY `fk_rp_module` (`Module_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_privileges`
--

INSERT INTO `role_privileges` (`Role_Privilege_ID`, `Role_ID`, `Module_ID`, `Privilege_ID`) VALUES
(1, 1, 1, '2,5'),
(2, 1, 2, '3'),
(3, 1, 3, '4'),
(4, 1, 4, '1,6'),
(5, 2, 1, '2'),
(6, 2, 2, '3'),
(7, 3, 1, '2');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `User_ID` int NOT NULL AUTO_INCREMENT,
  `Email` varchar(191) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `First_Name` varchar(100) NOT NULL,
  `Last_Name` varchar(100) NOT NULL,
  `Department` varchar(255) DEFAULT NULL,
  `Role` varchar(255) DEFAULT NULL,
  `Status` enum('Online','Offline') DEFAULT 'Offline',
  PRIMARY KEY (`User_ID`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Email`, `Password`, `First_Name`, `Last_Name`, `Department`, `Role`, `Status`) VALUES
(1, 'superadmin@example.com', '$2y$10$superadminhash', 'Super', 'Admin', 'IT,Finance', '1,2,3,4', 'Online'),
(2, 'admin@example.com', '$2y$10$adminhash', 'Admin', 'User', 'IT', '5,6', 'Online'),
(3, 'user@example.com', '$2y$10$userhash', 'Regular', 'User', 'HR,Sales', '7', 'Offline'),
(4, 'multirole@example.com', '$2y$10$multihash', 'Multi', 'Role', 'IT,HR', '1,2,3,4,5,6', 'Online');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `role_privileges`
--
ALTER TABLE `role_privileges`
  ADD CONSTRAINT `fk_rp_module` FOREIGN KEY (`Module_ID`) REFERENCES `modules` (`Module_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`Role_ID`) REFERENCES `roles` (`Role_ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
