<?php
$pageSubtitle = 'لوحة تنفيذية لإدارة الفروع والمبيعات والمشتريات والمصروفات والحسابات';
$activitiesPage = max(1, (int) request_input('activities_page', 1));
$activitiesPerPage = 15;
$dashboard = (new DashboardService())->data($activitiesPage, $activitiesPerPage);
$chartLabels = array_column($dashboard['sales_chart'], 'month_key');
$chartTotals = array_map('floatval', array_column($dashboard['sales_chart'], 'total_amount'));
$activitiesPageData = $dashboard['activities_page'] ?? ['total' => 0];
$activitiesTotal = (int) ($activitiesPageData['total'] ?? 0);
$activitiesTotalPages = $activitiesTotal > 0 ? (int) ceil($activitiesTotal / $activitiesPerPage) : 0;
$activitiesStart = $activitiesTotal > 0 ? (($activitiesPage - 1) * $activitiesPerPage) + 1 : 0;
$activitiesEnd = $activitiesTotal > 0 ? min($activitiesTotal, $activitiesPage * $activitiesPerPage) : 0;
$previousActivitiesUrl = 'index.php?module=dashboard&activities_page=' . max(1, $activitiesPage - 1);
$nextActivitiesUrl = 'index.php?module=dashboard&activities_page=' . min(max(1, $activitiesTotalPages), $activitiesPage + 1);
$primaryStats = [
    [
        'label' => 'مبيعات اليوم',
        'hint' => 'إجمالي البيع المسجل خلال اليوم الحالي',
        'value' => format_currency($dashboard['stats']['today_sales']),
        'icon' => 'fa-sack-dollar',
        'tone' => 'sales',
    ],
    [
        'label' => 'مشتريات اليوم',
        'hint' => 'قيمة التوريد المدخلة اليوم',
        'value' => format_currency($dashboard['stats']['today_purchases']),
        'icon' => 'fa-cart-flatbed',
        'tone' => 'purchases',
    ],
    [
        'label' => 'مصروفات اليوم',
        'hint' => 'الحركة الخارجة على المصروفات',
        'value' => format_currency($dashboard['stats']['today_expenses']),
        'icon' => 'fa-file-invoice-dollar',
        'tone' => 'expenses',
    ],
    [
        'label' => 'صافي الحركة',
        'hint' => 'الفارق بين الإيرادات والمصروفات',
        'value' => format_currency($dashboard['stats']['net_profit']),
        'icon' => 'fa-chart-line',
        'tone' => 'profit',
    ],
];
$secondaryStats = [
    [
        'label' => 'الفروع',
        'hint' => 'عدد الفروع النشطة في النظام',
        'value' => (string) $dashboard['stats']['branches_count'],
        'icon' => 'fa-building',
        'tone' => 'branches',
    ],
    [
        'label' => 'المسوقون',
        'hint' => 'المسوقون المرتبطون بالفروع',
        'value' => (string) $dashboard['stats']['marketers_count'],
        'icon' => 'fa-bullhorn',
        'tone' => 'marketers',
    ],
    [
        'label' => 'العملاء',
        'hint' => 'العملاء الفعالون في قاعدة البيانات',
        'value' => (string) $dashboard['stats']['customers_count'],
        'icon' => 'fa-users',
        'tone' => 'customers',
    ],
    [
        'label' => 'المستخدمون',
        'hint' => 'المستخدمون المصرح لهم بالدخول',
        'value' => (string) $dashboard['stats']['users_count'],
        'icon' => 'fa-user-shield',
        'tone' => 'users',
    ],
];
?>

<div class="card dashboard-hero">
    <div class="dashboard-hero-copy">
        <span class="dashboard-hero-badge">نظرة تنفيذية مباشرة</span>
        <h2>لوحة تشغيل موحدة للشركة والفروع</h2>
        <p>واجهة مسطحة ونظيفة تركز على أهم المؤشرات اليومية، مع بطاقات واضحة ومساحة عمل أوسع عند طي القائمة الجانبية.</p>
    </div>
    <div class="dashboard-hero-meta">
        <div class="dashboard-hero-pill">
            <small>ديون العملاء</small>
            <strong><?= e(format_currency($dashboard['stats']['customers_due'])); ?></strong>
        </div>
        <div class="dashboard-hero-pill">
            <small>ديون الموردين</small>
            <strong><?= e(format_currency($dashboard['stats']['suppliers_due'])); ?></strong>
        </div>
        <div class="dashboard-hero-pill">
            <small>إجمالي التحصيلات</small>
            <strong><?= e(format_currency($dashboard['stats']['collections_total'])); ?></strong>
        </div>
    </div>
</div>

