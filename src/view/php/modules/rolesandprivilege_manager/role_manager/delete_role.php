<?php
/**
 * Delete Role Script
 *
 * This script handles the deletion of a role from the system. It performs a soft delete by marking the role as disabled,
 * logs the action in the audit log, and returns a JSON response indicating the success or failure of the operation.
 *
 */
session_start();
require_once('../../../../../../config/ims-tmdd.php'); // Adjust path as needed

/**
 * Validate Request Method
 *
 * Ensures that the request method is POST to prevent unauthorized access or incorrect usage.
 *
 * @return void
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

/**
 * Validate Role ID
 *
 * Checks if a role ID is provided in the POST data to ensure the correct role is targeted for deletion.
 *
 * @return void
 */
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'No role ID specified.']);
    exit();
}

$role_id = intval($_POST['id']);
$userId = $_SESSION['user_id'] ?? null;

/**
 * Set Current User ID
 *
 * Sets the current user ID for audit purposes in the database session. If not authenticated, returns an error.
 *
 * @param int|null $userId The ID of the currently logged-in user.
 * @return void
 */
if ($userId) {
    $pdo->exec("SET @current_user_id = " . (int)$userId);
} else {
    $pdo->exec("SET @current_user_id = NULL");
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}
try {
    /**
     * Begin Database Transaction
     *
     * Starts a transaction to ensure data consistency during the deletion process.
     *
     * @return void
     */
    $pdo->beginTransaction();

    /**
     * Fetch Role Details
     *
     * Retrieves the details of the role to be deleted for audit logging purposes.
     *
     * @param int $role_id The ID of the role to fetch.
     * @return array|null The role details if found, null otherwise.
     */
    $stmt = $pdo->prepare("SELECT id, role_name FROM roles WHERE id = ? AND is_disabled = 0");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        throw new Exception('Role not found or already deleted.');
    }

    /**
     * Fetch Role Privileges
     *
     * Retrieves the modules and privileges associated with the role for audit logging.
     *
     * @param int $role_id The ID of the role to fetch privileges for.
     * @return array The list of modules and privileges associated with the role.
     */
    $sql = "
        SELECT 
            m.module_name,
            p.priv_name
        FROM role_module_privileges rmp
        JOIN modules m ON m.id = rmp.module_id
        JOIN privileges p ON p.id = rmp.privilege_id
        WHERE rmp.role_id = ?
        ORDER BY m.module_name, p.priv_name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$role_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4) Build associative array: [ module => [privileges...] ]
    $modulesAndPrivs = [];
    foreach ($rows as $r) {
        $modulesAndPrivs[ $r['module_name'] ][] = $r['priv_name'];
    }

    // 5) Prepare OldVal payload in the shape formatNewValue() expects
    $oldValueArray = [
        'role_id'                => $role['id'],
        'role_name'              => $role['role_name'],
        'modules_and_privileges' => $modulesAndPrivs
    ];
    $oldValue = json_encode($oldValueArray);

    /**
     * Soft Delete Role
     *
     * Marks the role as disabled in the database to perform a soft delete.
     *
     * @param int $role_id The ID of the role to delete.
     * @return bool True on success, false on failure.
     */
    $stmt = $pdo->prepare("UPDATE roles SET is_disabled = 1 WHERE id = ?");
    if (!$stmt->execute([$role_id])) {
        throw new Exception('Failed to archive role.');
    }

    // 7) Build NewVal if you want (optional; formatDetailsAndChanges for 'remove' uses OldVal)
    $stmt = $pdo->prepare("SELECT id, role_name FROM roles WHERE id = ?");
    $stmt->execute([$role_id]);
    $updatedRole = $stmt->fetch(PDO::FETCH_ASSOC);
    $newValueArray = [
        'role_id'                => $updatedRole['id'],
        'role_name'              => $updatedRole['role_name'],
        'modules_and_privileges' => $modulesAndPrivs
    ];
    $newValue = json_encode($newValueArray);

    /**
     * Log Deletion to Audit Log
     *
     * Records the deletion action in the audit log for tracking purposes.
     *
     * @param int $userId The ID of the user performing the deletion.
     * @param int $role_id The ID of the deleted role.
     * @param string $details A description of the action performed.
     * @param string $oldValue The state of the role before deletion.
     * @param string $newValue The state of the role after deletion.
     * @return void
     */
    $details = "Role '{$role['role_name']}' has been archived";
    $stmt = $pdo->prepare("INSERT INTO audit_log
          (UserID, EntityID, Action, Details, OldVal, NewVal, Module, Date_Time, Status)
        VALUES
          (?, ?, 'Remove', ?, ?, ?, 'Roles and Privileges', NOW(), 'Successful')
    ");
    $stmt->execute([
       $userId,
       $role_id,
       $details,
       $oldValue,
       $newValue
    ]);

    // 9) (Optional) legacy compatibility table
    $stmt = $pdo->prepare("
        INSERT INTO role_changes
          (UserID, RoleID, Action, OldRoleName, OldPrivileges)
        VALUES
          (?, ?, 'Delete', ?, ?)
    ");
    $stmt->execute([
       $userId,
       $role_id,
       $role['role_name'],
       $oldValue
    ]);

    /**
     * Commit Transaction
     *
     * Commits the database transaction if all operations are successful.
     *
     * @return void
     */
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Role deleted successfully.']);

} catch (Exception $e) {
    /**
     * Rollback Transaction on Error
     *
     * Rolls back the database transaction if an error occurs during the deletion process.
     *
     * @return void
     */
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit();
?>
