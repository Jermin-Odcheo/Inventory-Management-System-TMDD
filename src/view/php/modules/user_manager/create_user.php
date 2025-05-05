<?php
// create_user.php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Set header for JSON response
header('Content-Type: application/json');

// Set audit log variables
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
        // Retrieve form values
        $email = trim($_POST['email']);
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $password = $_POST['password'];
        $departmentID = $_POST['department'];
        $customDept = trim($_POST['custom_department'] ?? '');
        $departments = isset($_POST['departments']) && is_array($_POST['departments']) ? $_POST['departments'] : [];
        $roles = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : [];

        // Validate required fields
        $errors = [];
        if (empty($email)) $errors[] = "Email is required";
        if (empty($firstName)) $errors[] = "First name is required";
        if (empty($lastName)) $errors[] = "Last name is required";
        if (empty($password)) $errors[] = "Password is required";
        if (empty($departments) && empty($departmentID)) $errors[] = "At least one department is required";
        if (empty($roles)) $errors[] = "At least one role must be selected";

        // Handle single department selection (for backward compatibility)
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

        /* AUDIT LOG - USER MANAGEMENT
         * Check email uniqueness
         * Creating a user with existing email address will log and mark the status as 'Failed'
         */
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            // Get the existing user record (the conflicting record)
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
            $existingUserId = $existingUser['id'];

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

            throw new Exception("Email address already exists");
        }
        $pdo->beginTransaction();

        // Create user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users 
            (username, email, password, first_name, last_name, date_created) 
            VALUES (?, ?, ?, ?, ?, NOW())");

        $username = strtolower($firstName[0] . $lastName);
        $stmt->execute([
            $username,
            $email,
            $hashedPassword,
            $firstName,
            $lastName
        ]);
        $userID = $pdo->lastInsertId();

        // Add department associations
        $stmt = $pdo->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
        foreach ($departments as $deptId) {
            $deptId = filter_var($deptId, FILTER_VALIDATE_INT);
            if ($deptId) {
                $stmt->execute([$userID, $deptId]);
            }
        }
        
        // Add role associations
        $insertRoleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($roles as $roleId) {
            $roleId = filter_var($roleId, FILTER_VALIDATE_INT);
            if ($roleId) {
                $insertRoleStmt->execute([$userID, $roleId]);
            }
        }

        $pdo->commit();

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
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Return data for form initialization
echo json_encode([
    'departments' => $departments
]);

ob_end_flush();