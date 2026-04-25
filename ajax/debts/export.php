<?php
require_once __DIR__ . '/../../config/bootstrap.php';

Auth::requireLogin();
Auth::requirePermission('debts.view');

$debtView = (string) request_input('debt_view', 'customer');
if (!in_array($debtView, ['customer', 'supplier', 'marketer'], true)) {
    $debtView = 'customer';
}

$debtService = new DebtService();
$rows = $debtView === 'supplier'
    ? $debtService->supplierDebts()
    : ($debtView === 'marketer' ? $debtService->marketerDebts() : $debtService->customerDebts());

$filename = 'debts-' . $debtView . '-' . date('Y-m-d-His') . '.xls';
$title = $debtView === 'supplier' ? 'ديون الموردين' : ($debtView === 'marketer' ? 'ديون المسوقين' : 'ديون العملاء');
$partyLabel = $debtView === 'supplier' ? 'المورد' : ($debtView === 'marketer' ? 'المسوق' : 'العميل');
$dateLabel = $debtView === 'supplier' ? 'تاريخ الشراء' : 'تاريخ البيع';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
echo '<table border="1">';
echo '<thead>';
echo '<tr><th colspan="8">' . e($title) . '</th></tr>';
echo '<tr>';
$headers = [
    'المعرف',
    'رقم الفاتورة',
    $partyLabel,
    $dateLabel,
    'الإجمالي',
    'المسدّد',
    'المتبقي',
    'نوع القائمة',
];
foreach ($headers as $headerLabel) {
    echo '<th>' . e($headerLabel) . '</th>';
}
echo '</tr>';
echo '</thead><tbody>';

foreach ($rows as $row) {
    echo '<tr>';
    echo '<td>' . e((string) ($row['id'] ?? '')) . '</td>';
    echo '<td>' . e((string) ($row['invoice_no'] ?? '')) . '</td>';
    echo '<td>' . e((string) ($row['party_name'] ?? '-')) . '</td>';
    echo '<td>' . e((string) ($debtView === 'supplier' ? ($row['purchase_date'] ?? '') : ($row['sale_date'] ?? ''))) . '</td>';
    echo '<td>' . e(format_currency((float) ($row['total_amount'] ?? 0))) . '</td>';
    echo '<td>' . e(format_currency((float) ($row['paid_amount'] ?? 0))) . '</td>';
    echo '<td>' . e(format_currency((float) ($row['due_amount'] ?? 0))) . '</td>';
    echo '<td>' . e($title) . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
