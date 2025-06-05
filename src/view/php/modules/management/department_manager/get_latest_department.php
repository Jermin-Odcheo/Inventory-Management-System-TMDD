<?php
/**
 * @file get_latest_department.php
 * @brief retrieves the latest department ID from the database and calculates the next available ID
 *
 * This script retrieves the latest department ID from the database and calculates the next available ID.
 * It returns the next ID as a simple output for use in other parts of the application.
 */
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
