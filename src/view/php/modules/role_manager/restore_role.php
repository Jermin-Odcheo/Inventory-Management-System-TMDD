<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Ensure the request method is POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Check if the role ID is provided in the POST data.
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'No role ID specified.']);
    exit();
}

$role_id = intval($_POST['id']);
$userId = $_SESSION['user_id'] ?? null;

// Set the user ID for audit purposes
if ($userId) {
    $pdo->exec("SET @current_user_id = " . (int)$userId);
} else {
    $pdo->exec("SET @current_user_id = NULL");
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Verify that the role exists and is currently disabled.
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ? AND is_disabled = 1");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'Role not found or is not archived.']);
        exit();
    }

    // Store role data for audit log
    $oldValue = json_encode($role);

    // Restore the role by setting is_disabled to 0
    $stmt = $pdo->prepare("UPDATE roles SET is_disabled = 0 WHERE id = ?");
    if ($stmt->execute([$role_id])) {
        // Get updated role data for audit log
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $updatedRole = $stmt->fetch(PDO::FETCH_ASSOC);
        $newValue = json_encode($updatedRole);

        // Log the action in the audit_log table
        $stmt = $pdo->prepare("INSERT INTO audit_log 
            (UserID, EntityID, Action, Details, OldVal, NewVal, Module, Date_Time, Status) 
            VALUES (?, ?, 'Restore', ?, ?, ?, 'Roles and Privileges', NOW(), 'Successful')");
        $stmt->execute([
            $userId,
            $role_id,
            "Role '{$role['role_name']}' has been restored",
            $oldValue,
            $newValue
        ]);

        // Log the restoration action in the role_changes table (keep for compatibility)
        $stmt = $pdo->prepare("INSERT INTO role_changes (UserID, RoleID, Action, OldRoleName, NewRoleName) VALUES (?, ?, 'Restore', ?, ?)");
        $stmt->execute([
            $userId,
            $role_id,
            $role['role_name'],
            $role['role_name']
        ]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Role restored successfully.']);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to restore the role. Please try again.']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => "Database error: " . $e->getMessage()]);
}

exit();
?> 