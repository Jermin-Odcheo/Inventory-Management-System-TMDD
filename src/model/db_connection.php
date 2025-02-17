<?php
// db_connection.php
$host = 'localhost'; // Database host
$username = 'root'; // Database username
$password = ''; // Database password
$database = 'ims_tmdd'; // Database name

// Create a connection to the database
$db = new mysqli($host, $username, $password, $database);

// Check for connection errors
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}
?>
