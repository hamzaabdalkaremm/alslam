<?php
require_once __DIR__ . '/../../config/bootstrap.php';

Auth::requireLogin();
Auth::requirePermission('reports.view');

$reportService = new ReportService();
$productRows = $reportService->productsExportRows();
$filename = 'products-report-' . date('Y-m-d-His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
echo '<table border="1">';
echo '<thead><tr>';

$headers = [
    'المعرف',
    'الفرع',
    'التصنيف',
    'الوحدة الأساسية',
    'الكود',
    'اسم المنتج',
    'العلامة التجارية',
    'الباركود',
    'رصيد المخزون الحالي',
    'سعر الشراء',
    'سعر الجملة',
    'سعر نصف الجملة',
    'سعر المفرد',
    'حد التنبيه',
    'موقع الرف',
    'بيع بالحبة',
    'بيع بالكرتونة',
    'الحالة',
    'الوحدات والباركودات',
    'ملاحظات',
    'تاريخ الإنشاء',
    'آخر تحديث',
];

foreach ($headers as $headerLabel) {
    echo '<th>' . e($headerLabel) . '</th>';
}

echo '</tr></thead><tbody>';

foreach ($productRows as $row) {
    echo '<tr>';
    echo '<td>' . e((string) ($row['id'] ?? '')) . '</td>';
    echo '<td>' . e((string) ($row['branch_name'] ?? '')) . '</td>';
    echo '<td>' . e((string) ($row['category_name'] ?? '')) . '</td>';
    echo '<td>' . e((string) ($row['base_unit_name'] ?? '')) . '</td>';
    echo '<td>' . e((string) ($row['code'] ?? '')) . '</td>';
    echo '<td>' . e((string) ($row['name'] ?? '')) . '</td>';
    echo '<td>' . e((string) ($row['brand'] ?? '')) . '</td>';
    echo '<td>' . e((string) ($row['barcode'] ?? '')) . '</td>';
    echo '<td>' . e(format_number((float) ($row['stock_balance'] ?? 0), 3)) . '</td>';
    echo '<td>' . e(format_currency((float) ($row['cost_price'] ?? 0))) . '</td>';
    echo '<td>' . e(format_currency((float) ($row['wholesale_price'] ?? 0))) . '</td>';
    echo '<td>' . e(format_currency((float) ($row['half_wholesale_price'] ?? 0))) . '</td>';
    echo '<td>' . e(format_currency((float) ($row['retail_price'] ?? 0))) . '</td>';
    echo '<td>' . e((string) ($row['min_stock_alert'] ?? '0')) . '</td>';
    echo '<td>' . e((string) ($row['shelf_location'] ?? '')) . '</td>';
    echo '<td>' . e(!empty($row['sell_by_piece']) ? 'نعم' : 'لا') . '</td>';
    echo '<td>' . e(!empty($row['sell_by_carton']) ? 'نعم' : 'لا') . '</td>';
    echo '<td>' . e(!isset($row['is_active']) || (int) $row['is_active'] === 1 ? 'فعال' : 'موقوف') . '</td>';
    echo '<td>' . nl2br(e((string) ($row['units_summary'] ?? ''))) . '</td>';
    echo '<td>' . e((string) ($row['notes'] ?? '')) . '</td>';
    echo '<td>' . e((string) ($row['created_at'] ?? '')) . '</td>';
    echo '<td>' . e((string) ($row['updated_at'] ?? '')) . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
