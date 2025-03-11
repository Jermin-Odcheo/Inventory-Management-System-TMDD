<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['module_id'])) {
    echo json_encode(['success' => false, 'message' => 'No module_id provided.']);
    exit;
}

$moduleId = (int) $_GET['module_id'];

try {
    // Select all privileges and group by p.id to avoid duplicates.
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.priv_name,
            MAX(CASE WHEN mp.privilege_id IS NOT NULL THEN 1 ELSE 0 END) AS assigned
        FROM privileges p
        LEFT JOIN role_module_privileges mp 
            ON p.id = mp.privilege_id AND mp.module_id = :module_id
        GROUP BY p.id, p.priv_name
        ORDER BY p.priv_name
    ");
    $stmt->execute(['module_id' => $moduleId]);
    $privileges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert assigned field to boolean
    foreach ($privileges as &$priv) {
        $priv['assigned'] = (bool)$priv['assigned'];
    }
    unset($priv);

    echo json_encode([
        'success' => true,
        'privileges' => $privileges
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
?>
