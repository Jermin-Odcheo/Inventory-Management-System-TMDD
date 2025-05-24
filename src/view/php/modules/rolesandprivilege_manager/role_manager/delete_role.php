<?php
session_start();
require_once('../../../../../../config/ims-tmdd.php'); // Adjust path as needed

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
    // 1) Start transaction
    $pdo->beginTransaction();

    // 2) Fetch the existing role
    $stmt = $pdo->prepare("SELECT id, role_name FROM roles WHERE id = ? AND is_disabled = 0");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        throw new Exception('Role not found or already deleted.');
    }

    // 3) Fetch its modules & privileges
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

    // 6) Soft-delete the role
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

    // 8) Insert into audit_log
    $details = "Role '{$role['role_name']}' has been archived";
    $stmt = $pdo->prepare("
        INSERT INTO audit_log
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

    // 10) Commit and respond
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Role deleted successfully.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit();
?>
