-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 23, 2025 at 06:14 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(191) NOT NULL,
  `abbreviation` varchar(50) NOT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0,
  `name_active` varchar(191) GENERATED ALWAYS AS (case when `is_disabled` = 0 then `department_name` else NULL end) STORED,
  `abbr_active` varchar(50) GENERATED ALWAYS AS (case when `is_disabled` = 0 then `abbreviation` else NULL end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `abbreviation`, `is_disabled`) VALUES
(1, 'Office of the President', 'OP', 0),
(2, 'Office of the Executive Assistant to the President', 'OEAP', 0),
(3, 'Office of the Internal Auditor', 'OIA', 0),
(4, 'Office of the Vice President for Mission and Identity', 'OVPMI', 0),
(5, 'Center for Campus Ministry', 'CCM', 0),
(6, 'Community Extension and Outreach Programs Office', 'CEOP', 0),
(7, 'St. Aloysius Gonzaga Parish Office', 'SAGPO', 0),
(8, 'Sunflower Child and Youth Wellness Center', 'SCYWC', 0),
(9, 'Office of the Vice President for Academic Affairs', 'OVPAA', 0),
(10, 'School of Accountancy, Management, Computing and Information Studies', 'SAMCIS', 0),
(11, 'School of Advanced Studies', 'SAS', 0),
(12, 'School of Engineering and Architecture', 'SEA', 0),
(13, 'School of Law', 'SOL', 0),
(14, 'School of Medicine', 'SOM', 0),
(15, 'School of Nursing, Allied Health, and Biological Sciences Natural Sciences', 'SONAHBS', 0),
(16, 'School of Teacher Education and Liberal Arts', 'STELA', 0),
(17, 'Basic Education School', 'SLU BEdS', 0),
(18, 'Office of Institutional Development and Quality Assurance', 'OIDQA', 0),
(19, 'University Libraries', 'UL', 0),
(20, 'University Registrar\'s Office', 'URO', 0),
(21, 'University Research and Innovation Center', 'URIC', 0),
(22, 'Office of the Vice President for Finance', 'OVPF', 0),
(23, 'Asset Management and Inventory Control Office', 'AMICO', 0),
(24, 'Finance Office', 'FO', 0),
(25, 'Printing Operations Office', 'POO', 0),
(26, 'Technology Management and Development Department', 'TMDD', 0),
(27, 'Office of the Vice President for Administration', 'OVPA', 0),
(28, 'Athletics and Fitness Center', 'AFC', 0),
(29, 'Campus Planning, Maintenance, and Security Department', 'CPMSD', 0),
(30, 'Center for Culture and the Arts', 'CCA', 0),
(31, 'Dental Clinic', 'DC', 0),
(32, 'Guidance Center', 'GC', 0),
(33, 'Human Resource Department', 'HRD', 0),
(34, 'Students\' Residence Hall', 'SRH', 0),
(35, 'Medical Clinic', 'MC', 0),
(36, 'Office for Legal Affairs', 'OLA', 0),
(46, '5777', '9876', 0),
(48, '123', '123', 0),
(49, '321', '321', 1),
(53, '321', '321', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dept_active` (`name_active`,`abbr_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
