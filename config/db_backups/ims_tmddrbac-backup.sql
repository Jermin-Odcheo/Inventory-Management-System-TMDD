-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 19, 2025 at 10:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateUserAndDepartment` (IN `p_user_id` INT, IN `p_email` VARCHAR(255), IN `p_first_name` VARCHAR(191), IN `p_last_name` VARCHAR(191), IN `p_password` VARCHAR(255), IN `p_status` VARCHAR(50), IN `p_department_id` INT, IN `p_changed_by` INT, IN `p_module` VARCHAR(191))   BEGIN
    DECLARE diffList TEXT DEFAULT '';
    DECLARE old_email VARCHAR(255);
    DECLARE old_first_name VARCHAR(191);
    DECLARE old_last_name VARCHAR(191);
    DECLARE old_password VARCHAR(255);
    DECLARE old_department_id INT;
    DECLARE old_department_name VARCHAR(191) DEFAULT '';
    DECLARE new_department_name VARCHAR(191);
    DECLARE user_update_status INT DEFAULT 0;
    DECLARE final_status VARCHAR(20);

    -- Retrieve old user values
    SELECT email, first_name, last_name, password 
      INTO old_email, old_first_name, old_last_name, old_password
    FROM users 
    WHERE id = p_user_id;

    -- Retrieve the user's current department ID (if any)
    SELECT department_id 
      INTO old_department_id 
    FROM user_departments 
    WHERE user_id = p_user_id 
    LIMIT 1;

    -- If there is a current department, get its name
    IF old_department_id IS NOT NULL THEN
        SELECT department_name 
          INTO old_department_name 
        FROM departments 
        WHERE id = old_department_id 
        LIMIT 1;
    END IF;

    -- Get the new department name for comparison
    SELECT department_name 
      INTO new_department_name 
    FROM departments 
    WHERE id = p_department_id 
    LIMIT 1;

    -- Update the user details
    UPDATE users SET
        email = p_email,
        first_name = p_first_name,
        last_name = p_last_name
    WHERE id = p_user_id;

    -- Capture the number of rows affected
    SET user_update_status = ROW_COUNT();

    -- Handle department changes
    IF old_department_id IS NULL THEN
        INSERT INTO user_departments(user_id, department_id) 
            VALUES (p_user_id, p_department_id);
        SET diffList = CONCAT(diffList, 'department (added), ');
    ELSEIF old_department_id <> p_department_id THEN
        UPDATE user_departments 
            SET department_id = p_department_id 
            WHERE user_id = p_user_id;
        SET diffList = CONCAT(diffList, 'department, ');
    END IF;

    -- Append changes in user details to diffList
    IF old_email <> p_email THEN 
        SET diffList = CONCAT(diffList, 'email, ');
    END IF;
    IF old_first_name <> p_first_name THEN 
        SET diffList = CONCAT(diffList, 'first_name, ');
    END IF;
    IF old_last_name <> p_last_name THEN 
        SET diffList = CONCAT(diffList, 'last_name, ');
    END IF;

    -- Set the final status to 'Successful' unconditionally,
    -- because the execution itself was successful.
    SET final_status = 'Successful';

    -- Insert the audit log entry (using backticks for Status)
    INSERT INTO audit_log (
        UserID,
        EntityID,
        Action,
        Details,
        OldVal,
        NewVal,
        Module,
        `Status`,
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
        final_status,
        NOW()
    );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `TrackID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `EntityID` int(11) DEFAULT NULL,
  `Action` varchar(255) NOT NULL,
  `Details` text DEFAULT NULL,
  `OldVal` text DEFAULT NULL,
  `NewVal` text DEFAULT NULL,
  `Module` varchar(255) NOT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `Date_Time` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(123, 1, 11, 'Modified', 'Updated fields: email, first_name, last_name', '{\"id\": 11, \"email\": \"testingCase1111@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast1111\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst1111\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', '{\"id\": 11, \"email\": \"testingCase11111@gmail.com\", \"status\": \"Offline\", \"password\": \"$2y$10$xssRq5vZk5kyOCg2TQcIuejiaLhcUYxODegrxaJJW8mFQukeH1R4q\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast11111\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst11111\", \"date_created\": \"2025-03-07 11:52:51.000000\"}', 'User Management', 'Successful', '2025-03-07 13:05:24'),
