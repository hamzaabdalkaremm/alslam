<?php

$appConfig = require_once __DIR__ . '/app.php';
$dbConfig = require_once __DIR__ . '/database.php';
$moduleConfig = require_once __DIR__ . '/modules.php';

date_default_timezone_set($appConfig['timezone']);

require_once __DIR__ . '/../includes/core/Session.php';
require_once __DIR__ . '/../includes/core/Database.php';
require_once __DIR__ . '/../includes/core/CSRF.php';
require_once __DIR__ . '/../includes/core/Response.php';
require_once __DIR__ . '/../includes/core/Validator.php';
require_once __DIR__ . '/../includes/core/Auth.php';
require_once __DIR__ . '/../includes/core/ErrorHandler.php';
require_once __DIR__ . '/../includes/repositories/CrudRepository.php';
require_once __DIR__ . '/../includes/repositories/DashboardRepository.php';
require_once __DIR__ . '/../includes/services/CrudService.php';
require_once __DIR__ . '/../includes/services/DashboardService.php';
require_once __DIR__ . '/../includes/services/InventoryService.php';
require_once __DIR__ . '/../includes/services/SalesService.php';
require_once __DIR__ . '/../includes/services/PurchaseService.php';
require_once __DIR__ . '/../includes/services/DebtService.php';
require_once __DIR__ . '/../includes/services/CashboxService.php';
require_once __DIR__ . '/../includes/services/ReportService.php';
require_once __DIR__ . '/../includes/services/AccountingService.php';
require_once __DIR__ . '/../includes/helpers/functions.php';

if (PHP_SAPI !== 'cli' && ob_get_level() === 0) {
    ob_start();
}

ErrorHandler::register($appConfig);
Session::start($appConfig);
Database::boot($dbConfig);
Auth::boot();
