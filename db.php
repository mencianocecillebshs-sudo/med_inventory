<?php
$host = 'localhost';
$db = 'med_inventory';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


        // DATABASE THINGS

        // Database = med_inventory
        // SecurityManager - username
        // password1 - password
?>