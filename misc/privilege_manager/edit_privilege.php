<?php
/**
 * @file edit_privilege.php
 * @brief Handles the update of a privilege name in the database via an AJAX request.
 *
 * This script expects a POST request containing the 'id' of the privilege to be updated
 * and the new 'priv_name'. It returns a JSON response indicating success or failure
 * of the update operation.
 */

require_once('../../../../../../config/ims-tmdd.php'); // Include the database connection file, providing the $pdo object.
session_start(); // Start the PHP session.

header('Content-Type: application/json'); // Set the content type to JSON for all responses.

/**
 * @var string $id The ID of the privilege to be updated, retrieved from the POST request.
 * Defaults to an empty string if not set.
 * @var string $privName The new privilege name, retrieved and trimmed from the POST request.
 * Defaults to an empty string if not set.
 */
$id = $_POST['id'] ?? '';
$privName = trim($_POST['priv_name'] ?? '');

/**
 * Checks if both 'id' and 'priv_name' are provided and not empty.
 * If any required data is missing, returns a JSON error message and exits.
 */
if (!$id || $privName === '') {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

/**
 * Prepares and executes a SQL statement to update the `priv_name` for the privilege
 * with the given ID in the `privileges` table.
 *
 * @var PDOStatement $stmt The prepared SQL statement object.
 */
$stmt = $pdo->prepare("UPDATE privileges SET priv_name = ? WHERE id = ?");

/**
 * Executes the update query.
 * If the execution is successful, returns a JSON success response.
 * Otherwise, returns a JSON error message.
 */
if ($stmt->execute([$privName, $id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
