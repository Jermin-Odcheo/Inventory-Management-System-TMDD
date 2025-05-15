-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 13, 2025 at 02:15 PM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

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
DROP PROCEDURE IF EXISTS `UpdateUserAndDepartment`$$
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

DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE IF NOT EXISTS `audit_log` (
  `TrackID` int NOT NULL AUTO_INCREMENT,
  `UserID` int NOT NULL,
  `EntityID` int DEFAULT NULL,
  `Action` varchar(255) NOT NULL,
  `Details` text,
  `OldVal` text,
  `NewVal` text,
  `Module` varchar(255) NOT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `Date_Time` datetime NOT NULL,
  PRIMARY KEY (`TrackID`)
) ENGINE=MyISAM AUTO_INCREMENT=565 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(564, 1, 3, 'modified', 'Updated user information: em@example.com', NULL, NULL, 'User Management', 'Success', '2025-05-13 22:14:32');

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `charge_invoice`
--

INSERT INTO `charge_invoice` (`id`, `invoice_no`, `date_of_purchase`, `po_no`, `date_created`, `is_disabled`) VALUES
(9, '12312331', '2025-03-18', '1231231', '2025-03-17 11:20:29', 1);

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
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(20, 'University Registrar\'s Office', 'URO', 1),
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
(34, 'Students\' Residence Hall', 'SRH', 1),
(35, 'Medical Clinic', 'MC', 1),
(36, 'Office for Legal Affairs', 'OLA', 1),
(46, '57877', '9876', 0);

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
  `date_acquired` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `invoice_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `rr_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `accountable_individual` varchar(255) DEFAULT NULL,
  `remarks` text,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  `date_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_tag` (`asset_tag`),
  KEY `invoice_no` (`invoice_no`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipment_details`
--

INSERT INTO `equipment_details` (`id`, `asset_tag`, `asset_description_1`, `asset_description_2`, `specifications`, `brand`, `model`, `serial_number`, `invoice_no`, `rr_no`, `location`, `accountable_individual`, `remarks`, `date_created`, `is_disabled`) VALUES
(5, '5556233', '1231', '1123', '3123', '332123', '31233', '123123', NULL, '3123141', '2312312', '1235123', '1231231', '2025-03-17 15:31:00', 1);

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
  PRIMARY KEY (`equipment_location_id`),
  UNIQUE KEY `asset_tag` (`asset_tag`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipment_location`
--

INSERT INTO `equipment_location` (`equipment_location_id`, `asset_tag`, `building_loc`, `floor_no`, `specific_area`, `person_responsible`, `department_id`, `remarks`, `date_created`, `is_disabled`) VALUES
(1, '2222333111', 'Silang', '2', 'S231', 'Tester', 1, 'N/A', '2025-03-17 15:57:42', 0);

-- --------------------------------------------------------

--
-- Table structure for table `equipment_status`
--

DROP TABLE IF EXISTS `equipment_status`;
CREATE TABLE IF NOT EXISTS `equipment_status` (
  `equipment_status_id` int NOT NULL AUTO_INCREMENT,
  `asset_tag` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `remarks` text,
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_disabled` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`equipment_status_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_order`
--

INSERT INTO `purchase_order` (`id`, `po_no`, `date_of_order`, `no_of_units`, `item_specifications`, `date_created`, `is_disabled`) VALUES
(3, '132457', '2025-03-11', 21345, '2134567', '2025-03-11 11:21:03', 1),
(4, '23123123', '2025-03-11', 123123, '123123123', '2025-03-11 11:38:25', 1),
(5, '1231231', '2025-03-21', 123, '123123', '2025-03-11 11:51:12', 1),
(7, '76543111', '2025-03-14', 2, 'asdwwwqweasd', '2025-03-14 13:51:51', 1),
(8, '52342342342', '2025-03-14', 5, '125y5trhfgd', '2025-03-14 14:04:07', 1),
(10, '5687564352', '2025-03-14', 43, '4tersygchvbn wetzrdxgc', '2025-03-14 14:10:37', 1),
(11, '213123333', '2025-03-14', 2, 'azxcvwe', '2025-03-14 14:15:05', 1),
(12, '456787654345678', '2025-03-15', 5, '12wedfgheqwe', '2025-03-14 14:17:40', 1),
(13, '222231', '2025-03-14', 2, '33331', '2025-03-14 14:20:45', 1),
(14, '9990', '2025-03-14', 6, 'what', '2025-03-14 14:28:11', 1),
(15, '66634123', '2025-03-14', 3123, '123123', '2025-03-14 14:44:52', 1),
(16, '123123', '2025-03-17', 3, 'test123awq', '2025-03-17 08:50:56', 1),
(17, '123412311', '2025-04-02', 333, '333', '2025-03-17 10:20:59', 0),
(18, '312312312312', '2025-03-18', 131223, '12213123', '2025-03-18 09:13:58', 0);

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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=MyISAM AUTO_INCREMENT=279 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(278, 1, 32, 'Add', NULL, 'testsetes', '2025-05-09 15:23:21', NULL, NULL, 0);

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
) ENGINE=InnoDB AUTO_INCREMENT=1324 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(1017, 0, 1, 1),
(1105, 21, 1, 1),
(1109, 20, 1, 1),
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
(1250, 6, 1, 1),
(1287, 1, 1, 7),
(1288, 1, 2, 3),
(1289, 1, 2, 11),
(1290, 1, 2, 10),
(1291, 1, 2, 2),
(1292, 1, 2, 5),
(1293, 1, 2, 6),
(1294, 1, 2, 12),
(1295, 1, 2, 4),
(1296, 1, 2, 8),
(1297, 1, 2, 1),
(1298, 1, 2, 9),
(1299, 1, 2, 7),
(1300, 1, 3, 3),
(1301, 1, 3, 11),
(1302, 1, 3, 10),
(1303, 1, 3, 2),
(1304, 1, 3, 5),
(1305, 1, 3, 6),
(1306, 1, 3, 12),
(1307, 1, 3, 4),
(1308, 1, 3, 8),
(1309, 1, 3, 1),
(1310, 1, 3, 9),
(1311, 1, 3, 7),
(1312, 1, 4, 3),
(1313, 1, 4, 11),
(1314, 1, 4, 10),
(1315, 1, 4, 2),
(1316, 1, 4, 5),
(1317, 1, 4, 6),
(1318, 1, 4, 12),
(1319, 1, 4, 4),
(1320, 1, 4, 8),
(1321, 1, 4, 1),
(1322, 1, 4, 9),
(1323, 1, 4, 7);

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
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `date_created`, `status`, `is_disabled`) VALUES
(1, 'navithebear', 'navi@example.com', '$2y$12$2esj1uaDmbD3K6Fi.C0CiuOye96x8OjARwTc82ViEAPvmx4b1cL0S', 'navi', 'slu', '2025-02-19 01:19:52', 'Online', 0),
(2, 'userman', 'um@example.com', '$2y$12$wE3B0Dq4z0Bd1AHXf4gumexeObTqWXm7aASm7PnkCrtiL.iIfObS.', 'user', 'manager', '2025-02-19 05:40:35', 'Offline', 0),
(3, 'equipman', 'em@example.com', '$2y$12$J0iy9bwoalbG2/NkqDZchuLU4sWramGpsw1EsSZ6se0CefM/sqpZq', '123', '123', '2025-02-19 05:40:35', '', 0),
(4, 'rpman', 'rp@example.com', '$2y$12$dWnJinU4uO7ETYIKi9cL0uN4wJgjACaF.q0Pbkr5yNUK2q1HUQk8G', 'ropriv', 'manager', '2025-02-19 05:41:59', '', 1),
(106, 'ttest1', 'test1@example.com', '$2y$10$2bz/ybJjCzyFYEd26NEZr.tsuqUZTpSwQtSTU1IQ8fVHyD2dzjTkO', 'test1', 'test1', '2025-03-21 08:30:58', '', 1),
(107, 'ttest2', 'test2@example.com', '$2y$10$9uEUFx90zNh3wJmh8deSXenpr6PVopkRfkkzq4PtPAwPFRCx4cecW', 'test2', 'test2', '2025-03-21 08:31:14', '', 1),
(134, '1123', 'Testertesting123@example.com', '$2y$12$xFf4FmS./UoBc..wijJsUuk8on6EcSeIWiThkd5p5sMdFtoBs23pa', '123', '123', '2025-05-13 13:28:15', '', 1);

--
-- Triggers `users`
--
DROP TRIGGER IF EXISTS `after_user_disable`;
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
DROP TRIGGER IF EXISTS `user_after_delete`;
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
DROP TRIGGER IF EXISTS `user_after_restore`;
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
DROP TRIGGER IF EXISTS `user_status_change`;
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
DROP TRIGGER IF EXISTS `users_after_create`;
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

DROP TABLE IF EXISTS `user_department_roles`;
CREATE TABLE IF NOT EXISTS `user_department_roles` (
  `user_id` int NOT NULL,
  `department_id` int NOT NULL,
  `role_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`department_id`,`role_id`),
  KEY `department_id` (`department_id`),
  KEY `role_id` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_department_roles`
--

INSERT INTO `user_department_roles` (`user_id`, `department_id`, `role_id`) VALUES
(1, 1, 1),
(2, 12, 3),
(2, 12, 4),
(2, 12, 5),
(2, 12, 6),
(2, 12, 32),
(2, 24, 4),
(2, 24, 32),
(3, 1, 3),
(3, 3, 3),
(3, 5, 3),
(3, 46, 3),
(134, 5, 3);

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
