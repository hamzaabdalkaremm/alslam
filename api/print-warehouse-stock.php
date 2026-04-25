<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireLogin();

$warehouseId = (int) ($_GET['warehouse_id'] ?? 0);
if ($warehouseId <= 0) {
    exit('يجب تحديد المخزن أولاً.');
}

$warehouseStmt = Database::connection()->prepare(
    "SELECT w.*, b.name_ar AS branch_name, m.full_name AS marketer_name
     FROM warehouses w
     INNER JOIN branches b ON b.id = w.branch_id
     LEFT JOIN marketers m ON m.id = w.marketer_id
     WHERE w.id = :id
       AND w.deleted_at IS NULL
     LIMIT 1"
);
$warehouseStmt->execute(['id' => $warehouseId]);
$warehouse = $warehouseStmt->fetch();

if (!$warehouse) {
    exit('المخزن غير موجود.');
}

if (($warehouse['status'] ?? 'active') !== 'active') {
    exit('المخزن المحدد غير نشط.');
}

if (!Auth::canAccessBranch((int) ($warehouse['branch_id'] ?? 0))) {
    exit('لا تملك صلاحية الوصول إلى هذا المخزن.');
}

$inventoryService = new InventoryService();
$rows = $inventoryService->stockBalance(null, null, null, null, $warehouseId);
$rows = array_values(array_filter($rows, static fn(array $row): bool => (float) ($row['stock_balance'] ?? 0) > 0));

$company = company_profile();
$logoUrl = company_logo_url();
if ($logoUrl !== '') {
    $logoUrl = '../' . ltrim($logoUrl, '/');
}

$title = 'كشف مخزون - ' . ($warehouse['name'] ?? '');
$printDate = date('Y-m-d h:i A');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; direction: rtl; margin: 0; padding: 14px; background: #f4f7fb; color: #1f2937; }
        .toolbar { max-width: 210mm; margin: 0 auto 12px; }
        .print-btn { border: 0; background: #1d4ed8; color: #fff; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 700; }
        .sheet { width: 210mm; min-height: 297mm; margin: 0 auto; background: #fff; padding: 10mm; box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 16px; border-bottom: 2px solid #dbeafe; padding-bottom: 10px; margin-bottom: 12px; }
        .brand { display: flex; align-items: center; gap: 12px; }
        .logo { width: 56px; height: 56px; object-fit: contain; border: 1px solid #e5e7eb; border-radius: 10px; padding: 4px; background: #fff; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        .muted { color: #6b7280; font-size: 13px; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 8px 16px; margin: 14px 0; }
        .meta-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; background: #f9fafb; }
        .meta-card strong { display: block; color: #111827; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 13px; text-align: center; }
        th { background: #eff6ff; }
        td.name, th.name { text-align: right; }
        .footer-note { margin-top: 14px; border: 1px dashed #cbd5e1; padding: 10px; border-radius: 10px; font-size: 13px; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; width: 100%; min-height: auto; margin: 0; }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="print-btn" onclick="window.print()">طباعة</button>
    </div>
    <div class="sheet">
        <div class="header">
            <div class="brand">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= e($logoUrl); ?>" alt="الشعار" class="logo">
                <?php endif; ?>
                <div>
                    <h1><?= e($company['name'] ?? 'كشف مخزون'); ?></h1>
                    <div class="muted">كشف أصناف المخزن</div>
                </div>
            </div>
            <div class="muted">تاريخ الطباعة: <?= e($printDate); ?></div>
        </div>

        <div class="meta">
            <div class="meta-card"><strong>المخزن</strong><?= e($warehouse['name'] ?? '-'); ?></div>
            <div class="meta-card"><strong>الفرع</strong><?= e($warehouse['branch_name'] ?? '-'); ?></div>
            <div class="meta-card"><strong>نوع المخزن</strong><?= e(($warehouse['warehouse_type'] ?? '') === 'vehicle' ? 'مخزن سيارة' : 'مخزن رئيسي'); ?></div>
            <div class="meta-card"><strong>المسوق المسؤول</strong><?= e($warehouse['marketer_name'] ?? '-'); ?></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th style="width:120px;">الكود</th>
                    <th class="name">الصنف</th>
                    <th style="width:120px;">الرصيد الحالي</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows): ?>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td><?= e((string) ($index + 1)); ?></td>
                            <td><?= e($row['code'] ?? '-'); ?></td>
                            <td class="name"><?= e($row['name'] ?? '-'); ?></td>
                            <td><?= e(format_number($row['stock_balance'] ?? 0, 3)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">لا توجد أصناف برصيد داخل هذا المخزن.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer-note">
            عدد الأصناف الظاهرة في هذا الكشف: <strong><?= e((string) count($rows)); ?></strong>
        </div>
    </div>
</body>
</html>
