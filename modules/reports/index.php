<?php
$pageSubtitle = 'تقارير المبيعات والمشتريات والأرباح والحركة الذكية';
$reportService = new ReportService();
$from = request_input('date_from', date('Y-m-01'));
$to = request_input('date_to', date('Y-m-d'));

$salesReport = $reportService->salesByDate($from, $to);
$purchaseReport = $reportService->purchasesByDate($from, $to);
$profit = $reportService->profitSummary($from, $to);

// For all movements export filters
$movementProductId = (int) request_input('movement_product_id', 0);
$movementWarehouseId = (int) request_input('movement_warehouse_id', 0);
$movementType = trim((string) request_input('movement_type', ''));

// Get products and warehouses for filter dropdowns
$productsStmt = Database::connection()->prepare(
    "SELECT id, name, code FROM products WHERE deleted_at IS NULL ORDER BY name ASC"
);
$productsStmt->execute();
$allProducts = $productsStmt->fetchAll();

$warehousesStmt = Database::connection()->prepare(
    "SELECT w.id, w.name, b.name_ar AS branch_name FROM warehouses w LEFT JOIN branches b ON b.id = w.branch_id WHERE w.deleted_at IS NULL AND w.status = 'active' ORDER BY b.name_ar ASC, w.name ASC"
);
$warehousesStmt->execute();
$allWarehouses = $warehousesStmt->fetchAll();
?>
<div class="card">
    <form method="get" class="form-grid">
        <input type="hidden" name="module" value="reports">
        <div><label>من تاريخ</label><input type="date" name="date_from" value="<?= e($from); ?>"></div>
        <div><label>إلى تاريخ</label><input type="date" name="date_to" value="<?= e($to); ?>"></div>
        <div class="align-self-end"><button class="btn btn-primary" type="submit">تحديث التقرير</button></div>
        <?php if (Auth::can('reports.view')): ?>
            <div class="align-self-end"><a class="btn btn-light" href="ajax/reports/products-export.php">تنزيل تقرير المنتجات Excel</a></div>
            <div class="align-self-end"><a class="btn btn-primary" href="api/all-movements-export.php" target="_blank">تنزيل كل الحركات Excel</a></div>
        <?php endif; ?>
    </form>
</div>

<div class="card mt-2">
    <div class="toolbar">
        <div>
            <h3>تصدير كل حركات المنصة</h3>
            <p class="card-intro">تحميل جميع حركات المخزون (الشراء، البيع، التسويات، النقل، التالف، المرتجع) كملف إكسل مع إمكانية التصفية بالصنف أو المخزن أو نوع الحركة أو الفترة الزمنية.</p>
        </div>
    </div>

    <form method="get" class="form-grid" style="max-width: 800px;">
        <input type="hidden" name="module" value="reports">
        <div>
            <label>الصنف (اختياري)</label>
            <select name="movement_product_id">
                <option value="">كل الأصناف</option>
                <?php foreach ($allProducts as $prod): ?>
                    <option value="<?= e((string) $prod['id']); ?>" <?= $movementProductId === (int) $prod['id'] ? 'selected' : ''; ?>>
                        <?= e($prod['name']); ?> (<?= e($prod['code']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>المخزن (اختياري)</label>
            <select name="movement_warehouse_id">
                <option value="">كل المخازن</option>
                <?php foreach ($allWarehouses as $wh): ?>
                    <option value="<?= e((string) $wh['id']); ?>" <?= $movementWarehouseId === (int) $wh['id'] ? 'selected' : ''; ?>>
                        <?= e($wh['name']); ?> (<?= e($wh['branch_name'] ?? '-'); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>نوع الحركة (اختياري)</label>
            <select name="movement_type">
                <option value="">جميع الأنواع</option>
                <?php
                $movementTypes = [
                    'purchase' => 'شراء - وارد',
                    'sale' => 'بيع - صادر',
                    'purchase_return' => 'مرتجع شراء - وارد',
                    'sale_return' => 'مرتجع بيع - وارد',
                    'transfer_in' => 'نقل - وارد',
                    'transfer_out' => 'نقل - صادر',
                    'adjustment' => 'تسوية',
                    'damage' => 'تالف',
                ];
                foreach ($movementTypes as $key => $label):
                ?>
                    <option value="<?= e($key); ?>" <?= $movementType === $key ? 'selected' : ''; ?>>
                        <?= e($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>من تاريخ (اختياري)</label>
            <input type="date" name="date_from" value="<?= e($from); ?>" max="<?= date('Y-m-d'); ?>">
        </div>

        <div>
            <label>إلى تاريخ (اختياري)</label>
            <input type="date" name="date_to" value="<?= e($to); ?>" max="<?= date('Y-m-d'); ?>">
        </div>

        <?php if (Auth::can('reports.view')): ?>
        <div class="flex gap-2 items-end">
            <a class="btn btn-primary" href="api/all-movements-export.php?<?= http_build_query(array_filter([
                'product_id' => $movementProductId ?: null,
                'warehouse_id' => $movementWarehouseId ?: null,
                'movement_type' => $movementType ?: null,
                'date_from' => $from ?: null,
                'date_to' => $to ?: null,
            ])); ?>" target="_blank">
                <i class="fa-solid fa-download"></i> تحميل كل الحركات Excel
            </a>
        </div>
        <?php endif; ?>
    </form>
</div>

<div class="grid grid-4 mt-2">
    <div class="card stat-card"><div class="stat-label">إجمالي المبيعات</div><div class="stat-value"><?= e(format_currency($profit['sales'])); ?></div></div>
    <div class="card stat-card"><div class="stat-label">تكلفة الشراء</div><div class="stat-value"><?= e(format_currency($profit['costs'])); ?></div></div>
    <div class="card stat-card"><div class="stat-label">إجمالي المصروفات</div><div class="stat-value"><?= e(format_currency($profit['expenses'])); ?></div></div>
    <div class="card stat-card"><div class="stat-label">صافي الربح</div><div class="stat-value"><?= e(format_currency($profit['net_profit'])); ?></div></div>
</div>

<div class="grid grid-2 mt-2">
    <div class="card">
        <h3>تقرير المبيعات</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الفاتورة</th><th>التاريخ</th><th>الإجمالي</th><th>المدفوع</th><th>المتبقي</th></tr></thead>
                <tbody>
                <?php foreach ($salesReport as $row): ?>
                    <tr>
                        <td><?= e($row['invoice_no']); ?></td>
                        <td><?= e($row['sale_date']); ?></td>
                        <td><?= e(format_currency($row['total_amount'])); ?></td>
                        <td><?= e(format_currency($row['paid_amount'])); ?></td>
                        <td><?= e(format_currency($row['due_amount'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h3>تقرير المشتريات</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الفاتورة</th><th>التاريخ</th><th>الإجمالي</th><th>المدفوع</th><th>المتبقي</th></tr></thead>
                <tbody>
                <?php foreach ($purchaseReport as $row): ?>
                    <tr>
                        <td><?= e($row['invoice_no']); ?></td>
                        <td><?= e($row['purchase_date']); ?></td>
                        <td><?= e(format_currency($row['total_amount'])); ?></td>
                        <td><?= e(format_currency($row['paid_amount'])); ?></td>
                        <td><?= e(format_currency($row['due_amount'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
