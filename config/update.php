<?php
// Your current DB connection (already set up)
$host = 'localhost';
$db   = 'ims_tmdd';
$user = 'root'; // default username for localhost
$pass = '';     // default password for localhost
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    throw new PDOException($e->getMessage(), (int)$e->getCode());
}

// Path to your SQL script file
$sqlFile = 'ims_tmdd.sql';

// Check if the file exists
if (!file_exists($sqlFile)) {
    die("SQL file not found: " . $sqlFile);
}

// Load the SQL file contents into a string
$sql = file_get_contents($sqlFile);

// Split the SQL script into individual queries using semicolon as the delimiter
// Note: This is a simple approach and might need adjustment for more complex scripts.
$queries = array_filter(array_map('trim', explode(';', $sql)));

// Execute each SQL query
foreach ($queries as $query) {
    if (!empty($query)) {
        try {
            $pdo->exec($query);
        } catch (PDOException $e) {
            echo "Error executing query: <br><pre>$query</pre><br>" . $e->getMessage() . "<br>";
        }
    }
}

echo "SQL script executed successfully.";
?>
