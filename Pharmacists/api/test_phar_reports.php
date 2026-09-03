<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['type'] = 'inventory';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
include 'phar_reports.php';
