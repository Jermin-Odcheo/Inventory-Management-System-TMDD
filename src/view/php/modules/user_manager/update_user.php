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
    $department= filter_var($_POST['department'] ?? '', FILTER_VALIDATE_INT);
    $password  = $_POST['password'] ?? '';

    if (!$userId || !$email || !$firstName || !$lastName) {
        throw new Exception('Invalid input data');
    }

    // Optional: Check if provided department ID exists
    if ($department) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ?");
        $stmt->execute([$department]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Invalid department ID');
        }
    } else {
        throw new Exception('Department ID is required.');
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

    // Execute Stored Procedure to update user and department
    $stmt = $pdo->prepare("CALL UpdateUserAndDepartment(?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $email,
        $firstName,
        $lastName,
        $hashedPassword,
        'active', // Adjust based on your status logic
        $department,
        $_SESSION['user_id'],
        'User Management'
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
            'department' => $department
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
