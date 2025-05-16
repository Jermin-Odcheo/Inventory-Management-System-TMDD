<?php
// update_user_department.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

// For debugging
$requestData = file_get_contents('php://input');
error_log("Received data: " . $requestData);

$data = json_decode($requestData, true);
if (!isset($data['userId']) || !isset($data['departmentId'])) {
    echo json_encode(['success' => false, 'error' => 'Missing essential parameters']);
    exit;
}

$userId = (int)$data['userId'];
// Treat missing or empty oldRoleId as 0
$oldRoleId = isset($data['oldRoleId']) && $data['oldRoleId'] !== ''
    ? (int)$data['oldRoleId']
    : 0;

// Convert roleIds to integers, using 0 for null or non-numeric
$roleIds = [];
if (isset($data['roleIds']) && is_array($data['roleIds'])) {
    foreach ($data['roleIds'] as $rid) {
        $roleIds[] = is_numeric($rid) ? (int)$rid : 0;
    }
}

$departmentId = (int)$data['departmentId'];

error_log("Processed data: userId=$userId, oldRoleId=$oldRoleId, departmentId=$departmentId, roleIds=" . json_encode($roleIds));

try {
    $pdo->beginTransaction();

    // 1) Remove the old role if it’s no longer in the new list
    if ($oldRoleId !== 0 && !in_array($oldRoleId, $roleIds)) {
        $stmtDeleteRole = $pdo->prepare("
            DELETE FROM user_department_roles
            WHERE user_id = ? AND role_id = ? AND department_id = ?
        ");
        $stmtDeleteRole->execute([$userId, $oldRoleId, $departmentId]);
    }
    // 2) If oldRoleId was “none”, delete any existing zero-role entry
    else if ($oldRoleId === 0) {
        $stmtDeleteZero = $pdo->prepare("
            DELETE FROM user_department_roles
            WHERE user_id = ? AND role_id = 0 AND department_id = ?
        ");
        $stmtDeleteZero->execute([$userId, $departmentId]);
    }

    // 3) If no roles selected at all, ensure a single “0” record exists
    if (empty($roleIds)) {
        // remove everything for this dept
        $stmtDeleteAll = $pdo->prepare("
            DELETE FROM user_department_roles
            WHERE user_id = ? AND department_id = ?
        ");
        $stmtDeleteAll->execute([$userId, $departmentId]);

        // insert the zero-role placeholder
        $stmtInsertZero = $pdo->prepare("
            INSERT INTO user_department_roles (user_id, department_id, role_id)
            VALUES (?, ?, 0)
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
        ");
        $stmtInsertZero->execute([$userId, $departmentId]);
    } else {
        // 4) Remove any existing zero-role so we only have explicit roles
        $stmtDeleteZero = $pdo->prepare("
            DELETE FROM user_department_roles
            WHERE user_id = ? AND role_id = 0 AND department_id = ?
        ");
        $stmtDeleteZero->execute([$userId, $departmentId]);

        // 5) Insert each selected role
        $stmtInsertRole = $pdo->prepare("
            INSERT INTO user_department_roles (user_id, department_id, role_id)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
        ");

        foreach ($roleIds as $roleId) {
            // skip re-inserting the oldRoleId if it’s still valid
            if ($roleId === $oldRoleId) continue;

            // check if already exists
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*) FROM user_department_roles
                WHERE user_id = ? AND role_id = ? AND department_id = ?
            ");
            $stmtCheck->execute([$userId, $roleId, $departmentId]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                $stmtInsertRole->execute([$userId, $departmentId, $roleId]);
            }
        }
    }

    // 6) Build the response: group department_ids by role_id
    $stmtGetAll = $pdo->prepare("
        SELECT role_id, department_id
        FROM user_department_roles
        WHERE user_id = ?
    ");
    $stmtGetAll->execute([$userId]);
    $combinations = $stmtGetAll->fetchAll(PDO::FETCH_ASSOC);

    $roleMap = [];
    foreach ($combinations as $combo) {
        $r = (int)$combo['role_id'];
        $d = (int)$combo['department_id'];
        $roleMap[$r][] = $d;
    }

    $assignments = [];
    foreach ($roleMap as $r => $depts) {
        $assignments[] = [
            'userId'        => $userId,
            'roleId'        => $r,
            'departmentIds' => array_values(array_unique($depts)),
        ];
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'assignments' => $assignments]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
