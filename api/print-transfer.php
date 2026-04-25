<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireLogin();
$company = company_profile();
$logoUrl = company_logo_url();
if ($logoUrl !== '') {
    $logoUrl = '../' . ltrim($logoUrl, '/');
}

$saleId = (int) ($_GET['id'] ?? 0);

if ($saleId <= 0) {
    exit('رقم الفاتورة غير صالح.');
}

try {
    $stmt = Database::connection()->prepare("SELECT * FROM sales WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $saleId]);
    $sale = $stmt->fetch();
} catch (Throwable $e) {
    exit('خطأ في جلب البيانات: ' . $e->getMessage());
}

if (!$sale) {
    exit('الفاتورة غير موجودة. رقم: ' . $saleId);
}

try {
    $itemsStmt = Database::connection()->prepare(
        "SELECT si.*, p.name
         FROM sale_items si
         INNER JOIN products p ON p.id = si.product_id
         WHERE si.sale_id = :sale_id"
    );
    $itemsStmt->execute(['sale_id' => $saleId]);
    $items = $itemsStmt->fetchAll();
} catch (Throwable $e) {
    $items = [];
}

$branchName = '';
if (!empty($sale['branch_id'])) {
    try {
        $branchStmt = Database::connection()->prepare("SELECT name_ar FROM branches WHERE id = ? LIMIT 1");
        $branchStmt->execute([$sale['branch_id']]);
        $branchName = $branchStmt->fetchColumn() ?: '';
    } catch (Throwable $e) {}
}

$marketerName = '';
if (!empty($sale['marketer_id'])) {
    try {
        $marketerStmt = Database::connection()->prepare("SELECT full_name FROM marketers WHERE id = ? LIMIT 1");
        $marketerStmt->execute([$sale['marketer_id']]);
        $marketerName = $marketerStmt->fetchColumn() ?: '';
    } catch (Throwable $e) {}
}

$customerName = '';
if (!empty($sale['customer_id'])) {
    try {
        $customerStmt = Database::connection()->prepare("SELECT full_name FROM customers WHERE id = ? LIMIT 1");
        $customerStmt->execute([$sale['customer_id']]);
        $customerName = $customerStmt->fetchColumn() ?: '';
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طباعة فاتورة <?= e($sale['invoice_no']); ?></title>
    <style>
        body { font-family: Tahoma, sans-serif; margin: 24px; direction: rtl; color: #222; }
        .invoice { max-width: 900px; margin: 0 auto; }
        .header, .footer { text-align: center; margin-bottom: 18px; }
        .logo { max-width: 110px; max-height: 90px; object-fit: contain; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: right; }
        .totals { margin-top: 18px; width: 320px; margin-right: auto; }
        .totals td { border: 0; padding: 6px 0; }
        .print-btn { margin-bottom: 20px; }
        @media print { .print-btn { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="invoice">
        <button class="print-btn" onclick="window.print()">طباعة</button>
        <div class="header">
            <h2>مطعم السلام</h2>
            <div><strong><?= e($sale['invoice_no'] ?? 'فاتورة'); ?></strong></div>
        </div>
        <table>
            <thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>الخصم</th><th>الإجمالي</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['name'] ?? 'صنف'); ?></td>
                    <td><?= e(number_format((float) ($item['quantity'] ?? 0), 3)); ?></td>
                    <td><?= e(number_format((float) ($item['unit_price'] ?? 0), 2)); ?></td>
                    <td><?= e(number_format((float) ($item['discount_value'] ?? 0), 2)); ?></td>
                    <td><?= e(number_format((float) ($item['line_total'] ?? 0), 2)); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table class="totals">
            <tr><td>الإجمالي الفرعي</td><td><?= e(number_format((float) ($sale['subtotal'] ?? 0), 2)); ?> ل.د</td></tr>
            <tr><td>الخصم</td><td><?= e(number_format((float) ($sale['discount_value'] ?? 0), 2)); ?> ل.د</td></tr>
            <tr><td>الإجمالي النهائي</td><td><?= e(number_format((float) ($sale['total_amount'] ?? 0), 2)); ?> ل.د</td></tr>
            <tr><td>المدفوع</td><td><?= e(number_format((float) ($sale['paid_amount'] ?? 0), 2)); ?> ل.د</td></tr>
            <tr><td>المتبقي</td><td><?= e(number_format((float) ($sale['due_amount'] ?? 0), 2)); ?> ل.د</td></tr>
        </table>
        
        <div class="footer-info">
            <div><strong>الفرع:</strong> <?= e($branchName ?: 'عام'); ?></div>
            <div><strong>التاريخ:</strong> <?= e($sale['sale_date'] ? date('Y-m-d H:i', strtotime($sale['sale_date'])) : date('Y-m-d H:i')); ?></div>
            <?php if ($marketerName): ?>
            <div><strong>المسوق:</strong> <?= e($marketerName); ?></div>
            <?php endif; ?>
            <?php if ($customerName): ?>
            <div><strong>العميل:</strong> <?= e($customerName); ?></div>
            <?php endif; ?>
        </div>
        
        <div class="footer">شكراً لتعاملكم معنا</div>
    </div>
    
    <style>
    .footer-info { margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 8px; }
    .footer-info div { margin: 5px 0; }
    </style>
</body>
</html>
