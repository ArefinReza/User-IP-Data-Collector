<?php
// db_config.php
$servername = "localhost";     // Database server (localhost if local)
$username = "root";            // Database username
$password = "";                // Database password
$dbname = "ipcollection";     // Name of your database

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
