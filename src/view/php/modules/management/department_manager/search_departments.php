<?php
/**
 * Department Search Endpoint
 * 
 * This file handles the search functionality for departments.
 * It returns JSON data containing matching departments.
 */

// Start output buffering
ob_start();
session_start();
require_once('../../../../../../config/ims-tmdd.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// Initialize RBAC
$rbac = new RBACService($pdo, $_SESSION['user_id']);

// Check if user has view privilege
if (!$rbac->hasPrivilege('Administration', 'View')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

// Set headers for JSON response
header('Content-Type: application/json');

// Get search term
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    if (!empty($searchTerm)) {
        $searchStmt = $pdo->prepare("
            SELECT id, abbreviation, department_name 
            FROM departments 
            WHERE (department_name LIKE ? OR abbreviation LIKE ?)
            AND is_disabled = 0
            ORDER BY id DESC
        ");
        $searchTerm = "%{$searchTerm}%";
        $searchStmt->execute([$searchTerm, $searchTerm]);
    } else {
        $searchStmt = $pdo->query("
            SELECT id, abbreviation, department_name 
            FROM departments 
            WHERE is_disabled = 0 
            ORDER BY id DESC
        ");
    }
    
    $results = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 