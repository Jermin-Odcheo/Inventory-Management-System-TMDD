<?php
/**
 * @file asset_tag_info.php
 * @brief Provides asset tag information (location and accountable individual) via AJAX requests.
 *
 * This script is designed to be called via AJAX. It fetches details from either
 * `equipment_location` or `equipment_details` tables based on a provided asset tag.
 * It ensures the request is an AJAX request and that the user is authenticated.
 */

session_start(); // Start the PHP session.
date_default_timezone_set('Asia/Manila'); // Set the default timezone for date/time functions.
require_once('../../../../../config/ims-tmdd.php'); // Include the database connection file, providing the $pdo object.

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
    echo json_encode(['status' => 'error', 'message' => 'Direct access not allowed']);
    exit;
}

/**
 * @var int|null $userId Retrieves the user ID from the session.
 * Defaults to null if not set.
 */
$userId = $_SESSION['user_id'] ?? null;

/**
 * Ensures the user is logged in.
 * If no user ID is found in the session, respond with a 401 Unauthorized status
 * and an error message, then exit.
 */
if (!$userId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

/**
 * Checks if the request method is POST and if the 'action' and 'asset_tag'
 * parameters are provided and valid for fetching asset information.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_asset_info' && isset($_POST['asset_tag'])) {
    /**
     * @var string $assetTag The asset tag provided in the POST request, trimmed of whitespace.
     */
    $assetTag = trim($_POST['asset_tag']);

    /**
     * @var array $response Initializes the response array with a default error status.
     */
    $response = [
        'status' => 'error',
        'message' => 'No data found for the asset tag',
        'data' => null
    ];

    try {
        // First check equipment_location table
        /**
         * Prepares and executes a SQL statement to fetch location and person responsible
         * from the `equipment_location` table for the given asset tag.
         * It prioritizes active (is_disabled = 0) and most recently created records.
         *
         * @var PDOStatement $stmt The prepared SQL statement object.
         * @var array|false $locationData The fetched row from `equipment_location`, or false if not found.
         */
        $stmt = $pdo->prepare("SELECT building_loc, specific_area, person_responsible
                              FROM equipment_location
                              WHERE asset_tag = ? AND is_disabled = 0
                              ORDER BY date_created DESC LIMIT 1");
        $stmt->execute([$assetTag]);
        $locationData = $stmt->fetch(PDO::FETCH_ASSOC);

        /**
         * If data is found in `equipment_location`, format the location and set the response.
         */
        if ($locationData) {
            /**
             * @var string $location Formats the location string, combining building and specific area if both exist.
             */
            $location = '';
            if (!empty($locationData['building_loc']) && !empty($locationData['specific_area'])) {
                $location = $locationData['building_loc'] . ', ' . $locationData['specific_area'];
            } elseif (!empty($locationData['building_loc'])) {
                $location = $locationData['building_loc'];
            } elseif (!empty($locationData['specific_area'])) {
                $location = $locationData['specific_area'];
            }

            $response = [
                'status' => 'success',
                'message' => 'Asset tag information found',
                'data' => [
                    'location' => $location,
                    'accountable_individual' => $locationData['person_responsible'] ?? null
                ]
            ];
        } else {
            // If not found in equipment_location, check equipment_details table
            /**
             * If no data is found in `equipment_location`, prepares and executes a SQL statement
             * to fetch location and accountable individual from the `equipment_details` table.
             * It prioritizes active (is_disabled = 0) and most recently created records.
             *
             * @var PDOStatement $stmt The prepared SQL statement object.
             * @var array|false $detailsData The fetched row from `equipment_details`, or false if not found.
             */
            $stmt = $pdo->prepare("SELECT location, accountable_individual
                                  FROM equipment_details
                                  WHERE asset_tag = ? AND is_disabled = 0
                                  ORDER BY date_created DESC LIMIT 1");
            $stmt->execute([$assetTag]);
            $detailsData = $stmt->fetch(PDO::FETCH_ASSOC);

            /**
             * If data is found in `equipment_details`, set the response.
             */
            if ($detailsData) {
                $response = [
                    'status' => 'success',
                    'message' => 'Asset tag information found',
                    'data' => [
                        'location' => $detailsData['location'] ?? null,
                        'accountable_individual' => $detailsData['accountable_individual'] ?? null
                    ]
                ];
            }
        }
    } catch (PDOException $e) {
        /**
         * Catches PDO exceptions (database errors) and sets an error response.
         */
        $response = [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => null
        ];
    }

    // Return JSON response
    header('Content-Type: application/json'); // Set the content type to JSON.
    echo json_encode($response); // Encode the response array to JSON and output it.
    exit; // Terminate the script.
} else {
    // Invalid request
    /**
     * If the required POST parameters are missing or the action is invalid,
     * respond with a 400 Bad Request status and an error message, then exit.
     */
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request. Required parameters missing.'
    ]);
    exit;
}
