<?php

/**
 * Retrieves the list of roles associated with a specific user in the Inventory Management System.
 * 
 * This script fetches the roles linked to a user based on their ID provided via GET request.
 * It performs authentication and permission checks to ensure the requester has the necessary access,
 * validates the input user ID, and returns a JSON response with the list of roles associated with the user.
 */
declare(strict_types=1);
require_once '../../../../../config/ims-tmdd.php';
// RBACService.php is already required in config.php - no need to include it again
session_start();

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Set content type
header('Content-Type: application/json');

/**
 * Performs authentication check to ensure the user is logged in before accessing role data.
 */
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

/**
 * Validates the input user ID received via GET request to ensure it is a valid integer.
 */
$targetUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$targetUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

/**
 * Initializes RBAC service and checks if the user has the necessary permission to view user roles.
 */
$rbac = new RBACService($pdo, (int)$userId);
if (!$rbac->hasPrivilege('User Management', 'View')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Fetch the user's roles
try {
    /**
     * Fetches the list of roles associated with the target user from the database.
     */
    $stmt = $pdo->prepare("
        SELECT role_id 
        FROM user_department_roles 
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