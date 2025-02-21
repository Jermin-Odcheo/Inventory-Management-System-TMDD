<?php
$host = "localhost";
$username = "root";  // Use your actual MySQL username
$password = "";  // If you have a MySQL password, add it here
$database = "ims_tmdd4";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
