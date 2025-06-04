<?php
/**
 * @file delete_privilege.php
 * @brief Handles the deletion of a privilege from the database via an AJAX request.
 *
 * This script expects a POST request containing the 'id' of the privilege to be deleted.
 * It returns a JSON response indicating success or failure of the deletion operation.
 */

require_once('../../../../../../config/ims-tmdd.php'); // Include the database connection file, providing the $pdo object.
session_start(); // Start the PHP session.

header('Content-Type: application/json'); // Set the content type to JSON for all responses.

/**
 * @var string $id The ID of the privilege to be deleted, retrieved from the POST request.
 * Defaults to an empty string if not set.
 */
$id = $_POST['id'] ?? '';

/**
 * Checks if the 'id' is provided. If not, returns a JSON error message and exits.
 */
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

/**
 * Prepares and executes a SQL statement to delete the privilege with the given ID from the `privileges` table.
 *
 * @var PDOStatement $stmt The prepared SQL statement object.
 */
$stmt = $pdo->prepare("DELETE FROM privileges WHERE id = ?");

/**
 * Executes the deletion query.
 * If the execution is successful, returns a JSON success response.
 * Otherwise, returns a JSON error message.
 */
if ($stmt->execute([$id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
}