<div class="grid grid-4 dashboard-kpi-grid mt-2">
    <?php foreach ($primaryStats as $stat): ?>
        <div class="card stat-card stat-card-<?= e($stat['tone']); ?>">
            <div class="stat-card-head">
                <div>
                    <div class="stat-label"><?= e($stat['label']); ?></div>
                    <div class="stat-hint"><?= e($stat['hint']); ?></div>
                </div>
                <span class="stat-icon"><i class="fa-solid <?= e($stat['icon']); ?>"></i></span>
            </div>
            <div class="stat-value"><?= e($stat['value']); ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-4 dashboard-kpi-grid mt-2">
    <?php foreach ($secondaryStats as $stat): ?>
        <div class="card stat-card stat-card-<?= e($stat['tone']); ?>">
            <div class="stat-card-head">
                <div>
                    <div class="stat-label"><?= e($stat['label']); ?></div>
                    <div class="stat-hint"><?= e($stat['hint']); ?></div>
                </div>
                <span class="stat-icon"><i class="fa-solid <?= e($stat['icon']); ?>"></i></span>
            </div>
            <div class="stat-value"><?= e($stat['value']); ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-2 mt-2">
    <div class="card">
        <div class="section-title"><h2>حركة المبيعات</h2></div>
        <canvas id="salesChart" data-labels='<?= e(json_encode($chartLabels, JSON_UNESCAPED_UNICODE)); ?>' data-totals='<?= e(json_encode($chartTotals)); ?>'></canvas>
    </div>
    <div class="card">
        <h3>تنبيهات تشغيلية</h3>
        <div class="summary-box">
            <p><strong>ديون العملاء:</strong> <?= e(format_currency($dashboard['stats']['customers_due'])); ?></p>
            <p><strong>ديون الموردين:</strong> <?= e(format_currency($dashboard['stats']['suppliers_due'])); ?></p>
            <p class="mb-0"><strong>التحصيلات المسجلة:</strong> <?= e(format_currency($dashboard['stats']['collections_total'])); ?></p>
        </div>
        <h3 class="mt-2">أصناف منخفضة</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الصنف</th><th>الرصيد</th><th>حد التنبيه</th></tr></thead>
                <tbody>
                <?php foreach ($dashboard['low_stock'] as $item): ?>
                    <tr>
                        <td><?= e($item['name']); ?></td>
                        <td><?= e(format_number($item['stock_balance'], 2)); ?></td>
                        <td><?= e(number_format((float) $item['min_stock_alert'], 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2 mt-2">
    <div class="card">
        <h3>أفضل المنتجات مبيعًا</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الصنف</th><th>الكمية</th><th>الإجمالي</th></tr></thead>
                <tbody>
                <?php foreach ($dashboard['top_products'] as $product): ?>
                    <tr>
                        <td><?= e($product['name']); ?></td>
                        <td><?= e(format_number($product['sold_quantity'], 2)); ?></td>
                        <td><?= e(format_currency($product['sold_total'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h3>أفضل المسوقين أداءً</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>المسوق</th><th>المبيعات</th><th>التحصيلات</th></tr></thead>
                <tbody>
                <?php foreach ($dashboard['top_marketers'] as $marketer): ?>
                    <tr>
                        <td><?= e($marketer['full_name']); ?></td>
                        <td><?= e(format_currency($marketer['sales_total'])); ?></td>
                        <td><?= e(format_currency($marketer['collections_total'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2 mt-2">
    <div class="card">
        <h3>أفضل الفروع</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الفرع</th><th>المبيعات</th><th>المشتريات</th><th>المصروفات</th></tr></thead>
                <tbody>
                <?php foreach ($dashboard['best_branches'] as $branch): ?>
                    <tr>
                        <td><?= e($branch['name_ar']); ?></td>
                        <td><?= e(format_currency($branch['sales_total'])); ?></td>
                        <td><?= e(format_currency($branch['purchases_total'])); ?></td>
                        <td><?= e(format_currency($branch['expenses_total'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h3>ديون متأخرة</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الفاتورة</th><th>العميل</th><th>الفرع</th><th>المتبقي</th></tr></thead>
                <tbody>
                <?php foreach ($dashboard['overdue_debts'] as $debt): ?>
                    <tr>
                        <td><?= e($debt['invoice_no']); ?></td>
                        <td><?= e($debt['customer_name'] ?? '-'); ?></td>
                        <td><?= e($debt['branch_name'] ?? '-'); ?></td>
                        <td><?= e(format_currency($debt['due_amount'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="toolbar">
        <div>
            <h3>آخر العمليات</h3>
            <p class="card-intro">يعرض هذا الجدول آخر 15 عملية لكل صفحة من سجل النشاط خلال آخر شهرين.</p>
        </div>
        <?php if ($activitiesTotal > 0): ?>
            <div class="muted">عرض <?= e((string) $activitiesStart); ?> - <?= e((string) $activitiesEnd); ?> من <?= e((string) $activitiesTotal); ?></div>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>المستخدم</th><th>الفرع</th><th>الوحدة</th><th>الإجراء</th><th>الوصف</th><th>الوقت</th></tr></thead>
            <tbody>
            <?php if ($dashboard['activities']): ?>
                <?php foreach ($dashboard['activities'] as $activity): ?>
                    <tr>
                        <td><?= e($activity['full_name'] ?? 'النظام'); ?></td>
                        <td><?= e($activity['branch_name'] ?? '-'); ?></td>
                        <td><?= e($activity['module_key']); ?></td>
                        <td><?= e($activity['action_key']); ?></td>
                        <td><?= e($activity['description']); ?></td>
                        <td><?= e($activity['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="muted">لا توجد عمليات مسجلة خلال آخر شهرين.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($activitiesTotalPages > 1): ?>
        <div class="pagination-bar">
            <div class="pagination-summary">صفحة <?= e((string) $activitiesPage); ?> من <?= e((string) $activitiesTotalPages); ?></div>
            <nav class="pagination-links" aria-label="Activity pagination">
                <?php if ($activitiesPage > 1): ?>
                    <a class="pagination-link" href="<?= e($previousActivitiesUrl); ?>">السابق</a>
                <?php endif; ?>
                <?php if ($activitiesPage < $activitiesTotalPages): ?>
                    <a class="pagination-link" href="<?= e($nextActivitiesUrl); ?>">التالي</a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>
