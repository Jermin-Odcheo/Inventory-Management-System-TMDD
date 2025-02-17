-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 17, 2025 at 08:37 AM
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
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `chargeinvoice`
--

INSERT INTO `chargeinvoice` (`ChargeInvoiceID`, `ChargeInvoiceNo`, `DateOfChargeInvoice`, `PurchaseOrderNumber`) VALUES
(2, 'CI6790', '2024-02-06', 'PO12346'),
(3, '213456', '2025-02-13', '12346798');

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_privileges`
--

INSERT INTO `role_privileges` (`Role_Privilege_ID`, `Role_ID`, `Module_ID`, `Privilege_ID`) VALUES
(5, 2, 1, '2'),
(6, 2, 2, '3'),
(7, 3, 1, '2'),
(8, 1, 4, '5,4,3,1,6,2'),
(9, 1, 3, '5,4,3,1,6,2'),
(10, 1, 2, '5,4,3,1,6,2'),
(11, 1, 1, '5,4,3,1,6,2');

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
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `last_active` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`User_ID`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Email`, `Password`, `First_Name`, `Last_Name`, `Department`, `Role`, `Status`, `is_deleted`, `last_active`) VALUES
(1, 'superadmin@example.com', '$2y$10$3vuvasFU2cKxdNGDMD6KSuQuLQnStJd.XCkZ/6OlHvRjpISznQkWe', 'Super', 'Admin', 'IT,Finance', '1,2,3,4', 'Online', 0, '2025-02-17 03:53:15'),
(2, 'admin@example.com', '$2y$10$KxqZZo87r8sJeJ9kBG.PTuzw5VO/3YorIbPgl/1m9NWWQc4Wpv53O', 'Admin', 'User', 'IT', '5,6', 'Online', 0, '2025-02-17 03:53:15'),
(3, 'user@example.com', '$2y$10$r4v3g85rIJ.ZbEuOsgg1n.cY3HtSlOaEUP/pJOqxozZcpHOkZ89SG', 'Regular', 'User', 'HR,Sales', '7', 'Offline', 0, '2025-02-17 05:04:35'),
(4, 'multirole@example.com', '$2y$10$Ye.mq22CGO7AsENRtIdlzuatqCnAghr9KEiNn2r2kVRSaxenlBfYS', 'Multi', 'Role', 'IT,HR', '1,2,3,4,5,6', 'Online', 1, '2025-02-17 03:53:15');

--
-- Triggers `users`
--
DROP TRIGGER IF EXISTS `after_user_permanent_delete`;
DELIMITER $$
CREATE TRIGGER `after_user_permanent_delete` AFTER DELETE ON `users` FOR EACH ROW BEGIN
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
        'Permanent Delete',    -- Action type
        'User permanently deleted from the database', -- Details
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
DROP TRIGGER IF EXISTS `after_user_restore`;
DELIMITER $$
CREATE TRIGGER `after_user_restore` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    -- Check if the update is a soft delete (is_deleted changed from 0 to 1)
    IF OLD.is_deleted = 1 AND NEW.is_deleted = 0 THEN
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
            'Restore',         -- Action type
            'User restored (is_deleted set to 0)', -- Details
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
DROP TRIGGER IF EXISTS `after_user_soft_delete`;
DELIMITER $$
CREATE TRIGGER `after_user_soft_delete` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
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
            'Soft Delete',         -- Action type
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
DROP TRIGGER IF EXISTS `after_user_update`;
DELIMITER $$
CREATE TRIGGER `after_user_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    -- Skip logging if the only change is a soft delete (is_deleted from 0 to 1)
    IF NOT (OLD.is_deleted = 0 AND NEW.is_deleted = 1) THEN
        IF (OLD.Email <> NEW.Email 
            OR OLD.First_Name <> NEW.First_Name 
            OR OLD.Last_Name <> NEW.Last_Name 
            OR OLD.Department <> NEW.Department 
            OR OLD.Status <> NEW.Status 
            OR OLD.last_active <> NEW.last_active) THEN

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
                NEW.User_ID,
                'Modified',
                CONCAT('User has been updated'),
                JSON_OBJECT(
                    'User_ID', OLD.User_ID,
                    'Email', OLD.Email,
                    'First_Name', OLD.First_Name,
                    'Last_Name', OLD.Last_Name,
                    'Department', OLD.Department,
                    'Status', OLD.Status,
                    'Last_Active', OLD.last_active
                ),
                JSON_OBJECT(
                    'User_ID', NEW.User_ID,
                    'Email', NEW.Email,
                    'First_Name', NEW.First_Name,
                    'Last_Name', NEW.Last_Name,
                    'Department', NEW.Department,
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
DROP TRIGGER IF EXISTS `users_after_insert`;
DELIMITER $$
CREATE TRIGGER `users_after_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        `UserID`,              -- The user who performed the action
        `EntityID`,            -- The ID of the new user record
        `Action`,              -- The action performed (e.g., 'Add')
        `Details`,             -- Description of the action
        `OldVal`,              -- Previous values (empty for 'Add')
        `NewVal`,              -- New values (details of the added user)
        `Module`,              -- Module where the action occurred
        `Status`,              -- Status of the action (e.g., 'Successful')
        `Date_Time`            -- Timestamp of the action
    ) VALUES (
        @current_user_id,      -- Replace with the actual user ID performing the action
        NEW.User_ID,           -- The ID of the newly added user
        'Add',                 -- Action type
        'New user added',      -- Details of the action
        '',                    -- No old values for 'Add'
        CONCAT(                -- New values as a concatenated string
            'User_ID: ', NEW.User_ID,
            ', Email: ', NEW.Email,
            ', First_Name: ', NEW.First_Name,
            ', Last_Name: ', NEW.Last_Name,
            ', Department: ', NEW.Department,
            ', Status: ', NEW.Status,
            ', Last_Active: ', NEW.last_active,
            ', is_deleted: ', NEW.is_deleted
        ),
        IFNULL(@current_module, 'User Management'), -- Default to 'User Management' if @current_module is not set
        'Successful',          -- Status of the action
        NOW()                  -- Current timestamp
    );
END
$$
DELIMITER ;

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
