-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 03, 2025 at 03:44 AM
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
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `Role_ID` int NOT NULL AUTO_INCREMENT,
  `Role_Name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`Role_ID`),
  UNIQUE KEY `Role_Name` (`Role_Name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  PRIMARY KEY (`User_ID`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Email`, `Password`, `First_Name`, `Last_Name`, `Department`, `Status`) VALUES
(1, 'superadmin@example.com', '$2y$10$i4ei.yjO/RWfQmsCjZrbUO8PdC8YKip8/JRf8yXYC17AU7RbHt8vq', 'super', 'admin', 'TMDD', 'Offline'),
(2, 'administrator@example.com', '$2y$10$ScYu3YXjK.lTncFipqz4S.IvGbgEgfxcfH.wD5nkvLyDl5Xkbz7Z.', 'admin', 'strator', 'Department', 'Offline'),
(3, 'superuser@example.com', '$2y$10$VJoWfKo9G6H37vDA3pvtB.dNL0ig2bBkH4jdo31Ept4HSJ7Ojp/A6', 'super', 'user', 'under department', 'Offline'),
(4, 'regularuser@example.com', '$2y$10$OboOl8t8zAEU8E1prxQo7u2I0Z/yf1T0Q867ffx.AAgs0H/f1sLMy', 'regular', 'user', 'regular user', 'Offline');

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
