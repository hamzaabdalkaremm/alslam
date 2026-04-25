<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireLogin();
Response::json((new DashboardService())->data());
