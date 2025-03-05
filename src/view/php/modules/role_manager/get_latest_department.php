<?php
// Database connection
require_once('../../../../../config/ims-tmdd.php'); 

try {
    // Get the latest Department_ID
    $sql = "SELECT MAX(Department_ID) AS latest_id FROM departments";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $latestDepartmentID = $row['latest_id'] ?? 0; // Default to 0 if no departments exist
    $nextDepartmentID = $latestDepartmentID + 1;

    echo $nextDepartmentID;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage(); 
}
?>
