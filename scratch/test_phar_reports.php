<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['type'] = 'inventory';
session_start();
$_SESSION['user_id'] = 1;
include 'Pharmacists/api/phar_reports.php';
