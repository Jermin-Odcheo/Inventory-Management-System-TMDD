<?php
declare(strict_types=1);
require_once '../../../../../config/ims-tmdd.php';
require_once '../../clients/admins/RBACService.php';
session_start();

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Set content type
header('Content-Type: application/json');

// Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Validate input
$targetUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$targetUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

// Init RBAC & enforce "View" permission
$rbac = new RBACService($pdo, (int)$userId);
if (!$rbac->hasPrivilege('User Management', 'View')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Fetch the user's roles
try {
    $stmt = $pdo->prepare("
        SELECT role_id 
        FROM user_roles 
        WHERE user_id = ?
    ");
    $stmt->execute([(int)$targetUserId]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'roles' => $roles
    ]);
} catch (PDOException $e) {
    error_log('Error fetching user roles: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error fetching user roles']);
} 