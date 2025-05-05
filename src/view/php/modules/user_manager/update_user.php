<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
require_once('../../clients/admins/RBACService.php');

ob_start();

// JSON Response Headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

try {
    // Validate inputs
    if (!isset($_POST['user_id'], $_POST['email'], $_POST['first_name'], $_POST['last_name'])) {
        throw new Exception('Missing required fields');
    }

    $userId    = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $email     = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $firstName = trim($_POST['first_name']);
    $lastName  = trim($_POST['last_name']);
    $departments = isset($_POST['departments']) && is_array($_POST['departments']) ? $_POST['departments'] : [];
    $password  = $_POST['password'] ?? '';
    
    if (!$userId || !$email || !$firstName || !$lastName) {
        throw new Exception('Invalid input data');
    }

    // Validate departments
    $validDepartments = [];
    if (!empty($departments)) {
        foreach ($departments as $deptId) {
            $deptId = filter_var($deptId, FILTER_VALIDATE_INT);
            if ($deptId) {
                // Verify department exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ? AND is_disabled = 0");
                $stmt->execute([$deptId]);
                if ($stmt->fetchColumn() > 0) {
                    $validDepartments[] = $deptId;
                }
            }
        }
    }

    // Hash the password only if provided
    $hashedPassword = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : '';

    /* AUDIT LOG - USER MANAGEMENT
     * Check email uniqueness
     * Check if the new email already exists (excluding the current user)
     * Updating a user with existing email address will log and mark the status as 'Failed'
     */
    $dupStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $dupStmt->execute([$email, $userId]);
    if ($dupStmt->rowCount() > 0) {
        // Retrieve the current email of the user being modified.
        $currentStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $currentStmt->execute([$userId]);
        $currentUser = $currentStmt->fetch(PDO::FETCH_ASSOC);
        $currentEmail = $currentUser['email'];

        // Prepare JSON objects with the plain email value
        $oldValJson = json_encode(['email' => $currentEmail]);
        $newValJson = json_encode(['email' => $email]);

        // Insert an audit log entry for the duplicate email update attempt.
        // Here, the EntityID is set to the ID of the user being modified.
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
        VALUES (?, ?, 'modified', ?, ?, ?, 'User Management', 'Failed', NOW())
    ");
        $customMessage = 'Attempted to change email from ' . $currentEmail . ' to an existing email: ' . $email;
        $auditStmt->execute([
            $_SESSION['user_id'],
            $userId, // The user being modified
            $customMessage,
            $oldValJson,
            $newValJson
        ]);

        throw new Exception("Email address already exists");
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Set user and module variables for audit (if needed by your stored procedure)
    $pdo->exec("SET @current_user_id = " . intval($_SESSION['user_id']));
    $pdo->exec("SET @current_module = 'User Management'");

    // Update the user basic information
    $updateUserStmt = $pdo->prepare("
        UPDATE users 
        SET email = ?, 
            first_name = ?, 
            last_name = ?, 
            status = ?
        WHERE id = ?
    ");
    $updateUserStmt->execute([
        $email,
        $firstName,
        $lastName,
        'active', // Adjust based on your status logic
        $userId
    ]);
    
    // Update password if provided
    if (!empty($hashedPassword)) {
        $updatePasswordStmt = $pdo->prepare("
            UPDATE users 
            SET password = ? 
            WHERE id = ?
        ");
        $updatePasswordStmt->execute([$hashedPassword, $userId]);
    }
    
    // Update departments (remove all existing and add new ones)
    $deleteDepartmentsStmt = $pdo->prepare("DELETE FROM user_departments WHERE user_id = ?");
    $deleteDepartmentsStmt->execute([$userId]);
    
    // Add new departments
    if (!empty($validDepartments)) {
        $insertDepartmentStmt = $pdo->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
        foreach ($validDepartments as $deptId) {
            $insertDepartmentStmt->execute([$userId, $deptId]);
        }
    }
    
    // Create audit log entry for the update
    $auditStmt = $pdo->prepare("
        INSERT INTO audit_log (
            UserID,
            EntityID,
            Action,
            Details,
            Module,
            `Status`,
            Date_Time
        )
        VALUES (?, ?, 'modified', ?, 'User Management', 'Success', NOW())
    ");
    $details = "Updated user information: " . $email;
    $auditStmt->execute([
        $_SESSION['user_id'],
        $userId,
        $details
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully',
        'data'    => [
            'id'         => $userId,
            'email'      => $email,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'departments' => $validDepartments
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Update user error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
