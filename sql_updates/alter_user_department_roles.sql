-- Allow NULL values for role_id in user_department_roles table
ALTER TABLE `user_department_roles`
MODIFY `role_id` int NULL,
DROP PRIMARY KEY,
ADD PRIMARY KEY (`user_id`, `department_id`);

-- Update existing triggers to handle NULL role_id
DELIMITER $$

-- If there are triggers that need modification, add them here
-- Example: Update trigger definition based on your database design
/* 
DROP TRIGGER IF EXISTS `trigger_name`;
CREATE TRIGGER `trigger_name` ...
*/

DELIMITER ;

-- Note: You might need to update foreign key constraints if applicable 