<?php
declare(strict_types=1);
require_once '../../../../../config/ims-tmdd.php';
session_start();

// 1) Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}
$userId = (int)$userId;

// 2) Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('User Management', 'View');

// 3) Validate input
if (!isset($_GET['user_id']) || !filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}
$targetUserId = (int)$_GET['user_id'];

// 4) Fetch user departments
try {
    $stmt = $pdo->prepare("
        SELECT ud.department_id as id, d.department_name as name
        FROM user_departments ud
        JOIN departments d ON ud.department_id = d.id
        WHERE ud.user_id = ? AND d.is_disabled = 0
        ORDER BY d.department_name
    ");
    $stmt->execute([$targetUserId]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'departments' => $departments
    ]);
    
} catch (PDOException $e) {
    error_log('Error fetching user departments: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} 