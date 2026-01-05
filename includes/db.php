<?php
$host = "localhost";
$dbname = "relaxo_wears";
$username = "root";
$password = "Fortune_12012006";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
