<?php
header('Content-Type: text/plain');
echo json_encode(['raw' => file_get_contents('php://input')]);
