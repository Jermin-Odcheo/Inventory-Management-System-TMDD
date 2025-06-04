<?php
/**
 * @file get_locations.php
 * @brief Provides location and specific area data via AJAX requests for report generation filters.
 *
 * This script handles AJAX requests to fetch distinct building locations and specific areas
 * from the `equipment_location` table. It can filter specific areas by building location
 * and vice-versa, depending on the 'action' parameter.
 */

require_once __DIR__ . '/../../../../../../config/ims-tmdd.php'; // Include the database connection file, providing the $pdo object.

header('Content-Type: application/json'); // Set the content type to JSON for all responses.

try {
    // Ensure PDO database connection is established.
    if (!isset($pdo)) {
        throw new Exception('Database connection not established');
    }

    /**
     * @var string $action The action to perform, retrieved from the GET request.
     * Expected values: 'get_locations', 'get_specific_areas', 'get_locations_for_area'.
     * Defaults to an empty string if not set.
     * @var array $response An array to build the JSON response.
     */
    $action = $_GET['action'] ?? '';
    $response = [];

    /**
     * Handles different actions based on the 'action' GET parameter.
     */
    if ($action === 'get_locations') {
        // Fetches all distinct building locations.
        $stmt = $pdo->query("SELECT DISTINCT building_loc FROM equipment_location ORDER BY building_loc");
        $response['locations'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($action === 'get_specific_areas') {
        // Fetches distinct specific areas, optionally filtered by building location.
        $building_loc = $_GET['building_loc'] ?? '';
        if ($building_loc && $building_loc !== 'all') {
            $stmt = $pdo->prepare("SELECT DISTINCT specific_area FROM equipment_location WHERE building_loc = ? ORDER BY specific_area");
            $stmt->execute([$building_loc]);
            $response['specific_areas'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // If no specific building location or 'all' is selected, fetch all distinct specific areas.
            $stmt = $pdo->query("SELECT DISTINCT specific_area FROM equipment_location ORDER BY specific_area");
            $response['specific_areas'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } elseif ($action === 'get_locations_for_area') {
        // Fetches distinct building locations, optionally filtered by specific area.
        $specific_area = $_GET['specific_area'] ?? '';
        if ($specific_area && $specific_area !== 'all') {
            $stmt = $pdo->prepare("SELECT DISTINCT building_loc FROM equipment_location WHERE specific_area = ? ORDER BY building_loc");
            $stmt->execute([$specific_area]);
            $response['locations'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // If no specific area or 'all' is selected, fetch all distinct building locations.
            $stmt = $pdo->query("SELECT DISTINCT building_loc FROM equipment_location ORDER BY building_loc");
            $response['locations'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } else {
        // Throws an exception for an invalid or unrecognized action.
        throw new Exception('Invalid action');
    }

    // Encode the response array to JSON and output it.
    echo json_encode($response);
} catch (Exception $e) {
    // Catches any exceptions, sets a 500 Internal Server Error status,
    // and returns a JSON error message.
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
