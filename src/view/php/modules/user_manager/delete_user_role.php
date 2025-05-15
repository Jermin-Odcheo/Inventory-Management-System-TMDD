<?php
// save_user_role_delete.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (
    !isset($data['userId']) ||
    !isset($data['departmentId'])
) {
    echo json_encode(['success' => false, 'error' => 'Missing essential parameters']);
    exit;
}

$userId       = (int)$data['userId'];
$roleId       = isset($data['roleId']) && is_numeric($data['roleId'])
    ? (int)$data['roleId']
    : null;
$departmentId = (int)$data['departmentId'];

try {
    $pdo->beginTransaction();

    // 1) delete the specific (user, dept, role) triple
    if ($roleId === null) {
        $del = $pdo->prepare("
            DELETE FROM user_department_roles
             WHERE user_id       = ?
               AND department_id = ?
               AND role_id       IS NULL
        ");
        $del->execute([$userId, $departmentId]);
    } else {
        $del = $pdo->prepare("
            DELETE FROM user_department_roles
             WHERE user_id       = ?
               AND department_id = ?
               AND role_id       = ?
        ");
        $del->execute([$userId, $departmentId, $roleId]);
    }

    // 1a) if that was the last row for this (user, department),
    //      re-insert a NULL-role row so the dept still shows up
    $check = $pdo->prepare("
        SELECT COUNT(*) AS cnt
          FROM user_department_roles
         WHERE user_id       = ?
           AND department_id = ?
    ");
    $check->execute([$userId, $departmentId]);
    if ((int)$check->fetchColumn() === 0) {
        $ins = $pdo->prepare("
            INSERT INTO user_department_roles
               (user_id, department_id, role_id)
            VALUES (?, ?, NULL)
        ");
        $ins->execute([$userId, $departmentId]);
    }

    // 2) re-fetch everything for this user
    $fetch = $pdo->prepare("
        SELECT role_id, department_id
          FROM user_department_roles
         WHERE user_id = ?
    ");
    $fetch->execute([$userId]);
    $rows = $fetch->fetchAll(PDO::FETCH_ASSOC);

    // 3) group department_ids by role_id
    $byRole = [];
    foreach ($rows as $r) {
        $rid = $r['role_id'] !== null ? (int)$r['role_id'] : null;
        $did = (int)$r['department_id'];

        if (!isset($byRole[$rid])) {
            $byRole[$rid] = [];
        }
        if (!in_array($did, $byRole[$rid], true)) {
            $byRole[$rid][] = $did;
        }
    }

    // 4) build result array
    $assignments = [];
    foreach ($byRole as $rid => $dids) {
        $assignments[] = [
            'userId'        => $userId,
            'roleId'        => $rid,
            'departmentIds' => $dids,
        ];
    }

    $pdo->commit();

    echo json_encode([
        'success'     => true,
        'assignments' => $assignments
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error'   => 'DB error: ' . $e->getMessage()
    ]);
}
