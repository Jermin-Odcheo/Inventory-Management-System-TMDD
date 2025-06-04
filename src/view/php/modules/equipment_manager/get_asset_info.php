<?php
/**
 * @file get_asset_info.php
 * @brief Provides asset location and responsible person information via AJAX requests.
 *
 * This script is designed to be called via AJAX. It fetches the `building_loc`,
 * `specific_area`, and `person_responsible` from the `equipment_location` table
 * based on a provided asset tag. It ensures the request is an AJAX request and
 * that the user is authenticated.
 */

session_start(); // Start the PHP session.
require_once('../../../../../config/ims-tmdd.php'); // Include the database connection file, providing the $pdo object.

// Enable error reporting for debugging
ini_set('display_errors', 1); // Display errors on the page.
ini_set('display_startup_errors', 1); // Display startup errors.
error_reporting(E_ALL); // Report all PHP errors.

// Set content type to JSON
header('Content-Type: application/json'); // Set the content type to JSON for responses.

/**
 * @var bool $isAjax Checks if the current request is an AJAX request.
 * This is determined by the 'HTTP_X_REQUESTED_WITH' header.
 */
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

/**
 * If the request is not an AJAX request, respond with a 403 Forbidden status
 * and an error message, then exit.
 */
if (!$isAjax) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Direct access not allowed.']);
    exit;
}

/**
 * Checks if the user is logged in.
 * If no user ID is found in the session, respond with a 401 Unauthorized status
 * and an error message, then exit.
 */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

/**
 * @var string $assetTag Retrieves the asset tag from the GET request.
 * Defaults to an empty string if not set.
 */
$assetTag = $_GET['asset_tag'] ?? '';

/**
 * If the asset tag is empty, return an error message and exit.
 */
if (empty($assetTag)) {
    echo json_encode(['status' => 'error', 'message' => 'Asset tag is required']);
    exit;
}

try {
    // Log the request for debugging
    error_log("Fetching asset info for tag: " . $assetTag);

    // Query the equipment_location table to get location and person responsible
    /**
     * Prepares and executes a SQL statement to fetch location and person responsible
     * from the `equipment_location` table for the given asset tag.
     * It prioritizes active (is_disabled = 0) and most recently created records.
     *
     * @var PDOStatement $stmt The prepared SQL statement object.
     * @var array|false $data The fetched row from `equipment_location`, or false if not found.
     */
    $stmt = $pdo->prepare("SELECT building_loc, specific_area, person_responsible
                          FROM equipment_location
                          WHERE asset_tag = ? AND is_disabled = 0
                          ORDER BY id DESC LIMIT 1");
    $stmt->execute([$assetTag]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        // Log successful data retrieval
        error_log("Asset info found for tag: " . $assetTag);

        // Handle null values to prevent JS errors
        $data['building_loc'] = $data['building_loc'] ?? '';
        $data['specific_area'] = $data['specific_area'] ?? '';
        $data['person_responsible'] = $data['person_responsible'] ?? '';

        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    } else {
        // Log if no location data is found for the asset tag.
        error_log("No location data found for asset tag: " . $assetTag);
        echo json_encode([
            'status' => 'error',
            'message' => 'No location data found for the provided asset tag'
        ]);
    }
} catch (PDOException $e) {
    /**
     * Catches PDO exceptions (database errors), logs the error,
     * and returns an error message.
     */
    error_log('Database error in get_asset_info.php: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
