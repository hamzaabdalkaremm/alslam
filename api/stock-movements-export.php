<?php
require_once __DIR__ . '/../config/bootstrap.php';

Auth::requireLogin();
Auth::requirePermission('reports.view');

$inventoryService = new InventoryService();

// Get filter parameters
$productId = (int) ($_GET['product_id'] ?? 0);
$warehouseId = (int) ($_GET['warehouse_id'] ?? 0);
$movementType = trim((string) ($_GET['movement_type'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

$isSuperAdmin = Auth::isSuperAdmin();
$accessibleBranchIds = Auth::branchIds();

// Build query
$select = [
    'sm.id',
    'sm.movement_date',
    'sm.movement_type',
    'sm.source_type',
    'sm.source_id',
    'sm.quantity_in',
    'sm.quantity_out',
    'sm.unit_cost',
    'sm.notes',
    'p.id AS product_id',
    'p.name AS product_name',
    'p.code AS product_code',
    'p.barcode AS product_barcode',
    'w.id AS warehouse_id',
    'w.name AS warehouse_name',
    'w.code AS warehouse_code',
    'b.id AS branch_id',
    'b.name_ar AS branch_name',
];

$from = ' FROM stock_movements sm
          LEFT JOIN products p ON p.id = sm.product_id
          LEFT JOIN warehouses w ON w.id = sm.warehouse_id
          LEFT JOIN branches b ON b.id = sm.branch_id';

// Note: stock_movements table does not have deleted_at column
$where = [];
$params = [];

// Product filter
if ($productId > 0) {
    $where[] = 'sm.product_id = :product_id';
    $params['product_id'] = $productId;
}

// Warehouse filter
if ($warehouseId > 0) {
    $where[] = 'sm.warehouse_id = :warehouse_id';
    $params['warehouse_id'] = $warehouseId;
}

// Movement type filter
if ($movementType !== '') {
    $where[] = 'sm.movement_type = :movement_type';
    $params['movement_type'] = $movementType;
}

// Date range filter
if ($dateFrom !== '') {
    $where[] = 'DATE(sm.movement_date) >= :date_from';
    $params['date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'DATE(sm.movement_date) <= :date_to';
    $params['date_to'] = $dateTo;
}

// Access control
if (!$isSuperAdmin) {
    if ($accessibleBranchIds) {
        $placeholders = [];
        foreach ($accessibleBranchIds as $index => $branchId) {
            $placeholder = ':branch_' . $index;
            $placeholders[] = $placeholder;
            $params['branch_' . $index] = (int) $branchId;
        }
        $where[] = '(sm.branch_id IS NULL OR sm.branch_id IN (' . implode(', ', $placeholders) . '))';
    } else {
        $where[] = '1 = 0';
    }
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$sql = 'SELECT ' . implode(', ', $select) . $from . $whereSql;
$sql .= ' ORDER BY sm.movement_date DESC, sm.id DESC';

$stmt = Database::connection()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll();

// Movement type labels in Arabic
$movementTypeLabels = [
    'purchase' => 'شراء - وارد',
    'sale' => 'بيع - صادر',
    'purchase_return' => 'مرتجع شراء - وارد',
    'sale_return' => 'مرتجع بيع - وارد',
    'transfer_in' => 'نقل - وارد',
    'transfer_out' => 'نقل - صادر',
    'adjustment' => 'تسوية',
    'damage' => 'تالف',
];

// Generate Excel file
$filename = 'stock-movements-' . date('Y-m-d-His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
echo '<table border="1">';
echo '<thead><tr>';

$headers = [
    'م',
    'التاريخ',
    'كود الصنف',
    'اسم الصنف',
    'المخزن',
    'الفرع',
    'نوع الحركة',
    'المرجع',
    'الكمية الداخلة',
    'الكمية الخارجة',
    'تكلفة الوحدة',
    'ملاحظات',
];

foreach ($headers as $headerLabel) {
    echo '<th>' . e($headerLabel) . '</th>';
}

echo '</tr></thead><tbody>';

$counter = 1;
foreach ($rows as $row) {
    $movementTypeKey = $row['movement_type'];
    $movementTypeLabel = $movementTypeLabels[$movementTypeKey] ?? ($movementTypeKey ?: '-');
    $reference = $row['source_type'] ? ($row['source_type'] . ' #' . $row['source_id']) : '-';

    echo '<tr>';
    echo '<td>' . e((string) $counter++) . '</td>';
    echo '<td>' . e($row['movement_date'] ?? '') . '</td>';
    echo '<td>' . e($row['product_code'] ?? '') . '</td>';
    echo '<td>' . e($row['product_name'] ?? '') . '</td>';
    echo '<td>' . e($row['warehouse_name'] ?? '-') . '</td>';
    echo '<td>' . e($row['branch_name'] ?? '-') . '</td>';
    echo '<td>' . e($movementTypeLabel) . '</td>';
    echo '<td>' . e($reference) . '</td>';
    echo '<td>' . ($row['quantity_in'] > 0 ? e(format_number($row['quantity_in'], 3)) : '') . '</td>';
    echo '<td>' . ($row['quantity_out'] > 0 ? e(format_number($row['quantity_out'], 3)) : '') . '</td>';
    echo '<td>' . e(format_currency($row['unit_cost'] ?? 0)) . '</td>';
    echo '<td>' . e($row['notes'] ?? '') . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