(134, 1, 11, 'Restored', 'User has been restored', '{\"id\": 11, \"email\": \"testingCase11111@gmail.com\", \"status\": \"\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast11111\", \"first_name\": \"TestingCaseFirst11111\", \"is_disabled\": 1, \"date_created\": \"2025-03-07 11:52:51.000000\"}', '', 'User Management', 'Successful', '2025-03-07 13:36:28'),
(135, 1, 11, 'Remove', 'User has been removed', '{\"id\": 11, \"email\": \"testingCase11111@gmail.com\", \"status\": \"\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast11111\", \"first_name\": \"TestingCaseFirst11111\", \"is_disabled\": 0, \"date_created\": \"2025-03-07 11:52:51.000000\"}', '', 'User Management', 'Successful', '2025-03-07 13:37:11'),
(136, 1, 11, 'Restored', 'User has been restored', '{\"id\": 11, \"email\": \"testingCase11111@gmail.com\", \"status\": \"\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast11111\", \"first_name\": \"TestingCaseFirst11111\", \"is_disabled\": 1, \"date_created\": \"2025-03-07 11:52:51.000000\"}', '', 'User Management', 'Successful', '2025-03-07 13:37:22'),
(179, 1, 11, 'Modified', 'Updated fields: department, email, first_name, last_name', '{\"email\": \"testingCase@gmail.com\", \"last_name\": \"TestingCaseLast\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"TestingCaseFirst\"}', '{\"email\": \"testingCase1@gmail.com\", \"last_name\": \"TestingCaseLast1\", \"department\": \"Office of the President\", \"first_name\": \"TestingCaseFirst1\"}', 'User Management', 'Successful', '2025-03-07 16:55:26'),
(180, 1, 11, 'Modified', 'Updated fields: department', '{\"email\": \"testingCase1@gmail.com\", \"last_name\": \"TestingCaseLast1\", \"department\": \"Office of the President\", \"first_name\": \"TestingCaseFirst1\"}', '{\"email\": \"testingCase1@gmail.com\", \"last_name\": \"TestingCaseLast1\", \"department\": \"Office of the Vice President for Mission and Identity\", \"first_name\": \"TestingCaseFirst1\"}', 'User Management', 'Successful', '2025-03-07 16:55:33'),
(181, 1, 11, 'Modified', 'Updated fields: email, first_name, last_name', '{\"email\": \"testingCase1@gmail.com\", \"last_name\": \"TestingCaseLast1\", \"department\": \"Office of the Vice President for Mission and Identity\", \"first_name\": \"TestingCaseFirst1\"}', '{\"email\": \"testingCase123@gmail.com\", \"last_name\": \"TestingCaseLast123\", \"department\": \"Office of the Vice President for Mission and Identity\", \"first_name\": \"TestingCaseFirst123\"}', 'User Management', 'Successful', '2025-03-07 16:55:38'),
(182, 1, 20, 'Modified', 'Department details modified', '{\"Department_Acronym\":null,\"Department_Name\":null}', '{\"Department_Acronym\":\"URO\",\"Department_Name\":\"University Registrar\\u2019s Offices\"}', 'Departments', 'Successful', '2025-03-10 08:17:20'),
(183, 1, 20, 'Modified', 'Department details modified', '{\"Department_Acronym\":null,\"Department_Name\":null}', '{\"Department_Acronym\":\"URO\",\"Department_Name\":\"University Registrar\\u2019s Offices\"}', 'Departments', 'Successful', '2025-03-10 08:17:54'),
(184, 1, 20, 'Modified', 'Department details modified', '{\"Department_Acronym\":null,\"Department_Name\":null}', '{\"Department_Acronym\":\"URO\",\"Department_Name\":\"University Registrar\\u2019s Offices\"}', 'Departments', 'Successful', '2025-03-10 08:18:00'),
(185, 1, 20, 'Modified', 'Department details modified', '{\"abbreviation\":\"URO\",\"department_name\":\"University Registrar\\u2019s Offices\"}', '{\"abbreviation\":\"URO\",\"department_name\":\"University Registrar\\u2019s Offices\"}', 'Departments', 'Successful', '2025-03-10 08:34:13'),
(186, 1, 20, 'Modified', 'Department details modified', '{\"abbreviation\":\"URO\",\"department_name\":\"University Registrar\\u2019s Offices\"}', '{\"abbreviation\":\"URO\",\"department_name\":\"University Registrar\\u2019s Offices\"}', 'Departments', 'Successful', '2025-03-10 08:34:19'),
(187, 1, 20, 'Modified', 'Department details modified', '{\"abbreviation\":\"URO\",\"department_name\":\"University Registrar\\u2019s Offices\"}', '{\"abbreviation\":\"URO\",\"department_name\":\"University Registrar\\u2019s Office\"}', 'Departments', 'Successful', '2025-03-10 08:34:22'),
(188, 1, 1, 'Delete', 'Equipment has been deleted', '{\"id\":1,\"asset_tag\":\"23123\",\"asset_description_1\":\"1231\",\"asset_description_2\":\"123\",\"specifications\":\"123\",\"brand\":\"123\",\"model\":\"123\",\"serial_number\":\"123123\",\"date_created\":\"2025-03-13 16:38:00\"}', NULL, 'Equipment Management', 'Successful', '2025-03-10 09:02:35'),
(189, 1, 41, 'Add', 'New department added', NULL, '{\"id\":\"41\",\"abbreviation\":\"test\",\"department_name\":\"test\"}', 'Department Management', 'Successful', '2025-03-10 09:27:11'),
(190, 1, 41, 'Delete', 'Department deleted', '{\"id\":41,\"abbreviation\":\"test\",\"department_name\":\"test\"}', NULL, 'Departments', 'Successful', '2025-03-10 09:27:15'),
(191, 1, 2, 'Add', 'New equipment added', NULL, '{\"asset_tag\":\"asd\",\"asset_description_1\":\"asd\",\"asset_description_2\":\"asd\",\"specifications\":\"asd\",\"brand\":\"asd\",\"model\":\"asd\",\"serial_number\":\"asd\",\"date_created\":\"2025-03-15T13:02\",\"remarks\":\"asd\"}', 'Equipment Details', 'Successful', '2025-03-10 13:03:02'),
(192, 1, 4, 'Add', 'New equipment added', NULL, '{\"asset_tag\":\"asdasd\",\"asset_description_1\":\"asdasd\",\"asset_description_2\":\"asdasd\",\"specifications\":\"asdasd\",\"brand\":\"asdasd\",\"model\":\"asdasd\",\"serial_number\":\"asdasd\",\"date_created\":\"2025-03-10T13:05\",\"remarks\":\"asdasd\"}', 'Equipment Details', 'Successful', '2025-03-10 13:05:46'),
(193, 1, 5, 'Add', 'New equipment added', NULL, '{\"asset_tag\":\"test\",\"asset_description_1\":\"test\",\"asset_description_2\":\"test\",\"specifications\":\"test\",\"brand\":\"test\",\"model\":\"test\",\"serial_number\":\"test\",\"date_created\":\"2025-03-10T13:15\",\"remarks\":\"testetset\"}', 'Equipment Details', 'Successful', '2025-03-10 13:15:53'),
(194, 1, 7, 'Add', 'New equipment added', NULL, '{\"asset_tag\":\"asdasd123\",\"asset_description_1\":\"asdasdaa\",\"asset_description_2\":\"asdasdas\",\"specifications\":\"asdasd\",\"brand\":\"asdasd\",\"model\":\"asdasd\",\"serial_number\":\"asdasdasd\",\"date_created\":\"2025-03-23T13:36\",\"remarks\":\"asdasd\"}', 'Equipment Details', 'Successful', '2025-03-10 13:36:34'),
(195, 1, 7, 'Modified', 'Equipment details modified', '{\"id\":7,\"asset_tag\":\"asdasd123\",\"asset_description_1\":\"asdasdaa\",\"asset_description_2\":\"asdasdas\",\"specifications\":\"asdasd\",\"brand\":\"asdasd\",\"model\":\"asdasd\",\"serial_number\":\"asdasdasd\",\"date_created\":\"2025-03-23 13:36:00\",\"remarks\":\"asdasd\"}', '{\"asset_tag\":\"asdasd123\",\"asset_description_1\":\"asdasdaa\",\"asset_description_2\":\"asdasdas\",\"specifications\":\"test\",\"brand\":\"asdasd\",\"model\":\"asdasd\",\"serial_number\":\"asdasdasd\",\"date_created\":\"2025-03-23T13:36\",\"remarks\":\"asdasd\"}', 'Equipment Details', 'Successful', '2025-03-10 13:41:36'),
(196, 1, 5, 'Modified', 'Equipment details modified', '{\"id\":5,\"asset_tag\":\"test\",\"asset_description_1\":\"test\",\"asset_description_2\":\"test\",\"specifications\":\"test\",\"brand\":\"test\",\"model\":\"test\",\"serial_number\":\"test\",\"date_created\":\"2025-03-10 13:15:00\",\"remarks\":\"testetset\"}', '{\"asset_tag\":\"test\",\"asset_description_1\":\"test\",\"asset_description_2\":\"test\",\"specifications\":\"testing\",\"brand\":\"test\",\"model\":\"test\",\"serial_number\":\"test\",\"date_created\":\"2025-03-10T13:15\",\"remarks\":\"testetset\"}', 'Equipment Details', 'Successful', '2025-03-10 13:41:50'),
(197, 1, 5, 'Modified', 'Equipment details modified', '{\"id\":5,\"asset_tag\":\"test\",\"asset_description_1\":\"test\",\"asset_description_2\":\"test\",\"specifications\":\"testing\",\"brand\":\"test\",\"model\":\"test\",\"serial_number\":\"test\",\"date_created\":\"2025-03-10 13:15:00\",\"remarks\":\"testetset\"}', '{\"asset_tag\":\"test123\",\"asset_description_1\":\"test123\",\"asset_description_2\":\"test123\",\"specifications\":\"testing123\",\"brand\":\"test123\",\"model\":\"test123\",\"serial_number\":\"test123\",\"date_created\":\"2025-03-10T13:15\",\"remarks\":\"testetset123\"}', 'Equipment Details', 'Successful', '2025-03-10 13:42:26'),
(198, 1, 2, 'Delete', 'Equipment has been deleted', '{\"id\":2,\"asset_tag\":\"asd\",\"asset_description_1\":\"asd\",\"asset_description_2\":\"asd\",\"specifications\":\"asd\",\"brand\":\"asd\",\"model\":\"asd\",\"serial_number\":\"asd\",\"date_created\":\"2025-03-15 13:02:00\"}', NULL, 'Equipment Management', 'Successful', '2025-03-10 13:43:29'),
(199, 1, 4, 'Delete', 'Equipment has been deleted', '{\"id\":4,\"asset_tag\":\"asdasd\",\"asset_description_1\":\"asdasd\",\"asset_description_2\":\"asdasd\",\"specifications\":\"asdasd\",\"brand\":\"asdasd\",\"model\":\"asdasd\",\"serial_number\":\"asdasd\",\"date_created\":\"2025-03-10 13:05:00\"}', NULL, 'Equipment Management', 'Successful', '2025-03-10 13:43:31'),
(203, 1, 1, '', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-03-11 09:40:30'),
(204, 1, 1, '', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-03-11 09:40:35'),
(205, 1, 1, '', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-03-11 09:42:38'),
(206, 1, 1, '', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-03-11 09:42:43'),
(207, 1, 1, '', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-03-11 10:01:31'),
(208, 1, 1, '', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-03-11 10:01:36'),
(209, 1, 1, '', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-03-11 14:21:26'),
(210, 1, 1, '', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-03-11 14:21:30'),
(221, 1, 12, 'Create', 'New user added: 1123', NULL, '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"Offline\", \"username\": \"1123\", \"last_name\": \"123\", \"department\": \"Unknown\", \"first_name\": \"123\", \"date_created\": \"2025-03-12 14:09:07.000000\"}', 'User Management', 'Successful', '2025-03-12 14:09:07'),
(222, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"Offline\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-12 14:13:10'),
(223, 1, 12, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 14:13:10'),
(226, 1, 13, 'Create', 'New user added: aasd', NULL, '{\"id\": 13, \"email\": \"asd123@gmail.com\", \"status\": \"Offline\", \"username\": \"aasd\", \"last_name\": \"asd\", \"department\": \"Unknown\", \"first_name\": \"asd\", \"date_created\": \"2025-03-12 15:07:29.000000\"}', 'User Management', 'Successful', '2025-03-12 15:07:29'),
(227, 1, 13, 'Remove', 'User has been removed', '{\"id\": 13, \"email\": \"asd123@gmail.com\", \"status\": \"Offline\", \"username\": \"aasd\", \"last_name\": \"asd\", \"first_name\": \"asd\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:07:29.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:07:36'),
(228, 1, 13, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:07:36'),
(230, 1, 11, 'Remove', 'User has been removed', '{\"id\": 11, \"email\": \"testingCase123@gmail.com\", \"status\": \"\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast123\", \"first_name\": \"TestingCaseFirst123\", \"is_disabled\": 0, \"date_created\": \"2025-03-07 11:52:51.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:09:28'),
(231, 1, 42, 'Delete', 'Department deleted', '{\"id\":42,\"abbreviation\":\"\",\"department_name\":\"aasd\"}', NULL, 'Departments', 'Successful', '2025-03-12 15:09:47'),
(232, 1, 5, 'Modified', 'Updated fields: department (added)', '{\"email\": \"auds@example.com\", \"last_name\": \"broom broom\", \"department\": null, \"first_name\": \"audi\"}', '{\"email\": \"auds@example.com\", \"last_name\": \"broom broom\", \"department\": \"Office of the Executive Assistant to the President\", \"first_name\": \"audi\"}', 'User Management', 'Successful', '2025-03-12 15:20:15'),
(233, 1, 5, 'Modified', 'Updated fields: department', '{\"email\": \"auds@example.com\", \"last_name\": \"broom broom\", \"department\": \"Office of the Executive Assistant to the President\", \"first_name\": \"audi\"}', '{\"email\": \"auds@example.com\", \"last_name\": \"broom broom\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"audi\"}', 'User Management', 'Successful', '2025-03-12 15:20:20'),
(234, 1, 5, 'Modified', 'Updated fields: department', '{\"email\": \"auds@example.com\", \"last_name\": \"broom broom\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"audi\"}', '{\"email\": \"auds@example.com\", \"last_name\": \"broom broom\", \"department\": \"TMDD Developers\", \"first_name\": \"audi\"}', 'User Management', 'Successful', '2025-03-12 15:20:25'),
(235, 1, 5, 'Modified', 'Updated fields: department', '{\"email\": \"auds@example.com\", \"last_name\": \"broom broom\", \"department\": \"TMDD Developers\", \"first_name\": \"audi\"}', '{\"email\": \"auds@example.com\", \"last_name\": \"broom broom\", \"department\": \"Office of the Executive Assistant to the President\", \"first_name\": \"audi\"}', 'User Management', 'Successful', '2025-03-12 15:20:31'),
(236, 1, 37, 'Create', 'New user added: asadf', NULL, '{\"id\": 37, \"email\": \"superuserasdf@example.com\", \"status\": \"Offline\", \"username\": \"asadf\", \"last_name\": \"sadf\", \"department\": \"Unknown\", \"first_name\": \"asdf\", \"date_created\": \"2025-03-12 15:22:56.000000\"}', 'User Management', 'Successful', '2025-03-12 15:22:56'),
(238, 1, 37, 'Remove', 'User has been removed', '{\"id\": 37, \"email\": \"superuserasdf@example.com\", \"status\": \"Offline\", \"username\": \"asadf\", \"last_name\": \"sadf\", \"first_name\": \"asdf\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:22:56.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:24:55'),
(239, 1, 37, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:24:55'),
(240, 1, 48, 'Create', 'New user added: ttest', NULL, '{\"id\": 48, \"email\": \"testtesttest@example.com\", \"status\": \"Offline\", \"username\": \"ttest\", \"last_name\": \"test\", \"department\": \"Unknown\", \"first_name\": \"test\", \"date_created\": \"2025-03-12 15:26:50.000000\"}', 'User Management', 'Successful', '2025-03-12 15:26:50'),
(241, 1, 49, 'Create', 'New user added: aasdasd', NULL, '{\"id\": 49, \"email\": \"asdasasdasdsddasdasd@example.com\", \"status\": \"Offline\", \"username\": \"aasdasd\", \"last_name\": \"asdasd\", \"department\": \"Unknown\", \"first_name\": \"asdasd\", \"date_created\": \"2025-03-12 15:27:18.000000\"}', 'User Management', 'Successful', '2025-03-12 15:27:18'),
(242, 1, 50, 'Create', 'New user added: aasdasdasd', NULL, '{\"id\": 50, \"email\": \"asdfasdfasfasdfasdf@gmail.com\", \"status\": \"Offline\", \"username\": \"aasdasdasd\", \"last_name\": \"asdasdasd\", \"department\": \"Unknown\", \"first_name\": \"asdasdasd\", \"date_created\": \"2025-03-12 15:28:36.000000\"}', 'User Management', 'Successful', '2025-03-12 15:28:36'),
(244, 1, 69, 'Create', 'New user added: aasdf', NULL, '{\"id\": 69, \"email\": \"superuseradsf@example.com\", \"status\": \"Offline\", \"username\": \"aasdf\", \"last_name\": \"asdf\", \"department\": \"Unknown\", \"first_name\": \"asdf\", \"date_created\": \"2025-03-12 15:31:49.000000\"}', 'User Management', 'Successful', '2025-03-12 15:31:49'),
(246, 1, 69, 'Remove', 'User has been removed', '{\"id\": 69, \"email\": \"superuseradsf@example.com\", \"status\": \"Offline\", \"username\": \"aasdf\", \"last_name\": \"asdf\", \"first_name\": \"asdf\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:31:49.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:39:05'),
(247, 1, 69, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:39:05'),
(248, 1, 50, 'Remove', 'User has been removed', '{\"id\": 50, \"email\": \"asdfasdfasfasdfasdf@gmail.com\", \"status\": \"Offline\", \"username\": \"aasdasdasd\", \"last_name\": \"asdasdasd\", \"first_name\": \"asdasdasd\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:28:36.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:39:12'),
(249, 1, 50, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:39:12'),
(250, 1, 49, 'Remove', 'User has been removed', '{\"id\": 49, \"email\": \"asdasasdasdsddasdasd@example.com\", \"status\": \"Offline\", \"username\": \"aasdasd\", \"last_name\": \"asdasd\", \"first_name\": \"asdasd\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:27:18.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:39:16'),
(251, 1, 49, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:39:16'),
(252, 1, 48, 'Remove', 'User has been removed', '{\"id\": 48, \"email\": \"testtesttest@example.com\", \"status\": \"Offline\", \"username\": \"ttest\", \"last_name\": \"test\", \"first_name\": \"test\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:26:50.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:39:20'),
(253, 1, 48, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:39:20'),
(254, 1, 74, 'Create', 'New user added: qqwe', NULL, '{\"id\": 74, \"email\": \"testingcase123321@gmail.com\", \"status\": \"Offline\", \"username\": \"qqwe\", \"last_name\": \"qwe\", \"department\": \"Unknown\", \"first_name\": \"qwe\", \"date_created\": \"2025-03-12 15:42:37.000000\"}', 'User Management', 'Successful', '2025-03-12 15:42:37'),
(255, 1, 74, 'Remove', 'User has been removed', '{\"id\": 74, \"email\": \"testingcase123321@gmail.com\", \"status\": \"Offline\", \"username\": \"qqwe\", \"last_name\": \"qwe\", \"first_name\": \"qwe\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:42:37.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:43:06'),
(256, 1, 74, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:43:06'),
(257, 1, 75, 'Create', 'New user added: 33333', NULL, '{\"id\": 75, \"email\": \"superuser3333333@example.com\", \"status\": \"Offline\", \"username\": \"33333\", \"last_name\": \"3333\", \"department\": \"Unknown\", \"first_name\": \"3333\", \"date_created\": \"2025-03-12 15:44:37.000000\"}', 'User Management', 'Successful', '2025-03-12 15:44:37'),
(258, 1, 75, 'Remove', 'User has been removed', '{\"id\": 75, \"email\": \"superuser3333333@example.com\", \"status\": \"Offline\", \"username\": \"33333\", \"last_name\": \"3333\", \"first_name\": \"3333\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:44:37.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:47:53'),
(259, 1, 75, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:47:53'),
(260, 1, 82, 'Create', 'New user added: ttesttesttest', NULL, '{\"id\": 82, \"email\": \"testtesttesttest@example.com\", \"status\": \"Offline\", \"username\": \"ttesttesttest\", \"last_name\": \"testtesttest\", \"department\": \"Unknown\", \"first_name\": \"testtesttest\", \"date_created\": \"2025-03-12 15:48:27.000000\"}', 'User Management', 'Successful', '2025-03-12 15:48:27'),
(261, 1, 83, 'Create', 'New user added: dfsgkyjtsdraf', NULL, '{\"id\": 83, \"email\": \"testtest123321123@example.com\", \"status\": \"Offline\", \"username\": \"dfsgkyjtsdraf\", \"last_name\": \"fsgkyjtsdraf\", \"department\": \"Unknown\", \"first_name\": \"DASFHHSGAEFd\", \"date_created\": \"2025-03-12 15:49:10.000000\"}', 'User Management', 'Successful', '2025-03-12 15:49:10'),
(263, 1, 83, 'Remove', 'User has been removed', '{\"id\": 83, \"email\": \"testtest123321123@example.com\", \"status\": \"Offline\", \"username\": \"dfsgkyjtsdraf\", \"last_name\": \"fsgkyjtsdraf\", \"first_name\": \"DASFHHSGAEFd\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:49:10.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:54:54'),
(264, 1, 83, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:54:54'),
(265, 1, 84, 'Create', 'New user added: gggg', NULL, '{\"id\": 84, \"email\": \"superuserggg@example.com\", \"status\": \"Offline\", \"username\": \"gggg\", \"last_name\": \"ggg\", \"department\": \"Unknown\", \"first_name\": \"ggg\", \"date_created\": \"2025-03-12 15:55:08.000000\"}', 'User Management', 'Successful', '2025-03-12 15:55:08'),
(266, 1, 84, 'Remove', 'User has been removed', '{\"id\": 84, \"email\": \"superuserggg@example.com\", \"status\": \"Offline\", \"username\": \"gggg\", \"last_name\": \"ggg\", \"first_name\": \"ggg\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:55:08.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:55:11'),
(267, 1, 84, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:55:11'),
(268, 1, 5, 'Remove', 'User has been removed', '{\"id\": 5, \"email\": \"auds@example.com\", \"status\": \"Offline\", \"username\": \"auds\", \"last_name\": \"broom broom\", \"first_name\": \"audi\", \"is_disabled\": 0, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:55:20'),
(269, 1, 5, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:55:20'),
(270, 1, 82, 'Remove', 'User has been removed', '{\"id\": 82, \"email\": \"testtesttesttest@example.com\", \"status\": \"Offline\", \"username\": \"ttesttesttest\", \"last_name\": \"testtesttest\", \"first_name\": \"testtesttest\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:48:27.000000\"}', '', 'User Management', 'Successful', '2025-03-12 15:55:20'),
(271, 1, 82, '', 'Status changed from Offline to ', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-12 15:55:20'),
(272, 1, 1, '', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-03-13 08:01:38'),
(273, 1, 1, '', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-03-13 08:01:46'),
(274, 1, 1, '', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-03-13 08:04:24'),
(275, 1, 1, '', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-03-13 08:04:29'),
(276, 1, 41, 'Add', 'New department added', NULL, '{\"id\":\"41\",\"abbreviation\":\"asd\",\"department_name\":\"asdasdasdasd\"}', 'Department Management', 'Successful', '2025-03-13 14:54:52'),
(277, 1, 41, 'Modified', 'Department details modified', '{\"abbreviation\":\"asd\",\"department_name\":\"asdasdasdasd\"}', '{\"abbreviation\":\"asd\",\"department_name\":\"asdasdasdasd\"}', 'Departments', 'Successful', '2025-03-13 14:55:00'),
(278, 1, 41, 'Delete', 'Department deleted', '{\"id\":41,\"abbreviation\":\"asd\",\"department_name\":\"asdasdasdasd\"}', NULL, 'Departments', 'Successful', '2025-03-13 14:55:05'),
(279, 1, 41, 'Add', 'New department added', NULL, '{\"id\":\"41\",\"abbreviation\":\"test\",\"department_name\":\"test\"}', 'Department Management', 'Successful', '2025-03-13 15:06:20'),
(280, 1, 41, 'Delete', 'Department deleted', '{\"id\":41,\"abbreviation\":\"test\",\"department_name\":\"test\"}', NULL, 'Departments', 'Successful', '2025-03-13 15:06:38'),
(281, 1, 41, 'Add', 'New department added', NULL, '{\"id\":\"41\",\"abbreviation\":\"test\",\"department_name\":\"test\"}', 'Department Management', 'Successful', '2025-03-13 15:30:44'),
(282, 1, 41, 'Modified', 'Department details modified', '{\"abbreviation\":\"test\",\"department_name\":\"test\"}', '{\"abbreviation\":\"test\",\"department_name\":\"test\"}', 'Departments', 'Successful', '2025-03-13 15:30:57'),
(283, 1, 41, 'Delete', 'Department deleted', '{\"id\":41,\"abbreviation\":\"test\",\"department_name\":\"test\"}', NULL, 'Departments', 'Successful', '2025-03-13 15:31:00'),
(284, 1, 41, 'Add', 'New department added', NULL, '{\"id\":\"41\",\"abbreviation\":\"test\",\"department_name\":\"test\"}', 'Department Management', 'Successful', '2025-03-13 15:32:13'),
(285, 1, 41, 'Delete', 'Department deleted', '{\"id\":41,\"abbreviation\":\"test\",\"department_name\":\"test\"}', NULL, 'Departments', 'Successful', '2025-03-13 15:32:27'),
(286, 1, 41, 'Add', 'New department added', NULL, '{\"id\":\"41\",\"abbreviation\":\"testtest\",\"department_name\":\"testtest\"}', 'Department Management', 'Successful', '2025-03-13 15:40:23'),
(287, 1, 41, 'Delete', 'Department deleted', '{\"id\":41,\"abbreviation\":\"testtest\",\"department_name\":\"testtest\"}', NULL, 'Departments', 'Successful', '2025-03-13 15:40:55'),
(288, 1, 41, 'Add', 'New department added', NULL, '{\"id\":\"41\",\"abbreviation\":\"test\",\"department_name\":\"test\"}', 'Department Management', 'Successful', '2025-03-13 15:44:48'),
(289, 1, 41, 'Modified', 'Department details modified', '{\"abbreviation\":\"test\",\"department_name\":\"test\"}', '{\"abbreviation\":\"test\",\"department_name\":\"testd\"}', 'Departments', 'Successful', '2025-03-13 15:44:53'),
(290, 1, 41, 'Delete', 'Department deleted', '{\"id\":41,\"abbreviation\":\"test\",\"department_name\":\"testd\"}', NULL, 'Departments', 'Successful', '2025-03-13 15:44:55'),
(291, 1, 41, 'Add', 'New department added', NULL, '{\"id\":\"41\",\"abbreviation\":\"testtest\",\"department_name\":\"test\"}', 'Department Management', 'Successful', '2025-03-13 15:53:12'),
(292, 1, 41, 'Delete', 'Department deleted', '{\"id\":41,\"abbreviation\":\"testtest\",\"department_name\":\"test\"}', NULL, 'Departments', 'Successful', '2025-03-13 15:53:48'),
(293, 1, 40, 'Delete', 'Department deleted', '{\"id\":40,\"abbreviation\":\"TMDD-Dev\",\"department_name\":\"TMDD Developers\"}', NULL, 'Departments', 'Successful', '2025-03-13 15:56:31'),
(294, 1, 38, 'Add', 'New department added', NULL, '{\"id\":\"38\",\"abbreviation\":\"test\",\"department_name\":\"test\"}', 'Department Management', 'Successful', '2025-03-13 15:59:39'),
(295, 1, 38, 'Delete', 'Department deleted', '{\"id\":38,\"abbreviation\":\"test\",\"department_name\":\"test\"}', NULL, 'Departments', 'Successful', '2025-03-13 15:59:50'),
(296, 1, 38, 'Add', 'New department added', NULL, '{\"id\":\"38\",\"abbreviation\":\"test\",\"department_name\":\"test\"}', 'Department Management', 'Successful', '2025-03-13 15:59:58'),
(297, 1, 37, 'Delete', 'Department deleted', '{\"id\":37,\"abbreviation\":\"OSA\",\"department_name\":\"Office of Student Affairs\"}', NULL, 'Departments', 'Successful', '2025-03-13 16:00:09'),
(298, 1, 38, 'Modified', 'Department details modified', '{\"abbreviation\":\"test\",\"department_name\":\"test\"}', '{\"abbreviation\":\"test\",\"department_name\":\"test\"}', 'Departments', 'Successful', '2025-03-13 16:00:28'),
(299, 1, 39, 'Add', 'New department added', NULL, '{\"id\":\"39\",\"abbreviation\":\"test123321\",\"department_name\":\"test321123\"}', 'Department Management', 'Successful', '2025-03-13 16:12:02'),
(300, 1, 39, 'Modified', 'Department details modified', '{\"abbreviation\":\"test123321\",\"department_name\":\"test321123\"}', '{\"abbreviation\":\"test1233212\",\"department_name\":\"23123123\"}', 'Departments', 'Successful', '2025-03-13 16:12:12'),
(301, 1, 39, 'Delete', 'Department deleted', '{\"id\":39,\"abbreviation\":\"test1233212\",\"department_name\":\"23123123\"}', NULL, 'Departments', 'Successful', '2025-03-13 16:12:18'),
(302, 1, 38, 'Delete', 'Department deleted', '{\"id\":38,\"abbreviation\":\"test\",\"department_name\":\"test\"}', NULL, 'Departments', 'Successful', '2025-03-13 16:12:23'),
(303, 1, 37, 'Add', 'New department added', NULL, '{\"id\":\"37\",\"abbreviation\":\"testtest123\",\"department_name\":\"testtest123\"}', 'Department Management', 'Successful', '2025-03-13 16:12:37'),
(304, 1, 38, 'Add', 'New department added', NULL, '{\"id\":\"38\",\"abbreviation\":\"testtesttest\",\"department_name\":\"testtesttest\"}', 'Department Management', 'Successful', '2025-03-13 16:20:22'),
(305, 1, 38, 'Delete', 'Department deleted', '{\"id\":38,\"abbreviation\":\"testtesttest\",\"department_name\":\"testtesttest\"}', NULL, 'Departments', 'Successful', '2025-03-13 16:20:35'),
(306, 1, 37, 'Modified', 'Department details modified', '{\"abbreviation\":\"testtest123\",\"department_name\":\"testtest123\"}', '{\"abbreviation\":\"testtest123\",\"department_name\":\"testtest123\"}', 'Departments', 'Successful', '2025-03-13 16:20:43'),
(307, 1, 37, 'Modified', 'Department details modified', '{\"abbreviation\":\"testtest123\",\"department_name\":\"testtest123\"}', '{\"abbreviation\":\"testtest1233\",\"department_name\":\"testtest1233\"}', 'Departments', 'Successful', '2025-03-13 16:20:55'),
(308, 1, 38, 'Add', 'New department added', NULL, '{\"id\":\"38\",\"abbreviation\":\"123123\",\"department_name\":\"123123\"}', 'Department Management', 'Successful', '2025-03-13 16:45:45'),
(309, 1, 37, 'Modified', 'Department details modified', '{\"abbreviation\":\"testtest1233\",\"department_name\":\"testtest1233\"}', '{\"abbreviation\":\"testtest123333\",\"department_name\":\"testtest123333\"}', 'Departments', 'Successful', '2025-03-13 16:45:53'),
(310, 1, 37, 'Delete', 'Department deleted', '{\"id\":37,\"abbreviation\":\"testtest123333\",\"department_name\":\"testtest123333\"}', NULL, 'Departments', 'Successful', '2025-03-13 16:45:56'),
(311, 1, 39, 'Add', 'New department added', NULL, '{\"id\":\"39\",\"abbreviation\":\"testtesttest123\",\"department_name\":\"testtesttest123\"}', 'Department Management', 'Successful', '2025-03-14 08:55:47'),
(312, 1, 39, 'Modified', 'Department details modified', '{\"abbreviation\":\"testtesttest123\",\"department_name\":\"testtesttest123\"}', '{\"abbreviation\":\"testtesttest123321\",\"department_name\":\"testtesttest123321\"}', 'Departments', 'Successful', '2025-03-14 08:56:00'),
(313, 1, 39, 'Delete', 'Department deleted', '{\"id\":39,\"abbreviation\":\"testtesttest123321\",\"department_name\":\"testtesttest123321\"}', NULL, 'Departments', 'Successful', '2025-03-14 08:56:03'),
(314, 1, 39, 'Add', 'New department added', NULL, '{\"id\":\"39\",\"abbreviation\":\"lotus\",\"department_name\":\"lotus\"}', 'Department Management', 'Successful', '2025-03-14 08:59:43'),
(315, 1, 40, 'Add', 'New department added', NULL, '{\"id\":\"40\",\"abbreviation\":\"fasdfadafds\",\"department_name\":\"fasdfasdf\"}', 'Department Management', 'Successful', '2025-03-14 09:07:46'),
(316, 1, 41, 'Add', 'New department added', NULL, '{\"id\":\"41\",\"abbreviation\":\"fas\",\"department_name\":\"fas\"}', 'Department Management', 'Successful', '2025-03-14 09:07:54'),
(317, 1, 41, 'Delete', 'Department deleted', '{\"id\":41,\"abbreviation\":\"fas\",\"department_name\":\"fas\"}', NULL, 'Departments', 'Successful', '2025-03-14 09:08:00'),
(318, 1, 40, 'Delete', 'Department deleted', '{\"id\":40,\"abbreviation\":\"fasdfadafds\",\"department_name\":\"fasdfasdf\"}', NULL, 'Departments', 'Successful', '2025-03-14 09:08:08'),
(319, 1, 39, 'Delete', 'Department deleted', '{\"id\":39,\"abbreviation\":\"lotus\",\"department_name\":\"lotus\"}', NULL, 'Departments', 'Successful', '2025-03-14 09:08:16'),
(320, 1, 38, 'Delete', 'Department deleted', '{\"id\":38,\"abbreviation\":\"123123\",\"department_name\":\"123123\"}', NULL, 'Departments', 'Successful', '2025-03-14 09:08:23'),
(321, 1, 36, 'Modified', 'Department details modified', '{\"abbreviation\":\"OLA\",\"department_name\":\"Office for Legal Affairs\"}', '{\"abbreviation\":\"OLA\",\"department_name\":\"Office for Legal Affairs\"}', 'Departments', 'Successful', '2025-03-14 09:09:00'),
(322, 1, 37, 'Add', 'New department added', NULL, '{\"id\":\"37\",\"abbreviation\":\"fast\",\"department_name\":\"fast\"}', 'Department Management', 'Successful', '2025-03-14 09:09:41'),
(323, 1, 38, 'Add', 'New department added', NULL, '{\"id\":\"38\",\"abbreviation\":\"wqertyj\",\"department_name\":\"wqertyy\"}', 'Department Management', 'Successful', '2025-03-14 09:25:25'),
(324, 1, 39, 'Add', 'New department added', NULL, '{\"id\":\"39\",\"abbreviation\":\"zxcvvnmb\",\"department_name\":\"asdfgbgmjh\"}', 'Department Management', 'Successful', '2025-03-14 09:25:32'),
(325, 1, 85, 'Create', 'New user added: zzxc', NULL, '{\"id\": 85, \"email\": \"zxczxczxc@gmail.com\", \"status\": \"Offline\", \"username\": \"zzxc\", \"last_name\": \"zxc\", \"department\": \"Unknown\", \"first_name\": \"zxc\", \"date_created\": \"2025-03-14 09:41:16.000000\"}', 'User Management', 'Successful', '2025-03-14 09:41:16'),
(326, 1, 40, 'Add', 'New department added', NULL, '{\"id\":\"40\",\"abbreviation\":\"asdasddasdasdasd\",\"department_name\":\"asdasdasdasdasdas\"}', 'Department Management', 'Successful', '2025-03-14 10:28:21'),
(327, 1, 41, 'Add', 'New department added', NULL, '{\"id\":\"41\",\"abbreviation\":\"jgfdsf\",\"department_name\":\"jhgfd\"}', 'Department Management', 'Successful', '2025-03-14 10:28:38'),
(328, 1, 42, 'Add', 'New department added', NULL, '{\"id\":\"42\",\"abbreviation\":\"zxczxczxczxczc\",\"department_name\":\"zxczxczxczxcz\"}', 'Department Management', 'Successful', '2025-03-14 10:29:05'),
(329, 1, 43, 'Add', 'New department added', NULL, '{\"id\":\"43\",\"abbreviation\":\"xcbxcvx\",\"department_name\":\"bxcvxc\"}', 'Department Management', 'Successful', '2025-03-14 10:29:40'),
(330, 1, 44, 'Add', 'New department added', NULL, '{\"id\":\"44\",\"abbreviation\":\"asdasdasdasdsa\",\"department_name\":\"asdasdasdasdasdasd\"}', 'Department Management', 'Successful', '2025-03-14 10:33:42'),
(331, 1, 45, 'Add', 'New department added', NULL, '{\"id\":\"45\",\"abbreviation\":\"lkjhg\",\"department_name\":\"lkjh\"}', 'Department Management', 'Successful', '2025-03-14 10:39:19'),
(332, 1, 85, 'Remove', 'User has been removed', '{\"id\": 85, \"email\": \"zxczxczxc@gmail.com\", \"status\": \"Offline\", \"username\": \"zzxc\", \"last_name\": \"zxc\", \"first_name\": \"zxc\", \"is_disabled\": 0, \"date_created\": \"2025-03-14 09:41:16.000000\"}', '', 'User Management', 'Successful', '2025-03-14 13:33:53'),
(333, 1, 85, '', 'Status changed', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-14 13:33:53'),
(334, 1, 1, 'Modified', 'Equipment details modified', '{\"id\":1,\"asset_tag\":\"23123\",\"asset_description_1\":\"1231\",\"asset_description_2\":\"123\",\"specifications\":\"123\",\"brand\":\"123\",\"model\":\"123\",\"serial_number\":\"123123\",\"invoice_no\":null,\"rr_no\":null,\"location\":null,\"accountable_individual\":null,\"remarks\":\"123123\",\"date_created\":\"2025-03-13 16:38:00\",\"is_disabled\":0}', '{\"asset_tag\":\"23123\",\"asset_description_1\":\"1231\",\"asset_description_2\":\"123\",\"specifications\":\"123\",\"brand\":\"123\",\"model\":\"123\",\"serial_number\":\"123123\",\"location\":\"N\\/A\",\"accountable_individual\":\"N\\/A\",\"rr_no\":\"N\\/A\",\"date_created\":\"2025-03-13T16:38\",\"remarks\":\"123123\"}', 'Equipment Details', 'Successful', '2025-03-17 14:02:46'),
(335, 1, 2, 'Add', 'New equipment added', NULL, '{\"asset_tag\":\"23123123\",\"asset_description_1\":\"123123123\",\"asset_description_2\":\"123132\",\"specifications\":\"12312312\",\"brand\":\"123123\",\"model\":\"123123\",\"serial_number\":\"123123123\",\"location\":\"51235123\",\"accountable_individual\":\"5432124\",\"rr_no\":\"3123125123\",\"date_created\":\"2025-03-15T17:02\",\"remarks\":\"wedfgjhweqe\"}', 'Equipment Details', 'Successful', '2025-03-17 14:03:07'),
(336, 1, 1, 'Modified', 'Equipment details modified', '{\"id\":1,\"asset_tag\":\"23123\",\"asset_description_1\":\"1231\",\"asset_description_2\":\"123\",\"specifications\":\"123\",\"brand\":\"123\",\"model\":\"123\",\"serial_number\":\"123123\",\"invoice_no\":null,\"rr_no\":\"N\\/A\",\"location\":\"N\\/A\",\"accountable_individual\":\"N\\/A\",\"remarks\":\"123123\",\"date_created\":\"2025-03-13 16:38:00\",\"is_disabled\":0}', '{\"asset_tag\":\"23123123123\",\"asset_description_1\":\"1231\",\"asset_description_2\":\"123\",\"specifications\":\"123\",\"brand\":\"123\",\"model\":\"123\",\"serial_number\":\"123123\",\"location\":\"N\\/A\",\"accountable_individual\":\"N\\/A\",\"rr_no\":\"N\\/A\",\"date_created\":\"2025-03-13T16:38\",\"remarks\":\"123123\"}', 'Equipment Details', 'Successful', '2025-03-17 14:03:27'),
(337, 1, 2, 'Delete', 'Equipment has been deleted', '{\"id\":2,\"asset_tag\":\"23123123\",\"asset_description_1\":\"123123123\",\"asset_description_2\":\"123132\",\"specifications\":\"12312312\",\"brand\":\"123123\",\"model\":\"123123\",\"serial_number\":\"123123123\",\"date_created\":\"2025-03-15 17:02:00\"}', NULL, 'Equipment Management', 'Successful', '2025-03-17 14:03:31'),
(338, 1, 1, 'Delete', 'Equipment has been deleted', '{\"id\":1,\"asset_tag\":\"23123123123\",\"asset_description_1\":\"1231\",\"asset_description_2\":\"123\",\"specifications\":\"123\",\"brand\":\"123\",\"model\":\"123\",\"serial_number\":\"123123\",\"date_created\":\"2025-03-13 16:38:00\"}', NULL, 'Equipment Management', 'Successful', '2025-03-17 14:03:33'),
(339, 1, 3, 'Add', 'New equipment added', NULL, '{\"asset_tag\":\"assettag\",\"asset_description_1\":\"desc1\",\"asset_description_2\":\"desc2\",\"specifications\":\"assetspec\",\"brand\":\"amd\",\"model\":\"amd model\",\"serial_number\":\"66647123\",\"location\":\"There\",\"accountable_individual\":\"N\\/A\",\"rr_no\":\"33322133\",\"date_created\":\"2025-03-17T14:05\",\"remarks\":\"4444123\"}', 'Equipment Details', 'Successful', '2025-03-17 14:05:36'),
(340, 1, 3, 'Modified', 'Equipment details modified', '{\"id\":3,\"asset_tag\":\"assettag\",\"asset_description_1\":\"desc1\",\"asset_description_2\":\"desc2\",\"specifications\":\"assetspec\",\"brand\":\"amd\",\"model\":\"amd model\",\"serial_number\":\"66647123\",\"invoice_no\":null,\"rr_no\":\"33322133\",\"location\":\"There\",\"accountable_individual\":\"N\\/A\",\"remarks\":\"4444123\",\"date_created\":\"2025-03-17 14:05:00\",\"is_disabled\":0}', '{\"asset_tag\":\"assettagtag\",\"asset_description_1\":\"desc1\",\"asset_description_2\":\"desc2\",\"specifications\":\"assetspec\",\"brand\":\"amd\",\"model\":\"amd model\",\"serial_number\":\"66647123\",\"location\":\"There\",\"accountable_individual\":\"N\\/A\",\"rr_no\":\"33322133\",\"date_created\":\"2025-03-17T14:05\",\"remarks\":\"4444123\"}', 'Equipment Details', 'Successful', '2025-03-17 14:05:46'),
(341, 1, 3, 'Delete', 'Equipment has been deleted', '{\"id\":3,\"asset_tag\":\"assettagtag\",\"asset_description_1\":\"desc1\",\"asset_description_2\":\"desc2\",\"specifications\":\"assetspec\",\"brand\":\"amd\",\"model\":\"amd model\",\"serial_number\":\"66647123\",\"date_created\":\"2025-03-17 14:05:00\"}', NULL, 'Equipment Management', 'Successful', '2025-03-17 14:05:57'),
(342, 1, 4, 'Add', 'New equipment added', NULL, '{\"asset_tag\":\"0987666\",\"asset_description_1\":\"yyyy\",\"asset_description_2\":\"yyyy\",\"specifications\":\"yyty\",\"brand\":\"123\",\"model\":\"123\",\"serial_number\":\"65123\",\"location\":\"723466\",\"accountable_individual\":\"2346634\",\"rr_no\":\"61235123\",\"date_created\":\"2025-03-17T14:59\",\"remarks\":\"1245u656\"}', 'Equipment Details', 'Successful', '2025-03-17 14:59:32'),
(343, 1, 4, 'Delete', 'Equipment has been deleted', '{\"id\":4,\"asset_tag\":\"0987666\",\"asset_description_1\":\"yyyy\",\"asset_description_2\":\"yyyy\",\"specifications\":\"yyty\",\"brand\":\"123\",\"model\":\"123\",\"serial_number\":\"65123\",\"date_created\":\"2025-03-17 14:59:00\"}', NULL, 'Equipment Management', 'Successful', '2025-03-17 15:26:13'),
(344, 1, 5, 'Add', 'New equipment added', NULL, '{\"asset_tag\":\"5556233\",\"asset_description_1\":\"1231\",\"asset_description_2\":\"1123\",\"specifications\":\"3123\",\"brand\":\"332123\",\"model\":\"31233\",\"serial_number\":\"123123\",\"location\":\"2312312\",\"accountable_individual\":\"1235123\",\"rr_no\":\"3123141\",\"date_created\":\"2025-03-17T15:31\",\"remarks\":\"1231231\"}', 'Equipment Details', 'Successful', '2025-03-17 15:31:10'),
(345, 1, 5, 'Delete', 'Equipment has been deleted', '{\"id\":5,\"asset_tag\":\"5556233\",\"asset_description_1\":\"1231\",\"asset_description_2\":\"1123\",\"specifications\":\"3123\",\"brand\":\"332123\",\"model\":\"31233\",\"serial_number\":\"123123\",\"date_created\":\"2025-03-17 15:31:00\"}', NULL, 'Equipment Management', 'Successful', '2025-03-17 15:32:32'),
(346, 1, 3, 'Delete', 'Equipment location deleted', '{\"asset_tag\":\"asdasd123\",\"building_loc\":\"1233321\",\"floor_no\":\"223\",\"specific_area\":\"33123\",\"person_responsible\":\"11331232\",\"department_id\":2,\"remarks\":\"3312231\"}', NULL, 'Equipment Location', 'Successful', '2025-03-17 16:35:45'),
(347, 1, 37, 'Restored', 'User has been restored', '{\"id\": 37, \"email\": \"superuserasdf@example.com\", \"status\": \"\", \"username\": \"asadf\", \"last_name\": \"sadf\", \"first_name\": \"asdf\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 15:22:56.000000\"}', '', 'User Management', 'Successful', '2025-03-18 10:51:22'),
(348, 1, 5, 'Restored', 'User has been restored', '{\"id\": 5, \"email\": \"auds@example.com\", \"status\": \"\", \"username\": \"auds\", \"last_name\": \"broom broom\", \"first_name\": \"audi\", \"is_disabled\": 1, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 11:10:17'),
(349, 1, 85, 'Restored', 'User has been restored', '{\"id\": 85, \"email\": \"zxczxczxc@gmail.com\", \"status\": \"\", \"username\": \"zzxc\", \"last_name\": \"zxc\", \"first_name\": \"zxc\", \"is_disabled\": 1, \"date_created\": \"2025-03-14 09:41:16.000000\"}', '', 'User Management', 'Successful', '2025-03-18 11:20:47'),
(350, 1, 11, 'Restored', 'User has been restored', '{\"id\": 11, \"email\": \"testingCase123@gmail.com\", \"status\": \"\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast123\", \"first_name\": \"TestingCaseFirst123\", \"is_disabled\": 1, \"date_created\": \"2025-03-07 11:52:51.000000\"}', '', 'User Management', 'Successful', '2025-03-18 12:53:25'),
(351, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:13:56'),
(352, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:14:01'),
(353, 1, 11, 'Remove', 'User has been removed', '{\"id\": 11, \"email\": \"testingCase123@gmail.com\", \"status\": \"\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast123\", \"first_name\": \"TestingCaseFirst123\", \"is_disabled\": 0, \"date_created\": \"2025-03-07 11:52:51.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:18:49'),
(354, 1, 37, 'Remove', 'User has been removed', '{\"id\": 37, \"email\": \"superuserasdf@example.com\", \"status\": \"\", \"username\": \"asadf\", \"last_name\": \"sadf\", \"first_name\": \"asdf\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:22:56.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:18:49');
INSERT INTO `audit_log` (`TrackID`, `UserID`, `EntityID`, `Action`, `Details`, `OldVal`, `NewVal`, `Module`, `Status`, `Date_Time`) VALUES
(355, 1, 85, 'Remove', 'User has been removed', '{\"id\": 85, \"email\": \"zxczxczxc@gmail.com\", \"status\": \"\", \"username\": \"zzxc\", \"last_name\": \"zxc\", \"first_name\": \"zxc\", \"is_disabled\": 0, \"date_created\": \"2025-03-14 09:41:16.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:18:49'),
(356, 1, 11, 'Restored', 'User has been restored', '{\"id\": 11, \"email\": \"testingCase123@gmail.com\", \"status\": \"\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast123\", \"first_name\": \"TestingCaseFirst123\", \"is_disabled\": 1, \"date_created\": \"2025-03-07 11:52:51.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:42:57'),
(357, 1, 11, 'Remove', 'User has been removed', '{\"id\": 11, \"email\": \"testingCase123@gmail.com\", \"status\": \"\", \"username\": \"ttestinglast\", \"last_name\": \"TestingCaseLast123\", \"first_name\": \"TestingCaseFirst123\", \"is_disabled\": 0, \"date_created\": \"2025-03-07 11:52:51.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:43:00'),
(358, 1, 37, 'Restored', 'User has been restored', '{\"id\": 37, \"email\": \"superuserasdf@example.com\", \"status\": \"\", \"username\": \"asadf\", \"last_name\": \"sadf\", \"first_name\": \"asdf\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 15:22:56.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:54:52'),
(359, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:58:00'),
(360, 1, 85, 'Restored', 'User has been restored', '{\"id\": 85, \"email\": \"zxczxczxc@gmail.com\", \"status\": \"\", \"username\": \"zzxc\", \"last_name\": \"zxc\", \"first_name\": \"zxc\", \"is_disabled\": 1, \"date_created\": \"2025-03-14 09:41:16.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:58:00'),
(361, 1, 37, 'Remove', 'User has been removed', '{\"id\": 37, \"email\": \"superuserasdf@example.com\", \"status\": \"\", \"username\": \"asadf\", \"last_name\": \"sadf\", \"first_name\": \"asdf\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:22:56.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:59:03'),
(362, 1, 85, 'Remove', 'User has been removed', '{\"id\": 85, \"email\": \"zxczxczxc@gmail.com\", \"status\": \"\", \"username\": \"zzxc\", \"last_name\": \"zxc\", \"first_name\": \"zxc\", \"is_disabled\": 0, \"date_created\": \"2025-03-14 09:41:16.000000\"}', '', 'User Management', 'Successful', '2025-03-18 13:59:03'),
(363, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:00:22'),
(364, 1, 5, 'Remove', 'User has been removed', '{\"id\": 5, \"email\": \"auds@example.com\", \"status\": \"\", \"username\": \"auds\", \"last_name\": \"broom broom\", \"first_name\": \"audi\", \"is_disabled\": 0, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:00:51'),
(365, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:11:14'),
(366, 1, 37, 'Restored', 'User has been restored', '{\"id\": 37, \"email\": \"superuserasdf@example.com\", \"status\": \"\", \"username\": \"asadf\", \"last_name\": \"sadf\", \"first_name\": \"asdf\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 15:22:56.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:11:53'),
(367, 1, 37, 'Remove', 'User has been removed', '{\"id\": 37, \"email\": \"superuserasdf@example.com\", \"status\": \"\", \"username\": \"asadf\", \"last_name\": \"sadf\", \"first_name\": \"asdf\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:22:56.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:13:02'),
(368, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:13:05'),
(369, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:19:48'),
(370, 1, 37, 'Restored', 'User has been restored', '{\"id\": 37, \"email\": \"superuserasdf@example.com\", \"status\": \"\", \"username\": \"asadf\", \"last_name\": \"sadf\", \"first_name\": \"asdf\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 15:22:56.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:19:48'),
(371, 1, 37, 'Remove', 'User has been removed', '{\"id\": 37, \"email\": \"superuserasdf@example.com\", \"status\": \"\", \"username\": \"asadf\", \"last_name\": \"sadf\", \"first_name\": \"asdf\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 15:22:56.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:20:03'),
(372, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:20:05'),
(373, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:20:14'),
(374, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:26:50'),
(375, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:26:53'),
(376, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:36:09'),
(377, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:36:13'),
(378, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:36:26'),
(379, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:36:34'),
(380, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:36:39'),
(381, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:50:59'),
(382, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:51:04'),
(383, 1, 4, 'Remove', 'User has been removed', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"Offline\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 0, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:51:07'),
(384, 1, 4, '', 'Status changed', '{\"status\": \"Offline\"}', '{\"status\": \"\"}', 'User Management', 'Successful', '2025-03-18 14:51:07'),
(385, 1, 4, 'Restored', 'User has been restored', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 1, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:51:11'),
(386, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 14:51:11'),
(387, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:28:32'),
(388, 1, 4, 'Remove', 'User has been removed', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 0, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:28:34'),
(389, 1, 4, 'Restored', 'User has been restored', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 1, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:28:39'),
(390, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:28:39'),
(391, 1, 12, 'Modified', 'Updated fields: first_name, last_name', '{\"email\": \"admin123@example.com\", \"last_name\": \"123\", \"department\": \"Office of the President\", \"first_name\": \"123\"}', '{\"email\": \"admin123@example.com\", \"last_name\": \"1233\", \"department\": \"Office of the President\", \"first_name\": \"1233\"}', 'User Management', 'Successful', '2025-03-18 15:29:06'),
(392, 1, 4, 'Remove', 'User has been removed', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 0, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:30:03'),
(393, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:30:03'),
(394, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:43:51'),
(395, 1, 4, 'Restored', 'User has been restored', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 1, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:43:53'),
(396, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:43:57'),
(397, 1, 4, 'Remove', 'User has been removed', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 0, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:43:59'),
(398, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:46:14'),
(399, 1, 4, 'Restored', 'User has been restored', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 1, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:46:16'),
(400, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:46:19'),
(401, 1, 4, 'Remove', 'User has been removed', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 0, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:46:21'),
(402, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:46:46'),
(403, 1, 4, 'Restored', 'User has been restored', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 1, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:46:48'),
(404, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 15:46:53'),
(405, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 16:56:53'),
(406, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 16:56:55'),
(407, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 16:57:00'),
(409, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-18 16:57:17'),
(410, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-19 08:34:18'),
(411, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-19 08:34:28'),
(412, 1, 4, 'Remove', 'User has been removed', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 0, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-19 08:39:10'),
(413, 1, 4, 'Restored', 'User has been restored', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 1, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-03-19 08:39:20'),
(414, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"1233\", \"first_name\": \"1233\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-19 08:39:20'),
(416, 1, 12, 'Modified', 'Updated fields: first_name, last_name', '{\"email\": \"admin123@example.com\", \"last_name\": \"1233\", \"department\": \"Office of the President\", \"first_name\": \"1233\"}', '{\"email\": \"admin123@example.com\", \"last_name\": \"321\", \"department\": \"Office of the President\", \"first_name\": \"321\"}', 'User Management', 'Successful', '2025-03-19 09:35:46'),
(417, 1, 12, 'Modified', 'Updated fields: first_name, last_name', '{\"email\": \"admin123@example.com\", \"last_name\": \"321\", \"department\": \"Office of the President\", \"first_name\": \"321\"}', '{\"email\": \"admin123@example.com\", \"last_name\": \"321123\", \"department\": \"Office of the President\", \"first_name\": \"321123\"}', 'User Management', 'Successful', '2025-03-19 09:36:47'),
(418, 1, 12, 'Modified', 'Updated fields: first_name, last_name', '{\"email\": \"admin123@example.com\", \"last_name\": \"321123\", \"department\": \"Office of the President\", \"first_name\": \"321123\"}', '{\"email\": \"admin123@example.com\", \"last_name\": \"321123123\", \"department\": \"Office of the President\", \"first_name\": \"321123123\"}', 'User Management', 'Successful', '2025-03-19 11:10:48'),
(419, 1, 12, 'Modified', 'Updated fields: first_name, last_name', '{\"email\": \"admin123@example.com\", \"last_name\": \"321123123\", \"department\": \"Office of the President\", \"first_name\": \"321123123\"}', '{\"email\": \"admin123@example.com\", \"last_name\": \"123\", \"department\": \"Office of the President\", \"first_name\": \"123\"}', 'User Management', 'Successful', '2025-03-19 11:11:24'),
(423, 1, 12, 'Modified', 'Updated fields: first_name, last_name', '{\"email\": \"admin123@example.com\", \"last_name\": \"123\", \"department\": \"Office of the President\", \"first_name\": \"123\"}', '{\"email\": \"admin123@example.com\", \"last_name\": \"123123\", \"department\": \"Office of the President\", \"first_name\": \"123123\"}', 'User Management', 'Successful', '2025-03-21 08:58:36'),
(428, 1, 12, 'Modified', 'No changes', '{\"email\": \"admin123@example.com\", \"last_name\": \"123123\", \"department\": \"Office of the President\", \"first_name\": \"123123\"}', '{\"email\": \"admin123@example.com\", \"last_name\": \"123123\", \"department\": \"Office of the President\", \"first_name\": \"123123\"}', 'User Management', 'Successful', '2025-03-21 09:24:05'),
(429, 1, 12, 'Modified', 'Updated fields: email, first_name, last_name', '{\"email\": \"admin123@example.com\", \"last_name\": \"123123\", \"department\": \"Office of the President\", \"first_name\": \"123123\"}', '{\"email\": \"admin1234@example.com\", \"last_name\": \"321\", \"department\": \"Office of the President\", \"first_name\": \"321\"}', 'User Management', 'Successful', '2025-03-21 09:24:45'),
(430, 1, 12, 'Modified', 'No changes', '{\"email\": \"admin1234@example.com\", \"last_name\": \"321\", \"department\": \"Office of the President\", \"first_name\": \"321\"}', '{\"email\": \"admin1234@example.com\", \"last_name\": \"321\", \"department\": \"Office of the President\", \"first_name\": \"321\"}', 'User Management', 'Successful', '2025-03-21 09:25:06'),
(431, 1, 12, 'Modified', 'No changes', '{\"email\": \"admin1234@example.com\", \"last_name\": \"321\", \"department\": \"Office of the President\", \"first_name\": \"321\"}', '{\"email\": \"admin1234@example.com\", \"last_name\": \"321\", \"department\": \"Office of the President\", \"first_name\": \"321\"}', 'User Management', 'Successful', '2025-03-21 09:25:57'),
(439, 1, 86, 'Create', 'New user added: 3321', NULL, '{\"id\": 86, \"email\": \"admin12345@example.com\", \"status\": \"Offline\", \"username\": \"3321\", \"last_name\": \"321\", \"department\": \"Unknown\", \"first_name\": \"321\", \"date_created\": \"2025-03-21 09:51:15.000000\"}', 'User Management', 'Successful', '2025-03-21 09:51:15'),
(440, 1, 86, 'Modified', 'No changes', '{\"email\": \"admin12345@example.com\", \"last_name\": \"321\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"321\"}', '{\"email\": \"admin12345@example.com\", \"last_name\": \"321\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"321\"}', 'User Management', 'Successful', '2025-03-21 09:58:24'),
(441, 1, 86, 'Modified', 'Updated fields: department, first_name, last_name', '{\"email\": \"admin12345@example.com\", \"last_name\": \"321\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"321\"}', '{\"email\": \"admin12345@example.com\", \"last_name\": \"321222\", \"department\": \"Center for Campus Ministry\", \"first_name\": \"321222\"}', 'User Management', 'Successful', '2025-03-21 09:58:34'),
(452, 1, 86, 'Modified', 'Attempted to change email from admin12345@example.com to an existing email: admin1234@example.com', '{\"email\":\"admin12345@example.com\"}', '{\"email\":\"admin1234@example.com\"}', 'User Management', 'Failed', '2025-03-21 10:39:26'),
(453, 1, 86, 'Create', 'Attempted to create user with existing email: admin12345@example.com', NULL, '{\"email\":\"admin12345@example.com\"}', 'User Management', 'Failed', '2025-03-21 10:39:35'),
(454, 1, 86, 'Modified', 'Attempted to change email from admin12345@example.com to an existing email: admin1234@example.com', '{\"email\":\"admin12345@example.com\"}', '{\"email\":\"admin1234@example.com\"}', 'User Management', 'Failed', '2025-03-21 10:41:59'),
(455, 1, 86, 'Modified', 'Attempted to change email from admin12345@example.com to an existing email: admin1234@example.com', '{\"email\":\"admin12345@example.com\"}', '{\"email\":\"admin1234@example.com\"}', 'User Management', 'Failed', '2025-03-21 10:53:52'),
(456, 1, 86, 'Modified', 'Attempted to change email from admin12345@example.com to an existing email: admin1234@example.com', '{\"email\":\"admin12345@example.com\"}', '{\"email\":\"admin1234@example.com\"}', 'User Management', 'Failed', '2025-03-21 10:55:39'),
(457, 1, 86, 'Remove', 'User has been removed', '{\"id\": 86, \"email\": \"admin12345@example.com\", \"status\": \"Offline\", \"username\": \"3321\", \"last_name\": \"321222\", \"first_name\": \"321222\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 09:51:15.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:25:50'),
(459, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin1234@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"321\", \"first_name\": \"321\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:31:04'),
(460, 1, 1, '', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-03-21 13:31:10'),
(461, 1, 1, '', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-03-21 13:31:14'),
(462, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin1234@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"321\", \"first_name\": \"321\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:44:18'),
(463, 1, 86, 'Restored', 'User has been restored', '{\"id\": 86, \"email\": \"admin12345@example.com\", \"status\": \"\", \"username\": \"3321\", \"last_name\": \"321222\", \"first_name\": \"321222\", \"is_disabled\": 1, \"date_created\": \"2025-03-21 09:51:15.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:44:18'),
(464, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin1234@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"321\", \"first_name\": \"321\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:44:29'),
(465, 1, 86, 'Remove', 'User has been removed', '{\"id\": 86, \"email\": \"admin12345@example.com\", \"status\": \"\", \"username\": \"3321\", \"last_name\": \"321222\", \"first_name\": \"321222\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 09:51:15.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:44:29'),
(466, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin1234@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"321\", \"first_name\": \"321\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:51:58'),
(467, 1, 86, 'Restored', 'User has been restored', '{\"id\": 86, \"email\": \"admin12345@example.com\", \"status\": \"\", \"username\": \"3321\", \"last_name\": \"321222\", \"first_name\": \"321222\", \"is_disabled\": 1, \"date_created\": \"2025-03-21 09:51:15.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:51:58'),
(468, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin1234@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"321\", \"first_name\": \"321\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:56:32'),
(469, 1, 86, 'Remove', 'User has been removed', '{\"id\": 86, \"email\": \"admin12345@example.com\", \"status\": \"\", \"username\": \"3321\", \"last_name\": \"321222\", \"first_name\": \"321222\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 09:51:15.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:56:32'),
(470, 1, 12, 'Restored', 'User has been restored', '{\"id\": 12, \"email\": \"admin1234@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"321\", \"first_name\": \"321\", \"is_disabled\": 1, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:56:39'),
(471, 1, 86, 'Restored', 'User has been restored', '{\"id\": 86, \"email\": \"admin12345@example.com\", \"status\": \"\", \"username\": \"3321\", \"last_name\": \"321222\", \"first_name\": \"321222\", \"is_disabled\": 1, \"date_created\": \"2025-03-21 09:51:15.000000\"}', '', 'User Management', 'Successful', '2025-03-21 13:56:39'),
(472, 1, 87, 'Create', 'New user added: tcase', NULL, '{\"id\": 87, \"email\": \"TestCase@gmail.com\", \"status\": \"Offline\", \"username\": \"tcase\", \"last_name\": \"Case\", \"department\": \"Unknown\", \"first_name\": \"Test\", \"date_created\": \"2025-03-21 14:09:13.000000\"}', 'User Management', 'Successful', '2025-03-21 14:09:13'),
(473, 1, 92, 'Create', 'New user added: 333333', NULL, '{\"id\": 92, \"email\": \"admin12344444@example.com\", \"status\": \"Offline\", \"username\": \"333333\", \"last_name\": \"33333\", \"department\": \"Unknown\", \"first_name\": \"3333\", \"date_created\": \"2025-03-21 14:12:37.000000\"}', 'User Management', 'Successful', '2025-03-21 14:12:37'),
(474, 1, 92, 'Remove', 'User has been removed', '{\"id\": 92, \"email\": \"admin12344444@example.com\", \"status\": \"Offline\", \"username\": \"333333\", \"last_name\": \"33333\", \"first_name\": \"3333\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 14:12:37.000000\"}', '', 'User Management', 'Successful', '2025-03-21 14:12:55'),
(475, 1, 87, 'Remove', 'User has been removed', '{\"id\": 87, \"email\": \"TestCase@gmail.com\", \"status\": \"Offline\", \"username\": \"tcase\", \"last_name\": \"Case\", \"first_name\": \"Test\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 14:09:13.000000\"}', '', 'User Management', 'Successful', '2025-03-21 14:12:57'),
(476, 1, 86, 'Remove', 'User has been removed', '{\"id\": 86, \"email\": \"admin12345@example.com\", \"status\": \"\", \"username\": \"3321\", \"last_name\": \"321222\", \"first_name\": \"321222\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 09:51:15.000000\"}', '', 'User Management', 'Successful', '2025-03-21 14:13:04'),
(477, 1, 12, 'Remove', 'User has been removed', '{\"id\": 12, \"email\": \"admin1234@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"321\", \"first_name\": \"321\", \"is_disabled\": 0, \"date_created\": \"2025-03-12 14:09:07.000000\"}', '', 'User Management', 'Successful', '2025-03-21 14:13:05'),
(478, 1, 93, 'Create', 'New user added: ttest', NULL, '{\"id\": 93, \"email\": \"testingCase123@gmail.com\", \"status\": \"Offline\", \"username\": \"ttest\", \"last_name\": \"test\", \"department\": \"Unknown\", \"first_name\": \"test\", \"date_created\": \"2025-03-21 14:14:16.000000\"}', 'User Management', 'Successful', '2025-03-21 14:14:16'),
(479, 1, 94, 'Create', 'New user added: 1123123', NULL, '{\"id\": 94, \"email\": \"superuser3123123@example.com\", \"status\": \"Offline\", \"username\": \"1123123\", \"last_name\": \"123123\", \"department\": \"Unknown\", \"first_name\": \"123123\", \"date_created\": \"2025-03-21 14:14:31.000000\"}', 'User Management', 'Successful', '2025-03-21 14:14:31'),
(480, 1, 95, 'Create', 'New user added: 3321', NULL, '{\"id\": 95, \"email\": \"testcase321@example.com\", \"status\": \"Offline\", \"username\": \"3321\", \"last_name\": \"321\", \"department\": \"Unknown\", \"first_name\": \"321\", \"date_created\": \"2025-03-21 14:17:55.000000\"}', 'User Management', 'Successful', '2025-03-21 14:17:55'),
(481, 1, 96, 'Create', 'New user added: 1123321', NULL, '{\"id\": 96, \"email\": \"testtest@example.com\", \"status\": \"Offline\", \"username\": \"1123321\", \"last_name\": \"123321\", \"department\": \"Unknown\", \"first_name\": \"123321\", \"date_created\": \"2025-03-21 14:23:15.000000\"}', 'User Management', 'Successful', '2025-03-21 14:23:15'),
(482, 1, 96, 'Remove', 'User has been removed', '{\"id\": 96, \"email\": \"testtest@example.com\", \"status\": \"Offline\", \"username\": \"1123321\", \"last_name\": \"123321\", \"first_name\": \"123321\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 14:23:15.000000\"}', '', 'User Management', 'Successful', '2025-03-21 14:23:20'),
(483, 1, 95, 'Modified', 'Updated fields: email, first_name, last_name', '{\"email\": \"testcase321@example.com\", \"last_name\": \"321\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"321\"}', '{\"email\": \"testcase3213@example.com\", \"last_name\": \"3213\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"3213\"}', 'User Management', 'Successful', '2025-03-21 14:23:25'),
(484, 1, 95, 'Modified', 'Updated fields: email, first_name, last_name', '{\"email\": \"testcase3213@example.com\", \"last_name\": \"3213\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"3213\"}', '{\"email\": \"testcase32131@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', 'User Management', 'Successful', '2025-03-21 14:23:32'),
(485, 1, 95, 'Modified', 'Updated fields: email', '{\"email\": \"testcase32131@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', '{\"email\": \"testcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', 'User Management', 'Successful', '2025-03-21 14:23:41'),
(486, 1, 95, 'Modified', 'Updated fields: email', '{\"email\": \"testcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', '{\"email\": \"testingcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', 'User Management', 'Successful', '2025-03-21 14:23:53'),
(488, 1, 95, 'Modified', 'No changes', '{\"email\": \"testingcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', '{\"email\": \"testingcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', 'User Management', 'Successful', '2025-03-21 14:24:21'),
(489, 1, 95, 'Modified', 'No changes', '{\"email\": \"testingcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', '{\"email\": \"testingcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', 'User Management', 'Successful', '2025-03-21 14:24:50'),
(490, 1, 95, 'Modified', 'No changes', '{\"email\": \"testingcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', '{\"email\": \"testingcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', 'User Management', 'Successful', '2025-03-21 14:27:18'),
(491, 1, 95, 'Modified', 'No changes', '{\"email\": \"testingcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', '{\"email\": \"testingcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', 'User Management', 'Successful', '2025-03-21 14:29:52'),
(492, 1, 95, 'Modified', 'Attempted to change email from testingcase123@example.com to an existing email: testingcase123@gmail.com', '{\"email\":\"testingcase123@example.com\"}', '{\"email\":\"testingcase123@gmail.com\"}', 'User Management', 'Failed', '2025-03-21 14:30:12'),
(493, 1, 95, 'Modified', 'No changes', '{\"email\": \"testingcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', '{\"email\": \"testingcase123@example.com\", \"last_name\": \"32131\", \"department\": \"Office of the Internal Auditor\", \"first_name\": \"32131\"}', 'User Management', 'Successful', '2025-03-21 14:30:22'),
(500, 1, 95, 'Create', 'Attempted to create user with existing email: testingcase123@example.com', NULL, '{\"email\":\"testingcase123@example.com\"}', 'User Management', 'Failed', '2025-03-21 14:47:03'),
(503, 1, 95, 'Create', 'Attempted to create user with existing email: testingcase123@example.com', NULL, '{\"email\":\"testingcase123@example.com\"}', 'User Management', 'Failed', '2025-03-21 15:03:47'),
(504, 1, 95, 'Create', 'Attempted to create user with existing email: testingcase123@example.com', NULL, '{\"email\":\"testingcase123@example.com\"}', 'User Management', 'Failed', '2025-03-21 15:04:26'),
(505, 1, 97, 'Create', 'Attempted to create user with existing email: testingcase1233@example.com', NULL, '{\"email\":\"testingcase1233@example.com\"}', 'User Management', 'Failed', '2025-03-21 15:05:33'),
(508, 1, 96, 'Restored', 'User has been restored', '{\"id\": 96, \"email\": \"testtest@example.com\", \"status\": \"\", \"username\": \"1123321\", \"last_name\": \"123321\", \"first_name\": \"123321\", \"is_disabled\": 1, \"date_created\": \"2025-03-21 14:23:15.000000\"}', '', 'User Management', 'Successful', '2025-03-21 15:10:29'),
(509, 1, 97, 'Remove', 'User has been removed', '{\"id\": 97, \"email\": \"testingcase1233@example.com\", \"status\": \"Offline\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 14:47:10.000000\"}', '', 'User Management', 'Successful', '2025-03-21 15:10:40'),
(510, 1, 101, 'Remove', 'User has been removed', '{\"id\": 101, \"email\": \"testingcase87654@example.com\", \"status\": \"Offline\", \"username\": \"1213456u\", \"last_name\": \"213456u\", \"first_name\": \"123456\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 15:05:45.000000\"}', '', 'User Management', 'Successful', '2025-03-21 15:10:40'),
(511, 1, 96, 'Create', 'Attempted to create user with existing email: testtest@example.com', NULL, '{\"email\":\"testtest@example.com\"}', 'User Management', 'Failed', '2025-03-21 15:10:52'),
(512, 1, 96, 'Create', 'Attempted to create user with existing email: testtest@example.com', NULL, '{\"email\":\"testtest@example.com\"}', 'User Management', 'Failed', '2025-03-21 15:11:14'),
(513, 1, 103, 'Create', 'New user added: 1123321333', NULL, '{\"id\": 103, \"email\": \"testtesttest@example.com\", \"status\": \"Offline\", \"username\": \"1123321333\", \"last_name\": \"123321333\", \"department\": \"Unknown\", \"first_name\": \"123321333\", \"date_created\": \"2025-03-21 15:11:33.000000\"}', 'User Management', 'Successful', '2025-03-21 15:11:33'),
(514, 1, 103, 'Create', 'Attempted to create user with existing email: testtesttest@example.com', NULL, '{\"email\":\"testtesttest@example.com\"}', 'User Management', 'Failed', '2025-03-21 15:11:44'),
(515, 1, 103, 'Modified', 'No changes', '{\"email\": \"testtesttest@example.com\", \"last_name\": \"123321333\", \"department\": \"Office of the Executive Assistant to the President\", \"first_name\": \"123321333\"}', '{\"email\": \"testtesttest@example.com\", \"last_name\": \"123321333\", \"department\": \"Office of the Executive Assistant to the President\", \"first_name\": \"123321333\"}', 'User Management', 'Successful', '2025-03-21 15:13:52'),
(516, 1, 103, 'Create', 'Attempted to create user with existing email: testtesttest@example.com', NULL, '{\"email\":\"testtesttest@example.com\"}', 'User Management', 'Failed', '2025-03-21 15:14:05'),
(517, 1, 104, 'Create', 'New user added: jjjjj', NULL, '{\"id\": 104, \"email\": \"testtesttest123@example.com\", \"status\": \"Offline\", \"username\": \"jjjjj\", \"last_name\": \"jjjj\", \"department\": \"Unknown\", \"first_name\": \"jjjj\", \"date_created\": \"2025-03-21 15:14:11.000000\"}', 'User Management', 'Successful', '2025-03-21 15:14:11'),
(518, 1, 104, 'Modified', 'Attempted to change email from testtesttest123@example.com to an existing email: testtesttest@example.com', '{\"email\":\"testtesttest123@example.com\"}', '{\"email\":\"testtesttest@example.com\"}', 'User Management', 'Failed', '2025-03-21 15:14:27'),
(519, 1, 104, 'Modified', 'Updated fields: email, first_name, last_name', '{\"email\": \"testtesttest123@example.com\", \"last_name\": \"jjjj\", \"department\": \"Office of the President\", \"first_name\": \"jjjj\"}', '{\"email\": \"testtesttest1111@example.com\", \"last_name\": \"jjjj11\", \"department\": \"Office of the President\", \"first_name\": \"jjjj11\"}', 'User Management', 'Successful', '2025-03-21 15:14:37'),
(520, 1, 96, 'Remove', 'User has been removed', '{\"id\": 96, \"email\": \"testtest@example.com\", \"status\": \"\", \"username\": \"1123321\", \"last_name\": \"123321\", \"first_name\": \"123321\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 14:23:15.000000\"}', '', 'User Management', 'Successful', '2025-03-21 15:14:46'),
(521, 1, 103, 'Remove', 'User has been removed', '{\"id\": 103, \"email\": \"testtesttest@example.com\", \"status\": \"Offline\", \"username\": \"1123321333\", \"last_name\": \"123321333\", \"first_name\": \"123321333\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 15:11:33.000000\"}', '', 'User Management', 'Successful', '2025-03-21 15:14:46'),
(522, 1, 104, 'Remove', 'User has been removed', '{\"id\": 104, \"email\": \"testtesttest1111@example.com\", \"status\": \"Offline\", \"username\": \"jjjjj\", \"last_name\": \"jjjj11\", \"first_name\": \"jjjj11\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 15:14:11.000000\"}', '', 'User Management', 'Successful', '2025-03-21 15:14:46'),
(523, 1, 95, 'Remove', 'User has been removed', '{\"id\": 95, \"email\": \"testingcase123@example.com\", \"status\": \"Offline\", \"username\": \"3321\", \"last_name\": \"32131\", \"first_name\": \"32131\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 14:17:55.000000\"}', '', 'User Management', 'Successful', '2025-03-21 15:14:48'),
(524, 1, 94, 'Remove', 'User has been removed', '{\"id\": 94, \"email\": \"superuser3123123@example.com\", \"status\": \"Offline\", \"username\": \"1123123\", \"last_name\": \"123123\", \"first_name\": \"123123\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 14:14:31.000000\"}', '', 'User Management', 'Successful', '2025-03-21 15:28:17'),
(525, 1, 96, 'Delete', 'User permanently deleted: testtest@example.com', '{\"id\": 96, \"email\": \"testtest@example.com\", \"username\": \"1123321\", \"is_disabled\": 1}', NULL, 'User Management', 'Successful', '2025-03-21 15:32:49'),
(526, 1, 95, 'Delete', 'User permanently deleted: testingcase123@example.com', '{\"id\": 95, \"email\": \"testingcase123@example.com\", \"username\": \"3321\", \"is_disabled\": 1}', NULL, 'User Management', 'Successful', '2025-03-21 15:33:20'),
(527, 1, 94, 'Delete', 'User permanently deleted: superuser3123123@example.com', '{\"id\": 94, \"email\": \"superuser3123123@example.com\", \"username\": \"1123123\", \"is_disabled\": 1}', NULL, 'User Management', 'Successful', '2025-03-21 15:38:42'),
(528, 1, 93, 'Remove', 'User has been removed', '{\"id\": 93, \"email\": \"testingCase123@gmail.com\", \"status\": \"Offline\", \"username\": \"ttest\", \"last_name\": \"test\", \"first_name\": \"test\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 14:14:16.000000\"}', '', 'User Management', 'Successful', '2025-03-21 15:43:07'),
(529, 1, 93, 'Delete', 'User permanently deleted: testingCase123@gmail.com', '{\"id\": 93, \"email\": \"testingCase123@gmail.com\", \"username\": \"ttest\", \"is_disabled\": 1}', NULL, 'User Management', 'Successful', '2025-03-21 16:26:50'),
(530, 1, 105, 'Create', 'New user added: ttest', NULL, '{\"id\": 105, \"email\": \"admintest@example.com\", \"status\": \"Offline\", \"username\": \"ttest\", \"last_name\": \"test\", \"department\": \"Unknown\", \"first_name\": \"test\", \"date_created\": \"2025-03-21 16:27:19.000000\"}', 'User Management', 'Successful', '2025-03-21 16:27:19'),
(531, 1, 105, 'Remove', 'User has been removed', '{\"id\": 105, \"email\": \"admintest@example.com\", \"status\": \"Offline\", \"username\": \"ttest\", \"last_name\": \"test\", \"first_name\": \"test\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 16:27:19.000000\"}', '', 'User Management', 'Successful', '2025-03-21 16:27:22'),
(532, 1, 105, 'Delete', 'User permanently deleted: admintest@example.com', '{\"id\": 105, \"email\": \"admintest@example.com\", \"username\": \"ttest\", \"is_disabled\": 1}', NULL, 'User Management', 'Successful', '2025-03-21 16:27:34'),
(533, 1, 106, 'Create', 'New user added: ttest1', NULL, '{\"id\": 106, \"email\": \"test1@example.com\", \"status\": \"Offline\", \"username\": \"ttest1\", \"last_name\": \"test1\", \"department\": \"Unknown\", \"first_name\": \"test1\", \"date_created\": \"2025-03-21 16:30:58.000000\"}', 'User Management', 'Successful', '2025-03-21 16:30:58'),
(534, 1, 107, 'Create', 'New user added: ttest2', NULL, '{\"id\": 107, \"email\": \"test2@example.com\", \"status\": \"Offline\", \"username\": \"ttest2\", \"last_name\": \"test2\", \"department\": \"Unknown\", \"first_name\": \"test2\", \"date_created\": \"2025-03-21 16:31:14.000000\"}', 'User Management', 'Successful', '2025-03-21 16:31:14'),
(535, 1, 106, 'Remove', 'User has been removed', '{\"id\": 106, \"email\": \"test1@example.com\", \"status\": \"Offline\", \"username\": \"ttest1\", \"last_name\": \"test1\", \"first_name\": \"test1\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 16:30:58.000000\"}', '', 'User Management', 'Successful', '2025-03-21 16:31:21'),
(536, 1, 107, 'Remove', 'User has been removed', '{\"id\": 107, \"email\": \"test2@example.com\", \"status\": \"Offline\", \"username\": \"ttest2\", \"last_name\": \"test2\", \"first_name\": \"test2\", \"is_disabled\": 0, \"date_created\": \"2025-03-21 16:31:14.000000\"}', '', 'User Management', 'Successful', '2025-03-21 16:31:21'),
(537, 1, 4, 'Remove', 'User has been removed', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 0, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-05-09 11:15:29'),
(538, 1, 1, 'Logout', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-05-09 14:28:25'),
(539, 1, 1, 'Login', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-05-09 14:28:28'),
(540, 1, 1, 'Modified', 'Modified Fields: Audit: Removed Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Equipment Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Equipment Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-09 14:28:34');
INSERT INTO `audit_log` (`TrackID`, `UserID`, `EntityID`, `Action`, `Details`, `OldVal`, `NewVal`, `Module`, `Status`, `Date_Time`) VALUES
(541, 1, 1, 'Modified', 'Modified Fields: Audit: Added View', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Equipment Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"View\"\n        ],\n        \"Equipment Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-09 14:28:39'),
(542, 1, 32, 'Create', 'Role \'testsetes\' has been created', NULL, '{\"id\":32,\"role_name\":\"testsetes\",\"is_disabled\":0}', 'Roles and Privileges', 'Successful', '2025-05-09 15:23:21'),
(543, 1, 46, 'Create', 'Department \'57877\' has been Created', NULL, '{\"id\":\"46\",\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', 'Department Management', 'Successful', '2025-05-09 15:23:30'),
(544, 1, 1, 'Logout', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-05-13 17:55:39'),
(545, 1, 1, 'Login', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-05-13 17:55:41'),
(546, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 20:28:51'),
(547, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 20:29:04'),
(548, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 20:29:13'),
(549, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 20:40:25'),
(550, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 20:41:03'),
(551, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 20:52:00'),
(552, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 20:54:14'),
(553, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 20:56:52'),
(554, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 20:57:03'),
(555, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 20:58:08'),
(556, 1, 134, 'Create', 'New user added: 1123', NULL, '{\"id\": 134, \"email\": \"Testertesting123@example.com\", \"status\": \"Offline\", \"username\": \"1123\", \"last_name\": \"123\", \"department\": \"Unknown\", \"first_name\": \"123\", \"date_created\": \"2025-05-13 21:28:15.000000\"}', 'User Management', 'Successful', '2025-05-13 21:28:15'),
(557, 1, 134, 'Remove', 'User has been removed', '{\"id\": 134, \"email\": \"Testertesting123@example.com\", \"status\": \"Offline\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-05-13 21:28:15.000000\"}', '', 'User Management', 'Successful', '2025-05-13 21:48:51'),
(558, 1, 134, 'Restored', 'User has been restored', '{\"id\": 134, \"email\": \"Testertesting123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 1, \"date_created\": \"2025-05-13 21:28:15.000000\"}', '', 'User Management', 'Successful', '2025-05-13 22:09:13'),
(559, 1, 4, 'Restored', 'User has been restored', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 1, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-05-13 22:09:15'),
(560, 1, 134, 'Remove', 'User has been removed', '{\"id\": 134, \"email\": \"Testertesting123@example.com\", \"status\": \"\", \"username\": \"1123\", \"last_name\": \"123\", \"first_name\": \"123\", \"is_disabled\": 0, \"date_created\": \"2025-05-13 21:28:15.000000\"}', '', 'User Management', 'Successful', '2025-05-13 22:09:24'),
(561, 1, 4, 'Remove', 'User has been removed', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 0, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-05-13 22:14:15'),
(562, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 22:14:19'),
(563, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 22:14:24'),
(564, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 22:14:32'),
(565, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:02:40'),
(566, 1, 135, 'Create', 'New user added: ttest', NULL, '{\"id\": 135, \"email\": \"tester1233321@gmail.com\", \"status\": \"Offline\", \"username\": \"ttest\", \"last_name\": \"test\", \"department\": \"Unknown\", \"first_name\": \"test\", \"date_created\": \"2025-05-15 09:03:25.000000\"}', 'User Management', 'Successful', '2025-05-15 09:03:25'),
(567, 1, 135, 'modified', 'Updated user information: tester1233321@gmail.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:07:41'),
(568, 1, 135, 'modified', 'Updated user information: tester1233321@gmail.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:11:11'),
(569, 1, 135, 'modified', 'Updated user information: tester1233321@gmail.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:11:20'),
(570, 1, 135, 'modified', 'Updated user information: tester1233321@gmail.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:14:53'),
(571, 1, 135, 'modified', 'Updated user information: tester1233321@gmail.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:15:01'),
(572, 1, 135, 'modified', 'Updated user information: tester1233321@gmail.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:19:22'),
(573, 1, 135, 'modified', 'Updated user information: tester1233321@gmail.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:19:34'),
(574, 1, 135, 'modified', 'Updated user information: tester1233321@gmail.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:19:47'),
(575, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:20:07'),
(576, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:30:10'),
(577, 1, 135, 'modified', 'Updated user information: tester1233321@gmail.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:30:19'),
(578, 1, 46, 'Remove', 'Department \'57877\' has been moved to archive', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', 'Department Management', 'Successful', '2025-05-15 09:36:45'),
(579, 1, 46, 'Restored', 'Department \'57877\' has been restored', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', 'Department Management', 'Successful', '2025-05-15 09:48:04'),
(580, 1, 4, 'Restored', 'User has been restored', '{\"id\": 4, \"email\": \"rp@example.com\", \"status\": \"\", \"username\": \"rpman\", \"last_name\": \"manager\", \"first_name\": \"ropriv\", \"is_disabled\": 1, \"date_created\": \"2025-02-19 13:41:59.000000\"}', '', 'User Management', 'Successful', '2025-05-15 09:48:30'),
(581, 1, 4, 'modified', 'Updated user information: rp@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 09:48:40'),
(582, 1, 46, 'Remove', 'Department \'57877\' has been moved to archive', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', 'Department Management', 'Successful', '2025-05-15 09:48:54'),
(583, 1, 46, 'Restored', 'Department \'57877\' has been restored', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', 'Department Management', 'Successful', '2025-05-15 09:55:48'),
(584, 1, 46, 'Remove', 'Department \'57877\' has been moved to archive', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', 'Department Management', 'Successful', '2025-05-15 09:56:14'),
(585, 1, 46, 'Restored', 'Department \'57877\' has been restored', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', 'Department Management', 'Successful', '2025-05-15 09:56:25'),
(586, 1, 32, 'Remove', 'Role \'testsetes\' has been archived', '{\n    \"role_id\": 32,\n    \"role_name\": \"testsetes\",\n    \"modules_and_privileges\": []\n}', '{\"id\":32,\"role_name\":\"testsetes\",\"is_disabled\":1}', 'Roles and Privileges', 'Successful', '2025-05-15 10:17:35'),
(587, 1, 2, 'modified', 'Updated user information: um@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 10:27:53'),
(588, 1, 2, 'modified', 'Updated user information: um@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 10:28:20'),
(589, 1, 3, 'Remove', 'Role \'Equipment Manager\' has been archived', '{\n    \"role_id\": 3,\n    \"role_name\": \"Equipment Manager\",\n    \"modules_and_privileges\": []\n}', '{\"id\":3,\"role_name\":\"Equipment Manager\",\"is_disabled\":1}', 'Roles and Privileges', 'Successful', '2025-05-15 10:30:36'),
(590, 1, 3, 'Restore', 'Role \'Equipment Manager\' has been restored', '{\"role_id\":3,\"role_name\":\"Equipment Manager\",\"modules_and_privileges\":[]}', '{\"role_id\":3,\"role_name\":\"Equipment Manager\",\"modules_and_privileges\":[]}', 'Roles and Privileges', 'Successful', '2025-05-15 10:30:50'),
(591, 1, 2, 'modified', 'Updated user information: um@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 10:54:10'),
(592, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-15 10:55:05'),
(593, 1, 1, 'Modified', 'Modified Fields: Equipment Transaction: Added Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"View\"\n        ],\n        \"Equipment Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"View\"\n        ],\n        \"Equipment Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"Equipment Transaction\": [\n            \"Track\"\n        ],\n        \"Roles and Privileges\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-16 11:52:02'),
(594, 1, 1, 'Modified', 'Modified Fields: Equipment Transaction: Added Create, Remove, View', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"View\"\n        ],\n        \"Equipment Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"Equipment Transaction\": [\n            \"Track\"\n        ],\n        \"Roles and Privileges\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"View\"\n        ],\n        \"Equipment Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"Equipment Transaction\": [\n            \"Create\",\n            \"Remove\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-16 11:52:55'),
(595, 1, NULL, 'add', NULL, NULL, '{\"po_no\":\"PO6787654\",\"date_of_order\":\"2025-05-18\",\"no_of_units\":\"12\",\"item_specifications\":\"34\"}', 'Purchase Order', NULL, '2025-05-16 11:53:07'),
(596, 1, NULL, 'add', NULL, NULL, '{\"po_no\":\"PO2345654\",\"date_of_order\":\"2025-05-21\",\"no_of_units\":\"123123\",\"item_specifications\":\"123\"}', 'Purchase Order', NULL, '2025-05-16 13:03:33'),
(597, 1, 1, 'Modified', 'Modified Fields: Equipment Transaction: Added Modify', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"View\"\n        ],\n        \"Equipment Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"Equipment Transaction\": [\n            \"Create\",\n            \"Remove\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"View\"\n        ],\n        \"Equipment Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"Equipment Transaction\": [\n            \"Create\",\n            \"Modify\",\n            \"Remove\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Add\",\n            \"Approve\",\n            \"Assign\",\n            \"Create\",\n            \"Delete\",\n            \"Modify\",\n            \"Reject\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"Undo\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-16 13:05:55'),
(598, 1, 20, 'modified', NULL, '{\"id\":20,\"po_no\":\"PO2345654\",\"date_of_order\":\"2025-05-21\",\"no_of_units\":123123,\"item_specifications\":\"123\",\"date_created\":\"2025-05-16 13:03:33\",\"is_disabled\":0}', '{\"po_no\":\"PO23456542\",\"date_of_order\":\"2025-05-21\",\"no_of_units\":\"123123\",\"item_specifications\":\"123\"}', 'Purchase Order', NULL, '2025-05-16 14:00:47'),
(599, 1, NULL, 'Create', NULL, NULL, '{\"po_no\":\"PO345123\",\"date_of_order\":\"2025-05-22\",\"no_of_units\":\"1\",\"item_specifications\":\"331\"}', 'Purchase Order', NULL, '2025-05-16 14:01:19'),
(600, 1, 21, 'delete', NULL, '{\"id\":21,\"po_no\":\"PO345123\",\"date_of_order\":\"2025-05-22\",\"no_of_units\":1,\"item_specifications\":\"331\",\"date_created\":\"2025-05-16 14:01:19\",\"is_disabled\":0}', NULL, 'Purchase Order', NULL, '2025-05-16 14:01:26'),
(601, 1, 20, 'modified', NULL, '{\"id\":20,\"po_no\":\"PO23456542\",\"date_of_order\":\"2025-05-21\",\"no_of_units\":123123,\"item_specifications\":\"123\",\"date_created\":\"2025-05-16 13:03:33\",\"is_disabled\":0}', '{\"po_no\":\"PO234565422\",\"date_of_order\":\"2025-05-21\",\"no_of_units\":\"123123\",\"item_specifications\":\"123\"}', 'Purchase Order', NULL, '2025-05-16 14:39:13'),
(602, 1, 20, 'Modified', 'Purchase Order PO22222331 updated', '{\"id\":20,\"po_no\":\"PO234565422\",\"date_of_order\":\"2025-05-21\",\"no_of_units\":123123,\"item_specifications\":\"123\",\"date_created\":\"2025-05-16 13:03:33\",\"is_disabled\":0}', '{\"po_no\":\"PO22222331\",\"date_of_order\":\"2025-05-21\",\"no_of_units\":\"123123\",\"item_specifications\":\"123\"}', 'Purchase Order', 'Successful', '2025-05-16 14:44:47'),
(603, 1, 20, 'Modified', 'The PO No was changed from \'PO22222331\' to \'PO3333312\'. The No of Units was changed from \'123123\' to \'123123\'.', '{\"po_no\":\"PO22222331\",\"no_of_units\":123123}', '{\"po_no\":\"PO3333312\",\"no_of_units\":\"123123\"}', 'Purchase Order', 'Successful', '2025-05-16 14:52:28'),
(604, 1, 20, 'Modified', 'The No of Units was changed from \'123123\' to \'33333\'. The Item Specifications was changed from \'123\' to \'33333\'.', '{\"no_of_units\":123123,\"item_specifications\":\"123\"}', '{\"no_of_units\":\"33333\",\"item_specifications\":\"33333\"}', 'Purchase Order', 'Successful', '2025-05-16 14:52:33'),
(605, 1, 19, 'Modified', 'The PO No was changed from \'PO6787654\' to \'PO67876543\'.', '{\"po_no\":\"PO6787654\"}', '{\"po_no\":\"PO67876543\"}', 'Purchase Order', 'Successful', '2025-05-16 14:56:52'),
(606, 1, 19, 'Modified', 'The Item Specifications was changed from \'34\' to \'341\'.', '{\"item_specifications\":\"34\"}', '{\"item_specifications\":\"341\"}', 'Purchase Order', 'Successful', '2025-05-16 14:56:59'),
(607, 1, 18, 'Delete', 'Purchase Order 312312312312 deleted', '{\"id\":18,\"po_no\":\"312312312312\",\"date_of_order\":\"2025-03-18\",\"no_of_units\":131223,\"item_specifications\":\"12213123\",\"date_created\":\"2025-03-18 09:13:58\",\"is_disabled\":0}', NULL, 'Purchase Order', 'Successful', '2025-05-16 14:57:11'),
(608, 1, 17, 'Delete', 'Purchase Order 123412311 deleted', '{\"id\":17,\"po_no\":\"123412311\",\"date_of_order\":\"2025-04-02\",\"no_of_units\":333,\"item_specifications\":\"333\",\"date_created\":\"2025-03-17 10:20:59\",\"is_disabled\":0}', NULL, 'Purchase Order', 'Successful', '2025-05-16 14:57:12'),
(609, 1, 20, 'Remove', 'Purchase Order PO3333312 removed', '{\"id\":20,\"po_no\":\"PO3333312\",\"date_of_order\":\"2025-05-21\",\"no_of_units\":33333,\"item_specifications\":\"33333\",\"date_created\":\"2025-05-16 13:03:33\",\"is_disabled\":0}', NULL, 'Purchase Order', 'Successful', '2025-05-16 15:07:09'),
(610, 1, 19, 'Remove', 'Purchase Order PO67876543 removed', '{\"id\":19,\"po_no\":\"PO67876543\",\"date_of_order\":\"2025-05-18\",\"no_of_units\":12,\"item_specifications\":\"341\",\"date_created\":\"2025-05-16 11:53:07\",\"is_disabled\":0}', NULL, 'Purchase Order', 'Successful', '2025-05-16 15:15:13'),
(611, 1, 1, 'Modified', 'Modified Fields: ', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Audit\": [\n            \"View\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"View\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-16 15:46:15'),
(612, 1, 13, 'Create', NULL, NULL, '{\"invoice_no\":\"CI21346\",\"date_of_purchase\":\"2025-05-23\",\"po_no\":null}', 'Charge Invoice', NULL, '2025-05-16 16:18:36'),
(613, 1, 14, 'Create', NULL, NULL, '{\"invoice_no\":\"CI23213123\",\"date_of_purchase\":\"2025-05-18\",\"po_no\":null}', 'Charge Invoice', NULL, '2025-05-16 16:20:25'),
(614, 1, 1, 'Login', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-05-16 16:27:36'),
(615, 1, 19, 'Create', 'Purchase Order PO23123123 created', NULL, '{\"po_no\":\"PO23123123\",\"date_of_order\":\"2025-05-16\",\"no_of_units\":\"23\",\"item_specifications\":\"1212\"}', 'Purchase Order', 'Successful', '2025-05-16 16:33:22'),
(616, 1, 15, 'Create', NULL, NULL, '{\"invoice_no\":\"CI33316123\",\"date_of_purchase\":\"2025-05-16\",\"po_no\":\"312312312312\"}', 'Charge Invoice', NULL, '2025-05-16 16:33:44'),
(617, 1, 13, 'Delete', NULL, '{\"id\":13,\"invoice_no\":\"CI21346\",\"date_of_purchase\":\"2025-05-23\",\"po_no\":null,\"date_created\":\"2025-05-16 16:18:36\",\"is_disabled\":0}', NULL, 'Charge Invoice', NULL, '2025-05-16 16:36:39'),
(618, 1, 16, 'Create', 'Charge Invoice CI98765432 created', NULL, '{\"invoice_no\":\"CI98765432\",\"date_of_purchase\":\"2025-05-16\",\"po_no\":null}', 'Charge Invoice', 'Successful', '2025-05-16 16:47:52'),
(619, 1, 17, 'Create', 'Charge Invoice CI098765423456 created', NULL, '{\"invoice_no\":\"CI098765423456\",\"date_of_purchase\":\"2025-05-17\",\"po_no\":\"123412311\"}', 'Charge Invoice', 'Successful', '2025-05-16 16:48:06'),
(620, 1, 17, 'Delete', 'Charge Invoice CI098765423456 deleted', '{\"id\":17,\"invoice_no\":\"CI098765423456\",\"date_of_purchase\":\"2025-05-17\",\"po_no\":\"123412311\",\"date_created\":\"2025-05-16 16:48:06\",\"is_disabled\":0}', NULL, 'Charge Invoice', 'Successful', '2025-05-16 16:48:11'),
(621, 1, 15, 'Modified', 'Charge Invoice CI333161232 updated', '{\"id\":15,\"invoice_no\":\"CI33316123\",\"date_of_purchase\":\"2025-05-16\",\"po_no\":\"312312312312\",\"date_created\":\"2025-05-16 16:33:44\",\"is_disabled\":0}', '{\"invoice_no\":\"CI333161232\",\"date_of_purchase\":\"2025-05-16\",\"po_no\":\"312312312312\"}', 'Charge Invoice', 'Successful', '2025-05-16 16:58:21'),
(622, 1, 1, 'Logout', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-05-16 23:28:20'),
(623, 1, 1, 'Login', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-05-16 23:28:22'),
(624, 1, 1, 'Logout', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-05-17 11:43:40'),
(625, 1, 1, 'Login', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-05-17 11:43:41'),
(626, 1, 46, 'Modified', 'Department \'5777\' details modified', '{\"id\":46,\"abbreviation\":\"9876\",\"department_name\":\"57877\"}', '{\"id\":\"46\",\"abbreviation\":\"9876\",\"department_name\":\"5777\"}', 'Department Management', 'Successful', '2025-05-17 12:42:03'),
(627, 1, 18, 'Create', 'Charge Invoice CI123123123123123 created', NULL, '{\"invoice_no\":\"CI123123123123123\",\"date_of_purchase\":\"2025-05-21\",\"po_no\":\"312312312312\"}', 'Charge Invoice', 'Successful', '2025-05-17 13:33:25'),
(628, 1, 19, 'Create', 'Charge Invoice CI123123123 created', NULL, '{\"invoice_no\":\"CI123123123\",\"date_of_purchase\":\"2025-05-07\",\"po_no\":null}', 'Charge Invoice', 'Successful', '2025-05-17 13:55:02'),
(629, 1, NULL, 'add', NULL, NULL, '{\"rr_no\":\"RR989898\",\"accountable_individual\":\"Steve\",\"po_no\":\"PO23123123\",\"ai_loc\":\"Silang\",\"date_created\":\"2025-05-17 06:02:00\"}', 'Receiving Report', NULL, '2025-05-17 14:02:55'),
(630, 1, 20, 'Create', 'Purchase Order PO9928922 created', NULL, '{\"po_no\":\"PO9928922\",\"date_of_order\":\"2025-05-18\",\"no_of_units\":\"12\",\"item_specifications\":\"AMD\"}', 'Purchase Order', 'Successful', '2025-05-17 14:03:16'),
(631, 1, 17, 'Remove', 'Purchase Order 123412311 removed', '{\"id\":17,\"po_no\":\"123412311\",\"date_of_order\":\"2025-04-02\",\"no_of_units\":333,\"item_specifications\":\"333\",\"date_created\":\"2025-03-17 10:20:59\",\"is_disabled\":0}', NULL, 'Purchase Order', 'Successful', '2025-05-17 14:03:19'),
(632, 1, 18, 'Remove', 'Purchase Order 312312312312 removed', '{\"id\":18,\"po_no\":\"312312312312\",\"date_of_order\":\"2025-03-18\",\"no_of_units\":131223,\"item_specifications\":\"12213123\",\"date_created\":\"2025-03-18 09:13:58\",\"is_disabled\":0}', NULL, 'Purchase Order', 'Successful', '2025-05-17 14:03:20'),
(633, 1, 20, 'Create', 'Charge Invoice CI3123123 created', NULL, '{\"invoice_no\":\"CI3123123\",\"date_of_purchase\":\"2025-05-01\",\"po_no\":\"PO23123123\"}', 'Charge Invoice', 'Successful', '2025-05-17 14:10:04'),
(634, 1, 20, 'Modified', 'Charge Invoice CI3123123 updated', '{\"id\":20,\"invoice_no\":\"CI3123123\",\"date_of_purchase\":\"2025-05-01\",\"po_no\":\"PO23123123\",\"date_created\":\"2025-05-17 14:10:04\",\"is_disabled\":0}', '{\"invoice_no\":\"CI3123123\",\"date_of_purchase\":\"2025-05-01\",\"po_no\":\"PO9928922\"}', 'Charge Invoice', 'Successful', '2025-05-17 14:28:42'),
(635, 1, 22, 'Create', 'Charge Invoice CI213123123 created', NULL, '{\"invoice_no\":\"CI213123123\",\"date_of_purchase\":\"2025-05-17\",\"po_no\":\"PO9928922\"}', 'Charge Invoice', 'Successful', '2025-05-17 14:28:53'),
(636, 1, 21, 'Create', 'Purchase Order PO0000978 created', NULL, '{\"po_no\":\"PO0000978\",\"date_of_order\":\"2025-05-18\",\"no_of_units\":\"2\",\"item_specifications\":\"TRA\"}', 'Purchase Order', 'Successful', '2025-05-17 22:59:41'),
(637, 1, 23, 'Create', 'Charge Invoice CI8886734 created', NULL, '{\"invoice_no\":\"CI8886734\",\"date_of_purchase\":\"2025-05-18\",\"po_no\":\"PO0000978\"}', 'Charge Invoice', 'Successful', '2025-05-17 22:59:59'),
(638, 1, NULL, 'add', NULL, NULL, '{\"rr_no\":\"RR3478123\",\"accountable_individual\":\"N\\/A\",\"po_no\":\"PO9928922\",\"ai_loc\":\"over there\",\"date_created\":\"2025-05-18 04:08:00\"}', 'Receiving Report', NULL, '2025-05-18 12:08:58'),
(639, 1, NULL, 'modified', NULL, '{\"id\":18,\"rr_no\":\"RR3478123\",\"accountable_individual\":\"N\\/A\",\"ai_loc\":\"over there\",\"po_no\":\"PO9928922\",\"date_created\":\"2025-05-18 04:08:00\",\"is_disabled\":0}', '{\"rr_no\":\"RR3478123\",\"accountable_individual\":\"N\\/A\",\"po_no\":\"PO23123123\",\"ai_loc\":\"over there\",\"date_created\":\"2025-05-18 04:08:00\"}', 'Receiving Report', NULL, '2025-05-18 12:09:03'),
(640, 1, 1, 'Logout', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-05-18 13:05:12'),
(641, 1, 1, 'Login', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-05-18 13:05:14'),
(642, 1, 1, 'Logout', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-05-18 16:13:28'),
(643, 1, 1, 'Login', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-05-18 16:13:31'),
(644, 1, 1, 'Modified', 'Modified Fields: Audit: Added View', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"View\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 16:49:11'),
(645, 1, 1, 'Modified', 'Modified Fields: Audit: Removed View', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Audit\": [\n            \"View\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 16:53:12'),
(646, 1, 1, 'Modified', 'Modified Fields: Audit: Removed Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Audit\": [\n            \"Track\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 17:04:28'),
(647, 1, 2, 'Modified', 'Modified Fields: Audit: Added Track', '{\n    \"role_id\": 2,\n    \"role_name\": \"Super Admin\",\n    \"privileges\": {\n        \"User Management\": [\n            \"Restore\",\n            \"View\"\n        ]\n    }\n}', '{\n    \"role_id\": 2,\n    \"role_name\": \"Super Admin\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"User Management\": [\n            \"Restore\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 17:11:20'),
(648, 1, 2, 'Modified', 'Modified Fields: Audit: Removed Track', '{\n    \"role_id\": 2,\n    \"role_name\": \"Super Admin\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"User Management\": [\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 2,\n    \"role_name\": \"Super Admin\",\n    \"privileges\": {\n        \"User Management\": [\n            \"Restore\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 17:11:23'),
(649, 1, 1, 'Modified', 'Modified Fields: Audit: Added Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 17:11:28');
INSERT INTO `audit_log` (`TrackID`, `UserID`, `EntityID`, `Action`, `Details`, `OldVal`, `NewVal`, `Module`, `Status`, `Date_Time`) VALUES
(650, 1, 1, 'Modified', 'Modified Fields: ', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 17:11:32'),
(651, 1, 1, 'Modified', 'Modified Fields: Audit: Removed Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 17:13:13'),
(652, 1, 1, 'Modified', 'Modified Fields: Audit: Added Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 17:13:17'),
(653, 1, 1, 'Modified', 'Modified Fields: Audit: Removed Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 17:13:25'),
(654, 1, 1, 'Modified', 'Modified Fields: Audit: Added Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 17:13:32'),
(655, 1, 1, 'Modified', 'Modified Fields: Roles and Privileges: Removed Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 17:13:45'),
(656, 1, 1, 'Modified', 'Modified Fields: Roles and Privileges: Added Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 17:13:49'),
(657, 1, 1, 'Modified', 'Modified Fields: Audit: Removed Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 18:03:23'),
(658, 1, 1, 'Modified', 'Modified Fields: Audit: Added Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 18:03:32'),
(659, 1, 1, 'Modified', 'Modified Fields: Audit: Removed Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 18:03:36'),
(660, 1, 1, 'Modified', 'Modified Fields: Audit: Added Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 18:03:45'),
(661, 1, 1, 'Modified', 'Modified Fields: Audit: Removed Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 18:03:49'),
(662, 1, 1, 'Modified', 'Modified Fields: Audit: Added Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 18:03:54'),
(663, 1, 1, 'Modified', 'Modified Fields: Audit: Removed Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 18:04:08'),
(664, 1, 1, 'Modified', 'Modified Fields: Audit: Added Track', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Roles and Privileges\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"User Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Equipment Transactions\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Reports\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ],\n        \"Management\": [\n            \"Track\",\n            \"Create\",\n            \"Remove\",\n            \"Permanently Delete\",\n            \"Modify\",\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 1,\n    \"role_name\": \"TMDD-Dev\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Roles and Privileges\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 18:04:12'),
(665, 1, 2, 'Modified', 'Modified Fields: Audit: Added Track', '{\n    \"role_id\": 2,\n    \"role_name\": \"Super Admin\",\n    \"privileges\": {\n        \"User Management\": [\n            \"View\",\n            \"Restore\"\n        ]\n    }\n}', '{\n    \"role_id\": 2,\n    \"role_name\": \"Super Admin\",\n    \"privileges\": {\n        \"Audit\": [\n            \"Track\"\n        ],\n        \"User Management\": [\n            \"Restore\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-18 18:04:15'),
(666, 1, 3, 'Modified', 'Modified Fields: Equipment Management: Added Create, Modify, Permanently Delete, Remove, Restore, Track, View, Equipment Transactions: Added Create, Modify, Permanently Delete, Remove, Restore, Track, View', '{\n    \"role_id\": 3,\n    \"role_name\": \"Equipment Manager\",\n    \"privileges\": []\n}', '{\n    \"role_id\": 3,\n    \"role_name\": \"Equipment Manager\",\n    \"privileges\": {\n        \"Equipment Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Equipment Transactions\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-19 08:34:57'),
(667, 3, 12, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"SLU000004087\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop CPU\",\"specifications\":\"OS: Windows 11 Pro\\r\\nCPU: Intel Core i5-13400\\r\\nGPU: Inter UHD Graphics 730\\r\\nDisplay: refer to SLU000004088\\r\\nRam: 16GB DDR4\\r\\nStorage: 256GB SSD 1TB\\r\\nPSU: Integrated\\r\\nOffice: Office Home and Student 2021 (4,350.00)\\r\\nAccessories: 1. USB KBM-wired | Acer\\r\\n                    2. Webcam | Genius (1,250.00)\\r\\n\\r\\n\",\"brand\":\"Acer\",\"model\":\"Veriton X4710G\",\"serial_number\":\"DTVYGSP01B40700C1E3000\",\"location\":\"HRD-Extension-Job Analysis\",\"accountable_individual\":\"Alyana Mikaela E. De Guzman\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 08:45:06\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 08:45:06'),
(668, 3, 13, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"SLU000004088\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop Monitor\",\"specifications\":\"22\\\"\",\"brand\":\"Acer\",\"model\":\"V226HQL\",\"serial_number\":\"MMTYBSP001409006D13S11\",\"location\":\"HRD-Extension-Job Analysis\",\"accountable_individual\":\"Alyana Mikaela E. De Guzman\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 08:49:06\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 08:49:06');
INSERT INTO `audit_log` (`TrackID`, `UserID`, `EntityID`, `Action`, `Details`, `OldVal`, `NewVal`, `Module`, `Status`, `Date_Time`) VALUES
(669, 3, 14, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"SLU000004089\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop CPU\",\"specifications\":\"OS: Windows 11 Pro \\r\\nCPU: Intel Core i5-13400 \\r\\nGPU: Inter UHD Graphics 730 \\r\\nDisplay: refer to SLU000004088 \\r\\nRam: 16GB DDR4 \\r\\nStorage: 256GB SSD 1TB \\r\\nPSU: Integrated \\r\\nOffice: Office Home and Student 2021 (4,350.00) \\r\\nAccessories: 1. USB KBM-wired | Acer \\r\\n                    2. Webcam | Genius (1,250.00)\",\"brand\":\"\",\"model\":\"\",\"serial_number\":\"\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 08:49:28\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 08:49:28'),
(670, 3, 14, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"SLU000004089\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop CPU\",\"specifications\":\"OS: Windows 11 Pro \\r\\nCPU: Intel Core i5-13400 \\r\\nGPU: Inter UHD Graphics 730 \\r\\nDisplay: refer to SLU000004088 \\r\\nRam: 16GB DDR4 \\r\\nStorage: 256GB SSD 1TB \\r\\nPSU: Integrated \\r\\nOffice: Office Home and Student 2021 (4,350.00) \\r\\nAccessories: 1. USB KBM-wired | Acer \\r\\n                    2. Webcam | Genius (1,250.00)\",\"brand\":\"\",\"model\":\"\",\"serial_number\":\"\",\"date_acquired\":\"2025-05-19 08:49:28\",\"invoice_no\":null,\"rr_no\":null,\"location\":\"\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 08:49:28\"}', '{\"asset_tag\":\"SLU000004089\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop CPU\",\"specifications\":\"OS: Windows 11 Pro \\r\\nCPU: Intel Core i5-13400 \\r\\nGPU: Inter UHD Graphics 730 \\r\\nDisplay: refer to SLU000004090\\r\\nRam: 16GB DDR4 \\r\\nStorage: 256GB SSD 1TB \\r\\nPSU: Integrated \\r\\nOffice: Office Home and Student 2021 (4,350.00) \\r\\nAccessories: 1. USB KBM-wired | Acer \\r\\n                    2. Webcam | Genius (1,250.00)\",\"brand\":\"\",\"model\":\"\",\"serial_number\":\"\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"N\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 08:50:10'),
(671, 3, 12, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"SLU000004087\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop CPU\",\"specifications\":\"OS: Windows 11 Pro\\r\\nCPU: Intel Core i5-13400\\r\\nGPU: Inter UHD Graphics 730\\r\\nDisplay: refer to SLU000004088\\r\\nRam: 16GB DDR4\\r\\nStorage: 256GB SSD 1TB\\r\\nPSU: Integrated\\r\\nOffice: Office Home and Student 2021 (4,350.00)\\r\\nAccessories: 1. USB KBM-wired | Acer\\r\\n                    2. Webcam | Genius (1,250.00)\\r\\n\\r\\n\",\"brand\":\"Acer\",\"model\":\"Veriton X4710G\",\"serial_number\":\"DTVYGSP01B40700C1E3000\",\"date_acquired\":\"2025-05-19 08:45:06\",\"invoice_no\":null,\"rr_no\":null,\"location\":\"HRD-Extension-Job Analysis\",\"accountable_individual\":\"Alyana Mikaela E. De Guzman\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 08:45:06\"}', '{\"asset_tag\":\"SLU000004087\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop CPU\",\"specifications\":\"OS: Windows 11 Pro\\r\\nCPU: Intel Core i5-13400\\r\\nMB: Veriton X4710G\\r\\nGPU: Inter UHD Graphics 730\\r\\nDisplay: refer to SLU000004088\\r\\nRam: 16GB DDR4\\r\\nStorage: 256GB SSD 1TB\\r\\nPSU: Integrated\\r\\nOffice: Office Home and Student 2021 (4,350.00)\\r\\nAccessories: 1. USB KBM-wired | Acer\\r\\n                    2. Webcam | Genius (1,250.00)\\r\\n\\r\\n\",\"brand\":\"Acer\",\"model\":\"Veriton X4710G\",\"serial_number\":\"DTVYGSP01B40700C1E3000\",\"location\":\"HRD-Extension-Job Analysis\",\"accountable_individual\":\"Alyana Mikaela E. De Guzman\",\"rr_no\":\"N\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 08:50:56'),
(672, 3, 14, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"SLU000004089\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop CPU\",\"specifications\":\"OS: Windows 11 Pro \\r\\nCPU: Intel Core i5-13400 \\r\\nGPU: Inter UHD Graphics 730 \\r\\nDisplay: refer to SLU000004090\\r\\nRam: 16GB DDR4 \\r\\nStorage: 256GB SSD 1TB \\r\\nPSU: Integrated \\r\\nOffice: Office Home and Student 2021 (4,350.00) \\r\\nAccessories: 1. USB KBM-wired | Acer \\r\\n                    2. Webcam | Genius (1,250.00)\",\"brand\":\"\",\"model\":\"\",\"serial_number\":\"\",\"date_acquired\":\"2025-05-19 08:49:28\",\"invoice_no\":null,\"rr_no\":\"RRN\\/A\",\"location\":\"\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 08:50:10\"}', '{\"asset_tag\":\"SLU000004089\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop CPU\",\"specifications\":\"OS: Windows 11 Pro \\r\\nCPU: Intel Core i5-13400\\r\\nMB: Veriton X4710G \\r\\nGPU: Inter UHD Graphics 730 \\r\\nDisplay: refer to SLU000004090\\r\\nRam: 16GB DDR4 \\r\\nStorage: 256GB SSD 1TB \\r\\nPSU: Integrated \\r\\nOffice: Office Home and Student 2021 (4,350.00) \\r\\nAccessories: 1. USB KBM-wired | Acer \\r\\n                    2. Webcam | Genius (1,250.00)\",\"brand\":\"Acer\",\"model\":\"V226HQL\",\"serial_number\":\"DTVYGSP01B40700C3E3000\",\"location\":\"HRD-Extension-Job Analysis\",\"accountable_individual\":\"Alyana Mikaela E. De Guzman\",\"rr_no\":\"\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 08:52:59'),
(673, 3, 15, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"SLU000004090\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop Monitor\",\"specifications\":\"22\\\"\",\"brand\":\"Acer\",\"model\":\"M\",\"serial_number\":\"\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 08:53:42\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 08:53:42'),
(674, 3, 15, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"SLU000004090\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop Monitor\",\"specifications\":\"22\\\"\",\"brand\":\"Acer\",\"model\":\"M\",\"serial_number\":\"\",\"date_acquired\":\"2025-05-19 08:53:42\",\"invoice_no\":null,\"rr_no\":null,\"location\":\"\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 08:53:42\"}', '{\"asset_tag\":\"SLU000004090\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop Monitor\",\"specifications\":\"22\\\"\",\"brand\":\"Acer\",\"model\":\"V226HQL\",\"serial_number\":\"MMTYBSP001409006D33S11\",\"location\":\"HRD-Extension-Job Analysis\",\"accountable_individual\":\"Aira Rhayne Salazar\",\"rr_no\":\"\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 08:56:25'),
(675, 3, 14, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"SLU000004089\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop CPU\",\"specifications\":\"OS: Windows 11 Pro \\r\\nCPU: Intel Core i5-13400\\r\\nMB: Veriton X4710G \\r\\nGPU: Inter UHD Graphics 730 \\r\\nDisplay: refer to SLU000004090\\r\\nRam: 16GB DDR4 \\r\\nStorage: 256GB SSD 1TB \\r\\nPSU: Integrated \\r\\nOffice: Office Home and Student 2021 (4,350.00) \\r\\nAccessories: 1. USB KBM-wired | Acer \\r\\n                    2. Webcam | Genius (1,250.00)\",\"brand\":\"Acer\",\"model\":\"V226HQL\",\"serial_number\":\"DTVYGSP01B40700C3E3000\",\"date_acquired\":\"2025-05-19 08:49:28\",\"invoice_no\":null,\"rr_no\":null,\"location\":\"HRD-Extension-Job Analysis\",\"accountable_individual\":\"Alyana Mikaela E. De Guzman\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 08:52:59\"}', '{\"asset_tag\":\"SLU000004089\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop CPU\",\"specifications\":\"OS: Windows 11 Pro \\r\\nCPU: Intel Core i5-13400\\r\\nMB: Veriton X4710G \\r\\nGPU: Inter UHD Graphics 730 \\r\\nDisplay: refer to SLU000004090\\r\\nRam: 16GB DDR4 \\r\\nStorage: 256GB SSD 1TB \\r\\nPSU: Integrated \\r\\nOffice: Office Home and Student 2021 (4,350.00) \\r\\nAccessories: 1. USB KBM-wired | Acer \\r\\n                    2. Webcam | Genius (1,250.00)\",\"brand\":\"Acer\",\"model\":\"V226HQL\",\"serial_number\":\"DTVYGSP01B40700C3E3000\",\"location\":\"HRD-Extension-Job Analysis\",\"accountable_individual\":\"Aira Rhayne Salazar\",\"rr_no\":\"N\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 08:56:49'),
(676, 3, 16, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"SLU000004091\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop CPU\",\"specifications\":\"OS: Windows 11 Pro \\r\\nCPU: Intel Core i5-13400\\r\\nMB: Veriton X4710G \\r\\nGPU: Inter UHD Graphics 730 \\r\\nDisplay: refer to SLU000004092\\r\\nRam: 16GB DDR4 \\r\\nStorage: 256GB SSD 1TB \\r\\nPSU: Integrated \\r\\nOffice: Office Home and Student 2021 (4,350.00) \\r\\nAccessories: 1. USB KBM-wired | Acer \\r\\n                    2. Webcam | Genius (1,250.00)\",\"brand\":\"Acer\",\"model\":\"Veritom X4710G\",\"serial_number\":\"DTVYGSP01B40700C263000\",\"location\":\"HRD Office - Cubicle 1\",\"accountable_individual\":\"Nissah T. Lipao\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 08:59:40\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 08:59:40'),
(677, 3, 17, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"SLU000004092\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop Monitor\",\"specifications\":\"22\\\"\",\"brand\":\"Acer\",\"model\":\"V226HQL\",\"serial_number\":\"MMTYBSP001409006E03S11\",\"location\":\"HRD Office - Cubicle 1\",\"accountable_individual\":\"Nissah T. Lipao\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 09:00:54\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 09:00:54'),
(678, 3, 18, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"SLU000004093\",\"asset_description_1\":\"UPS\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"ACP\",\"model\":\"BVX6501-PH\",\"serial_number\":\"9B2430A19187\",\"location\":\"HRD-Extension-Job Analysis\",\"accountable_individual\":\"Alyana Mikaela E. De Guzman\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 09:02:28\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 09:02:28'),
(679, 3, 22, 'Create', 'Purchase Order PO1025000664 created', NULL, '{\"po_no\":\"PO1025000664\",\"date_of_order\":\"2024-11-12\",\"no_of_units\":\"3\",\"item_specifications\":\"Desktop Computer: Acer Veriton X47\"}', 'Purchase Order', 'Successful', '2025-05-19 09:29:56'),
(680, 3, 22, 'Modified', 'The Item Specifications was changed from \'Desktop Computer: Acer Veriton X47\' to \'SET\'.', '{\"item_specifications\":\"Desktop Computer: Acer Veriton X47\"}', '{\"item_specifications\":\"SET\"}', 'Purchase Order', 'Successful', '2025-05-19 09:30:11'),
(681, 3, 24, 'Create', 'Charge Invoice CI20247 created', NULL, '{\"invoice_no\":\"CI20247\",\"date_of_purchase\":\"2024-11-19\",\"po_no\":\"PO1025000664\"}', 'Charge Invoice', 'Successful', '2025-05-19 09:31:23'),
(682, 3, NULL, 'add', NULL, NULL, '{\"rr_no\":\"RR007571\",\"accountable_individual\":\"Jeremy Lee Dela Cruz\",\"po_no\":\"PO1025000664\",\"ai_loc\":\"HRD\",\"date_created\":\"2025-05-19 03:32:00\"}', 'Receiving Report', NULL, '2025-05-19 09:33:33'),
(683, 3, NULL, 'modified', NULL, '{\"id\":19,\"rr_no\":\"RR007571\",\"accountable_individual\":\"Jeremy Lee Dela Cruz\",\"ai_loc\":\"HRD\",\"po_no\":\"PO1025000664\",\"date_created\":\"2025-05-19 03:32:00\",\"is_disabled\":0}', '{\"rr_no\":\"RR007571\",\"accountable_individual\":\"Jeremy Lee Dela Cruz\",\"po_no\":\"PO1025000664\",\"ai_loc\":\"HRD\",\"date_created\":\"2024-11-19 03:32:00\"}', 'Receiving Report', NULL, '2025-05-19 09:34:03'),
(684, 1, 1, 'Logout', 'navithebear is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-05-19 10:49:06'),
(685, 1, 1, 'Login', 'navithebear is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-05-19 11:03:20'),
(686, 1, 136, 'Create', 'New user added: pbalanza', NULL, '{\"id\": 136, \"username\": \"pbalanza\", \"email\": \"psalmer@example.com\", \"first_name\": \"psalmer\", \"last_name\": \"balanza\", \"department\": \"Unknown\", \"status\": \"Offline\", \"date_created\": \"2025-05-19 13:24:39\"}', 'User Management', 'Successful', '2025-05-19 13:24:39'),
(687, 1, 137, 'Create', 'New user added: pbalanza', NULL, '{\"id\": 137, \"username\": \"pbalanza\", \"email\": \"psalmer@example.com\", \"first_name\": \"psalmer\", \"last_name\": \"balanza\", \"department\": \"Unknown\", \"status\": \"Offline\", \"date_created\": \"2025-05-19 13:24:45\"}', 'User Management', 'Successful', '2025-05-19 13:24:45'),
(688, 1, 138, 'Create', 'New user added: pbalanza', NULL, '{\"id\": 138, \"username\": \"pbalanza\", \"email\": \"psalmer@example.com\", \"first_name\": \"psalmer\", \"last_name\": \"balanza\", \"department\": \"Unknown\", \"status\": \"Offline\", \"date_created\": \"2025-05-19 13:24:51\"}', 'User Management', 'Successful', '2025-05-19 13:24:51'),
(689, 1, 139, 'Create', 'New user added: pbalanza', NULL, '{\"id\": 139, \"username\": \"pbalanza\", \"email\": \"psalmer@example.com\", \"first_name\": \"psalmer\", \"last_name\": \"balanza\", \"department\": \"Unknown\", \"status\": \"Offline\", \"date_created\": \"2025-05-19 13:24:59\"}', 'User Management', 'Successful', '2025-05-19 13:24:59'),
(690, 1, 140, 'Create', 'New user added: pbalanza', NULL, '{\"id\": 140, \"username\": \"pbalanza\", \"email\": \"psalmer@example.com\", \"first_name\": \"psalmer\", \"last_name\": \"balanza\", \"department\": \"Unknown\", \"status\": \"Offline\", \"date_created\": \"2025-05-19 13:26:48\"}', 'User Management', 'Successful', '2025-05-19 13:26:48'),
(691, 1, 25, 'Create', 'Charge Invoice CI12123456 created', NULL, '{\"invoice_no\":\"CI12123456\",\"date_of_purchase\":\"2024-12-12\",\"po_no\":\"PO0000978\"}', 'Charge Invoice', 'Successful', '2025-05-19 13:44:27'),
(692, 1, 19, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"56988456\",\"asset_description_1\":\"Computer\",\"asset_description_2\":\"Desktop\",\"specifications\":\"14 inch\",\"brand\":\"Asus\",\"model\":\"Zen\",\"serial_number\":\"090909\",\"location\":\"Diego Silang\",\"accountable_individual\":\"Justine Lucas\",\"rr_no\":\"89078\",\"date_created\":\"2025-05-19 13:47:03\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 13:47:03'),
(693, 1, 1, 'Add', 'New equipment status added', NULL, '{\"asset_tag\":\"90909890\",\"status\":\"For Disposal\",\"action\":\"Collection\",\"remarks\":\"\",\"is_disabled\":0}', 'Equipment Status', 'Successful', '0000-00-00 00:00:00'),
(694, 3, 20, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001244-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859AC1759\",\"serial_number\":\"\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:32:13\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:32:13'),
(695, 3, 20, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001244-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859AC1759\",\"serial_number\":\"\",\"date_acquired\":\"2025-05-19 14:32:13\",\"invoice_no\":null,\"rr_no\":null,\"location\":\"\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:32:13\"}', '{\"asset_tag\":\"00001244-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400398\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"N\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:33:24'),
(696, 3, 21, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001245-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400391\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:34:58\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:34:58'),
(697, 3, 20, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001244-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400398\",\"date_acquired\":\"2025-05-19 14:32:13\",\"invoice_no\":null,\"rr_no\":\"RRN\\/A\",\"location\":\"\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:33:24\"}', '{\"asset_tag\":\"00001244-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400398\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"RRN\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:35:10'),
(698, 3, 22, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001246-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400396\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:36:06\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:36:06'),
(699, 3, 20, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001244-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400398\",\"date_acquired\":\"2025-05-19 14:32:13\",\"invoice_no\":null,\"rr_no\":\"RRN\\/A\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:35:10\"}', '{\"asset_tag\":\"00001244-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400398\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:36:30'),
(700, 3, 23, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001247-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400397\",\"location\":\"Rizal\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:37:09\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:37:09'),
(701, 3, 24, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001248-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400392\",\"location\":\"Die\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:38:52\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:38:52'),
(702, 3, 24, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001248-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400392\",\"date_acquired\":\"2025-05-19 14:38:52\",\"invoice_no\":null,\"rr_no\":null,\"location\":\"Die\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:38:52\"}', '{\"asset_tag\":\"00001248-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400392\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"N\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:39:01'),
(703, 3, 25, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001249-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000047\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:40:13\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:40:13'),
(704, 3, 24, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001248-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400392\",\"date_acquired\":\"2025-05-19 14:38:52\",\"invoice_no\":null,\"rr_no\":\"RRN\\/A\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:39:01\"}', '{\"asset_tag\":\"00001248-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000392\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"RRN\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:40:22'),
(705, 3, 23, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001247-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400397\",\"date_acquired\":\"2025-05-19 14:37:09\",\"invoice_no\":null,\"rr_no\":null,\"location\":\"Rizal\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:37:09\"}', '{\"asset_tag\":\"00001247-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000397\",\"location\":\"Rizal\",\"accountable_individual\":\"\",\"rr_no\":\"N\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:40:29'),
(706, 3, 20, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001244-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400398\",\"date_acquired\":\"2025-05-19 14:32:13\",\"invoice_no\":null,\"rr_no\":null,\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:36:30\"}', '{\"asset_tag\":\"00001244-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000398\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"N\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:40:35'),
(707, 3, 21, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001245-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400391\",\"date_acquired\":\"2025-05-19 14:34:58\",\"invoice_no\":null,\"rr_no\":null,\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:34:58\"}', '{\"asset_tag\":\"00001245-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000391\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"N\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:40:41'),
(708, 3, 22, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001246-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM31400396\",\"date_acquired\":\"2025-05-19 14:36:06\",\"invoice_no\":null,\"rr_no\":null,\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:36:06\"}', '{\"asset_tag\":\"00001246-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000396\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"N\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:40:47'),
(709, 3, 26, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001250-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000399\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:42:01\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:42:01'),
(710, 3, 27, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001251-TMD\",\"asset_description_1\":\"Switch\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Linksys\",\"model\":\"LGS108 (8 Port)\",\"serial_number\":\"13KK20F16A03756\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:43:29\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:43:29'),
(711, 3, 28, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001252-TMD\",\"asset_description_1\":\"Switch\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Linksys\",\"model\":\"LGS108 (8 Port)\",\"serial_number\":\"13KK20F16A03698\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:44:22\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:44:22'),
(712, 3, 29, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001253-TMD\",\"asset_description_1\":\"Switch\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Linksys\",\"model\":\"LGS108 (8 Port)\",\"serial_number\":\"13KK20F16A03741\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:45:43\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:45:43'),
(713, 3, 30, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001254-TMD\",\"asset_description_1\":\"Switch\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Linksys\",\"model\":\"LGS108 (8 Port)\",\"serial_number\":\"13KK20F16A03759\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:46:20\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:46:20'),
(714, 3, 31, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001255-TMD\",\"asset_description_1\":\"Switch\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Linksys\",\"model\":\"LGS108 (8 Port)\",\"serial_number\":\"13KK20F16A03734\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:47:39\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:47:39'),
(715, 3, 32, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001256-TMD\",\"asset_description_1\":\"Switch\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Linksys\",\"model\":\"LGS108 (8 Port)\",\"serial_number\":\"13KK20F16A03761\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:48:56\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:48:56'),
(716, 3, 33, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001257-TMD\",\"asset_description_1\":\"Switch\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"CISCO\",\"model\":\"SG95-24 (24 Port)\",\"serial_number\":\"DNI240209XU\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:50:39\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:50:39'),
(717, 3, 34, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001871-TMD\",\"asset_description_1\":\"Antenna - UHD RFID\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Chafon\",\"model\":\"CF-RA8080 \\/ Time-in 1\",\"serial_number\":\"\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:57:20\",\"remarks\":\"for university employees\"}', 'Equipment Details', 'Successful', '2025-05-19 14:57:20'),
(718, 3, 34, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001871-TMD\",\"asset_description_1\":\"Antenna - UHD RFID\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Chafon\",\"model\":\"CF-RA8080 \\/ Time-in 1\",\"serial_number\":\"\",\"date_acquired\":\"2025-05-19 14:57:20\",\"invoice_no\":null,\"rr_no\":null,\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"remarks\":\"for university employees\",\"date_modified\":\"2025-05-19 14:57:20\"}', '{\"asset_tag\":\"00001871-TMD\",\"asset_description_1\":\"Antenna - UHD RFID\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Chafon\",\"model\":\"CF-RA8080 \\/ Time-in 1\",\"serial_number\":\"\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"N\\/A\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:58:14'),
(719, 3, 34, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001871-TMD\",\"asset_description_1\":\"Antenna - UHD RFID\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Chafon\",\"model\":\"CF-RA8080 \\/ Time-in 1\",\"serial_number\":\"\",\"date_acquired\":\"2025-05-19 14:57:20\",\"invoice_no\":null,\"rr_no\":\"RRN\\/A\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:58:14\"}', '{\"asset_tag\":\"00001871-TMD\",\"asset_description_1\":\"Antenna - UHD RFID\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Chafon\",\"model\":\"CF-RA8080 \\/ Time-in 1\",\"serial_number\":\"\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:58:22'),
(720, 3, 24, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001248-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000392\",\"date_acquired\":\"2025-05-19 14:38:52\",\"invoice_no\":null,\"rr_no\":\"RRN\\/A\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:40:22\"}', '{\"asset_tag\":\"00001248-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000392\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:58:33'),
(721, 3, 23, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001247-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000397\",\"date_acquired\":\"2025-05-19 14:37:09\",\"invoice_no\":null,\"rr_no\":\"RRN\\/A\",\"location\":\"Rizal\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:40:29\"}', '{\"asset_tag\":\"00001247-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000397\",\"location\":\"Rizal\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:58:40'),
(722, 3, 22, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001246-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000396\",\"date_acquired\":\"2025-05-19 14:36:06\",\"invoice_no\":null,\"rr_no\":\"RRN\\/A\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:40:47\"}', '{\"asset_tag\":\"00001246-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000396\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:58:53'),
(723, 3, 21, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001245-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000391\",\"date_acquired\":\"2025-05-19 14:34:58\",\"invoice_no\":null,\"rr_no\":\"RRN\\/A\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:40:41\"}', '{\"asset_tag\":\"00001245-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000391\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:59:01'),
(724, 3, 20, 'Modified', 'Equipment details modified', '{\"asset_tag\":\"00001244-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000398\",\"date_acquired\":\"2025-05-19 14:32:13\",\"invoice_no\":null,\"rr_no\":\"RRN\\/A\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"remarks\":\"\",\"date_modified\":\"2025-05-19 14:40:35\"}', '{\"asset_tag\":\"00001244-TMD\",\"asset_description_1\":\"Access Point - Wireless\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"D-Link\",\"model\":\"DIR- 859 AC1750\",\"serial_number\":\"RZOM314000398\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:59:07'),
(725, 3, 35, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001872-TMD\",\"asset_description_1\":\"Antenna - UHD RFID\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Chafon\",\"model\":\"CF-RA8080 \\/ Time-in 2\",\"serial_number\":\"\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 14:59:58\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 14:59:58'),
(726, 3, 36, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001873-TMD\",\"asset_description_1\":\"Antenna - UHD RFID\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Chafon\",\"model\":\"CF-RA8080 \\/ Time-out 1\",\"serial_number\":\"\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 15:00:45\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 15:00:45'),
(727, 3, 37, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00001874-TMD\",\"asset_description_1\":\"Antenna - UHD RFID\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"Chafon\",\"model\":\"CF-RA8080 \\/ Time-out 2\",\"serial_number\":\"\",\"location\":\"Diego Silang\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 15:01:35\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 15:01:35'),
(728, 3, 38, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00016351-TMD\",\"asset_description_1\":\"UPS\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"APC\",\"model\":\"BVX650I-PH\",\"serial_number\":\"9B2224A06504\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 15:05:39\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 15:05:39'),
(729, 3, 39, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00016352-TMD\",\"asset_description_1\":\"UPS\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"APC\",\"model\":\"BVX650I-PH\",\"serial_number\":\"9B2222A18229\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 15:07:18\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 15:07:18'),
(730, 3, 40, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00016353-TMD\",\"asset_description_1\":\"UPS\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"APC\",\"model\":\"BVX650I-PH\",\"serial_number\":\"9B2238A30857\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 15:08:17\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 15:08:17'),
(731, 3, 7, 'Modified', 'Equipment location modified', '{\"equipment_location_id\":7,\"asset_tag\":\"00001247-TMD\",\"building_loc\":\"Diego Silang\",\"floor_no\":\"1\",\"specific_area\":\"Tuklas Lunas\",\"person_responsible\":\"Network Administrators\",\"department_id\":null,\"remarks\":\"Borrowed by Tuklas Lunas\",\"date_created\":\"2025-05-19 15:10:31\",\"is_disabled\":0}', '{\"asset_tag\":\"00001247-TMD\",\"building_loc\":\"Rizal\",\"floor_no\":\"1\",\"specific_area\":\"Tuklas Lunas\",\"person_responsible\":\"Network Administrators\",\"department_id\":null,\"remarks\":\"Borrowed by Tuklas Lunas\"}', 'Equipment Location', 'Successful', '0000-00-00 00:00:00'),
(732, 3, 21, 'Modified', 'Equipment location modified', '{\"equipment_location_id\":21,\"asset_tag\":\"00001872-TMD\",\"building_loc\":\"Diego Silang\",\"floor_no\":\"2\",\"specific_area\":\"Silang\",\"person_responsible\":\"TMDD Team\",\"department_id\":null,\"remarks\":\"\",\"date_created\":\"2025-05-19 15:25:33\",\"is_disabled\":0}', '{\"asset_tag\":\"00001872-TMD\",\"building_loc\":\"Diego Silang\",\"floor_no\":\"2\",\"specific_area\":\"Silang\",\"person_responsible\":\"TMDD Team\",\"department_id\":null,\"remarks\":\"for university employees\"}', 'Equipment Location', 'Successful', '0000-00-00 00:00:00'),
(733, 3, 3, 'Logout', 'equipman is now offline.', '{\"status\": \"Online\"}', '{\"status\": \"Offline\"}', 'User Management', 'Successful', '2025-05-19 15:33:01'),
(734, 1, 4, 'Modified', 'Modified Fields: Reports: Removed View', '{\n    \"role_id\": 4,\n    \"role_name\": \"User Manager\",\n    \"privileges\": {\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ],\n        \"Reports\": [\n            \"View\"\n        ],\n        \"Management\": [\n            \"View\"\n        ]\n    }\n}', '{\n    \"role_id\": 4,\n    \"role_name\": \"User Manager\",\n    \"privileges\": {\n        \"Management\": [\n            \"View\"\n        ],\n        \"User Management\": [\n            \"Create\",\n            \"Modify\",\n            \"Permanently Delete\",\n            \"Remove\",\n            \"Restore\",\n            \"Track\",\n            \"View\"\n        ]\n    }\n}', 'Roles and Privileges', 'Successful', '2025-05-19 15:33:25'),
(735, 3, 3, 'Login', 'equipman is now online.', '{\"status\": \"Offline\"}', '{\"status\": \"Online\"}', 'User Management', 'Successful', '2025-05-19 15:35:40'),
(736, 1, 23, 'Create', 'Purchase Order PO347823947 created', NULL, '{\"po_no\":\"PO347823947\",\"date_of_order\":\"274387-03-31\",\"no_of_units\":\"2\",\"item_specifications\":\"testing123\"}', 'Purchase Order', 'Successful', '2025-05-19 15:43:16'),
(737, 1, 23, 'Remove', 'Purchase Order PO347823947 removed', '{\"id\":23,\"po_no\":\"PO347823947\",\"date_of_order\":\"0000-00-00\",\"no_of_units\":2,\"item_specifications\":\"testing123\",\"date_created\":\"2025-05-19 15:43:16\",\"is_disabled\":0}', NULL, 'Purchase Order', 'Successful', '2025-05-19 15:43:25'),
(738, 1, NULL, 'add', NULL, NULL, '{\"rr_no\":\"RR2321\",\"accountable_individual\":\"testing123\",\"po_no\":\"PO9928922\",\"ai_loc\":\"here\",\"date_created\":\"2025-05-19 09:43:00\"}', 'Receiving Report', NULL, '2025-05-19 15:44:41'),
(739, 1, 20, 'delete', NULL, '{\"id\":20,\"rr_no\":\"RR2321\",\"accountable_individual\":\"testing123\",\"ai_loc\":\"here\",\"po_no\":\"PO9928922\",\"date_created\":\"2025-05-19 09:43:00\",\"is_disabled\":0}', NULL, 'Receiving Report', NULL, '2025-05-19 15:45:03'),
(740, 1, 41, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00016384-TMD\",\"asset_description_1\":\"UPS\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"APC\",\"model\":\"8VX6501\",\"serial_number\":\"982224A22039\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 15:48:02\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 15:48:02'),
(741, 1, 42, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00016385-TMD\",\"asset_description_1\":\"UPS\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"APC\",\"model\":\"BVX6501-PH\",\"serial_number\":\"982234A30870\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 15:50:08\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 15:50:08'),
(742, 1, 47, 'Create', 'Department \'test123\' has been Created', NULL, '{\"id\":\"47\",\"abbreviation\":\"tt\",\"department_name\":\"test123\"}', 'Department Management', 'Successful', '2025-05-19 15:51:46'),
(743, 1, 47, 'Modified', 'Department \'test123\' details modified', '{\"id\":47,\"abbreviation\":\"tt\",\"department_name\":\"test123\"}', '{\"id\":\"47\",\"abbreviation\":\"testing\",\"department_name\":\"test123\"}', 'Department Management', 'Successful', '2025-05-19 15:52:07'),
(744, 1, 47, 'Remove', 'Department \'test123\' has been moved to archive', '{\"id\":47,\"abbreviation\":\"testing\",\"department_name\":\"test123\"}', '{\"id\":47,\"abbreviation\":\"testing\",\"department_name\":\"test123\"}', 'Department Management', 'Successful', '2025-05-19 15:52:12'),
(745, 1, 43, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00016386-TMD\",\"asset_description_1\":\"UPS\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"APC\",\"model\":\"BVX6501-PH\",\"serial_number\":\"982222A18231\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 15:53:42\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 15:53:42'),
(746, 1, 44, 'Create', 'New equipment created', NULL, '{\"asset_tag\":\"00016387-TMD\",\"asset_description_1\":\"UPS\",\"asset_description_2\":\"\",\"specifications\":\"\",\"brand\":\"APC\",\"model\":\"BVX6501-PH\",\"serial_number\":\"982222B18238\",\"location\":\"\",\"accountable_individual\":\"\",\"rr_no\":\"\",\"date_created\":\"2025-05-19 16:30:33\",\"remarks\":\"\"}', 'Equipment Details', 'Successful', '2025-05-19 16:30:33');

-- --------------------------------------------------------

--
-- Table structure for table `charge_invoice`
--

CREATE TABLE `charge_invoice` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(20) DEFAULT NULL,
  `date_of_purchase` date NOT NULL,
  `po_no` varchar(20) DEFAULT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `charge_invoice`
--

INSERT INTO `charge_invoice` (`id`, `invoice_no`, `date_of_purchase`, `po_no`, `date_created`, `is_disabled`) VALUES
(13, 'CI21346', '2025-05-23', NULL, '2025-05-16 16:18:36', 1),
(14, 'CI23213123', '2025-05-18', NULL, '2025-05-16 16:20:25', 0),
(15, 'CI333161232', '2025-05-16', NULL, '2025-05-16 16:33:44', 0),
(16, 'CI98765432', '2025-05-16', NULL, '2025-05-16 16:47:52', 0),
(17, 'CI098765423456', '2025-05-17', NULL, '2025-05-16 16:48:06', 1),
(18, 'CI123123123123123', '2025-05-21', NULL, '2025-05-17 13:33:25', 0),
(19, 'CI123123123', '2025-05-07', NULL, '2025-05-17 13:55:02', 0),
(20, 'CI3123123', '2025-05-01', 'PO9928922', '2025-05-17 14:10:04', 0),
(22, 'CI213123123', '2025-05-17', 'PO9928922', '2025-05-17 14:28:53', 0),
(23, 'CI8886734', '2025-05-18', 'PO0000978', '2025-05-17 22:59:59', 0),
(24, 'CI20247', '2024-11-19', 'PO1025000664', '2025-05-19 09:31:23', 0),
(25, 'CI12123456', '2024-12-12', 'PO0000978', '2025-05-19 13:44:27', 0);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(191) NOT NULL,
  `abbreviation` varchar(50) NOT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0
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
(47, 'test123', 'testing', 1);

-- --------------------------------------------------------

--
-- Table structure for table `equipment_details`
--

CREATE TABLE `equipment_details` (
  `id` int(11) NOT NULL,
  `asset_tag` varchar(50) NOT NULL,
  `asset_description_1` text NOT NULL,
  `asset_description_2` text NOT NULL,
  `specifications` text NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `date_acquired` datetime NOT NULL DEFAULT current_timestamp(),
  `invoice_no` varchar(20) DEFAULT NULL,
  `rr_no` varchar(20) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `accountable_individual` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0,
  `date_modified` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_details`
--

INSERT INTO `equipment_details` (`id`, `asset_tag`, `asset_description_1`, `asset_description_2`, `specifications`, `brand`, `model`, `serial_number`, `date_acquired`, `invoice_no`, `rr_no`, `location`, `accountable_individual`, `remarks`, `date_created`, `is_disabled`, `date_modified`) VALUES
(5, '5556233', '1231', '1123', '3123', '332123', '31233', '123123', '2025-05-15 02:23:26', NULL, '3123141', '2312312', '1235123', '1231231', '2025-03-17 15:31:00', 1, '2025-05-15 02:23:26'),
(12, 'SLU000004087', 'Computer', 'Desktop CPU', 'OS: Windows 11 Pro\r\nCPU: Intel Core i5-13400\r\nMB: Veriton X4710G\r\nGPU: Inter UHD Graphics 730\r\nDisplay: refer to SLU000004088\r\nRam: 16GB DDR4\r\nStorage: 256GB SSD 1TB\r\nPSU: Integrated\r\nOffice: Office Home and Student 2021 (4,350.00)\r\nAccessories: 1. USB KBM-wired | Acer\r\n                    2. Webcam | Genius (1,250.00)\r\n\r\n', 'Acer', 'Veriton X4710G', 'DTVYGSP01B40700C1E3000', '2025-05-19 08:45:06', NULL, 'RRN/A', 'HRD-Extension-Job Analysis', 'Alyana Mikaela E. De Guzman', '', '2025-05-19 08:45:06', 0, '2025-05-19 08:50:56'),
(13, 'SLU000004088', 'Computer', 'Desktop Monitor', '22\"', 'Acer', 'V226HQL', 'MMTYBSP001409006D13S11', '2025-05-19 08:49:06', NULL, NULL, 'HRD-Extension-Job Analysis', 'Alyana Mikaela E. De Guzman', '', '2025-05-19 08:49:06', 0, '2025-05-19 08:49:06'),
(14, 'SLU000004089', 'Computer', 'Desktop CPU', 'OS: Windows 11 Pro \r\nCPU: Intel Core i5-13400\r\nMB: Veriton X4710G \r\nGPU: Inter UHD Graphics 730 \r\nDisplay: refer to SLU000004090\r\nRam: 16GB DDR4 \r\nStorage: 256GB SSD 1TB \r\nPSU: Integrated \r\nOffice: Office Home and Student 2021 (4,350.00) \r\nAccessories: 1. USB KBM-wired | Acer \r\n                    2. Webcam | Genius (1,250.00)', 'Acer', 'V226HQL', 'DTVYGSP01B40700C3E3000', '2025-05-19 08:49:28', NULL, 'RRN/A', 'HRD-Extension-Job Analysis', 'Aira Rhayne Salazar', '', '2025-05-19 08:49:28', 0, '2025-05-19 08:56:49'),
(15, 'SLU000004090', 'Computer', 'Desktop Monitor', '22\"', 'Acer', 'V226HQL', 'MMTYBSP001409006D33S11', '2025-05-19 08:53:42', NULL, NULL, 'HRD-Extension-Job Analysis', 'Aira Rhayne Salazar', '', '2025-05-19 08:53:42', 0, '2025-05-19 08:56:25'),
(16, 'SLU000004091', 'Computer', 'Desktop CPU', 'OS: Windows 11 Pro \r\nCPU: Intel Core i5-13400\r\nMB: Veriton X4710G \r\nGPU: Inter UHD Graphics 730 \r\nDisplay: refer to SLU000004092\r\nRam: 16GB DDR4 \r\nStorage: 256GB SSD 1TB \r\nPSU: Integrated \r\nOffice: Office Home and Student 2021 (4,350.00) \r\nAccessories: 1. USB KBM-wired | Acer \r\n                    2. Webcam | Genius (1,250.00)', 'Acer', 'Veritom X4710G', 'DTVYGSP01B40700C263000', '2025-05-19 08:59:40', NULL, NULL, 'HRD Office - Cubicle 1', 'Nissah T. Lipao', '', '2025-05-19 08:59:40', 0, '2025-05-19 08:59:40'),
(17, 'SLU000004092', 'Computer', 'Desktop Monitor', '22\"', 'Acer', 'V226HQL', 'MMTYBSP001409006E03S11', '2025-05-19 09:00:54', NULL, NULL, 'HRD Office - Cubicle 1', 'Nissah T. Lipao', '', '2025-05-19 09:00:54', 0, '2025-05-19 09:00:54'),
(18, 'SLU000004093', 'UPS', '', '', 'ACP', 'BVX6501-PH', '9B2430A19187', '2025-05-19 09:02:28', NULL, NULL, 'HRD-Extension-Job Analysis', 'Alyana Mikaela E. De Guzman', '', '2025-05-19 09:02:28', 0, '2025-05-19 09:02:28'),
(19, '56988456', 'Computer', 'Desktop', '14 inch', 'Asus', 'Zen', '090909', '2025-05-19 13:47:03', NULL, 'RR89078', 'Diego Silang', 'Justine Lucas', '', '2025-05-19 13:47:03', 0, '2025-05-19 13:47:03'),
(20, '00001244-TMD', 'Access Point - Wireless', '', '', 'D-Link', 'DIR- 859 AC1750', 'RZOM314000398', '2025-05-19 14:32:13', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:32:13', 0, '2025-05-19 14:59:07'),
(21, '00001245-TMD', 'Access Point - Wireless', '', '', 'D-Link', 'DIR- 859 AC1750', 'RZOM314000391', '2025-05-19 14:34:58', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:34:58', 0, '2025-05-19 14:59:01'),
(22, '00001246-TMD', 'Access Point - Wireless', '', '', 'D-Link', 'DIR- 859 AC1750', 'RZOM314000396', '2025-05-19 14:36:06', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:36:06', 0, '2025-05-19 14:58:53'),
(23, '00001247-TMD', 'Access Point - Wireless', '', '', 'D-Link', 'DIR- 859 AC1750', 'RZOM314000397', '2025-05-19 14:37:09', NULL, NULL, 'Rizal', '', '', '2025-05-19 14:37:09', 0, '2025-05-19 14:58:40'),
(24, '00001248-TMD', 'Access Point - Wireless', '', '', 'D-Link', 'DIR- 859 AC1750', 'RZOM314000392', '2025-05-19 14:38:52', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:38:52', 0, '2025-05-19 14:58:33'),
(25, '00001249-TMD', 'Access Point - Wireless', '', '', 'D-Link', 'DIR- 859 AC1750', 'RZOM314000047', '2025-05-19 14:40:13', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:40:13', 0, '2025-05-19 14:40:13'),
(26, '00001250-TMD', 'Access Point - Wireless', '', '', 'D-Link', 'DIR- 859 AC1750', 'RZOM314000399', '2025-05-19 14:42:01', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:42:01', 0, '2025-05-19 14:42:01'),
(27, '00001251-TMD', 'Switch', '', '', 'Linksys', 'LGS108 (8 Port)', '13KK20F16A03756', '2025-05-19 14:43:29', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:43:29', 0, '2025-05-19 14:43:29'),
(28, '00001252-TMD', 'Switch', '', '', 'Linksys', 'LGS108 (8 Port)', '13KK20F16A03698', '2025-05-19 14:44:22', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:44:22', 0, '2025-05-19 14:44:22'),
(29, '00001253-TMD', 'Switch', '', '', 'Linksys', 'LGS108 (8 Port)', '13KK20F16A03741', '2025-05-19 14:45:43', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:45:43', 0, '2025-05-19 14:45:43'),
(30, '00001254-TMD', 'Switch', '', '', 'Linksys', 'LGS108 (8 Port)', '13KK20F16A03759', '2025-05-19 14:46:20', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:46:20', 0, '2025-05-19 14:46:20'),
(31, '00001255-TMD', 'Switch', '', '', 'Linksys', 'LGS108 (8 Port)', '13KK20F16A03734', '2025-05-19 14:47:39', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:47:39', 0, '2025-05-19 14:47:39'),
(32, '00001256-TMD', 'Switch', '', '', 'Linksys', 'LGS108 (8 Port)', '13KK20F16A03761', '2025-05-19 14:48:56', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:48:56', 0, '2025-05-19 14:48:56'),
(33, '00001257-TMD', 'Switch', '', '', 'CISCO', 'SG95-24 (24 Port)', 'DNI240209XU', '2025-05-19 14:50:39', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:50:39', 0, '2025-05-19 14:50:39'),
(34, '00001871-TMD', 'Antenna - UHD RFID', '', '', 'Chafon', 'CF-RA8080 / Time-in 1', '', '2025-05-19 14:57:20', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:57:20', 0, '2025-05-19 14:58:22'),
(35, '00001872-TMD', 'Antenna - UHD RFID', '', '', 'Chafon', 'CF-RA8080 / Time-in 2', '', '2025-05-19 14:59:58', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 14:59:58', 0, '2025-05-19 14:59:58'),
(36, '00001873-TMD', 'Antenna - UHD RFID', '', '', 'Chafon', 'CF-RA8080 / Time-out 1', '', '2025-05-19 15:00:45', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 15:00:45', 0, '2025-05-19 15:00:45'),
(37, '00001874-TMD', 'Antenna - UHD RFID', '', '', 'Chafon', 'CF-RA8080 / Time-out 2', '', '2025-05-19 15:01:35', NULL, NULL, 'Diego Silang', '', '', '2025-05-19 15:01:35', 0, '2025-05-19 15:01:35'),
(38, '00016351-TMD', 'UPS', '', '', 'APC', 'BVX650I-PH', '9B2224A06504', '2025-05-19 15:05:39', NULL, NULL, '', '', '', '2025-05-19 15:05:39', 0, '2025-05-19 15:05:39'),
(39, '00016352-TMD', 'UPS', '', '', 'APC', 'BVX650I-PH', '9B2222A18229', '2025-05-19 15:07:18', NULL, NULL, '', '', '', '2025-05-19 15:07:18', 0, '2025-05-19 15:07:18'),
(40, '00016353-TMD', 'UPS', '', '', 'APC', 'BVX650I-PH', '9B2238A30857', '2025-05-19 15:08:17', NULL, NULL, '', '', '', '2025-05-19 15:08:17', 0, '2025-05-19 15:08:17'),
(41, '00016384-TMD', 'UPS', '', '', 'APC', '8VX6501', '982224A22039', '2025-05-19 15:48:02', NULL, NULL, '', '', '', '2025-05-19 15:48:02', 0, '2025-05-19 15:48:02'),
(42, '00016385-TMD', 'UPS', '', '', 'APC', 'BVX6501-PH', '982234A30870', '2025-05-19 15:50:08', NULL, NULL, '', '', '', '2025-05-19 15:50:08', 0, '2025-05-19 15:50:08'),
(43, '00016386-TMD', 'UPS', '', '', 'APC', 'BVX6501-PH', '982222A18231', '2025-05-19 15:53:42', NULL, NULL, '', '', '', '2025-05-19 15:53:42', 0, '2025-05-19 15:53:42'),
(44, '00016387-TMD', 'UPS', '', '', 'APC', 'BVX6501-PH', '982222B18238', '2025-05-19 16:30:33', NULL, NULL, '', '', '', '2025-05-19 16:30:33', 0, '2025-05-19 16:30:33');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_location`
--

CREATE TABLE `equipment_location` (
  `equipment_location_id` int(11) NOT NULL,
  `asset_tag` varchar(50) NOT NULL,
  `building_loc` varchar(255) DEFAULT NULL,
  `floor_no` varchar(50) DEFAULT NULL,
  `specific_area` text DEFAULT NULL,
  `person_responsible` varchar(255) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_location`
--

INSERT INTO `equipment_location` (`equipment_location_id`, `asset_tag`, `building_loc`, `floor_no`, `specific_area`, `person_responsible`, `department_id`, `remarks`, `date_created`, `is_disabled`) VALUES
(1, '2222333111', 'Silang', '2', 'S231', 'Tester', 1, 'N/A', '2025-03-17 15:57:42', 0),
(4, '00001244-TMD', 'Diego Silang', '', 'Basement - TMDD DATA CENTER', 'Network Administrators', NULL, '', '2025-05-19 14:54:36', 0),
(5, '00001245-TMD', 'Diego Silang', '', 'Basement - FINANCE MEZZANINE', 'Network Administrators', NULL, 'Borrowed by Finance', '2025-05-19 15:03:04', 0),
(6, '00001246-TMD', 'Diego Silang', '', 'Basement - TMDD DATA CENTER', 'Network Administrators', NULL, '', '2025-05-19 15:09:28', 0),
(7, '00001247-TMD', 'Rizal', '1', 'Tuklas Lunas', 'Network Administrators', NULL, 'Borrowed by Tuklas Lunas', '2025-05-19 15:10:31', 0),
(8, '00001248-TMD', 'Diego Silang', '', 'Basement - TMDD', 'Network Administrators', NULL, '', '2025-05-19 15:11:53', 0),
(9, '00001249-TMD', 'Diego Silang', '', 'Basement - TMDD', 'Network Administrators', NULL, '', '2025-05-19 15:13:06', 0),
(10, '00001250-TMD', 'Diego Silang', '', 'Basement - TMDD', 'Network Administrators', NULL, '', '2025-05-19 15:14:41', 0),
(12, '00001251-TMD', 'Diego Silang', '', 'Basement - TMDD DATA CENTER', 'Network Administrators', NULL, '', '2025-05-19 15:17:07', 0),
(13, '00001252-TMD', 'Diego Silang', '', 'Basement - TMDD', 'Network Administrators', NULL, '', '2025-05-19 15:17:46', 0),
(14, '00001253-TMD', 'Diego Silang', '', 'Basement', 'Network Administrators', NULL, '', '2025-05-19 15:18:49', 0),
(15, '00001254-TMD', 'Diego Silang', '', 'Basement - TMDD DATA CENTER', 'Network Administrators', NULL, '', '2025-05-19 15:19:22', 0),
(17, '00001255-TMD', 'Diego Silang', '', 'Basement', 'Network Administrators', NULL, '', '2025-05-19 15:21:13', 0),
(18, '00001256-TMD', 'Diego Silang', '', 'Basement - TMDD', 'Network Administrators', NULL, '', '2025-05-19 15:22:00', 0),
(19, '00001257-TMD', 'Diego Silang', '', 'Basement - TMDD', 'Network Administrators', NULL, '', '2025-05-19 15:23:08', 0),
(20, '00001871-TMD', 'Diego Silang', '2', 'Silang Lobby', 'TMDD Team', NULL, 'for university employees', '2025-05-19 15:24:42', 0),
(21, '00001872-TMD', 'Diego Silang', '2', 'Silang', 'TMDD Team', NULL, 'for university employees', '2025-05-19 15:25:33', 0),
(22, '00001873-TMD', 'Diego Silang', '2', 'Silang Lobby', 'TMDD Team', NULL, 'for university employees', '2025-05-19 15:26:25', 0),
(23, '00001874-TMD', 'Diego Silang', '2', 'Silang Lobby', 'TMDD Team', NULL, 'for university employees', '2025-05-19 15:27:01', 0);

-- --------------------------------------------------------

--
-- Table structure for table `equipment_status`
--

CREATE TABLE `equipment_status` (
  `equipment_status_id` int(11) NOT NULL,
  `asset_tag` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `remarks` text DEFAULT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `is_disabled` tinyint(1) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_status`
--

INSERT INTO `equipment_status` (`equipment_status_id`, `asset_tag`, `status`, `action`, `remarks`, `date_created`, `is_disabled`) VALUES
(1, '90909890', 'For Disposal', 'Collection', '', '2025-05-19 13:48:55', 0);

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `module_name`) VALUES
(1, 'Audit'),
(4, 'Equipment Management'),
(15, 'Equipment Transactions'),
(17, 'Management'),
(16, 'Reports'),
(2, 'Roles and Privileges'),
(3, 'User Management');

-- --------------------------------------------------------

--
-- Table structure for table `privileges`
--

CREATE TABLE `privileges` (
  `id` int(11) NOT NULL,
  `priv_name` varchar(191) NOT NULL,
  `is_disabled` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `privileges`
--

INSERT INTO `privileges` (`id`, `priv_name`, `is_disabled`) VALUES
(1, 'Track', 0),
(2, 'Create', 0),
(4, 'Remove', 0),
(5, 'Permanently Delete', 0),
(6, 'Modify', 0),
(7, 'View', 0),
(8, 'Restore', 0);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order`
--

CREATE TABLE `purchase_order` (
  `id` int(11) NOT NULL,
  `po_no` varchar(20) DEFAULT NULL,
  `date_of_order` date NOT NULL,
  `no_of_units` int(11) NOT NULL,
  `item_specifications` text NOT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order`
--

INSERT INTO `purchase_order` (`id`, `po_no`, `date_of_order`, `no_of_units`, `item_specifications`, `date_created`, `is_disabled`) VALUES
(19, 'PO23123123', '2025-05-16', 23, '1212', '2025-05-16 16:33:22', 0),
(20, 'PO9928922', '2025-05-18', 12, 'AMD', '2025-05-17 14:03:16', 0),
(21, 'PO0000978', '2025-05-18', 2, 'TRA', '2025-05-17 22:59:41', 0),
(22, 'PO1025000664', '2024-11-12', 3, 'SET', '2025-05-19 09:29:56', 0),
(23, 'PO347823947', '0000-00-00', 2, 'testing123', '2025-05-19 15:43:16', 1);

-- --------------------------------------------------------

--
-- Table structure for table `receive_report`
--

CREATE TABLE `receive_report` (
  `id` int(11) NOT NULL,
  `rr_no` varchar(20) DEFAULT NULL,
  `accountable_individual` varchar(255) DEFAULT NULL,
  `ai_loc` text DEFAULT NULL,
  `po_no` varchar(20) DEFAULT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `receive_report`
--

INSERT INTO `receive_report` (`id`, `rr_no`, `accountable_individual`, `ai_loc`, `po_no`, `date_created`, `is_disabled`) VALUES
(17, 'RR989898', 'Steve', 'Silang', 'PO23123123', '2025-05-17 06:02:00', 0),
(18, 'RR3478123', 'N/A', 'over there', 'PO23123123', '2025-05-18 04:08:00', 0),
(19, 'RR007571', 'Jeremy Lee Dela Cruz', 'HRD', 'PO1025000664', '2024-11-19 03:32:00', 0),
(20, 'RR2321', 'testing123', 'here', 'PO9928922', '2025-05-19 09:43:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `is_disabled`) VALUES
(1, 'TMDD-Dev', 0),
(2, 'Super Admin', 0),
(3, 'Equipment Manager', 0),
(4, 'User Manager', 0),
(5, 'RP Manager', 0),
(6, 'Auditor', 0),
(32, 'testsetes', 0);

-- --------------------------------------------------------

--
-- Table structure for table `role_changes`
--

CREATE TABLE `role_changes` (
  `ChangeID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `RoleID` int(11) NOT NULL,
  `Action` enum('Add','Modified','Delete') NOT NULL,
  `OldRoleName` varchar(191) DEFAULT NULL,
  `NewRoleName` varchar(191) DEFAULT NULL,
  `ChangeTimestamp` datetime DEFAULT current_timestamp(),
  `OldPrivileges` text DEFAULT NULL,
  `NewPrivileges` text DEFAULT NULL,
  `IsUndone` tinyint(1) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_changes`
--

INSERT INTO `role_changes` (`ChangeID`, `UserID`, `RoleID`, `Action`, `OldRoleName`, `NewRoleName`, `ChangeTimestamp`, `OldPrivileges`, `NewPrivileges`, `IsUndone`) VALUES
(273, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:41:31', '[\"1|1\"]', '[\"1|1\"]', 0),
(274, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:41:34', '[\"1|1\"]', '[]', 0),
(275, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:41:38', '[]', '[\"1|1\"]', 0),
(266, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:31:18', '[\"1|1\"]', '[\"1|1\"]', 0),
(267, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:32:16', '[\"1|1\"]', '[\"1|1\"]', 0),
(268, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:32:21', '[\"1|1\"]', '[]', 0),
(269, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:32:24', '[]', '[\"1|1\"]', 0),
(270, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:33:37', '[\"1|1\"]', '[\"1|1\"]', 0),
(271, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:33:40', '[\"1|1\"]', '[\"1|1\"]', 0),
(272, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:33:45', '[\"1|1\"]', '[\"1|1\"]', 0),
(259, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:29:15', '[]', '[]', 0),
(260, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:29:18', '[]', '[\"1|1\"]', 0),
(261, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:29:18', '[\"1|1\"]', '[\"1|1\"]', 0),
(262, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:29:18', '[\"1|1\"]', '[\"1|1\"]', 0),
(263, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:30:06', '[\"1|1\"]', '[\"1|1\"]', 0),
(264, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:30:37', '[\"1|1\"]', '[\"1|1\"]', 0),
(265, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:31:12', '[\"1|1\"]', '[\"1|1\"]', 0),
(252, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:12:49', '[\"1|1\"]', '[\"1|1\"]', 0),
(253, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:12:57', '[\"1|1\"]', '[]', 0),
(254, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:27:26', '[]', '[\"1|1\"]', 0),
(255, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:28:16', '[\"1|1\"]', '[\"1|1\"]', 0),
(256, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:28:16', '[\"1|1\"]', '[\"1|1\"]', 0),
(257, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:28:16', '[\"1|1\"]', '[\"1|1\"]', 0),
(258, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-19 11:29:14', '[\"1|1\"]', '[]', 0),
(243, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-13 11:52:41', '[\"1|1\"]', '[\"1|1\"]', 0),
(244, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-13 11:52:45', '[\"1|1\"]', '[\"1|1\"]', 0),
(245, 1, 29, 'Add', NULL, 'test', '2025-03-13 11:54:13', NULL, NULL, 0),
(246, 1, 29, 'Delete', 'test', NULL, '2025-03-13 11:55:15', NULL, NULL, 0),
(247, 1, 30, 'Add', NULL, 'test', '2025-03-13 11:55:51', NULL, NULL, 0),
(248, 1, 30, 'Delete', 'test', NULL, '2025-03-13 11:56:20', NULL, NULL, 0),
(249, 1, 31, 'Add', NULL, 'test', '2025-03-13 11:56:23', NULL, NULL, 0),
(250, 1, 31, 'Delete', 'test', NULL, '2025-03-13 11:56:26', NULL, NULL, 0),
(251, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-18 09:13:28', '[\"1|1\"]', '[\"1|1\"]', 0),
(242, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-03-13 11:52:10', '[\"1|1\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', '[\"1|1\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(236, 1, 27, 'Modified', 'testtest', 'testtest', '2025-03-13 10:04:58', '[]', '[]', 0),
(237, 1, 28, 'Add', NULL, 'testtesttest', '2025-03-13 10:05:11', NULL, NULL, 0),
(238, 1, 26, 'Delete', 'test', NULL, '2025-03-13 10:05:15', NULL, NULL, 0),
(239, 1, 27, 'Delete', 'testtest', NULL, '2025-03-13 10:05:20', NULL, NULL, 0),
(240, 1, 28, 'Delete', 'testtesttest', NULL, '2025-03-13 10:05:24', NULL, NULL, 0),
(241, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-03-13 11:52:07', '[\"1|1\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', '[\"1|1\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(231, 1, 27, 'Modified', 'testtest', 'testtest', '2025-03-13 10:02:47', '[]', '[]', 0),
(232, 1, 26, 'Modified', 'test', 'test', '2025-03-13 10:02:53', '[]', '[]', 0),
(233, 1, 27, 'Modified', 'testtest', 'testtest', '2025-03-13 10:03:00', '[]', '[\"1|1\"]', 0),
(234, 1, 27, 'Modified', 'testtest', 'testtest', '2025-03-13 10:04:49', '[\"1|1\"]', '[\"1|1\"]', 0),
(235, 1, 27, 'Modified', 'testtest', 'testtest', '2025-03-13 10:04:55', '[\"1|1\"]', '[]', 0),
(56, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-03-11 15:14:43', '[]', '[\"1|1\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(57, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-03-11 15:38:21', '[\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', '[\"1|1\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(58, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-03-11 15:41:17', '[\"1|1\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', '[\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(59, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-03-11 15:42:02', '[\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', '[\"1|1\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(60, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-03-11 15:43:33', '[\"1|1\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', '[\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(61, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-03-11 15:43:41', '[\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', '[\"1|1\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(62, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 10:37:00', '[]', '[]', 0),
(63, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 10:39:58', '[]', '[]', 0),
(64, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 10:44:25', '[]', '[]', 0),
(65, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 10:44:59', '[]', '[]', 0),
(66, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 10:58:59', '[]', '[]', 0),
(67, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 10:59:12', '[]', '[]', 0),
(68, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 10:59:12', '[]', '[]', 0),
(69, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 10:59:13', '[]', '[]', 0),
(70, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 10:59:31', '[]', '[]', 0),
(71, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:02:13', '[]', '[]', 0),
(72, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:02:15', '[]', '[]', 0),
(73, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:02:16', '[]', '[]', 0),
(74, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:02:16', '[]', '[]', 0),
(75, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:02:31', '[]', '[]', 0),
(76, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:47:18', '[]', '[]', 0),
(77, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:47:20', '[]', '[]', 0),
(78, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:47:23', '[]', '[]', 0),
(79, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:47:32', '[]', '[]', 0),
(80, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:48:00', '[]', '[]', 0),
(81, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:48:11', '[]', '[]', 0),
(82, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 11:48:21', '[]', '[]', 0),
(83, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 14:27:42', '[]', '[]', 0),
(84, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 14:28:33', '[]', '[]', 0),
(85, 1, 11, 'Add', NULL, 'test', '2025-03-12 14:29:21', NULL, NULL, 0),
(86, 1, 11, 'Delete', NULL, NULL, '2025-03-12 14:32:14', NULL, NULL, 0),
(87, 1, 12, 'Add', NULL, 'asd', '2025-03-12 14:32:19', NULL, NULL, 0),
(88, 1, 12, 'Delete', NULL, NULL, '2025-03-12 14:32:23', NULL, NULL, 0),
(89, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 14:32:26', '[]', '[]', 0),
(90, 1, 13, 'Add', NULL, 'asdasd', '2025-03-12 14:36:20', NULL, NULL, 0),
(91, 1, 13, 'Delete', NULL, NULL, '2025-03-12 14:36:27', NULL, NULL, 0),
(92, 1, 14, 'Add', NULL, 'asd', '2025-03-12 14:46:53', NULL, NULL, 0),
(93, 1, 14, 'Delete', NULL, NULL, '2025-03-12 14:46:59', NULL, NULL, 0),
(94, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 14:48:54', '[]', '[]', 0),
(95, 1, 15, 'Add', NULL, 'asd', '2025-03-12 14:55:02', NULL, NULL, 0),
(96, 1, 15, 'Delete', NULL, NULL, '2025-03-12 14:55:07', NULL, NULL, 0),
(97, 1, 16, 'Add', NULL, 'asdasd', '2025-03-12 14:55:56', NULL, NULL, 0),
(98, 1, 16, 'Delete', NULL, NULL, '2025-03-12 14:56:27', NULL, NULL, 0),
(99, 1, 17, 'Add', NULL, 'asdasd', '2025-03-12 14:56:55', NULL, NULL, 0),
(100, 1, 17, 'Delete', NULL, NULL, '2025-03-12 14:57:13', NULL, NULL, 0),
(101, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 14:58:34', '[]', '[]', 0),
(102, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 14:58:47', '[]', '[]', 0),
(103, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 14:59:06', '[]', '[]', 0),
(104, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 14:59:23', '[]', '[]', 0),
(105, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 15:00:00', '[]', '[]', 0),
(106, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 15:00:03', '[]', '[]', 0),
(107, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 15:00:06', '[]', '[\"1|1\"]', 0),
(108, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 15:05:37', '[\"1|1\"]', '[\"1|1\"]', 0),
(109, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 15:05:43', '[\"1|1\"]', '[\"1|1\"]', 0),
(110, 1, 18, 'Add', NULL, 'asd', '2025-03-12 15:56:42', NULL, NULL, 0),
(111, 1, 18, 'Delete', NULL, NULL, '2025-03-12 15:56:54', NULL, NULL, 0),
(112, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 15:57:25', '[\"1|1\"]', '[\"1|1\"]', 0),
(113, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 15:57:26', '[\"1|1\"]', '[\"1|1\"]', 0),
(114, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 16:03:26', '[\"1|1\"]', '[\"1|1\"]', 0),
(115, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 16:03:28', '[\"1|1\"]', '[\"1|1\"]', 0),
(116, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 16:03:32', '[\"1|1\"]', '[\"1|1\"]', 0),
(117, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 16:03:33', '[\"1|1\"]', '[\"1|1\"]', 0),
(118, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 16:03:33', '[\"1|1\"]', '[\"1|1\"]', 0),
(119, 1, 19, 'Add', NULL, 'asd', '2025-03-12 16:03:40', NULL, NULL, 0),
(120, 1, 19, 'Modified', 'asd', 'asd', '2025-03-12 16:05:28', '[]', '[]', 0),
(121, 1, 19, 'Modified', 'asd', 'asd', '2025-03-12 16:10:21', '[]', '[]', 0),
(122, 1, 20, 'Add', NULL, 'asdasd', '2025-03-12 16:10:49', NULL, NULL, 0),
(123, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:19:05', '[]', '[]', 0),
(124, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:19:07', '[]', '[]', 0),
(125, 1, 19, 'Modified', 'asd', 'asd', '2025-03-12 16:19:21', '[]', '[]', 0),
(126, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:34:26', '[]', '[]', 0),
(127, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:34:29', '[]', '[]', 0),
(128, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:34:29', '[]', '[]', 0),
(129, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:34:30', '[]', '[]', 0),
(130, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:34:30', '[]', '[]', 0),
(131, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:37:40', '[]', '[]', 0),
(132, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:37:43', '[]', '[]', 0),
(133, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:37:44', '[]', '[]', 0),
(134, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:37:44', '[]', '[]', 0),
(135, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:37:44', '[]', '[]', 0),
(136, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:37:44', '[]', '[]', 0),
(137, 1, 6, 'Modified', 'Auditor', 'Auditor', '2025-03-12 16:41:14', '[\"1|1\"]', '[\"1|1\"]', 0),
(138, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:41:20', '[]', '[]', 0),
(139, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:41:41', '[]', '[]', 0),
(140, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:41:46', '[]', '[]', 0),
(141, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:54:04', '[]', '[]', 0),
(142, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:54:05', '[]', '[]', 0),
(143, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:54:26', '[]', '[]', 0),
(144, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:54:26', '[]', '[]', 0),
(145, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:54:35', '[]', '[]', 0),
(146, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-12 16:54:35', '[]', '[]', 0),
(147, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-13 08:04:56', '[]', '[]', 0),
(148, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-13 08:04:56', '[]', '[]', 0),
(149, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-13 08:05:54', '[]', '[]', 0),
(150, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-13 08:05:54', '[]', '[]', 0),
(151, 1, 21, 'Add', NULL, 'asdf', '2025-03-13 08:06:48', NULL, NULL, 0),
(152, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:11:58', '[]', '[]', 0),
(153, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:11:58', '[]', '[]', 0),
(154, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:18:50', '[]', '[]', 0),
(155, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:18:50', '[]', '[]', 0),
(156, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:18:57', '[]', '[]', 0),
(157, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:18:57', '[]', '[]', 0),
(158, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:19:28', '[]', '[]', 0),
(159, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:19:28', '[]', '[]', 0),
(160, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:19:33', '[]', '[]', 0),
(161, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:19:33', '[]', '[]', 0),
(162, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:20:40', '[]', '[]', 0),
(163, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:20:40', '[]', '[]', 0),
(164, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:21:05', '[]', '[]', 0),
(165, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:21:05', '[]', '[]', 0),
(166, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:21:17', '[]', '[]', 0),
(167, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:21:17', '[]', '[]', 0),
(168, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:21:20', '[]', '[]', 0),
(169, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:21:20', '[]', '[]', 0),
(170, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:21:49', '[]', '[]', 0),
(171, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:21:49', '[]', '[]', 0),
(172, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:02', '[]', '[]', 0),
(173, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:02', '[]', '[]', 0),
(174, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:09', '[]', '[]', 0),
(175, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:09', '[]', '[]', 0),
(176, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:12', '[]', '[]', 0),
(177, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:12', '[]', '[]', 0),
(178, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:25', '[]', '[]', 0),
(179, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:25', '[]', '[]', 0),
(180, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:57', '[]', '[]', 0),
(181, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:57', '[]', '[]', 0),
(182, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:59', '[]', '[]', 0),
(183, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:22:59', '[]', '[]', 0),
(184, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:23:24', '[]', '[]', 0),
(185, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:23:24', '[]', '[]', 0),
(186, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:23:27', '[]', '[]', 0),
(187, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:23:27', '[]', '[]', 0),
(188, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:23:30', '[]', '[]', 0),
(189, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:23:30', '[]', '[]', 0),
(190, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:23:59', '[]', '[]', 0),
(191, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:23:59', '[]', '[]', 0),
(192, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:25:04', '[]', '[]', 0),
(193, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:25:04', '[]', '[]', 0),
(194, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:25:15', '[]', '[]', 0),
(195, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:25:15', '[]', '[]', 0),
(196, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:29:26', '[]', '[]', 0),
(197, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:29:26', '[]', '[]', 0),
(198, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:30:00', '[]', '[]', 0),
(199, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:30:00', '[]', '[]', 0),
(200, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:30:27', '[]', '[\"1|1\"]', 0),
(201, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:30:27', '[\"1|1\"]', '[\"1|1\"]', 0),
(202, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:30:32', '[\"1|1\"]', '[]', 0),
(203, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:30:32', '[]', '[]', 0),
(204, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:31:02', '[]', '[\"1|1\"]', 0),
(205, 1, 21, 'Modified', 'asdf', 'asdf', '2025-03-13 08:31:02', '[\"1|1\"]', '[\"1|1\"]', 0),
(206, 1, 22, 'Add', NULL, 'asdasdas', '2025-03-13 08:34:02', NULL, NULL, 0),
(207, 1, 23, 'Add', NULL, 'asdasdasdasd', '2025-03-13 08:34:17', NULL, NULL, 0),
(208, 1, 24, 'Add', NULL, 'testtest', '2025-03-13 08:34:47', NULL, NULL, 0),
(209, 1, 24, 'Delete', 'testtest', NULL, '2025-03-13 08:43:11', NULL, NULL, 0),
(210, 1, 23, 'Delete', 'asdasdasdasd', NULL, '2025-03-13 08:43:15', NULL, NULL, 0),
(211, 1, 22, 'Delete', 'asdasdas', NULL, '2025-03-13 08:43:20', NULL, NULL, 0),
(212, 1, 21, 'Delete', 'asdf', NULL, '2025-03-13 08:43:22', NULL, NULL, 0),
(213, 1, 25, 'Add', NULL, 'testestetse', '2025-03-13 08:43:26', NULL, NULL, 0),
(214, 1, 25, 'Delete', 'testestetse', NULL, '2025-03-13 08:43:28', NULL, NULL, 0),
(215, 1, 19, 'Modified', 'asd', 'asd', '2025-03-13 08:43:58', '[]', '[]', 0),
(216, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-13 09:42:24', '[]', '[\"1|1\"]', 0),
(217, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-13 09:42:24', '[\"1|1\"]', '[\"1|1\"]', 0),
(218, 1, 19, 'Modified', 'asd', 'asd', '2025-03-13 09:42:28', '[]', '[]', 0),
(219, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-13 09:42:58', '[\"1|1\"]', '[\"1|1\"]', 0),
(220, 1, 20, 'Modified', 'asdasd', 'asdasd', '2025-03-13 09:42:58', '[\"1|1\"]', '[\"1|1\"]', 0),
(221, 1, 19, 'Modified', 'asd', 'asd', '2025-03-13 09:43:04', '[]', '[\"1|1\"]', 0),
(222, 1, 19, 'Modified', 'asd', 'asd', '2025-03-13 09:43:08', '[\"1|1\"]', '[]', 0),
(223, 1, 26, 'Add', NULL, 'test', '2025-03-13 09:43:53', NULL, NULL, 0),
(224, 1, 27, 'Add', NULL, 'testtest', '2025-03-13 09:44:10', NULL, NULL, 0),
(225, 1, 27, 'Modified', 'testtest', 'testtest', '2025-03-13 09:44:12', '[]', '[]', 0),
(226, 1, 27, 'Modified', 'testtest', 'testtest', '2025-03-13 09:44:12', '[]', '[]', 0),
(227, 1, 26, 'Modified', 'test', 'test', '2025-03-13 09:44:17', '[]', '[]', 0),
(228, 1, 27, 'Modified', 'testtest', 'testtest', '2025-03-13 09:44:21', '[]', '[]', 0),
(229, 1, 27, 'Modified', 'testtest', 'testtest', '2025-03-13 09:44:21', '[]', '[]', 0),
(230, 1, 26, 'Modified', 'test', 'test', '2025-03-13 09:44:23', '[]', '[]', 0),
(276, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-09 14:28:34', '[\"1|1\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\"]', '[\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(277, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-09 14:28:39', '[\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\"]', '[\"1|7\",\"2|3\",\"2|11\",\"2|10\",\"2|2\",\"2|5\",\"2|6\",\"2|12\",\"2|4\",\"2|8\",\"2|1\",\"2|9\",\"2|7\",\"3|3\",\"3|11\",\"3|10\",\"3|2\",\"3|5\",\"3|6\",\"3|12\",\"3|4\",\"3|8\",\"3|1\",\"3|9\",\"3|7\",\"4|3\",\"4|11\",\"4|10\",\"4|2\",\"4|5\",\"4|6\",\"4|12\",\"4|4\",\"4|8\",\"4|1\",\"4|9\",\"4|7\"]', 0),
(278, 1, 32, 'Add', NULL, 'testsetes', '2025-05-09 15:23:21', NULL, NULL, 0),
(279, 1, 2, 'Modified', 'Super Admin', 'Super Admin', '2025-05-16 15:08:39', '[]', '[\"3|8\",\"3|7\"]', 0),
(280, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-16 15:30:04', '[\"2|1\",\"3|1\",\"4|1\",\"2|2\",\"3|2\",\"4|2\",\"2|4\",\"3|4\",\"4|4\",\"2|5\",\"3|5\",\"4|5\",\"2|6\",\"3|6\",\"4|6\",\"1|7\",\"2|7\",\"3|7\",\"4|7\",\"2|8\",\"3|8\",\"4|8\"]', '[\"1|7\",\"2|2\",\"2|6\",\"2|5\",\"2|4\",\"2|8\",\"2|1\",\"2|7\",\"3|2\",\"3|6\",\"3|5\",\"3|4\",\"3|8\",\"3|1\",\"3|7\",\"4|2\",\"4|6\",\"4|5\",\"4|4\",\"4|8\",\"4|1\",\"4|7\",\"15|2\",\"15|6\",\"15|5\",\"15|4\",\"15|8\",\"15|1\",\"15|7\",\"16|2\",\"16|6\",\"16|5\",\"16|4\",\"16|8\",\"16|1\",\"16|7\",\"17|2\",\"17|6\",\"17|5\",\"17|4\",\"17|8\",\"17|1\",\"17|7\"]', 0),
(281, 1, 4, 'Modified', 'User Manager', 'User Manager', '2025-05-16 15:31:51', '[]', '[\"3|2\",\"3|6\",\"3|5\",\"3|4\",\"3|8\",\"3|1\",\"3|7\"]', 0),
(282, 1, 4, 'Modified', 'User Manager', 'User Manager', '2025-05-16 15:32:07', '[\"3|2\",\"3|6\",\"3|5\",\"3|4\",\"3|8\",\"3|1\",\"3|7\"]', '[\"3|2\",\"3|6\",\"3|5\",\"3|4\",\"3|8\",\"3|1\"]', 0),
(283, 1, 4, 'Modified', 'User Manager', 'User Manager', '2025-05-16 15:32:23', '[\"3|2\",\"3|6\",\"3|5\",\"3|4\",\"3|8\",\"3|1\"]', '[\"3|2\",\"3|6\",\"3|5\",\"3|4\",\"3|8\",\"3|1\",\"3|7\",\"16|7\",\"17|7\"]', 0),
(284, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-16 15:46:15', '[\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"1|7\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|7\",\"2|2\",\"2|6\",\"2|5\",\"2|4\",\"2|8\",\"2|1\",\"2|7\",\"3|2\",\"3|6\",\"3|5\",\"3|4\",\"3|8\",\"3|1\",\"3|7\",\"4|2\",\"4|6\",\"4|5\",\"4|4\",\"4|8\",\"4|1\",\"4|7\",\"15|2\",\"15|6\",\"15|5\",\"15|4\",\"15|8\",\"15|1\",\"15|7\",\"16|2\",\"16|6\",\"16|5\",\"16|4\",\"16|8\",\"16|1\",\"16|7\",\"17|2\",\"17|6\",\"17|5\",\"17|4\",\"17|8\",\"17|1\",\"17|7\"]', 0),
(285, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 16:49:11', '[\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|7\",\"2|2\",\"2|6\",\"2|5\",\"2|4\",\"2|8\",\"2|1\",\"2|7\",\"3|2\",\"3|6\",\"3|5\",\"3|4\",\"3|8\",\"3|1\",\"3|7\",\"4|2\",\"4|6\",\"4|5\",\"4|4\",\"4|8\",\"4|1\",\"4|7\",\"15|2\",\"15|6\",\"15|5\",\"15|4\",\"15|8\",\"15|1\",\"15|7\",\"16|2\",\"16|6\",\"16|5\",\"16|4\",\"16|8\",\"16|1\",\"16|7\",\"17|2\",\"17|6\",\"17|5\",\"17|4\",\"17|8\",\"17|1\",\"17|7\"]', 0),
(286, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 16:53:12', '[\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"1|7\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(287, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 17:04:28', '[\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"1|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(288, 1, 2, 'Modified', 'Super Admin', 'Super Admin', '2025-05-18 17:11:20', '[\"3|8\",\"3|7\"]', '[\"1|1\",\"3|7\",\"3|8\"]', 0),
(289, 1, 2, 'Modified', 'Super Admin', 'Super Admin', '2025-05-18 17:11:23', '[\"1|1\",\"3|7\",\"3|8\"]', '[\"3|7\",\"3|8\"]', 0),
(290, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 17:11:28', '[\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|1\",\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(291, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 17:11:32', '[\"1|1\",\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|1\",\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(292, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 17:13:13', '[\"1|1\",\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(293, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 17:13:17', '[\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|1\",\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(294, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 17:13:25', '[\"1|1\",\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(295, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 17:13:32', '[\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|1\",\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(296, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 17:13:45', '[\"1|1\",\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(297, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 17:13:49', '[\"1|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|1\",\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(298, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 18:03:23', '[\"1|1\",\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(299, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 18:03:32', '[\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|1\",\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(300, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 18:03:36', '[\"1|1\",\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(301, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 18:03:45', '[\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|1\",\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(302, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 18:03:49', '[\"1|1\",\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(303, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 18:03:54', '[\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|1\",\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(304, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 18:04:08', '[\"1|1\",\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(305, 1, 1, 'Modified', 'TMDD-Dev', 'TMDD-Dev', '2025-05-18 18:04:12', '[\"2|1\",\"3|1\",\"4|1\",\"15|1\",\"16|1\",\"17|1\",\"2|2\",\"3|2\",\"4|2\",\"15|2\",\"16|2\",\"17|2\",\"2|4\",\"3|4\",\"4|4\",\"15|4\",\"16|4\",\"17|4\",\"2|5\",\"3|5\",\"4|5\",\"15|5\",\"16|5\",\"17|5\",\"2|6\",\"3|6\",\"4|6\",\"15|6\",\"16|6\",\"17|6\",\"2|7\",\"3|7\",\"4|7\",\"15|7\",\"16|7\",\"17|7\",\"2|8\",\"3|8\",\"4|8\",\"15|8\",\"16|8\",\"17|8\"]', '[\"1|1\",\"2|1\",\"2|2\",\"2|4\",\"2|5\",\"2|6\",\"2|7\",\"2|8\",\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\",\"16|1\",\"16|2\",\"16|4\",\"16|5\",\"16|6\",\"16|7\",\"16|8\",\"17|1\",\"17|2\",\"17|4\",\"17|5\",\"17|6\",\"17|7\",\"17|8\"]', 0),
(306, 1, 2, 'Modified', 'Super Admin', 'Super Admin', '2025-05-18 18:04:15', '[\"3|7\",\"3|8\"]', '[\"1|1\",\"3|7\",\"3|8\"]', 0),
(307, 1, 3, 'Modified', 'Equipment Manager', 'Equipment Manager', '2025-05-19 08:34:57', '[]', '[\"4|1\",\"4|2\",\"4|4\",\"4|5\",\"4|6\",\"4|7\",\"4|8\",\"15|1\",\"15|2\",\"15|4\",\"15|5\",\"15|6\",\"15|7\",\"15|8\"]', 0),
(308, 1, 4, 'Modified', 'User Manager', 'User Manager', '2025-05-19 15:33:25', '[\"3|2\",\"3|6\",\"3|5\",\"3|4\",\"3|8\",\"3|1\",\"3|7\",\"16|7\",\"17|7\"]', '[\"3|1\",\"3|2\",\"3|4\",\"3|5\",\"3|6\",\"3|7\",\"3|8\",\"17|7\"]', 0);

-- --------------------------------------------------------

--
-- Table structure for table `role_module_privileges`
--

CREATE TABLE `role_module_privileges` (
  `id` int(11) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `privilege_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_module_privileges`
--

INSERT INTO `role_module_privileges` (`id`, `role_id`, `module_id`, `privilege_id`) VALUES
(869, 0, 2, 1),
(870, 0, 2, 2),
(871, 0, 2, 3),
(872, 0, 2, 4),
(873, 0, 2, 5),
(874, 0, 2, 6),
(875, 0, 2, 7),
(876, 0, 2, 8),
(877, 0, 2, 9),
(878, 0, 2, 10),
(879, 0, 2, 11),
(880, 0, 2, 12),
(1215, 0, 4, 1),
(1216, 0, 4, 2),
(1217, 0, 4, 3),
(1218, 0, 4, 4),
(1219, 0, 4, 5),
(1220, 0, 4, 6),
(1221, 0, 4, 7),
(1222, 0, 4, 8),
(1223, 0, 4, 9),
(1224, 0, 4, 10),
(1225, 0, 4, 11),
(1226, 0, 4, 12),
(1562, 0, 1, 1),
(2206, 1, 1, 1),
(2207, 1, 2, 1),
(2208, 1, 2, 2),
(2209, 1, 2, 4),
(2210, 1, 2, 5),
(2211, 1, 2, 6),
(2212, 1, 2, 7),
(2213, 1, 2, 8),
(2214, 1, 3, 1),
(2215, 1, 3, 2),
(2216, 1, 3, 4),
(2217, 1, 3, 5),
(2218, 1, 3, 6),
(2219, 1, 3, 7),
(2220, 1, 3, 8),
(2221, 1, 4, 1),
(2222, 1, 4, 2),
(2223, 1, 4, 4),
(2224, 1, 4, 5),
(2225, 1, 4, 6),
(2226, 1, 4, 7),
(2227, 1, 4, 8),
(2228, 1, 15, 1),
(2229, 1, 15, 2),
(2230, 1, 15, 4),
(2231, 1, 15, 5),
(2232, 1, 15, 6),
(2233, 1, 15, 7),
(2234, 1, 15, 8),
(2235, 1, 16, 1),
(2236, 1, 16, 2),
(2237, 1, 16, 4),
(2238, 1, 16, 5),
(2239, 1, 16, 6),
(2240, 1, 16, 7),
(2241, 1, 16, 8),
(2242, 1, 17, 1),
(2243, 1, 17, 2),
(2244, 1, 17, 4),
(2245, 1, 17, 5),
(2246, 1, 17, 6),
(2247, 1, 17, 7),
(2248, 1, 17, 8),
(2249, 2, 1, 1),
(2250, 2, 3, 7),
(2251, 2, 3, 8),
(2252, 3, 4, 1),
(2253, 3, 4, 2),
(2254, 3, 4, 4),
(2255, 3, 4, 5),
(2256, 3, 4, 6),
(2257, 3, 4, 7),
(2258, 3, 4, 8),
(2259, 3, 15, 1),
(2260, 3, 15, 2),
(2261, 3, 15, 4),
(2262, 3, 15, 5),
(2263, 3, 15, 6),
(2264, 3, 15, 7),
(2265, 3, 15, 8),
(2266, 4, 3, 1),
(2267, 4, 3, 2),
(2268, 4, 3, 4),
(2269, 4, 3, 5),
(2270, 4, 3, 6),
(2271, 4, 3, 7),
(2272, 4, 3, 8),
(2273, 4, 17, 7);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `date_created` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('Offline','Online') NOT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0,
  `profile_pic_path` varchar(2048) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `date_created`, `status`, `is_disabled`, `profile_pic_path`) VALUES
(1, 'navithebear', 'navi@example.com', '$2y$12$2esj1uaDmbD3K6Fi.C0CiuOye96x8OjARwTc82ViEAPvmx4b1cL0S', 'navi', 'slu', '2025-02-19 01:19:52', 'Online', 0, 'assets/img/user_images/user_1.gif'),
(2, 'userman', 'um@example.com', '$2y$12$wE3B0Dq4z0Bd1AHXf4gumexeObTqWXm7aASm7PnkCrtiL.iIfObS.', 'user', 'manager', '2025-02-19 05:40:35', 'Online', 0, NULL),
(3, 'equipman', 'em@example.com', '$2y$12$J0iy9bwoalbG2/NkqDZchuLU4sWramGpsw1EsSZ6se0CefM/sqpZq', '123', '123', '2025-02-19 05:40:35', 'Online', 0, NULL),
(4, 'rpman', 'rp@example.com', '$2y$12$dWnJinU4uO7ETYIKi9cL0uN4wJgjACaF.q0Pbkr5yNUK2q1HUQk8G', 'ropriv', 'manager', '2025-02-19 05:41:59', 'Offline', 0, NULL),
(106, 'ttest1', 'test1@example.com', '$2y$10$2bz/ybJjCzyFYEd26NEZr.tsuqUZTpSwQtSTU1IQ8fVHyD2dzjTkO', 'test1', 'test1', '2025-03-21 08:30:58', '', 1, NULL),
(107, 'ttest2', 'test2@example.com', '$2y$10$9uEUFx90zNh3wJmh8deSXenpr6PVopkRfkkzq4PtPAwPFRCx4cecW', 'test2', 'test2', '2025-03-21 08:31:14', '', 1, NULL),
(134, '1123', 'Testertesting123@example.com', '$2y$12$xFf4FmS./UoBc..wijJsUuk8on6EcSeIWiThkd5p5sMdFtoBs23pa', '123', '123', '2025-05-13 13:28:15', '', 1, NULL);

--
-- Triggers `users`
--
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
DELIMITER $$
CREATE TRIGGER `user_after_delete` AFTER DELETE ON `users` FOR EACH ROW BEGIN
    -- Only log if the user was archived (is_disabled = 1)
    IF OLD.is_disabled = 1 THEN
        INSERT INTO audit_log (
            UserID,            -- ID of the user performing the deletion
            EntityID,          -- ID of the deleted user
            Action,            -- Type of action
            Details,           -- Description of the action
            OldVal,            -- Old data as JSON
            NewVal,            -- New data (NULL for deletions)
            Module,            -- Module context
            Status,            -- Status of the action
            Date_Time          -- Timestamp
        ) VALUES (
            @current_user_id,  -- Set this variable before deletion
            OLD.id,            -- The deleted user's ID
            'Delete',          -- Action type
            CONCAT('User permanently deleted: ', OLD.email),
            JSON_OBJECT(       -- Store old user data
                'id', OLD.id,
                'username', OLD.username,
                'email', OLD.email,
                'is_disabled', OLD.is_disabled
            ),
            NULL,              -- No new value for a deletion
            'User Management', -- Module name
            'Successful',      -- Status
            NOW()              -- Current timestamp
        );
    END IF;
END
$$
DELIMITER ;
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
DELIMITER $$
CREATE TRIGGER `user_status_change` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    -- Only log if the status has changed
    IF OLD.status <> NEW.status THEN
        -- When user goes online (offline -> online)
        IF OLD.status = 'offline' AND NEW.status = 'online' THEN
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
                'Login',
                CONCAT(NEW.username, ' is now online.'),
                JSON_OBJECT('status', OLD.status),
                JSON_OBJECT('status', NEW.status),
                'User Management',
                'Successful',
                NOW()
            );
        -- When user goes offline (online -> offline)
        ELSEIF OLD.status = 'online' AND NEW.status = 'offline' THEN
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
                'Logout',
                CONCAT(NEW.username, ' is now offline.'),
                JSON_OBJECT('status', OLD.status),
                JSON_OBJECT('status', NEW.status),
                'User Management',
                'Successful',
                NOW()
            );
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `users_after_create` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    -- Declare variable to hold department name
    DECLARE dept_name VARCHAR(191);

    -- Set default value in case the query doesn't return results
    SET dept_name = 'Unknown';

    -- Attempt to get department name for the user using the correct table
    -- This uses user_department_roles (udr) instead of user_departments (ud)
    SELECT d.department_name INTO dept_name
    FROM departments d
    JOIN user_department_roles udr ON d.id = udr.department_id
    WHERE udr.user_id = NEW.id
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
-- Table structure for table `user_department_roles`
--

CREATE TABLE `user_department_roles` (
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_department_roles`
--

INSERT INTO `user_department_roles` (`user_id`, `department_id`, `role_id`) VALUES
(1, 1, 1),
(1, 29, 4),
(2, 29, 4),
(3, 2, 3),
(4, 5, 5),
(4, 29, 4);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`TrackID`);

--
-- Indexes for table `charge_invoice`
--
ALTER TABLE `charge_invoice`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `po_no` (`po_no`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_name` (`department_name`),
  ADD UNIQUE KEY `abbreviation` (`abbreviation`);

--
-- Indexes for table `equipment_details`
--
ALTER TABLE `equipment_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_tag` (`asset_tag`),
  ADD KEY `invoice_no` (`invoice_no`);

--
-- Indexes for table `equipment_location`
--
ALTER TABLE `equipment_location`
  ADD PRIMARY KEY (`equipment_location_id`),
  ADD UNIQUE KEY `asset_tag` (`asset_tag`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `equipment_status`
--
ALTER TABLE `equipment_status`
  ADD PRIMARY KEY (`equipment_status_id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_name` (`module_name`);

--
-- Indexes for table `privileges`
--
ALTER TABLE `privileges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_order`
--
ALTER TABLE `purchase_order`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_no` (`po_no`);

--
-- Indexes for table `receive_report`
--
ALTER TABLE `receive_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rr_no` (`rr_no`),
  ADD KEY `po_no` (`po_no`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_changes`
--
ALTER TABLE `role_changes`
  ADD PRIMARY KEY (`ChangeID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `RoleID` (`RoleID`);

--
-- Indexes for table `role_module_privileges`
--
ALTER TABLE `role_module_privileges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `module_id` (`module_id`),
  ADD KEY `fk_rmp_privilege` (`privilege_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_department_roles`
--
ALTER TABLE `user_department_roles`
  ADD PRIMARY KEY (`user_id`,`department_id`,`role_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `TrackID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=747;

--
-- AUTO_INCREMENT for table `charge_invoice`
--
ALTER TABLE `charge_invoice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `equipment_details`
--
ALTER TABLE `equipment_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `equipment_location`
--
ALTER TABLE `equipment_location`
  MODIFY `equipment_location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `equipment_status`
--
ALTER TABLE `equipment_status`
  MODIFY `equipment_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `privileges`
--
ALTER TABLE `privileges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `purchase_order`
--
ALTER TABLE `purchase_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `receive_report`
--
ALTER TABLE `receive_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `role_changes`
--
ALTER TABLE `role_changes`
  MODIFY `ChangeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=309;

--
-- AUTO_INCREMENT for table `role_module_privileges`
--
ALTER TABLE `role_module_privileges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2274;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

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
ALTER TABLE `equipment_location`
  ADD CONSTRAINT `equipment_location_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
