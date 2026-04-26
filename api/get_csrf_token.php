<?php
require_once __DIR__ . '/../config/bootstrap.php';
header('Content-Type: application/json');
echo json_encode(['csrf_token' => CSRF::generate()]);