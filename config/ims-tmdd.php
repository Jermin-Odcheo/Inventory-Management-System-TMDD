<?php
$host = "127.0.0.1";
$username = "root";
$password = "";  // Change if you have a MySQL password
$database = "ims_tmdd4";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
