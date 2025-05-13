<?php
 
session_start();
require_once '../../../../../config/ims-tmdd.php';
// 1) Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}
$userId = (int)$userId;
 
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
        SELECT DISTINCT 
            d.id, 
            d.department_name as name,
            d.abbreviation
        FROM user_department_roles udr
        JOIN departments d ON udr.department_id = d.id
        WHERE udr.user_id = ? AND d.is_disabled = 0
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
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
} 