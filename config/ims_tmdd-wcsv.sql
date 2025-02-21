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

-- /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
-- /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
-- /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
-- /*!40101 SET NAMES utf8mb4 */;

-- Database: `ims_tmdd-wcsv`

-- --------------------------------------------------------

-- Table structure for table `audit_log`

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

-- --------------------------------------------------------

-- Table structure for table `chargeinvoice`

DROP TABLE IF EXISTS `chargeinvoice`;
CREATE TABLE IF NOT EXISTS `chargeinvoice` (
  `ChargeInvoiceID` int NOT NULL AUTO_INCREMENT,
  `ChargeInvoiceNo` varchar(50) NOT NULL,
  `DateOfChargeInvoice` date NOT NULL,
  `PurchaseOrderNumber` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`ChargeInvoiceID`),
  KEY `PurchaseOrderNumber` (`PurchaseOrderNumber`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

-- Table structure for table `equipmentdetails`

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

-- --------------------------------------------------------

-- Table structure for table `equipmentlocation`

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

-- --------------------------------------------------------

-- Table structure for table `equipmentstatus`

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

-- --------------------------------------------------------

-- Table structure for table `modules`

DROP TABLE IF EXISTS `modules`;
CREATE TABLE IF NOT EXISTS `modules` (
  `Module_ID` int NOT NULL AUTO_INCREMENT,
  `Module_Name` varchar(191) NOT NULL,
  PRIMARY KEY (`Module_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

-- Table structure for table `privileges`

DROP TABLE IF EXISTS `privileges`;
CREATE TABLE IF NOT EXISTS `privileges` (
  `Privilege_ID` int NOT NULL AUTO_INCREMENT,
  `Privilege_Name` varchar(191) NOT NULL,
  PRIMARY KEY (`Privilege_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

-- Table structure for table `purchaseorder`

DROP TABLE IF EXISTS `purchaseorder`;
CREATE TABLE IF NOT EXISTS `purchaseorder` (
  `PurchaseOrderID` int NOT NULL AUTO_INCREMENT,
  `PurchaseOrderNumber` varchar(50) NOT NULL,
  `NumberOfUnits` int NOT NULL,
  `DateOfPurchaseOrder` date NOT NULL,
  `ItemsSpecification` text,
  PRIMARY KEY (`PurchaseOrderID`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

-- Table structure for table `receivingreportform`

DROP TABLE IF EXISTS `receivingreportform`;
CREATE TABLE IF NOT EXISTS `receivingreportform` (
  -- your structure for the receivingreportform table
);
