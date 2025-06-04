<?php
/**
 * Creates a new user in the Inventory Management System.
 * 
 * This script handles the creation of a new user by processing form data submitted via POST request.
 * It validates input fields such as email, username, password, and department assignments, checks for
 * uniqueness of email and username, logs the action in an audit log, and returns a JSON response indicating
 * the success or failure of the operation. The script also supports custom department creation and handles
 * multiple department and role assignments.
 */
// create_user.php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Suppress warnings in output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Set header for JSON response
header('Content-Type: application/json');

/**
 * Sets up audit log variables for tracking user creation actions.
 */
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// Fetch available departments from database
$departments = [];
$deptStmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_disabled = 0");
while ($dept = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
    $departments[$dept['id']] = $dept['department_name'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    try {
        /**
         * Retrieves and validates form data for creating a new user.
         * @var string $email The email address of the new user.
         * @var string $firstName The first name of the new user.
         * @var string $lastName The last name of the new user.
         * @var string $password The password for the new user.
         * @var string $username The username for the new user.
         * @var string|null $departmentID The ID of a single department (for backward compatibility).
         * @var string $customDept The name of a custom department if provided.
         * @var array $departments The list of department IDs for the new user.
         * @var array $roles The list of role IDs for the new user.
         */
        $email = trim($_POST['email']);
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $password = $_POST['password'];
        $username = trim($_POST['username']);
        $departmentID = isset($_POST['department']) ? $_POST['department'] : null;
        $customDept = trim($_POST['custom_department'] ?? '');
        $departments = isset($_POST['departments']) && is_array($_POST['departments']) ? $_POST['departments'] : [];
        $roles = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : [];

        // Validate required fields
        $errors = [];
        if (empty($email)) $errors[] = "Email is required";
        if (empty($firstName)) $errors[] = "First name is required";
        if (empty($lastName)) $errors[] = "Last name is required";
        if (empty($password)) $errors[] = "Password is required";
        if (empty($username)) $errors[] = "Username is required";
        if (empty($departments) && empty($departmentID)) $errors[] = "At least one department is required";

        /**
         * Handles the case of a single department selection or custom department creation.
         */
        if (!empty($departmentID) && empty($departments)) {
            if ($departmentID === 'custom' && !empty($customDept)) {
                // Check if custom department exists
                $stmt = $pdo->prepare("SELECT id FROM departments WHERE department_name = ?");
                $stmt->execute([$customDept]);
                $existingDept = $stmt->fetch();

                if ($existingDept) {
                    $departments[] = $existingDept['id'];
                } else {
                    // Insert new department
                    $stmt = $pdo->prepare("INSERT INTO departments (department_name) VALUES (?)");
                    $stmt->execute([$customDept]);
                    $departments[] = $pdo->lastInsertId();
                }
            } else {
                $departments[] = $departmentID;
            }
        }

        if (!empty($errors)) {
            throw new Exception(implode("<br>", $errors));
        }

        /**
         * Checks for duplicate email addresses to ensure uniqueness before user creation.
         * Logs an audit entry if a duplicate is found and throws an exception.
         */
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? and is_disabled = 0");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            // Get the existing user record (the conflicting record)
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
            $existingUserId = $existingUser['id'];
            $existingUsername = $existingUser['username'];

            // Prepare a JSON object that includes the attempted email address without extra labels
            $newValJson = json_encode(['email' => $email]);

            // Insert an audit log entry for the duplicate email creation attempt.
            $auditStmt = $pdo->prepare("
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
        VALUES (?, ?, 'create', ?, NULL, ?, 'User Management', 'Failed', NOW())
    ");
            // Custom details message explaining the failure
            $customMessage = 'Attempted to create user with existing email: ' . $email;
            $auditStmt->execute([
                $_SESSION['user_id'],
                $existingUserId, // The conflicting user's ID
                $customMessage,
                $newValJson
            ]);

            throw new Exception("Email address already exists for user: " . $existingUsername);
        }

        /**
         * Checks for duplicate usernames to ensure uniqueness before user creation.
         * Logs an audit entry if a duplicate is found and throws an exception.
         */
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? and is_disabled = 0");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            // Log the duplicate username attempt
            $auditStmt = $pdo->prepare("
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
                VALUES (?, NULL, 'create', ?, NULL, ?, 'User Management', 'Failed', NOW())
            ");
            $customMessage = 'Attempted to create user with existing username: ' . $username;
            $newValJson = json_encode(['username' => $username]);
            $auditStmt->execute([
                $_SESSION['user_id'],
                $customMessage,
                $newValJson
            ]);

            throw new Exception("Username is already taken. Please try a different username.");
        }

        /**
         * Begins a database transaction to ensure data consistency during user creation and department/role assignments.
         */
        $pdo->beginTransaction();

        // Create user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users 
            (username, email, password, first_name, last_name, date_created) 
            VALUES (?, ?, ?, ?, ?, NOW())");

        $stmt->execute([
            $username,
            $email,
            $hashedPassword,
            $firstName,
            $lastName
        ]);
        $userID = $pdo->lastInsertId();

        // Add department associations
        try {
            $stmt = $pdo->prepare("INSERT INTO user_department_roles (user_id, department_id, role_id) VALUES (?, ?, ?)");
            
            // If no departments were selected, set a default department
            if (empty($departments)) {
                // Use a default department (e.g., ID 1) with role_id = 0 (not null)
                $stmt->execute([$userID, 1, 0]);
            } else {
                // Add each department with each role
                foreach ($departments as $deptId) {
                    $deptId = filter_var($deptId, FILTER_VALIDATE_INT);
                    if ($deptId) {
                        // Check if roles are provided
                        if (!empty($roles)) {
                            foreach ($roles as $roleId) {
                                $roleId = filter_var($roleId, FILTER_VALIDATE_INT);
                                if ($roleId) {
                                    $stmt->execute([$userID, $deptId, $roleId]);
                                }
                            }
                        } else {
                            // No roles provided, assign department with role_id = 0 (not null)
                            $stmt->execute([$userID, $deptId, 0]);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            // Log the specific SQL error for debugging
            error_log("SQL Error in create_user.php: " . $e->getMessage());
            error_log("SQL State: " . $e->errorInfo[0]);
            error_log("SQL Code: " . $e->errorInfo[1]);
            error_log("SQL Message: " . $e->errorInfo[2]);
            
            // Check for duplicate username constraint violation
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'username') !== false) {
                throw new Exception("Username is already taken. Please choose a different username.");
            } else {
                throw new Exception("Database error: " . $e->getMessage());
            }
        }

        $pdo->commit();

        /**
         * Creates a success audit log entry with department information after successful user creation.
         */
        try {
            // Get department names for the audit log
            $deptNames = [];
            if (!empty($departments)) {
                $placeholders = str_repeat('?,', count($departments) - 1) . '?';
                $deptStmt = $pdo->prepare("SELECT id, department_name FROM departments WHERE id IN ($placeholders)");
                $deptStmt->execute($departments);
                
                while ($dept = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
                    $deptNames[] = $dept['department_name'];
                }
            }
            
            $deptStr = implode(', ', $deptNames);
            
            // Create new value object for audit log
            $newUserData = [
                'email' => $email,
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'departments' => $deptStr
            ];
            
            // Check if there's already an audit log entry for this user creation
            $checkAuditStmt = $pdo->prepare("
                SELECT COUNT(*) FROM audit_log 
                WHERE EntityID = ? AND Action = 'create' AND Module = 'User Management'
                AND Date_Time >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
            ");
            $checkAuditStmt->execute([$userID]);
            $hasExistingLog = $checkAuditStmt->fetchColumn() > 0;
            
            // Only create an audit log if there isn't already one from a trigger
            if (!$hasExistingLog) {
                // Insert audit log entry
                $auditStmt = $pdo->prepare("
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
                    VALUES (?, ?, 'create', ?, NULL, ?, 'User Management', 'Successful', NOW())
                ");
                
                $details = "Created new user with departments: " . $deptStr;
                $newValJson = json_encode($newUserData);
                
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $userID,
                    $details,
                    $newValJson
                ]);
            }
        } catch (Exception $auditEx) {
            // Just log any errors with the audit, don't throw
            error_log("Error creating audit log: " . $auditEx->getMessage());
        }

        // Success response
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $userID
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Update user error: " . $e->getMessage());
        
        // Check for duplicate username error
        if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'username') !== false) {
            echo json_encode([
                'success' => false,
                'message' => 'Username already exists. Please choose a different username.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
    exit();
}

// Return data for form initialization
echo json_encode([
    'departments' => $departments
]);

ob_end_flush();