-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 01, 2025 at 10:56 AM
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
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
CREATE TABLE IF NOT EXISTS `modules` (
  `Module_ID` int NOT NULL AUTO_INCREMENT,
  `Module_Name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `Privilege_Name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Module_ID` int DEFAULT NULL,
  PRIMARY KEY (`Privilege_ID`),
  KEY `Module_ID` (`Module_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `privileges`
--

INSERT INTO `privileges` (`Privilege_ID`, `Privilege_Name`, `Module_ID`) VALUES
(1, 'View', 1),
(2, 'Add', 1),
(3, 'Edit', 1),
(4, 'Delete', 1),
(5, 'Approve', NULL),
(6, 'Reject', NULL),
(7, 'Undo', NULL),
(8, 'Trace', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `Role_ID` int NOT NULL AUTO_INCREMENT,
  `Role_Name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`Role_ID`),
  UNIQUE KEY `Role_Name` (`Role_Name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`Role_ID`, `Role_Name`) VALUES
(2, 'Administrator'),
(4, 'Registrar'),
(5, 'regsol'),
(3, 'Sec'),
(1, 'Super User\r\n');

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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_privileges`
--

INSERT INTO `role_privileges` (`Role_Privilege_ID`, `Role_ID`, `Privilege_ID`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 3),
(4, 1, 4),
(5, 2, 1),
(6, 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `User_ID` int NOT NULL AUTO_INCREMENT,
  `Email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `First_Name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Last_Name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Department` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Status` enum('Online','Offline') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Offline',
  PRIMARY KEY (`User_ID`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Email`, `Password`, `First_Name`, `Last_Name`, `Department`, `Status`) VALUES
(1, 'admin@example.com', 'admin123', 'Admin', 'User', 'TMDD', 'Offline'),
(2, 'registrar@example.com', 'registrar123', 'John', 'Doe', 'Registrar', 'Offline'),
(3, 'faculty@example.com', 'faculty123', 'Jane', 'Smith', 'Faculty', 'Offline'),
(4, 'student@example.com', 'student123', 'Mike', 'Johnson', 'Student Affairs', 'Offline');

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`User_Role_ID`, `User_ID`, `Role_ID`) VALUES
(1, 1, 1),
(2, 2, 2),
(3, 3, 3),
(4, 4, 4);

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
