<?php
// Debug medicine API output from CLI with a fake authenticated session.
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['limit'] = 1000;
$_GET['page'] = 1;
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
session_start();
$_SESSION['user_id'] = 1;
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ob_start();
include 'Pharmacists/api/phar_medicines.php';
$output = ob_get_clean();
echo $output;
?>
