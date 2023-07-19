<?php
// Database configuration
$servername = "server name";
$username = "username";
$password = "password";
$dbname = "database name";

// Create a new connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
