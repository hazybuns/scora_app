<?php
// config.php

header("Access-Control-Allow-Origin: *"); // Allow all origins for development. Restrict this in production!
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$host = "localhost";
$db_name = "scora_db"; // Your database name
$username = "root";    // Your MySQL username
$password = "";        // Your MySQL password (empty for XAMPP default)

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Connected successfully"; // Uncomment for testing connection
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>