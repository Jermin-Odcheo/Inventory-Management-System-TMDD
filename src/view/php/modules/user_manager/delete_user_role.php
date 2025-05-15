<?php
// save_user_role_delete.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (
    !isset($data['userId']) ||
    !isset($data['roleId']) ||
    !isset($data['departmentId'])
) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$userId       = (int)$data['userId'];
$roleId       = (int)$data['roleId'];
$departmentId = (int)$data['departmentId'];

try {
    $pdo->beginTransaction();

    // 1) delete that one triple
    $del = $pdo->prepare("
        DELETE 
          FROM user_department_roles 
         WHERE user_id = ? 
           AND role_id = ? 
           AND department_id = ?
    ");
    $del->execute([$userId, $roleId, $departmentId]);

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
        $rid = (int)$r['role_id'];
        $did = (int)$r['department_id'];
        $byRole[$rid] ??= [];
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
