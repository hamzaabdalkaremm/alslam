<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireLogin();

$company = company_profile();
$logoUrl = company_logo_url();
if ($logoUrl !== '') {
    $logoUrl = '../' . ltrim($logoUrl, '/');
}

$saleId = (int) ($_GET['id'] ?? 0);

$stmt = Database::connection()->prepare(
    "SELECT s.*,
            c.full_name AS customer_name,
            b.name_ar AS branch_name,
            m.full_name AS marketer_name,
            w.name AS warehouse_name
     FROM sales s
     LEFT JOIN customers c ON c.id = s.customer_id
     LEFT JOIN branches b ON b.id = s.branch_id
     LEFT JOIN marketers m ON m.id = s.marketer_id
     LEFT JOIN warehouses w ON w.id = s.warehouse_id
     WHERE s.id = :id
     LIMIT 1"
);
$stmt->execute(['id' => $saleId]);
$sale = $stmt->fetch();

if (!$sale) {
    exit('الفاتورة غير موجودة.');
}

$itemsStmt = Database::connection()->prepare(
    "SELECT si.*,
            p.name,
            pu.label AS unit_label
     FROM sale_items si
     INNER JOIN products p ON p.id = si.product_id
     LEFT JOIN product_units pu ON pu.id = si.product_unit_id
     WHERE si.sale_id = :sale_id"
);
$itemsStmt->execute(['sale_id' => $saleId]);
$items = $itemsStmt->fetchAll();

$paymentMethodMap = [
    'cash' => 'نقدي',
    'credit' => 'آجل',
    'deferred' => 'آجل',
    'bank' => 'تحويل بنكي',
    'card' => 'بطاقة',
    'mixed' => 'مختلط',
];
$paymentMethod = $paymentMethodMap[strtolower((string) ($sale['payment_method'] ?? ''))] ?? ($sale['payment_method'] ?: 'غير محدد');

$saleStatusMap = [
    'completed' => 'مكتملة',
    'pending' => 'قيد الانتظار',
    'cancelled' => 'ملغية',
    'draft' => 'مسودة',
    'approved' => 'معتمدة',
];
$saleStatus = $saleStatusMap[strtolower((string) ($sale['status'] ?? ''))] ?? ($sale['status'] ?: '-');

$saleModeMap = [
    'vehicle_sale' => 'بيع من مخزن السيارة',
    'delegate_order' => 'طلبية مندوب',
    'branch_sale' => 'بيع فرع',
    'direct_sale' => 'بيع مباشر',
];
$saleMode = $saleModeMap[strtolower((string) ($sale['sale_mode'] ?? ''))] ?? ($sale['sale_mode'] ?: '-');

$deliveryStatusMap = [
    'delivered' => 'تم التسليم',
    'pending' => 'قيد التسليم',
    'partial' => 'تسليم جزئي',
    'cancelled' => 'ملغي',
];
$deliveryStatus = $deliveryStatusMap[strtolower((string) ($sale['delivery_status'] ?? ''))] ?? ($sale['delivery_status'] ?: '-');

