-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 11, 2025 at 02:56 AM
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
  `User` varchar(255) NOT NULL,
  `Action` enum('View','Modified','Delete','Add','Undo') NOT NULL,
  `Details` text,
  `OldVal` text,
  `NewVal` text,
  `Module` varchar(50) NOT NULL,
  `Status` enum('Successful','Failed') NOT NULL,
  `Date_Time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`TrackID`)
) ENGINE=MyISAM AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`TrackID`, `User`, `Action`, `Details`, `OldVal`, `NewVal`, `Module`, `Status`, `Date_Time`) VALUES
(15, 'regularuser@example.com', 'Modified', 'Updated fields: ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-07 15:26:01'),
(14, 'regularuser@example.com', 'Modified', 'Updated fields: ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-07 15:24:42'),
(13, 'regularuser@example.com', 'Modified', 'Updated fields: ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-07 15:21:23'),
(12, 'regularuser@example.com', 'Modified', 'Updated fields: Department, Status, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"Online\", \"User_ID\": 4, \"Last_Name\": \"user\", \"Department\": \"regular user\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-07 15:21:05'),
(11, 'administrator@example.com', 'Modified', 'Updated fields: First_Name, Last_Name, Department, ', '{\"Email\": \"administrator@example.com\", \"Status\": \"\", \"User_ID\": 2, \"Last_Name\": \"Inistrator\", \"Department\": \"Department\", \"First_Name\": \"Admin\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"administrator@example.com\", \"Status\": \"\", \"User_ID\": 2, \"Last_Name\": \"Inistrator1\", \"Department\": \"Department1\", \"First_Name\": \"Admin1\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-07 15:20:43'),
(10, 'superadmin@example.com', 'Modified', 'Updated fields: First_Name, Last_Name, Department, ', '{\"Email\": \"superadmin@example.com\", \"Status\": \"\", \"User_ID\": 1, \"Last_Name\": \"Admin\", \"Department\": \"TMDD\", \"First_Name\": \"Super\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:08:09.000000\"}', '{\"Email\": \"superadmin@example.com\", \"Status\": \"\", \"User_ID\": 1, \"Last_Name\": \"Admin1\", \"Department\": \"TMDD1\", \"First_Name\": \"Super1\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:08:09.000000\"}', 'User Management', 'Successful', '2025-02-07 15:20:35'),
(16, 'regularuser@example.com', 'Modified', 'Updated fields: Password, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$n2ISfgCqlahqD5Rtn5QTeOXfNQYM1Qbw9mYiuk2YMH8gnTifTl1F6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-07 15:28:43'),
(17, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-07 16:57:37'),
(18, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 13:37:23'),
(19, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 13:37:26'),
(20, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 13:40:15'),
(21, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 13:40:19'),
(22, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 14:12:32'),
(23, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 14:12:36'),
(24, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 14:24:10'),
(25, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 14:24:10'),
(26, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 14:38:56'),
(27, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 14:41:04'),
(28, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 14:41:32'),
(29, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 14:41:36'),
(30, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 15:00:55'),
(31, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 15:00:55'),
(32, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 15:01:00'),
(33, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 15:01:00'),
(34, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 15:08:04'),
(35, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 15:08:06'),
(36, 'superuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"superuser@example.com\", \"Status\": \"\", \"User_ID\": 3, \"Password\": \"$2y$10$VJoWfKo9G6H37vDA3pvtB.dNL0ig2bBkH4jdo31Ept4HSJ7Ojp/A6\", \"Last_Name\": \"User\", \"Department\": \"Department User\", \"First_Name\": \"Super\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:11:33.000000\"}', '{\"Email\": \"superuser@example.com\", \"Status\": \"\", \"User_ID\": 3, \"Password\": \"$2y$10$VJoWfKo9G6H37vDA3pvtB.dNL0ig2bBkH4jdo31Ept4HSJ7Ojp/A6\", \"Last_Name\": \"User\", \"Department\": \"Department User\", \"First_Name\": \"Super\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:11:33.000000\"}', 'User Management', 'Successful', '2025-02-10 15:08:16'),
(37, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 15:08:16'),
(38, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 15:08:16'),
(39, 'superuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"superuser@example.com\", \"Status\": \"\", \"User_ID\": 3, \"Password\": \"$2y$10$VJoWfKo9G6H37vDA3pvtB.dNL0ig2bBkH4jdo31Ept4HSJ7Ojp/A6\", \"Last_Name\": \"User\", \"Department\": \"Department User\", \"First_Name\": \"Super\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:11:33.000000\"}', '{\"Email\": \"superuser@example.com\", \"Status\": \"\", \"User_ID\": 3, \"Password\": \"$2y$10$VJoWfKo9G6H37vDA3pvtB.dNL0ig2bBkH4jdo31Ept4HSJ7Ojp/A6\", \"Last_Name\": \"User\", \"Department\": \"Department User\", \"First_Name\": \"Super\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:11:33.000000\"}', 'User Management', 'Successful', '2025-02-10 15:08:19'),
(40, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 15:08:19'),
(41, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 15:08:19'),
(42, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 15:13:12'),
(43, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 15:13:12'),
(44, 'regularuser@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 1, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', '{\"Email\": \"regularuser@example.com\", \"Status\": \"\", \"User_ID\": 4, \"Password\": \"$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6\", \"Last_Name\": \"user\", \"Department\": \"Regular Department\", \"First_Name\": \"regular\", \"is_deleted\": 0, \"Last_Active\": \"2025-02-05 11:19:22.000000\"}', 'User Management', 'Successful', '2025-02-10 15:24:14'),
(45, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 15:57:04'),
(46, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 16:26:59'),
(47, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-10 16:27:02'),
(48, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-11 10:51:02'),
(49, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-11 10:51:45'),
(50, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-11 10:51:48'),
(51, 'test@example.com', 'Modified', 'Updated fields: is_deleted, ', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 1, \"Last_Active\": null}', '{\"Email\": \"test@example.com\", \"Status\": \"Offline\", \"User_ID\": 6, \"Password\": \"$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..\", \"Last_Name\": \"case1\", \"Department\": \"test case\", \"First_Name\": \"test\", \"is_deleted\": 0, \"Last_Active\": null}', 'User Management', 'Successful', '2025-02-11 10:51:50');

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
  `NumberOfUnits` int NOT NULL,
  `DateOfPurchaseOrder` date NOT NULL,
  `ItemsSpecification` text,
  PRIMARY KEY (`PurchaseOrderID`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchaseorder`
