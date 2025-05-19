<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
// RBACService.php is already required in config.php - no need to include it again

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
    $username  = trim($_POST['username'] ?? '');
    $firstName = trim($_POST['first_name']);
    $lastName  = trim($_POST['last_name']);
    $departments = isset($_POST['departments']) && is_array($_POST['departments']) ? $_POST['departments'] : [];
    $roles = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : [];
    $password  = $_POST['password'] ?? '';
    
    if (!$userId || !$email || !$firstName || !$lastName) {
        throw new Exception('Invalid input data');
    }

    // Get the current user data to check for changes
    $currentUserStmt = $pdo->prepare("
        SELECT email, username, first_name, last_name
        FROM users
        WHERE id = ?
    ");
    $currentUserStmt->execute([$userId]);
    $currentUser = $currentUserStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current department-role assignments before deleting them
    $currentAssignmentsStmt = $pdo->prepare("
        SELECT department_id, role_id 
        FROM user_department_roles 
        WHERE user_id = ?
    ");
    $currentAssignmentsStmt->execute([$userId]);
    $currentAssignments = $currentAssignmentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map of department_id => role_id for existing assignments
    $existingRoleMap = [];
    foreach ($currentAssignments as $assignment) {
        $existingRoleMap[$assignment['department_id']] = $assignment['role_id'];
    }
    
    // Sort arrays for comparison
    $currentDepartments = array_keys($existingRoleMap);
    sort($currentDepartments);
    $deptIdsToCompare = array_map('intval', $departments);
    sort($deptIdsToCompare);
    
    // Check if any data has changed
    $hasChanges = false;
    
    if ($currentUser['email'] !== $email || 
        $currentUser['username'] !== $username || 
        $currentUser['first_name'] !== $firstName || 
        $currentUser['last_name'] !== $lastName || 
        !empty($password) ||
        $currentDepartments !== $deptIdsToCompare) {
        $hasChanges = true;
    }
    
    // If no changes, return an error
    if (!$hasChanges) {
        echo json_encode([
            'success' => false,
            'message' => 'No changes detected to save'
        ]);
        exit();
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
    
    // If no valid departments, throw an error (departments are required)
    if (empty($validDepartments)) {
        throw new Exception('At least one valid department is required');
    }
    
    // Validate roles (now optional)
    $validRoles = [];
    if (!empty($roles)) {
        foreach ($roles as $roleId) {
            $roleId = filter_var($roleId, FILTER_VALIDATE_INT);
            if ($roleId) {
                // Verify role exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE id = ? AND is_disabled = 0");
                $stmt->execute([$roleId]);
                if ($stmt->fetchColumn() > 0) {
                    $validRoles[] = $roleId;
                }
            }
        }
    }
    // Roles are now optional - removed default role assignment

    // Hash the password only if provided
    $hashedPassword = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : '';

    /* AUDIT LOG - USER MANAGEMENT
     * Check email uniqueness
     * Check if the new email already exists (excluding the current user)
     * Updating a user with existing email address will log and mark the status as 'Failed'
     */
    $dupStmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND id != ?");
    $dupStmt->execute([$email, $userId]);
    if ($dupStmt->rowCount() > 0) {
        // Get the existing user with this email
        $existingUser = $dupStmt->fetch(PDO::FETCH_ASSOC);
        $existingUserId = $existingUser['id'];
        $existingUsername = $existingUser['username'];
        
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

        throw new Exception("Email address already exists for user: " . $existingUsername);
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
            username = ?,
            first_name = ?, 
            last_name = ?, 
            status = ?
        WHERE id = ?
    ");
    $updateUserStmt->execute([
        $email,
        $username,
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
    
    // Update departments and roles (remove all existing and add new ones)
    $deleteDepartmentsStmt = $pdo->prepare("DELETE FROM user_department_roles WHERE user_id = ?");
    $deleteDepartmentsStmt->execute([$userId]);
    
    // Insert new department associations
    $insertStmt = $pdo->prepare("INSERT INTO user_department_roles (user_id, department_id, role_id) VALUES (?, ?, ?)");
    
    foreach ($departments as $deptId) {
        // Check if this department had a previous role assignment
        if (isset($existingRoleMap[$deptId])) {
            // Preserve the existing role for this department
            $insertStmt->execute([$userId, $deptId, $existingRoleMap[$deptId]]);
        } else {
            // New department gets role_id = 0
            $insertStmt->execute([$userId, $deptId, 0]);
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
    // … after inserting user_department_roles …

    // 1) Build list of changed fields
    $oldVals = [];
    $newVals = [];
    $changes = [];

    if ($currentUser['email'] !== $email) {
        $oldVals['email'] = $currentUser['email'];
        $newVals['email'] = $email;
        $changes[] = "email: {$currentUser['email']} → {$email}";
    }
    if ($currentUser['username'] !== $username) {
        $oldVals['username'] = $currentUser['username'];
        $newVals['username'] = $username;
        $changes[] = "username: {$currentUser['username']} → {$username}";
    }
    if ($currentUser['first_name'] !== $firstName) {
        $oldVals['first_name'] = $currentUser['first_name'];
        $newVals['first_name'] = $firstName;
        $changes[] = "first name: {$currentUser['first_name']} → {$firstName}";
    }
    if ($currentUser['last_name'] !== $lastName) {
        $oldVals['last_name'] = $currentUser['last_name'];
        $newVals['last_name'] = $lastName;
        $changes[] = "last name: {$currentUser['last_name']} → {$lastName}";
    }
    if (!empty($password)) {
        // never log raw passwords—mask them
        $oldVals['password'] = '********';
        $newVals['password'] = '********';
        $changes[] = "password updated";
    }
    if ($currentDepartments !== $deptIdsToCompare) {
        // Fetch department names for the audit log instead of just IDs
        $oldDeptNames = [];
        $newDeptNames = [];
        
        // Get all department names in one query
        $allDeptIds = array_merge($currentDepartments, $deptIdsToCompare);
        if (!empty($allDeptIds)) {
            $placeholders = str_repeat('?,', count($allDeptIds) - 1) . '?';
            $deptStmt = $pdo->prepare("SELECT id, department_name FROM departments WHERE id IN ($placeholders)");
            $deptStmt->execute($allDeptIds);
            $deptMap = [];
            
            while ($dept = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
                $deptMap[$dept['id']] = $dept['department_name'];
            }
            
            // Convert IDs to names
            foreach ($currentDepartments as $deptId) {
                $oldDeptNames[] = $deptMap[$deptId] ?? "Department ID: $deptId";
            }
            
            foreach ($deptIdsToCompare as $deptId) {
                $newDeptNames[] = $deptMap[$deptId] ?? "Department ID: $deptId";
            }
        }
        
        $oldDeptStr = implode(', ', $oldDeptNames);
        $newDeptStr = implode(', ', $newDeptNames);
        
        $oldVals['departments'] = $oldDeptStr;
        $newVals['departments'] = $newDeptStr;
        $changes[] = "departments changed from [$oldDeptStr] to [$newDeptStr]";
    }

    $details    = "Updated user information: " . implode(", ", $changes);
    $oldValJson = json_encode($oldVals);
    $newValJson = json_encode($newVals);

    // 2) Insert including OldVal and NewVal
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
        ) VALUES (?, ?, 'modified', ?, ?, ?, 'User Management', 'Success', NOW())
    ");
    $auditStmt->execute([
        $_SESSION['user_id'],
        $userId,
        $details,
        $oldValJson,
        $newValJson
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
