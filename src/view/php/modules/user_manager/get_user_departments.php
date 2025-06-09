<?php
/**
 * Get User Departments Module
 *
 * This file provides functionality to retrieve and display detailed information about user department assignments. It is used to fetch specific user department data from the database, including department details and user assignments. The code ensures that only authorized users can access this information and supports integration with other modules for a comprehensive view of user department assignments.
 *
 * @package    InventoryManagementSystem
 * @subpackage UserManager
 * @author     TMDD Interns 25'
 */
session_start();
require_once '../../../../../config/ims-tmdd.php';

/**
 * Performs authentication check to ensure the user is logged in before accessing department data.
 */
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}
$userId = (int)$userId;

/**
 * Validates the input user ID received via GET request to ensure it is a valid integer.
 */
if (!isset($_GET['user_id']) || !filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}
$targetUserId = (int)$_GET['user_id'];

try {
    /**
     * Fetches the list of departments associated with the target user from the database.
     * Ensures only active departments are retrieved and orders them by name.
     */
    $stmt = $pdo->prepare("
        SELECT 
            d.id, 
            d.department_name AS name, 
            d.abbreviation
        FROM user_department_roles udr
        JOIN departments d ON udr.department_id = d.id
        WHERE udr.user_id = ? AND d.is_disabled = 0
        ORDER BY d.department_name
    ");
    $stmt->execute([$targetUserId]);
    $rawDepartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /**
     * Removes duplicate department entries to ensure each department is listed only once.
     * @var array $uniqueDepartments Stores the unique list of departments.
     * @var array $seenIds Tracks department IDs already processed to avoid duplicates.
     */
    $uniqueDepartments = [];
    $seenIds = [];

    foreach ($rawDepartments as $dept) {
        if (!in_array($dept['id'], $seenIds, true)) {
            $seenIds[] = $dept['id'];
            $uniqueDepartments[] = $dept;
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'departments' => $uniqueDepartments
    ]);

} catch (PDOException $e) {
    error_log('Error fetching user departments: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
