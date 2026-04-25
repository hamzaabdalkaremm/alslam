<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('cashbox.manage');
CSRF::verifyRequest();

$closingDate = $_POST['closing_date'] ?? date('Y-m-d');
$summary = (new CashboxService())->dailySummary($closingDate);
$openingBalance = (float) ($_POST['opening_balance'] ?? 0);
$actualBalance = (float) ($_POST['actual_balance'] ?? 0);
$expected = $openingBalance + $summary['balance'];

$closureId = (new CashboxService())->closeDay([
    'closing_date' => $closingDate,
    'opening_balance' => $openingBalance,
    'cash_in' => $summary['receipts'],
    'cash_out' => $summary['payments'],
    'expected_balance' => $expected,
    'actual_balance' => $actualBalance,
    'variance' => $actualBalance - $expected,
    'notes' => trim($_POST['notes'] ?? ''),
    'closed_by' => Auth::id(),
]);
Response::success('تم إقفال اليومية بنجاح.', ['id' => $closureId]);
