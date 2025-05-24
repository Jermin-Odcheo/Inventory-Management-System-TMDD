-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 24, 2025 at 05:33 AM
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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
