<?php
require_once __DIR__ . '/../config/bootstrap.php';

Auth::requireLogin();

$warehouseId = (int) ($_GET['warehouse_id'] ?? 0);
$idsParam = trim((string) ($_GET['ids'] ?? ''));

if ($warehouseId <= 0) {
    Response::error('يجب اختيار المخزن أولاً.');
}

$warehouseStmt = Database::connection()->prepare(
    'SELECT id, branch_id, status, deleted_at
     FROM warehouses
     WHERE id = :id
     LIMIT 1'
);
$warehouseStmt->execute(['id' => $warehouseId]);
$warehouse = $warehouseStmt->fetch();

if (!$warehouse || !empty($warehouse['deleted_at']) || ($warehouse['status'] ?? '') !== 'active') {
    Response::error('المخزن المحدد غير صالح.');
}

if (!Auth::canAccessBranch((int) ($warehouse['branch_id'] ?? 0))) {
    Response::error('لا تملك صلاحية الوصول إلى هذا المخزن.', 403);
}

$productIds = array_values(array_unique(array_filter(array_map(
    static fn (string $value): int => (int) trim($value),
    explode(',', $idsParam)
), static fn (int $id): bool => $id > 0)));

if (!$productIds) {
    Response::success('تم جلب الرصيد.', ['stocks' => []]);
}

$params = ['warehouse_id' => $warehouseId];
$placeholders = [];
$stocks = [];

foreach ($productIds as $index => $productId) {
    $key = 'product_' . $index;
    $placeholders[] = ':' . $key;
    $params[$key] = $productId;
    $stocks[(string) $productId] = [
        'product_id' => $productId,
        'stock_balance' => 0,
    ];
}

$sql = 'SELECT product_id, COALESCE(SUM(quantity_in - quantity_out), 0) AS stock_balance
        FROM stock_movements
        WHERE warehouse_id = :warehouse_id
          AND product_id IN (' . implode(', ', $placeholders) . ')
        GROUP BY product_id';

$stmt = Database::connection()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
}
$stmt->execute();

foreach ($stmt->fetchAll() as $row) {
    $productId = (string) ($row['product_id'] ?? 0);
    if ($productId === '0') {
        continue;
    }

    $stocks[$productId] = [
        'product_id' => (int) $row['product_id'],
        'stock_balance' => (float) $row['stock_balance'],
    ];
}

Response::success('تم جلب الرصيد.', ['stocks' => $stocks]);