$customerName = $sale['customer_name'] ?: 'عميل نقدي';
$invoiceDate = !empty($sale['sale_date']) ? date('Y-m-d h:i A', strtotime($sale['sale_date'])) : date('Y-m-d h:i A');
$footerText = trim((string) ($company['invoice_footer'] ?? '')) ?: 'شكرا لتعاملكم معنا';
$companyName = trim((string) ($company['name'] ?? ''));
$companyEnglishName = trim((string) ($company['name_en'] ?? ''));
$showCompanyEnglishName = $companyEnglishName !== ''
    && mb_strtolower($companyEnglishName, 'UTF-8') !== mb_strtolower($companyName, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة بيع <?= e($sale['invoice_no']); ?></title>
    <style>
        :root {
            --invoice-blue: #1d5fd0;
            --invoice-blue-soft: #edf4ff;
            --invoice-blue-deep: #123c85;
            --invoice-ink: #16324f;
            --invoice-muted: #64748b;
            --invoice-line: #d9e6fb;
            --invoice-paper: #ffffff;
            --invoice-shadow: 0 12px 30px rgba(22, 50, 79, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            background:
                radial-gradient(circle at top right, rgba(29, 95, 208, 0.12), transparent 28%),
                linear-gradient(180deg, #f7faff 0%, #eef4fb 100%);
            color: var(--invoice-ink);
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            direction: rtl;
            padding: 10px;
        }

        .print-toolbar {
            max-width: 210mm;
            margin: 0 auto 10px;
            display: flex;
            justify-content: flex-start;
        }

        .print-btn {
            border: 0;
            border-radius: 999px;
            background: var(--invoice-blue);
            color: #fff;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(29, 95, 208, 0.20);
        }

        .invoice-shell {
            width: 210mm;
            min-height: 297mm;
            max-width: 210mm;
            margin: 0 auto;
            background: var(--invoice-paper);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--invoice-shadow);
        }

        .invoice-topbar {
            height: 8px;
            background: linear-gradient(90deg, var(--invoice-blue-deep) 0%, var(--invoice-blue) 55%, #5e98ff 100%);
        }

        .invoice {
            padding: 8mm 8mm 6mm;
        }

        .hero,
        .info-grid,
        .content-grid,
        .footer-card,
        .brand-card,
        .invoice-card,
        .items-card,
        .summary-card {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 4px;
            align-items: start;
            margin-bottom: 8px;
        }

        .brand-card {
            border: 1px solid var(--invoice-line);
            border-radius: 16px;
            padding: 10px 12px;
            background: linear-gradient(180deg, rgba(237, 244, 255, 0.95), #ffffff 72%);
            position: relative;
            overflow: hidden;
            margin-bottom: 0;
        }

        .brand-card::after {
            content: "";
            position: absolute;
            inset: auto -55px -70px auto;
            width: 140px;
            height: 140px;
            background: radial-gradient(circle, rgba(29, 95, 208, 0.16), transparent 68%);
        }

        .brand-row {
            display: flex;
            gap: 10px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            border: 1px solid rgba(29, 95, 208, 0.15);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex: 0 0 48px;
        }

        .brand-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .brand-name {
            margin: 0;
            font-size: 16px;
            line-height: 1.1;
            color: var(--invoice-blue-deep);
        }

        .brand-subtitle {
            margin: 2px 0 0;
            color: var(--invoice-muted);
            font-size: 10px;
        }

        .brand-meta {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 4px 8px;
            margin-top: 8px;
            position: relative;
            z-index: 1;
        }

        .brand-meta div {
            font-size: 10px;
            color: var(--invoice-muted);
        }

        .brand-meta strong {
            display: block;
            margin-bottom: 2px;
            color: var(--invoice-ink);
            font-size: 10px;
        }

        .invoice-card {
            border-radius: 14px;
            background: linear-gradient(180deg, var(--invoice-blue-deep) 0%, var(--invoice-blue) 100%);
            color: #fff;
            padding: 8px 10px;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 8px 10px;
            align-items: center;
            min-height: 0;
            margin-top: -1px;
        }

        .invoice-card__main {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .invoice-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            font-size: 9px;
            letter-spacing: 0.05em;
            font-weight: 700;
            text-transform: uppercase;
            width: fit-content;
        }

        .invoice-no {
            margin: 0;
            font-size: 15px;
            line-height: 1;
            font-weight: 800;
        }

        .invoice-date {
            color: rgba(255, 255, 255, 0.85);
            font-size: 10px;
        }

        .invoice-quick {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 6px;
            margin-top: 0;
        }

        .invoice-quick div {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 10px;
            padding: 5px 6px;
            font-size: 10px;
        }

        .invoice-quick strong {
            display: block;
            font-size: 9px;
            margin-bottom: 3px;
            color: rgba(255, 255, 255, 0.78);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .info-card {
            border: 1px solid var(--invoice-line);
            border-radius: 12px;
            padding: 6px 8px;
            background: #fff;
        }

        .info-card strong {
            display: block;
            margin-bottom: 4px;
            font-size: 9px;
            color: var(--invoice-muted);
        }

        .info-card span {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: var(--invoice-ink);
            min-height: 0;
            line-height: 1.2;
            white-space: nowrap;
        }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 0;
            align-items: start;
        }

        .items-card,
        .summary-card,
        .footer-card {
            border: 1px solid var(--invoice-line);
            border-radius: 16px;
            background: #fff;
        }

        .items-card {
            overflow: hidden;
        }

        .section-title {
            margin: 0;
            padding: 8px 10px;
            border-bottom: 1px solid var(--invoice-line);
            background: linear-gradient(180deg, #fafcff 0%, #f3f8ff 100%);
            color: var(--invoice-blue-deep);
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: var(--invoice-blue-soft);
            color: var(--invoice-blue-deep);
            font-size: 10px;
            font-weight: 800;
            padding: 6px 8px;
            border-bottom: 1px solid var(--invoice-line);
            text-align: right;
        }

        tbody td {
            padding: 5px 7px;
            border-bottom: 1px solid #edf2fb;
            font-size: 10px;
            line-height: 1.15;
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .item-name {
            font-weight: 700;
            color: var(--invoice-ink);
            font-size: 10px;
            line-height: 1.1;
        }

        .item-unit {
            margin-top: 2px;
            color: var(--invoice-muted);
            font-size: 9px;
            line-height: 1.05;
        }

        .summary-section {
            margin-top: 10px;
            display: flex;
            justify-content: flex-start;
        }

        .summary-card {
            padding: 10px 12px;
            background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
        }

        .summary-card--footer {
            width: 320px;
            max-width: 100%;
        }

        .summary-title {
            margin: 0 0 8px;
            color: var(--invoice-blue-deep);
            font-size: 13px;
        }

        .summary-table td {
            padding: 4px 0;
            border-bottom: 1px dashed #d9e6fb;
            font-size: 10px;
        }

        .summary-table tr:last-child td {
            border-bottom: 0;
        }

        .summary-table td:last-child {
            text-align: left;
            font-weight: 800;
            color: var(--invoice-ink);
        }

        .summary-total {
            margin-top: 8px;
            padding: 10px 12px;
            border-radius: 14px;
            background: linear-gradient(180deg, var(--invoice-blue) 0%, var(--invoice-blue-deep) 100%);
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-total strong {
            font-size: 11px;
            opacity: 0.86;
        }

        .summary-total span {
            font-size: 17px;
            font-weight: 800;
        }

        .footer-card {
            margin-top: 10px;
            padding: 10px 12px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .footer-card h4 {
            margin: 0 0 4px;
            color: var(--invoice-blue-deep);
            font-size: 11px;
        }

        .footer-card p {
            margin: 0;
            color: var(--invoice-muted);
            font-size: 10px;
            line-height: 1.3;
            white-space: pre-line;
        }

        .thanks {
            text-align: left;
            align-self: end;
        }

        @page {
            size: A4 portrait;
            margin: 4mm;
        }

        @media print {
            html,
            body {
                width: 210mm;
                height: 297mm;
                overflow: hidden;
                background: #fff;
                padding: 0;
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-toolbar {
                display: none !important;
            }

            .invoice-shell {
                width: 210mm;
                min-height: 297mm;
                max-width: 210mm;
                box-shadow: none;
                border-radius: 0;
                overflow: hidden;
                margin: 0;
            }

            .invoice {
                padding: 5mm 5mm 4mm;
            }

            .hero,
            .info-grid,
            .content-grid,
            .footer-card,
            .summary-section,
            .brand-card,
            .invoice-card,
            .items-card,
            .summary-card,
            table,
            thead,
            tbody,
            tr,
            td,
            th {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .brand-card::after {
                width: 110px;
                height: 110px;
            }

            .brand-logo {
                width: 42px;
                height: 42px;
                flex: 0 0 42px;
            }

            .brand-name {
                font-size: 14px;
            }

            .brand-subtitle,
            .brand-meta div,
            .brand-meta strong,
            .invoice-date,
            .invoice-quick div,
            .invoice-quick strong,
            .info-card strong,
            .info-card span,
            thead th,
            tbody td,
            .item-name,
            .item-unit,
            .summary-table td,
            .footer-card p {
                font-size: 9px !important;
            }

            .invoice-no {
                font-size: 14px !important;
            }

            .section-title,
            .summary-title,
            .footer-card h4 {
                font-size: 11px !important;
            }

            .summary-total strong {
                font-size: 10px !important;
            }

            .summary-total span {
                font-size: 15px !important;
            }

            tbody td {
                padding: 4px 5px !important;
                line-height: 1.05 !important;
            }

            thead th {
                padding: 5px 6px !important;
            }

            .brand-card,
            .invoice-card,
            .info-card,
            .summary-card,
            .footer-card {
                border-radius: 10px;
            }

            .footer-card {
                grid-template-columns: 1fr 1fr;
            }

            .info-grid {
                gap: 6px;
                margin-bottom: 8px;
            }

            .summary-section {
                margin-top: 8px;
            }

            .footer-card {
                margin-top: 8px;
                padding: 8px 10px;
                gap: 8px;
            }
        }

        @media screen and (max-width: 900px) {
            body {
                padding: 8px;
            }

            .invoice-shell {
                width: 100%;
                min-height: auto;
                max-width: 100%;
            }

            .hero,
            .content-grid,
            .footer-card,
            .info-grid {
                grid-template-columns: 1fr;
            }

            .brand-meta,
            .invoice-quick,
            .invoice-card {
                grid-template-columns: 1fr;
            }

            .invoice-card__main {
                align-items: flex-start;
            }

            .invoice-quick {
                grid-template-columns: 1fr;
            }

            .thanks {
                text-align: right;
            }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <button class="print-btn" onclick="window.print()">طباعة الفاتورة</button>
    </div>

    <div class="invoice-shell">
        <div class="invoice-topbar"></div>
        <div class="invoice">
            <section class="hero">
                <div class="brand-card">
                    <div class="brand-row">
                        <div class="brand-logo">
                            <?php if ($logoUrl !== ''): ?>
                                <img src="<?= e($logoUrl); ?>" alt="<?= e($company['name']); ?>">
                            <?php else: ?>
                                <span style="font-size:22px;font-weight:800;color:#1d5fd0;">
                                    <?= e(mb_substr((string) $company['name'], 0, 1)); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h1 class="brand-name"><?= e($companyName); ?></h1>
                            <?php if ($showCompanyEnglishName): ?>
                                <p class="brand-subtitle"><?= e($companyEnglishName); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="brand-meta">
                        <div>
                            <strong>الهاتف</strong>
                           0927595996
                        </div>
                        <div>
                            <strong>البريد الإلكتروني</strong>
                            //
                        </div>
                        <div>
                            <strong>العنوان</strong>
                            تاجوراء الضواحي
                    </div>
                </div>

                <div class="invoice-card">
                    <div class="invoice-card__main">
                        <span class="invoice-label">Sales Invoice</span>
                        <div class="invoice-no"><?= e($sale['invoice_no']); ?></div>
                        <div class="invoice-date">تاريخ الإصدار: <?= e($invoiceDate); ?></div>
                    </div>

                    <div class="invoice-quick">
                        <div>
                            <strong>العميل</strong>
                            <?= e($customerName); ?>
                        </div>
                        <div>
                            <strong>الفرع</strong>
                            <?= e($sale['branch_name'] ?: 'عام'); ?>
                        </div>
                        <div>
                            <strong>المخزن</strong>
                            <?= e($sale['warehouse_name'] ?: '-'); ?>
                        </div>
                        <div>
                            <strong>طريقة الدفع</strong>
                            <?= e($paymentMethod); ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="info-grid">
                <div class="info-card">
                    <strong>المسوق</strong>
                    <span><?= e($sale['marketer_name'] ?: '-'); ?></span>
                </div>
                <div class="info-card">
                    <strong>حالة البيع</strong>
                    <span><?= e($saleStatus); ?></span>
                </div>
                <div class="info-card">
                    <strong>نوع البيع</strong>
                    <span><?= e($saleMode); ?></span>
                </div>
                <div class="info-card">
                    <strong>حالة التسليم</strong>
                    <span><?= e($deliveryStatus); ?></span>
                </div>
            </section>

            <section class="content-grid">
                <div class="items-card">
                    <h3 class="section-title">تفاصيل الفاتورة</h3>
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40%;">الصنف</th>
                                <th style="width:14%;">الكمية</th>
                                <th style="width:16%;">السعر</th>
                                <th style="width:14%;">الخصم</th>
                                <th style="width:16%;">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="item-name"><?= e($item['name']); ?></div>
                                    <?php if (!empty($item['unit_label'])): ?>
                                        <div class="item-unit">الوحدة: <?= e($item['unit_label']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(number_format((float) $item['quantity'], 3)); ?></td>
                                <td><?= e(number_format((float) $item['unit_price'], 2)); ?></td>
                                <td><?= e(number_format((float) $item['discount_value'], 2)); ?></td>
                                <td><?= e(number_format((float) $item['line_total'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="footer-card">
                <div>
                    <h4>ملاحظات الفاتورة</h4>
                    <p><?= e(trim((string) ($sale['notes'] ?? '')) ?: 'لا توجد ملاحظات إضافية على هذه الفاتورة.'); ?></p>
                </div>
                <div class="thanks">
                    <h4>رسالة ختامية</h4>
                    <p><?= e($footerText); ?></p>
                </div>
            </section>

            <section class="summary-section">
                <div class="summary-card summary-card--footer">
                    <h3 class="summary-title">ملخص المبالغ</h3>
                    <table class="summary-table">
                        <tr>
                            <td>الإجمالي الفرعي</td>
                            <td><?= e(format_currency($sale['subtotal'])); ?></td>
                        </tr>
                        <tr>
                            <td>الخصم</td>
                            <td><?= e(format_currency($sale['discount_value'])); ?></td>
                        </tr>
                        <tr>
                            <td>المدفوع</td>
                            <td><?= e(format_currency($sale['paid_amount'])); ?></td>
                        </tr>
                        <tr>
                            <td>المتبقي</td>
                            <td><?= e(format_currency($sale['due_amount'])); ?></td>
                        </tr>
                    </table>

                    <div class="summary-total">
                        <strong>الإجمالي النهائي</strong>
                        <span><?= e(format_currency($sale['total_amount'])); ?></span>
                    </div>
                </div>
            </section>
        </div>
    </div>
</body>
</html>