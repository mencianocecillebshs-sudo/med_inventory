<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/med_inventory/med_inventory/Admin/api/analytics.php?action=daily_trends&start=2026-08-01&end=2026-08-31';
session_id('debugadmin');
session_start();
$_SESSION['user_id'] = 1;
$_GET['action'] = 'daily_trends';
$_GET['start'] = '2026-08-01';
$_GET['end'] = '2026-08-31';
require __DIR__ . '/analytics.php';
