<?php
require_once('../../../../../config/ims-tmdd.php');

try {
    // Check if the column exists
    $columnExists = false;
    $columns = $pdo->query("SHOW COLUMNS FROM departments");
    
    while ($column = $columns->fetch(PDO::FETCH_ASSOC)) {
        if ($column['Field'] === 'is_disabled') {
            $columnExists = true;
            break;
        }
    }
    
    // If column doesn't exist, add it
    if (!$columnExists) {
        $pdo->exec("ALTER TABLE departments ADD COLUMN is_disabled TINYINT(1) NOT NULL DEFAULT 0");
        echo "Column 'is_disabled' has been added to the 'departments' table.";
    } else {
        echo "Column 'is_disabled' already exists in the 'departments' table.";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
} 