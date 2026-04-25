<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission(!empty($_POST['id']) ? 'products.update' : 'products.create');
CSRF::verifyRequest();

$incomingCode = trim((string) ($_POST['code'] ?? ''));
if ($incomingCode === '' && empty($_POST['id'])) {
    $_POST['code'] = next_product_code();
}

$errors = Validator::validate($_POST, ['code' => 'required', 'name' => 'required']);
if ($errors) {
    Response::error('تحقق من البيانات المدخلة.', 422, $errors);
}

$pdo = Database::connection();
$id = (int) ($_POST['id'] ?? 0);
$units = $_POST['units'] ?? [];

$payload = [
    'category_id' => $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null,
    'base_unit_id' => $_POST['base_unit_id'] !== '' ? (int) $_POST['base_unit_id'] : null,
    'branch_id' => !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : (Auth::isSuperAdmin() ? null : Auth::defaultBranchId()),
    'code' => trim($_POST['code']),
    'name' => trim($_POST['name']),
    'brand' => trim($_POST['brand'] ?? ''),
    'barcode' => null,
    'wholesale_price' => (float) ($_POST['wholesale_price'] ?? 0),
    'half_wholesale_price' => (float) ($_POST['half_wholesale_price'] ?? 0),
    'retail_price' => (float) ($_POST['retail_price'] ?? 0),
    'cost_price' => (float) ($_POST['cost_price'] ?? 0),
    'min_stock_alert' => max(0, (int) ($_POST['min_stock_alert'] ?? 0)),
    'sell_by_piece' => isset($_POST['sell_by_piece']) ? 1 : 0,
    'sell_by_carton' => isset($_POST['sell_by_carton']) ? 1 : 0,
    'shelf_location' => trim($_POST['shelf_location'] ?? ''),
    'notes' => trim($_POST['notes'] ?? ''),
    'is_active' => isset($_POST['is_active']) ? 1 : 0,
];

if (!$id) {
    $payload['created_by'] = Auth::id();
}

$preparedUnits = [];
foreach ($units as $unit) {
    if (empty($unit['unit_id'])) {
        continue;
    }

    $preparedUnits[] = [
        'unit_id' => (int) $unit['unit_id'],
        'label' => trim((string) ($unit['label'] ?? '')),
        'units_per_base' => (float) ($unit['units_per_base'] ?? 1),
        'barcode' => trim((string) ($unit['barcode'] ?? '')) !== '' ? trim((string) $unit['barcode']) : null,
        'purchase_price' => (float) ($unit['purchase_price'] ?? 0),
        'wholesale_price' => (float) ($unit['wholesale_price'] ?? 0),
        'half_wholesale_price' => (float) ($unit['half_wholesale_price'] ?? 0),
        'retail_price' => (float) ($unit['retail_price'] ?? 0),
        'is_default_sale_unit' => !empty($unit['is_default_sale_unit']) ? 1 : 0,
        'is_default_purchase_unit' => !empty($unit['is_default_purchase_unit']) ? 1 : 0,
    ];
}

if (!$preparedUnits && $payload['base_unit_id']) {
    $preparedUnits[] = [
        'unit_id' => (int) $payload['base_unit_id'],
        'label' => 'الوحدة الأساسية',
        'units_per_base' => 1,
        'barcode' => null,
        'purchase_price' => $payload['cost_price'],
        'wholesale_price' => $payload['wholesale_price'],
        'half_wholesale_price' => $payload['half_wholesale_price'],
        'retail_price' => $payload['retail_price'],
        'is_default_sale_unit' => 1,
        'is_default_purchase_unit' => 1,
    ];
}

foreach ($preparedUnits as $unit) {
    if ($unit['is_default_sale_unit']) {
        $payload['barcode'] = $unit['barcode'];
        $payload['wholesale_price'] = $unit['wholesale_price'];
        $payload['half_wholesale_price'] = $unit['half_wholesale_price'];
        $payload['retail_price'] = $unit['retail_price'];
        break;
    }
}

if ($payload['barcode'] === null && !empty($preparedUnits[0]['barcode'])) {
    $payload['barcode'] = $preparedUnits[0]['barcode'];
}

try {
    $pdo->beginTransaction();

    $recordId = (new CrudService())->save('products', $payload, $id ?: null);

    $deleteStmt = $pdo->prepare('DELETE FROM product_units WHERE product_id = :product_id');
    $deleteStmt->execute(['product_id' => $recordId]);

    $unitStmt = $pdo->prepare(
        'INSERT INTO product_units
         (product_id, unit_id, label, units_per_base, barcode, purchase_price, wholesale_price, half_wholesale_price, retail_price, is_default_sale_unit, is_default_purchase_unit)
         VALUES
         (:product_id, :unit_id, :label, :units_per_base, :barcode, :purchase_price, :wholesale_price, :half_wholesale_price, :retail_price, :is_default_sale_unit, :is_default_purchase_unit)'
    );

    foreach ($preparedUnits as $unit) {
        $unitStmt->execute([
            'product_id' => $recordId,
            'unit_id' => $unit['unit_id'],
            'label' => $unit['label'] !== '' ? $unit['label'] : 'وحدة',
            'units_per_base' => $unit['units_per_base'] > 0 ? $unit['units_per_base'] : 1,
            'barcode' => $unit['barcode'],
            'purchase_price' => $unit['purchase_price'],
            'wholesale_price' => $unit['wholesale_price'],
            'half_wholesale_price' => $unit['half_wholesale_price'],
            'retail_price' => $unit['retail_price'],
            'is_default_sale_unit' => $unit['is_default_sale_unit'],
            'is_default_purchase_unit' => $unit['is_default_purchase_unit'],
        ]);
    }

    $pdo->commit();
    log_activity('products', $id ? 'update' : 'create', ($id ? 'تحديث' : 'إضافة') . ' منتج مع وحداته وأسعاره', 'products', $recordId);
    Response::success('تم حفظ المنتج والوحدات والباركودات بنجاح.');
} catch (Throwable $e) {
    $pdo->rollBack();
    Response::error('تعذر حفظ المنتج: ' . $e->getMessage(), 500);
}
