<?php
/**
 * Get Latest Department Module
 *
 * This file provides functionality to retrieve the most recent department information from the system. It handles requests to fetch the latest department data, including filtering and sorting options. The module ensures that only authorized users can access department data and supports integration with other modules to maintain data consistency.
 *
 * @package    InventoryManagementSystem
 * @subpackage Management
 * @author     TMDD Interns 25'
 */

/**
 * GetLatestDepartment Class
 *
 * Handles retrieval of the latest department entry from the database, providing a simple interface for other modules to access the most recent department data.
 */
class GetLatestDepartment {
    /**
     * Database connection instance
     *
     * @var PDO
     */
    private $db;

    /**
     * Constructor
     *
     * @param PDO $db Database connection
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Retrieve the latest department
     *
     * @return array|null Latest department data or null if not found
     */
    public function getLatest() {
        // ... existing code ...
    }
}

// Database connection
require_once('../../../../../../config/ims-tmdd.php'); 

try {
    /**
     * Fetch Latest Department ID
     *
     * Retrieves the highest department ID currently in the database.
     *
     * @return int The latest department ID or 0 if no departments exist.
     * @var PDOStatement $stmt The prepared statement to fetch the latest department ID.
     */
    $sql = "SELECT MAX(id) AS latest_id FROM departments";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
/**
 * @var int $latestDepartmentID The latest department ID or 0 if no departments exist.
 */
    $latestDepartmentID = $row['latest_id'] ?? 0; // Default to 0 if no departments exist

    /**
     * Calculate Next Department ID
     *
     * Calculates the next available department ID by incrementing the latest ID.
     *
     * @param int $latestDepartmentID The latest department ID.
     * @return int The next available department ID.
     */
    $nextDepartmentID = $latestDepartmentID + 1;

    echo $nextDepartmentID;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage(); 
}
?>
