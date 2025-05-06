<?php
require_once '../../../../../config/ims-tmdd.php';

// Function to output results in a readable format
function printHeader($text) {
    echo "<h3 style='background:#333;color:white;padding:10px;margin-top:20px;'>$text</h3>";
}

// Set content type to HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Department Database Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Department Database Structure Check</h1>
    
    <?php
    try {
        // 1. Check connection
        printHeader("Database Connection");
        echo "<p class='success'>Database connection successful</p>";
        
        // 2. Check departments table structure
        printHeader("Departments Table Structure");
        $columns = $pdo->query("SHOW COLUMNS FROM departments");
        echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        $hasIsDisabled = false;
        while ($column = $columns->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            foreach ($column as $key => $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
            if ($column['Field'] === 'is_disabled') {
                $hasIsDisabled = true;
            }
        }
        echo "</table>";
        
        if (!$hasIsDisabled) {
            echo "<p class='error'>The departments table is missing the 'is_disabled' column!</p>";
            echo "<p>Adding is_disabled column...</p>";
            try {
                $pdo->exec("ALTER TABLE departments ADD COLUMN is_disabled TINYINT(1) NOT NULL DEFAULT 0");
                echo "<p class='success'>Column 'is_disabled' has been added successfully!</p>";
            } catch (PDOException $e) {
                echo "<p class='error'>Error adding is_disabled column: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='success'>The departments table has the required 'is_disabled' column.</p>";
        }
        
        // 3. Check audit_log table structure
        printHeader("Audit Log Table Structure");
        $columns = $pdo->query("SHOW COLUMNS FROM audit_log");
        echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($column = $columns->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            foreach ($column as $key => $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        
        // 4. Check recent audit logs
        printHeader("Recent Department Audit Logs");
        $stmt = $pdo->query("
            SELECT * FROM audit_log 
            WHERE Module = 'Department Management' 
            ORDER BY TrackID DESC LIMIT 10
        ");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($logs) > 0) {
            echo "<table><tr><th>TrackID</th><th>Action</th><th>Details</th><th>Date_Time</th></tr>";
            foreach ($logs as $log) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($log['TrackID']) . "</td>";
                echo "<td>" . htmlspecialchars($log['Action']) . "</td>";
                echo "<td>" . htmlspecialchars($log['Details']) . "</td>";
                echo "<td>" . htmlspecialchars($log['Date_Time']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No department audit logs found.</p>";
        }
        
        // 5. Check departments with is_disabled=1
        printHeader("Archived Departments (is_disabled=1)");
        $stmt = $pdo->query("SELECT * FROM departments WHERE is_disabled = 1");
        $archivedDepts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($archivedDepts) > 0) {
            echo "<table><tr><th>ID</th><th>Abbreviation</th><th>Department Name</th></tr>";
            foreach ($archivedDepts as $dept) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($dept['id']) . "</td>";
                echo "<td>" . htmlspecialchars($dept['abbreviation']) . "</td>";
                echo "<td>" . htmlspecialchars($dept['department_name']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No archived departments found.</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p class='error'>Database error: " . $e->getMessage() . "</p>";
    }
    ?>
</body>
</html> 