--

INSERT INTO `purchaseorder` (`PurchaseOrderID`, `PurchaseOrderNumber`, `NumberOfUnits`, `DateOfPurchaseOrder`, `ItemsSpecification`) VALUES
(1, 'PO12345', 10, '2024-02-01', 'Laptops'),
(2, 'PO12346', 5, '2024-02-02', 'Printers');

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
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  PRIMARY KEY (`User_ID`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Email`, `Password`, `First_Name`, `Last_Name`, `Department`, `Status`, `last_active`, `is_deleted`) VALUES
(1, 'superadmin@example.com', '$2y$10$i4ei.yjO/RWfQmsCjZrbUO8PdC8YKip8/JRf8yXYC17AU7RbHt8vq', 'Super1', 'Admin1', 'TMDD1', '', '2025-02-05 11:08:09', 0),
(2, 'administrator@example.com', '$2y$10$Pf0uRneLsEex.szkU8vJS.P27ttwz9EtZkND8w4ttadglX6AVQFpe', 'Admin1', 'Inistrator1', 'Department1', '', NULL, 0),
(3, 'superuser@example.com', '$2y$10$VJoWfKo9G6H37vDA3pvtB.dNL0ig2bBkH4jdo31Ept4HSJ7Ojp/A6', 'Super', 'User', 'Department User', '', '2025-02-05 11:11:33', 0),
(4, 'regularuser@example.com', '$2y$10$77dO3RFP.Pus3r/HhkprAuZDDt4VKaKUuBwRFF0nFagCDjxfKgAv6', 'regular', 'user', 'Regular Department', '', '2025-02-05 11:19:22', 0),
(6, 'test@example.com', '$2y$10$OnRvP/ieAMnTCTthCOHpwerURb1UlIkk7fYmMQ2OwcEFNoK3FJF..', 'test', 'case1', 'test case', 'Offline', NULL, 0);

--
-- Triggers `users`
--
DROP TRIGGER IF EXISTS `users_after_delete`;
DELIMITER $$
CREATE TRIGGER `users_after_delete` AFTER DELETE ON `users` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
      `User`, 
      `Action`, 
      `Details`, 
      `OldVal`, 
      `NewVal`, 
      `Module`, 
      `Status`
    )
    VALUES (
      OLD.Email,                                  -- The deleted user's email
      'Delete',                                   -- Action performed
      'User deleted',                             -- Details describing the event
      CONCAT(
         'User_ID: ', OLD.User_ID,
         ', Email: ', OLD.Email,
         ', First_Name: ', OLD.First_Name,
         ', Last_Name: ', OLD.Last_Name,
         ', Department: ', OLD.Department,
         ', Status: ', OLD.Status,
         ', Last_Active: ', OLD.last_active,
         ', is_deleted: ', OLD.is_deleted
      ),
      '',                                         -- No new values on deletion
      'User Management',                          -- Module name
      'Successful'                                -- Status of the action
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `users_after_insert`;
DELIMITER $$
CREATE TRIGGER `users_after_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
      `User`, 
      `Action`, 
      `Details`, 
      `OldVal`, 
      `NewVal`, 
      `Module`, 
      `Status`
    )
    VALUES (
      NEW.Email,                                  -- Who performed the action (or the user record itself)
      'Add',                                      -- Action performed
      'New user added',                           -- Details describing the event
      '',                                         -- No old values (this is an INSERT)
      CONCAT(
         'User_ID: ', NEW.User_ID,
         ', Email: ', NEW.Email,
         ', First_Name: ', NEW.First_Name,
         ', Last_Name: ', NEW.Last_Name,
         ', Department: ', NEW.Department,
         ', Status: ', NEW.Status,
         ', Last_Active: ', NEW.last_active,
         ', is_deleted: ', NEW.is_deleted
      ),
      'User Management',                          -- Module name
      'Successful'                                -- Status of the action
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `users_after_update`;
DELIMITER $$
CREATE TRIGGER `users_after_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    DECLARE diffList TEXT;
    SET diffList = '';
    
    -- Build a list of updated fields (you can adjust this as needed)
    IF OLD.Email <> NEW.Email THEN SET diffList = CONCAT(diffList, 'Email, '); END IF;
    IF OLD.First_Name <> NEW.First_Name THEN SET diffList = CONCAT(diffList, 'First_Name, '); END IF;
    IF OLD.Last_Name <> NEW.Last_Name THEN SET diffList = CONCAT(diffList, 'Last_Name, '); END IF;
    IF OLD.Department <> NEW.Department THEN SET diffList = CONCAT(diffList, 'Department, '); END IF;
    IF OLD.Status <> NEW.Status THEN SET diffList = CONCAT(diffList, 'Status, '); END IF;
    IF OLD.last_active <> NEW.last_active THEN SET diffList = CONCAT(diffList, 'Last_Active, '); END IF;
    IF OLD.is_deleted <> NEW.is_deleted THEN SET diffList = CONCAT(diffList, 'is_deleted, '); END IF;
    -- Always include the password in the JSON so it can be compared
    IF OLD.Password <> NEW.Password THEN 
        SET diffList = CONCAT(diffList, 'Password, ');
    END IF;
    
    INSERT INTO audit_log (
      `User`, 
      `Action`, 
      `Details`, 
      `OldVal`, 
      `NewVal`, 
      `Module`, 
      `Status`
    )
    VALUES (
      OLD.Email, 
      'Modified', 
      CONCAT('Updated fields: ', diffList), 
      JSON_OBJECT(
         'User_ID', OLD.User_ID,
         'Email', OLD.Email,
         'First_Name', OLD.First_Name,
         'Last_Name', OLD.Last_Name,
         'Department', OLD.Department,
         'Password', OLD.Password,  -- include the password hash here
         'Status', OLD.Status,
         'Last_Active', OLD.last_active,
         'is_deleted', OLD.is_deleted
      ),
      JSON_OBJECT(
         'User_ID', NEW.User_ID,
         'Email', NEW.Email,
         'First_Name', NEW.First_Name,
         'Last_Name', NEW.Last_Name,
         'Department', NEW.Department,
         'Password', NEW.Password,  -- include the new password hash here
         'Status', NEW.Status,
         'Last_Active', NEW.last_active,
         'is_deleted', NEW.is_deleted
      ),
      'User Management', 
      'Successful'
    );
END
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`User_Role_ID`, `User_ID`, `Role_ID`) VALUES
(1, 1, 1),
(2, 2, 2),
(3, 3, 3),
(4, 4, 4),
(8, 6, 4);

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
