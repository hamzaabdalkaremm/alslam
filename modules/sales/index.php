<?php

$pageSubtitle = 'فاتورة بيع احترافية مع التحصيل والتعليق';
$view = (string) request_input('view', 'list');
$saleId = (int) request_input('id', 0);
$editMode = $saleId > 0;
$editSale = null;
$editSaleItems = [];
$customers = [];
$products = [];
$categories = [];
$recentSales = [];
$salesProductsJson = '[]';

// Enforce create/update permissions for sales form
if ($view === 'form') {
    if ($saleId > 0) {
        Auth::requirePermission('sales.update');
    } else {
        Auth::requirePermission('sales.create');
    }
}

if ($editMode) {
    $editSaleStmt = Database::connection()->prepare(
        "SELECT s.*, c.full_name AS customer_name
         FROM sales s
         LEFT JOIN customers c ON c.id = s.customer_id
         WHERE s.id = :id LIMIT 1"
    );
    $editSaleStmt->execute(['id' => $saleId]);
    $editSale = $editSaleStmt->fetch();

    if ($editSale) {
        $itemsStmt = Database::connection()->prepare(
            "SELECT si.*, p.name AS product_name
             FROM sale_items si
             LEFT JOIN products p ON p.id = si.product_id
             WHERE si.sale_id = :sale_id
             ORDER BY si.id ASC"
        );
        $itemsStmt->execute(['sale_id' => $saleId]);
        $editSaleItems = $itemsStmt->fetchAll();
    }
}

if ($view === 'form') {
    $branches = branches_options();
    $warehouses = warehouses_options();
    $marketers = marketers_options();
    $isSuperAdmin = Auth::isSuperAdmin();
    $accessibleBranchIds = Auth::branchIds();

    if (schema_table_exists('customers')) {
        $customerParams = [];
        $customerNameSelect = schema_has_column('customers', 'full_name')
            ? 'full_name'
            : (schema_has_column('customers', 'name') ? 'name AS full_name' : 'CAST(id AS CHAR) AS full_name');

        $customerSql = "SELECT id, {$customerNameSelect} FROM customers";
        $customerWhere = [];

        if (schema_has_column('customers', 'deleted_at')) {
            $customerWhere[] = 'deleted_at IS NULL';
        }

        if (!$isSuperAdmin && schema_has_column('customers', 'branch_id')) {
            if ($accessibleBranchIds) {
                $placeholders = [];
                foreach ($accessibleBranchIds as $index => $branchId) {
                    $placeholder = ':branch_' . $index;
                    $placeholders[] = $placeholder;
                    $customerParams['branch_' . $index] = (int) $branchId;
                }
                $customerWhere[] = '(branch_id IS NULL OR branch_id = 0 OR branch_id IN (' . implode(', ', $placeholders) . '))';
            } else {
                $customerWhere[] = '1 = 0';
            }
        }

        if ($customerWhere) {
            $customerSql .= ' WHERE ' . implode(' AND ', $customerWhere);
        }

        $customerSql .= ' ORDER BY full_name ASC';
        $customerStmt = Database::connection()->prepare($customerSql);
        $customerStmt->execute($customerParams);
        $customers = $customerStmt->fetchAll();
    }

    if (schema_table_exists('products')) {
        $productsParams = [];
        $productSelect = [
            'p.id',
            schema_has_column('products', 'code') ? 'p.code' : 'CAST(p.id AS CHAR) AS code',
            schema_has_column('products', 'name') ? 'p.name' : "'' AS name",
            schema_has_column('products', 'category_id') ? 'p.category_id' : 'NULL AS category_id',
            schema_has_column('products', 'wholesale_price') ? 'p.wholesale_price' : '0 AS wholesale_price',
            schema_has_column('products', 'half_wholesale_price') ? 'p.half_wholesale_price' : '0 AS half_wholesale_price',
            schema_has_column('products', 'retail_price') ? 'p.retail_price' : '0 AS retail_price',
        ];

        if (schema_table_exists('product_units')) {
            $productSelect[] = '(SELECT pu.id
                FROM product_units pu
                WHERE pu.product_id = p.id
                ORDER BY pu.is_default_sale_unit DESC, pu.is_default_purchase_unit DESC, pu.id ASC
                LIMIT 1) AS default_sale_unit_id';
            $productSelect[] = '(SELECT pu.units_per_base
                FROM product_units pu
                WHERE pu.product_id = p.id
                ORDER BY pu.is_default_sale_unit DESC, pu.is_default_purchase_unit DESC, pu.id ASC
                LIMIT 1) AS sale_units_per_base';
            $productSelect[] = '(SELECT pu.label
                FROM product_units pu
                WHERE pu.product_id = p.id
                ORDER BY pu.is_default_sale_unit DESC, pu.is_default_purchase_unit DESC, pu.id ASC
                LIMIT 1) AS sale_unit_label';
        } else {
            $productSelect[] = 'NULL AS default_sale_unit_id';
            $productSelect[] = '1 AS sale_units_per_base';
            $productSelect[] = 'NULL AS sale_unit_label';
        }

        $productsFrom = ' FROM products p';
        if (
            schema_table_exists('product_categories')
            && schema_has_column('products', 'category_id')
            && schema_has_column('product_categories', 'id')
            && schema_has_column('product_categories', 'name')
        ) {
            $productsFrom .= ' LEFT JOIN product_categories pc ON pc.id = p.category_id';
            $productSelect[] = 'pc.name AS category_name';
        } else {
            $productSelect[] = 'NULL AS category_name';
        }

        $productsWhere = [];
        if (schema_has_column('products', 'deleted_at')) {
            $productsWhere[] = 'p.deleted_at IS NULL';
        }
        if (schema_has_column('products', 'is_active')) {
            $productsWhere[] = 'p.is_active = 1';
        }

        if (!$isSuperAdmin && schema_has_column('products', 'branch_id')) {
            if ($accessibleBranchIds) {
                $placeholders = [];
                foreach ($accessibleBranchIds as $index => $branchId) {
                    $placeholder = ':branch_' . $index;
                    $placeholders[] = $placeholder;
                    $productsParams['branch_' . $index] = (int) $branchId;
                }
                $productsWhere[] = '(p.branch_id IS NULL OR p.branch_id = 0 OR p.branch_id IN (' . implode(', ', $placeholders) . '))';
            } else {
                $productsWhere[] = '1 = 0';
            }
        }

        $productsSql = 'SELECT ' . implode(', ', $productSelect) . $productsFrom;
        if ($productsWhere) {
            $productsSql .= ' WHERE ' . implode(' AND ', $productsWhere);
        }
        $productsSql .= schema_has_column('products', 'name') ? ' ORDER BY p.name ASC' : ' ORDER BY p.id DESC';

        $productsStmt = Database::connection()->prepare($productsSql);
        $productsStmt->execute($productsParams);
        $products = $productsStmt->fetchAll();
    }

    $categoriesMap = [];
    foreach ($products as $product) {
        if (empty($product['category_id']) || empty($product['category_name'])) {
            continue;
        }

        $categoriesMap[(string) $product['category_id']] = [
            'id' => $product['category_id'],
            'name' => $product['category_name'],
        ];
    }

    $categories = array_values($categoriesMap);
    usort($categories, static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));

    $salesProductsJson = json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
} else {
    $isSuperAdmin = Auth::isSuperAdmin();
    $accessibleBranchIds = Auth::branchIds();
    $currentPage = max(1, (int) request_input('page', 1));
    $perPage = (int) app_config('per_page');
    if ($perPage <= 0) {
        $perPage = 20;
    }

    $salesSearch = trim((string) request_input('search', ''));
    $filterBranch = (int) request_input('filter_branch', 0);
    $filterMarketer = (int) request_input('filter_marketer', 0);
    $filterStatus = (string) request_input('filter_status', '');
    $filterPaymentMethod = (string) request_input('filter_payment_method', '');
    $filterDateFrom = (string) request_input('date_from', '');
    $filterDateTo = (string) request_input('date_to', '');
    $filterMinAmount = (float) request_input('min_amount', 0);
    $filterMaxAmount = (float) request_input('max_amount', 0);

    $pagination = paginate($currentPage, $perPage);
    $recentSalesPage = ['data' => [], 'total' => 0];

    $filterBranches = branches_options();
    $filterMarketers = marketers_options();

    $statusOptions = [
        'completed' => 'مكتمل',
        'pending' => 'معلق',
        'cancelled' => 'ملغي',
    ];

    if (schema_table_exists('sales')) {
        $recentSalesParams = [];
        $recentSalesSelect = [
            's.id',
            schema_has_column('sales', 'invoice_no') ? 's.invoice_no' : "CONCAT('SALE-', s.id) AS invoice_no",
            schema_has_column('sales', 'sale_date') ? 's.sale_date' : 'NULL AS sale_date',
            schema_has_column('sales', 'total_amount') ? 's.total_amount' : '0 AS total_amount',
            schema_has_column('sales', 'due_amount') ? 's.due_amount' : '0 AS due_amount',
            schema_has_column('sales', 'status') ? 's.status' : "'completed' AS status",
            schema_has_column('sales', 'payment_method') ? 's.payment_method' : "'cash' AS payment_method",
            'COALESCE(sr.returns_total, 0) AS returns_total',
        ];
        $recentSalesFrom = ' FROM sales s';
        $recentSalesFrom .= ' LEFT JOIN (SELECT sale_id, SUM(subtotal) AS returns_total FROM sale_returns GROUP BY sale_id) sr ON sr.sale_id = s.id';

        if (schema_table_exists('customers') && schema_has_column('sales', 'customer_id') && schema_has_column('customers', 'id')) {
            $customerNameField = '(CASE WHEN c.full_name IS NOT NULL AND c.full_name != "" THEN c.full_name ELSE CAST(c.id AS CHAR) END)';
            $recentSalesFrom .= ' LEFT JOIN customers c ON c.id = s.customer_id';
            $recentSalesSelect[] = $customerNameField . ' AS customer_name';
        } else {
            $recentSalesSelect[] = 'NULL AS customer_name';
        }

        if (schema_table_exists('branches') && schema_has_column('sales', 'branch_id') && schema_has_column('branches', 'id')) {
            $branchNameField = schema_has_column('branches', 'name_ar')
                ? 'b.name_ar'
                : (schema_has_column('branches', 'name') ? 'b.name' : 'NULL');
            $recentSalesFrom .= ' LEFT JOIN branches b ON b.id = s.branch_id';
            $recentSalesSelect[] = $branchNameField . ' AS branch_name';
            $recentSalesSelect[] = 'b.id AS branch_id';
        } else {
            $recentSalesSelect[] = 'NULL AS branch_name';
            $recentSalesSelect[] = 'NULL AS branch_id';
        }

        if (schema_table_exists('marketers') && schema_has_column('sales', 'marketer_id') && schema_has_column('marketers', 'id') && schema_has_column('marketers', 'full_name')) {
            $recentSalesFrom .= ' LEFT JOIN marketers m ON m.id = s.marketer_id';
            $recentSalesSelect[] = 'm.full_name AS marketer_name';
            $recentSalesSelect[] = 'm.id AS marketer_id';
        } else {
            $recentSalesSelect[] = 'NULL AS marketer_name';
            $recentSalesSelect[] = 'NULL AS marketer_id';
        }

        $recentSalesWhere = [];
        if (schema_has_column('sales', 'deleted_at')) {
            $recentSalesWhere[] = 's.deleted_at IS NULL';
        }

        if (!$isSuperAdmin && schema_has_column('sales', 'branch_id')) {
            if ($accessibleBranchIds) {
                $placeholders = [];
                foreach ($accessibleBranchIds as $index => $branchId) {
                    $placeholder = ':branch_' . $index;
                    $placeholders[] = $placeholder;
                    $recentSalesParams['branch_' . $index] = (int) $branchId;
                }
                $recentSalesWhere[] = '(s.branch_id IS NULL OR s.branch_id = 0 OR s.branch_id IN (' . implode(', ', $placeholders) . '))';
            } else {
                $recentSalesWhere[] = '1 = 0';
            }
        }

        // Text search
        if ($salesSearch !== '') {
            $searchParts = [];

            if (schema_has_column('sales', 'invoice_no')) {
                $recentSalesParams['search_invoice_exact'] = $salesSearch;
                $recentSalesParams['search_invoice_partial'] = '%' . $salesSearch . '%';
                $searchParts[] = '(s.invoice_no = :search_invoice_exact OR s.invoice_no LIKE :search_invoice_partial)';
            }

            if (schema_has_column('sales', 'notes')) {
                $recentSalesParams['search_notes'] = '%' . $salesSearch . '%';
                $searchParts[] = 's.notes LIKE :search_notes';
            }

            if (schema_table_exists('customers') && schema_has_column('sales', 'customer_id') && schema_has_column('customers', 'id')) {
                $customerNameClause = "CASE 
                    WHEN c.full_name IS NOT NULL AND c.full_name != '' THEN c.full_name
                    WHEN c.name IS NOT NULL AND c.name != '' THEN c.name
                    ELSE CAST(c.id AS CHAR)
                END";
                $recentSalesParams['search_customer'] = '%' . $salesSearch . '%';
                
                $searchParts[] = '(' . $customerNameClause . ' LIKE :search_customer OR c.id = :search_customer_id)';
                $recentSalesParams['search_customer_id'] = is_numeric($salesSearch) ? (int) $salesSearch : -1;
            }

            if (schema_table_exists('branches') && schema_has_column('sales', 'branch_id') && schema_has_column('branches', 'id')) {
                $recentSalesParams['search_branch'] = '%' . $salesSearch . '%';

                if (schema_has_column('branches', 'name_ar')) {
                    $searchParts[] = 'b.name_ar LIKE :search_branch';
                } elseif (schema_has_column('branches', 'name')) {
                    $searchParts[] = 'b.name LIKE :search_branch';
                }
            }

            if (
                schema_table_exists('marketers') &&
                schema_has_column('sales', 'marketer_id') &&
                schema_has_column('marketers', 'id') &&
                schema_has_column('marketers', 'full_name')
            ) {
                $recentSalesParams['search_marketer'] = '%' . $salesSearch . '%';
                $searchParts[] = 'm.full_name LIKE :search_marketer';
            }

            if (schema_table_exists('sale_items') && schema_table_exists('products')) {
                $productSearchParts = [];

                if (schema_has_column('products', 'name')) {
                    $productSearchParts[] = 'p.name LIKE :search_product';
                }

                if (schema_has_column('products', 'code')) {
                    $productSearchParts[] = 'p.code LIKE :search_product';
                }

                if ($productSearchParts) {
                    $recentSalesFrom .= ' LEFT JOIN sale_items si ON si.sale_id = s.id LEFT JOIN products p ON p.id = si.product_id';
                    $recentSalesParams['search_product'] = '%' . $salesSearch . '%';
                    $searchParts[] = '(' . implode(' OR ', $productSearchParts) . ')';
                    $recentSalesSelect[0] = 's.id';
                }
            }

            if ($searchParts) {
                $recentSalesWhere[] = '(' . implode(' OR ', $searchParts) . ')';
            }
        }

        if ($filterBranch > 0 && schema_has_column('sales', 'branch_id')) {
            $recentSalesParams['filter_branch'] = $filterBranch;
            $recentSalesWhere[] = 's.branch_id = :filter_branch';
        }

        if ($filterMarketer > 0 && schema_has_column('sales', 'marketer_id')) {
            $recentSalesParams['filter_marketer'] = $filterMarketer;
            $recentSalesWhere[] = 's.marketer_id = :filter_marketer';
        }

        if ($filterStatus !== '' && schema_has_column('sales', 'status')) {
            $recentSalesParams['filter_status'] = $filterStatus;
            $recentSalesWhere[] = 's.status = :filter_status';
        }

        if ($filterPaymentMethod !== '' && schema_has_column('sales', 'payment_method')) {
            $recentSalesParams['filter_payment_method'] = $filterPaymentMethod;
            $recentSalesWhere[] = 's.payment_method = :filter_payment_method';
        }

        if ($filterDateFrom !== '' && schema_has_column('sales', 'sale_date')) {
            $recentSalesParams['date_from'] = $filterDateFrom . ' 00:00:00';
            $recentSalesWhere[] = 's.sale_date >= :date_from';
        }

        if ($filterDateTo !== '' && schema_has_column('sales', 'sale_date')) {
            $recentSalesParams['date_to'] = $filterDateTo . ' 23:59:59';
            $recentSalesWhere[] = 's.sale_date <= :date_to';
        }

        if ($filterMinAmount > 0 && schema_has_column('sales', 'total_amount')) {
            $recentSalesParams['min_amount'] = $filterMinAmount;
            $recentSalesWhere[] = 's.total_amount >= :min_amount';
        }

        if ($filterMaxAmount > 0 && schema_has_column('sales', 'total_amount')) {
            $recentSalesParams['max_amount'] = $filterMaxAmount;
            $recentSalesWhere[] = 's.total_amount <= :max_amount';
        }

        $recentSalesWhereSql = $recentSalesWhere ? ' WHERE ' . implode(' AND ', $recentSalesWhere) : '';
        $countSql = 'SELECT COUNT(DISTINCT s.id)' . $recentSalesFrom . $recentSalesWhereSql;
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($recentSalesParams);
        $recentSalesPage['total'] = (int) $countStmt->fetchColumn();

        $recentSalesSql = 'SELECT ' . implode(', ', $recentSalesSelect) . $recentSalesFrom . $recentSalesWhereSql;
        if (strpos($recentSalesFrom, 'sale_items') !== false) {
            $recentSalesSql .= ' GROUP BY s.id';
        }
        $recentSalesSql .= schema_has_column('sales', 'sale_date')
            ? ' ORDER BY s.sale_date DESC, s.id DESC'
            : ' ORDER BY s.id DESC';
        $recentSalesSql .= ' LIMIT :limit OFFSET :offset';

        $recentSalesStmt = Database::connection()->prepare($recentSalesSql);
        foreach ($recentSalesParams as $key => $value) {
            $recentSalesStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $recentSalesStmt->bindValue(':limit', (int) $pagination['limit'], PDO::PARAM_INT);
        $recentSalesStmt->bindValue(':offset', (int) $pagination['offset'], PDO::PARAM_INT);
        $recentSalesStmt->execute();
        $recentSales = $recentSalesStmt->fetchAll();
        $recentSalesPage['data'] = $recentSales;
    }
}
?>

<?php if ($view === 'form'): ?>
    <form action="ajax/sales/create.php" method="post" data-ajax-form data-auto-calc="sale" data-sales-pos class="sales-pos-form">
        <?= csrf_field(); ?>
        <?php if ($editMode): ?>
            <input type="hidden" name="id" value="<?= e((string) $saleId); ?>">
        <?php endif; ?>

        <div class="sales-pos-layout">
            <aside class="sales-cart-panel">
                <div class="sales-cart-header">
                    <div class="flex justify-between items-center" style="width: 100%;">
                        <div>
                            <h3 style="margin-bottom: 2px;">سلة الطلب</h3>
                            <p class="muted" style="font-size: .8rem;">الأصناف المختارة تظهر هنا</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="cartItemCountBadge" class="badge" style="background: var(--primary-soft); color: var(--primary); font-size: .9rem;">0 أصناف</span>
                            <button type="button" class="sales-cart-clear btn btn-light btn-sm" data-sales-clear title="مسح السلة">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="sales-cart-meta">
                    <div>
                        <label>رقم الفاتورة</label>
                        <input name="invoice_no" value="<?= e(next_reference('invoice_prefix_sales', 'SAL')); ?>" required>
                        <small class="field-help">رقم مرجعي لفاتورة البيع.</small>
                    </div>
                    <div>
                        <label>العميل</label>
                        <select name="customer_id" id="customer_id">
                            <option value="">بيع نقدي / بدون عميل</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= e((string) $customer['id']); ?>" data-marketer-id="<?= e((string) ($customer['marketer_id'] ?? "")); ?>"><?= e($customer['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-help">في البيع النقدي يمكن تركه بدون عميل. أما البيع الآجل فيجب ربطه بعميل فعلي.</small>
                        <button type="button" class="btn btn-light mt-1" id="toggleCustomerQuickFormBtn">عميل جديد</button>
                    </div>
                    <div class="sales-quick-customer" id="customerQuickPanel" hidden>
                        <div class="sales-quick-customer-head">
                            <strong>إضافة عميل سريع</strong>
                            <button type="button" class="btn btn-light" id="closeCustomerQuickFormBtn">إلغاء</button>
                        </div>
                        <div class="sales-quick-customer-grid">
                            <div>
                                <label>اسم العميل</label>
<div style="position: relative;">
    <input type="text" id="quickCustomerFullName" placeholder="اسم العميل" autocomplete="off">
    <div id="customerSearchResults" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; background: white; border: 1px solid #ddd; border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></div>
</div>
<small class="field-help" id="customerSearchStatus" style="color: #666; font-size: 0.75rem;"></small>                            </div>
                            <div>
                                <label>الهاتف</label>
                                <input type="text" id="quickCustomerPhone" placeholder="رقم الهاتف">
                            </div>
                            <div>
                                <label>التصنيف</label>
                                <input type="text" id="quickCustomerCategory" placeholder="مثال: جملة">
                            </div>
                            <div>
                                <label>السقف الائتماني</label>
                                <input type="number" step="0.01" id="quickCustomerCreditLimit" value="0">
                            </div>
                            <input type="hidden" id="quickCustomerMarketerId" value="">
                        </div>
                        <div class="sales-quick-customer-actions">
                            <?php if (Auth::can('customers.create') || Auth::can('sales.create')): ?>
                                <button type="button" class="btn btn-primary" id="saveQuickCustomerBtn">
                                    <i class="fa-solid fa-plus"></i>
                                    <span>حفظ العميل واختياره</span>
                                </button>
                                <small class="muted">يمكنك إضافة عميل جديد مباشرة من هنا</small>
                            <?php else: ?>
                                <small class="muted">إنشاء عميل سريع: يحتاج صلاحية "إضافة عميل" أو "إنشاء فاتورة بيع"</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <label>الفرع</label>
                        <select name="branch_id">
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= e((string) $branch['id']); ?>" <?= (string) $branch['id'] === (string) Auth::defaultBranchId() ? 'selected' : ''; ?>><?= e($branch['name_ar']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-help">يتم ربط الفاتورة مباشرة بالفرع التشغيلي.</small>
                    </div>
                    <div>
                        <label>المخزن</label>
                        <select name="warehouse_id">
                            <option value="">بدون مخزن محدد</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?= e((string) $warehouse['id']); ?>"><?= e($warehouse['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>المسوق</label>
                        <select name="marketer_id" id="marketer_id">
                            <option value="">بدون مسوق</option>
                            <?php foreach ($marketers as $marketer): ?>
                                <option
                                    value="<?= e((string) $marketer['id']); ?>"
                                    data-type="<?= e($marketer['marketer_type'] ?? ''); ?>"
                                    data-warehouse-id="<?= e((string) ($marketer['default_warehouse_id'] ?? '')); ?>">
                                    <?= e($marketer['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>نوع السعر</label>
                        <select name="pricing_tier">
                            <option value="wholesale">جملة</option>
                            <option value="half_wholesale">نصف جملة</option>
                            <option value="retail" selected>مفرد</option>
                        </select>
                        <small class="field-help">يؤثر على السعر الذي يظهر في الأصناف والسلة.</small>
                    </div>
                    <div>
                        <label>طريقة الدفع</label>
                        <select name="payment_method">
                            <option value="cash">نقدي</option>
                            <option value="card">بطاقة</option>
                            <option value="deferred">آجل</option>
                        </select>
                        <small class="field-help">آجل ينقل الرصيد تلقائياً إلى الديون والتحصيل بعد الحفظ.</small>
                    </div>
                </div>

                <div class="sales-cart-items" id="salesItems">
                    <div class="sales-cart-empty" data-sales-empty>
                        <i class="fa-solid fa-cart-shopping" style="font-size: 3rem; color: var(--text-faint); margin-bottom: 12px;"></i>
                        <strong style="display: block; font-size: 1.1rem; margin-bottom: 4px;">السلة فارغة</strong>
                        <span style="font-size: .9rem; color: var(--text-soft);">اختر صنفاً من الشبكة لإضافته إلى الفاتورة</span>
                    </div>
                </div>

                <template id="salesCartRowTemplate">
                    <div class="sales-cart-item row" data-sales-item>
                        <input type="hidden" name="items[][product_id]" value="">
                        <input type="hidden" name="items[][product_unit_id]" value="">
                        <input type="hidden" name="items[][batch_id]" value="">
                        <div class="sales-cart-item-head">
                            <div>
                                <strong class="sales-cart-item-name"></strong>
                                <span class="sales-cart-item-meta"></span>
                            </div>
                            <button type="button" class="btn btn-danger sales-cart-item-remove" data-remove-row>حذف</button>
                        </div>
                        <div class="sales-cart-item-fields">
                            <div>
                                <label>الكمية</label>
                                <input type="number" step="0.001" name="items[][quantity]" value="1">
                            </div>
                            <div>
                                <label>سعر الوحدة</label>
                                <input type="number" step="0.01" name="items[][unit_price]" value="0">
                                <small class="field-help">يمكن تعديل سعر البيع يدوياً من هنا.</small>
                            </div>
                            <div>
                                <label>الخصم</label>
                                <input type="number" step="0.01" name="items[][discount_value]" value="0">
                            </div>
                        </div>
                        <div class="sales-cart-item-footer">
                            <small class="sales-cart-item-stock"></small>
                            <strong class="sales-cart-item-total">0.00</strong>
                        </div>
                    </div>
                </template>

                <div class="sales-cart-summary">
                    <div class="sales-summary-row">
                        <label>الإجمالي الفرعي</label>
                        <input type="number" step="0.01" name="subtotal" value="0" readonly class="summary-input">
                    </div>
                    <div class="sales-summary-row">
                        <label>الخصم العام</label>
                        <input type="number" step="0.01" name="discount_value" value="0" class="summary-input" placeholder="0.00">
                    </div>
                    <div class="sales-summary-row sales-summary-row-total">
                        <label>الإجمالي النهائي</label>
                        <input type="number" step="0.01" name="total_amount" value="0" readonly class="summary-input total">
                    </div>
                    <div class="sales-summary-row">
                        <label>المدفوع</label>
                        <input type="number" step="0.01" name="paid_amount" value="0" class="summary-input" placeholder="0.00">
                    </div>
                    <div class="sales-summary-row">
                        <label>المتبقي</label>
                        <input type="number" step="0.01" name="due_amount" value="0" readonly class="summary-input due">
                    </div>
                </div>

                <div class="sales-cart-note">
                    <label>ملاحظات</label>
                    <textarea name="notes" placeholder="ملاحظات على الفاتورة أو التسليم"></textarea>
                </div>

                <div class="sales-cart-actions">
                    <a class="btn btn-light" href="index.php?module=sales" title="العودة إلى قائمة الفواتير">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>آخر الفواتير</span>
                    </a>
                    <button class="btn btn-primary btn-block" type="submit" id="saveSaleBtn">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>حفظ الفاتورة</span>
                    </button>
                </div>

                <!-- Full-screen page loading (shown during save) -->
                <div class="page-loading" id="pageLoading" hidden>
                    <div class="page-loading__spinner"></div>
                    <p class="page-loading__text">جاري حفظ الفاتورة...</p>
                </div>
            </aside>

            <section class="sales-products-panel">
                <div class="sales-products-topbar">
                    <div>
                        <h3>نقطة البيع</h3>
                        <p>واجهة سريعة لاختيار الأصناف وإضافتها إلى السلة.</p>
                    </div>
                    <div class="sales-products-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="salesProductSearch" placeholder="ابحث عن المنتج أو الكود">
                    </div>
                </div>

                <div class="sales-category-filters" id="salesCategoryFilters">
                    <button type="button" class="sales-category-chip active" data-category-filter="all">الكل</button>
                    <?php foreach ($categories as $category): ?>
                        <button type="button" class="sales-category-chip" data-category-filter="<?= e((string) $category['id']); ?>">
                            <?= e($category['name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="sales-products-results">
                    <div class="sales-products-grid" id="salesProductsGrid"></div>
                    <div class="sales-products-empty" id="salesProductsEmpty" hidden>لا توجد أصناف مطابقة للبحث الحالي.</div>
                </div>
                <div class="sales-products-actions">
                    <button type="button" class="btn btn-light sales-products-more" id="salesProductsMore" hidden>عرض المزيد</button>
                </div>

                <template id="salesProductCardTemplate">
                    <button type="button" class="sales-product-card" data-sales-product>
                        <span class="sales-product-icon"><i class="fa-solid fa-box-open"></i></span>
                        <strong class="sales-product-name"></strong>
                        <span class="sales-product-subtitle"></span>
                        <span class="sales-product-stock-badge" data-stock-target>جاري تحديث المخزون...</span>
                        <div class="sales-product-footer">
                            <span class="sales-product-price" data-price-target>0.00</span>
                            <span class="sales-product-add"><i class="fa-solid fa-plus"></i></span>
                        </div>
                    </button>
                </template>
                <script type="application/json" id="salesProductsData"><?= $salesProductsJson; ?></script>
            </section>
        </div>
    </form>
<?php else: ?>

<div class="card">
    <div class="toolbar">
        <div>
            <h3>فواتير البيع</h3>
            <p class="card-intro">بحث وتصفية متقدمة مع عرض حالة المرتجعات</p>
        </div>
        <div class="toolbar gap-1">
            <form method="get" class="toolbar-search">
                <input type="hidden" name="module" value="sales">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="ابحث برقم الفاتورة أو العميل أو الفرع أو المسوق" value="<?= e($salesSearch); ?>">
            </form>
            <?php if (Auth::can('sales.create')): ?>
                <a class="btn btn-primary" href="index.php?module=sales&view=form">إنشاء فاتورة بيع</a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline" id="toggleFilters" title="فلاتر متقدمة">
                <i class="fa-solid fa-sliders"></i>
                <span>فلاتر</span>
            </button>
        </div>
    </div>

    <div class="card filter-panel" id="filterPanel" style="display: none; margin-bottom: 1rem;">
        <form method="get" id="salesFilterForm">
            <input type="hidden" name="module" value="sales">
            <input type="hidden" name="search" value="<?= e($salesSearch); ?>">

            <div class="filter-grid">
                <div class="filter-item">
                    <label for="filter_branch">الفرع</label>
                    <select id="filter_branch" name="filter_branch">
                        <option value="0">جميع الفروع</option>
                        <?php foreach ($filterBranches as $branch): ?>
                            <option value="<?= e((string) $branch['id']); ?>" <?= $filterBranch === (int) $branch['id'] ? 'selected' : ''; ?>>
                                <?= e($branch['name_ar']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter_marketer">المسوق</label>
                    <select id="filter_marketer" name="filter_marketer">
                        <option value="0">جميع المسوقين</option>
                        <?php foreach ($filterMarketers as $marketer): ?>
                            <option value="<?= e((string) $marketer['id']); ?>" <?= $filterMarketer === (int) $marketer['id'] ? 'selected' : ''; ?>>
                                <?= e($marketer['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter_status">الحالة</label>
                    <select id="filter_status" name="filter_status">
                        <option value="">جميع الحالات</option>
                        <?php foreach ($statusOptions as $key => $label): ?>
                            <option value="<?= e($key); ?>" <?= $filterStatus === $key ? 'selected' : ''; ?>>
                                <?= e($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter_payment_method">طريقة الدفع</label>
                    <select id="filter_payment_method" name="filter_payment_method">
                        <option value="">الكل</option>
                        <option value="cash" <?= $filterPaymentMethod === 'cash' ? 'selected' : ''; ?>>نقدي</option>
                        <option value="card" <?= $filterPaymentMethod === 'card' ? 'selected' : ''; ?>>بطاقة</option>
                        <option value="deferred" <?= $filterPaymentMethod === 'deferred' ? 'selected' : ''; ?>>آجل</option>
                    </select>
                </div>

                <div class="filter-item filter-item-date">
                    <label>من تاريخ</label>
                    <input type="date" id="date_from" name="date_from" value="<?= e($filterDateFrom); ?>" max="<?= date('Y-m-d'); ?>">
                </div>

                <div class="filter-item filter-item-date">
                    <label>إلى تاريخ</label>
                    <input type="date" id="date_to" name="date_to" value="<?= e($filterDateTo); ?>" max="<?= date('Y-m-d'); ?>">
                </div>

                <div class="filter-item">
                    <label for="min_amount">المبلغ الأدنى</label>
                    <input type="number" step="0.01" id="min_amount" name="min_amount" placeholder="0.00" min="0" value="<?= $filterMinAmount > 0 ? e((string) $filterMinAmount) : ''; ?>">
                </div>

                <div class="filter-item">
                    <label for="max_amount">المبلغ الأقصى</label>
                    <input type="number" step="0.01" id="max_amount" name="max_amount" placeholder="0.00" min="0" value="<?= $filterMaxAmount > 0 ? e((string) $filterMaxAmount) : ''; ?>">
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-search"></i> تطبيق الفلاتر
                </button>
                <a href="index.php?module=sales" class="btn btn-light">
                    <i class="fa-solid fa-rotate-right"></i> مسح الفلاتر
                </a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>الفاتورة</th>
                        <th>التاريخ</th>
                        <th>الفرع</th>
                        <th>العميل</th>
                        <th>المسوق</th>
                        <th>الإجمالي</th>
                        <th>المتبقي</th>
                        <th>الحالة</th>
                        <th>الدفع</th>
                        <th>المرتجع</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recentSales): ?>
                        <tr>
                            <td colspan="11" class="text-center">لا توجد فواتير مطابقة.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr class="<?= ((float) ($sale['returns_total'] ?? 0) > 0) ? 'bg-danger-subtle' : ''; ?>">
                                <td><?= e($sale['invoice_no'] ?? ''); ?></td>
                                <td><?= e($sale['sale_date'] ?? ''); ?></td>
                                <td><?= e($sale['branch_name'] ?? '-'); ?></td>
                                <td><?= e($sale['customer_name'] ?? '-'); ?></td>
                                <td><?= e($sale['marketer_name'] ?? '-'); ?></td>
                                <td><?= e(format_currency($sale['total_amount'] ?? 0)); ?></td>
                                <td><?= e(format_currency($sale['due_amount'] ?? 0)); ?></td>
                                <td>
                                    <span class="badge <?= ($sale['status'] ?? '') === 'completed' ? 'success' : 'warning'; ?>">
                                        <?= e($sale['status'] ?? ''); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $paymentLabels = ['cash' => 'نقدي', 'card' => 'بطاقة', 'deferred' => 'آجل'];
                                    $paymentClass = ['cash' => 'info', 'card' => 'primary', 'deferred' => 'warning'];
                                    $payMethod = $sale['payment_method'] ?? 'cash';
                                    $payClass = $paymentClass[$payMethod] ?? 'secondary';
                                    ?>
                                    <span class="badge <?= $payClass; ?>">
                                        <?= e($paymentLabels[$payMethod] ?? $payMethod); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ((float) ($sale['returns_total'] ?? 0) > 0): ?>
                                        <span class="text-danger font-bold">
                                            <?= e(number_format((float) $sale['returns_total'], 2)); ?> د.ل
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-1" style="flex-wrap: wrap;">
                                        <?php if (Auth::can('sales.print')): ?>
                                            <a class="btn btn-light btn-sm" target="_blank" href="api/print-sale.php?id=<?= e((string) ($sale['id'] ?? 0)); ?>" title="طباعة الفاتورة">
                                                <i class="fa-solid fa-print"></i>
                                                <span>طباعة</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (Auth::can('sales.update')): ?>
                                            <a class="btn btn-info btn-sm" href="index.php?module=sales&view=form&id=<?= e((string) ($sale['id'] ?? 0)); ?>" title="تعديل الفاتورة">
                                                <i class="fa-solid fa-pen"></i>
                                                <span>تعديل</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (($sale['status'] ?? '') === 'completed' && Auth::can('sales.suspend')): ?>
                                            <button type="button"
                                                    class="btn btn-danger btn-sm"
                                                    data-id="<?= e((string) ($sale['id'] ?? 0)); ?>"
                                                    data-delete-url="ajax/sales/suspend.php"
                                                    data-confirm="هل تريد تعليق هذه الفاتورة؟"
                                                    title="تعليق الفاتورة">
                                                <i class="fa-solid fa-pause"></i>
                                                <span>تعليق</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($recentSalesPage, $currentPage, $perPage, [
            'module' => 'sales',
            'search' => $salesSearch,
            'filter_branch' => $filterBranch,
            'filter_marketer' => $filterMarketer,
            'filter_status' => $filterStatus,
            'filter_payment_method' => $filterPaymentMethod,
            'date_from' => $filterDateFrom,
            'date_to' => $filterDateTo,
            'min_amount' => $filterMinAmount,
            'max_amount' => $filterMaxAmount,
        ]); ?>
    </div>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ========================
    // 1. العناصر الأساسية
    // ========================
    const customerSelect = document.getElementById('customer_id');
    const marketerSelect = document.querySelector('select[name="marketer_id"]');
    const warehouseSelect = document.querySelector('select[name="warehouse_id"]');
    const warehouseWrap = warehouseSelect ? warehouseSelect.closest('div') : null;
    const quickCustomerMarketerInput = document.getElementById('quickCustomerMarketerId');
    const cartItems = document.getElementById('salesItems');
    
    // عناصر نافذة العميل السريع
    const toggleQuickCustomerBtn = document.getElementById('toggleCustomerQuickFormBtn');
    const closeQuickCustomerBtn = document.getElementById('closeCustomerQuickFormBtn');
    const quickCustomerPanel = document.getElementById('customerQuickPanel');
    const saveQuickCustomerBtn = document.getElementById('saveQuickCustomerBtn');
    
    // حقول نموذج العميل السريع
    const quickFullName = document.getElementById('quickCustomerFullName');
    const quickPhone = document.getElementById('quickCustomerPhone');
    const quickCategory = document.getElementById('quickCustomerCategory');
    const quickCreditLimit = document.getElementById('quickCustomerCreditLimit');
    const quickMarketerId = document.getElementById('quickCustomerMarketerId');

    // ========================
    // 2. الدوال المساعدة
    // ========================
    function showToast(message, type, title) {
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container';
            toastContainer.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999;';
            document.body.appendChild(toastContainer);
        }
        
        const borderColor = type === 'success' ? '#10b981' : '#ef4444';
        const titleText = title || (type === 'success' ? 'نجاح' : 'خطأ');
        
        const toast = document.createElement('div');
        toast.style.cssText = 
            'background: white; border-radius: 8px; padding: 12px 20px; margin-bottom: 10px; ' +
            'box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-right: 4px solid ' + borderColor + '; ' +
            'direction: rtl; font-family: inherit;';
        toast.innerHTML = '<strong>' + titleText + '</strong><br>' + message;
        
        toastContainer.appendChild(toast);
        
        setTimeout(function() {
            toast.remove();
            if (toastContainer.children.length === 0) toastContainer.remove();
        }, 3000);
    }
    
    function syncCustomerMarketer() {
        if (!customerSelect || !marketerSelect) return;
        const option = customerSelect.options[customerSelect.selectedIndex];
        const customerMarketerId = option && option.dataset ? (option.dataset.marketerId || '') : '';
        if (customerMarketerId !== '') {
            marketerSelect.value = customerMarketerId;
            syncSaleMode();
        }
    }
    
    function syncQuickCustomerMarketer() {
        if (quickMarketerId && marketerSelect) {
            quickMarketerId.value = marketerSelect.value || '';
        }
    }
    
    function syncSaleMode() {
        if (!marketerSelect || !warehouseSelect || !warehouseWrap) return;
        const option = marketerSelect.options[marketerSelect.selectedIndex];
        const type = option && option.dataset ? (option.dataset.type || '') : '';
        const warehouseId = option && option.dataset ? (option.dataset.warehouseId || '') : '';
        
        warehouseWrap.style.display = '';
        warehouseSelect.disabled = false;
        if (warehouseId) warehouseSelect.value = warehouseId;
    }
    
    // دوال السلة
    function recalculateCart() {
        const rows = document.querySelectorAll('[data-sales-item]');
        const subtotalInput = document.querySelector('input[name="subtotal"]');
        const discountInput = document.querySelector('input[name="discount_value"]');
        const totalInput = document.querySelector('input[name="total_amount"]');
        const paidInput = document.querySelector('input[name="paid_amount"]');
        const dueInput = document.querySelector('input[name="due_amount"]');
        const emptyState = cartItems ? cartItems.querySelector('[data-sales-empty]') : null;
        const cartItemCountBadge = document.getElementById('cartItemCountBadge');
        
        let subtotal = 0;
        rows.forEach(function(row) {
            const qtyInput = row.querySelector('input[name="items[][quantity]"]');
            const unitPriceInput = row.querySelector('input[name="items[][unit_price]"]');
            const discountRowInput = row.querySelector('input[name="items[][discount_value]"]');
            const totalEl = row.querySelector('.sales-cart-item-total');
            
            const qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
            const unitPrice = parseFloat(unitPriceInput ? unitPriceInput.value : 0) || 0;
            const rowDiscount = parseFloat(discountRowInput ? discountRowInput.value : 0) || 0;
            const rowTotal = Math.max(0, (qty * unitPrice) - rowDiscount);
            subtotal += rowTotal;
            if (totalEl) totalEl.textContent = rowTotal.toFixed(2);
        });
        
        const generalDiscount = parseFloat(discountInput ? discountInput.value : 0) || 0;
        const total = Math.max(0, subtotal - generalDiscount);
        const paid = parseFloat(paidInput ? paidInput.value : 0) || 0;
        const due = Math.max(0, total - paid);
        
        if (subtotalInput) subtotalInput.value = subtotal.toFixed(2);
        if (totalInput) totalInput.value = total.toFixed(2);
        if (dueInput) dueInput.value = due.toFixed(2);
        if (emptyState) emptyState.hidden = rows.length > 0;
        if (cartItemCountBadge) cartItemCountBadge.textContent = rows.length + ' ' + (rows.length === 1 ? 'صنف' : 'أصناف');
    }
    
    function bindRowEvents(row) {
        if (!row) return;
        const qtyInput = row.querySelector('input[name="items[][quantity]"]');
        const unitPriceInput = row.querySelector('input[name="items[][unit_price]"]');
        const discountRowInput = row.querySelector('input[name="items[][discount_value]"]');
        const removeBtn = row.querySelector('[data-remove-row]');
        
        [qtyInput, unitPriceInput, discountRowInput].forEach(function(input) {
            if (input) {
                input.addEventListener('input', recalculateCart);
                input.addEventListener('change', recalculateCart);
            }
        });
        if (removeBtn) removeBtn.addEventListener('click', function() { row.remove(); recalculateCart(); });
    }
    
    function getProductPrice(product) {
        if (!product) return 0;
        const tierSelect = document.querySelector('select[name="pricing_tier"]');
        const tier = tierSelect ? tierSelect.value : 'retail';
        if (tier === 'wholesale') return parseFloat(product.wholesale_price || 0);
        if (tier === 'half_wholesale') return parseFloat(product.half_wholesale_price || 0);
        return parseFloat(product.retail_price || 0);
    }
    
    function addProductToCart(product) {
        if (!cartItems) return;
        const template = document.getElementById('salesCartRowTemplate');
        if (!template) return;
        const emptyState = cartItems.querySelector('[data-sales-empty]');
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('[data-sales-item]');
        if (!row) return;
        
        const productIdInput = row.querySelector('input[name="items[][product_id]"]');
        const productUnitInput = row.querySelector('input[name="items[][product_unit_id]"]');
        const batchInput = row.querySelector('input[name="items[][batch_id]"]');
        const nameEl = row.querySelector('.sales-cart-item-name');
        const metaEl = row.querySelector('.sales-cart-item-meta');
        const qtyInput = row.querySelector('input[name="items[][quantity]"]');
        const unitPriceInput = row.querySelector('input[name="items[][unit_price]"]');
        const discountRowInput = row.querySelector('input[name="items[][discount_value]"]');
        const stockEl = row.querySelector('.sales-cart-item-stock');
        
        if (productIdInput) productIdInput.value = product.id || '';
        if (productUnitInput) productUnitInput.value = product.default_sale_unit_id || '';
        if (batchInput) batchInput.value = '';
        if (nameEl) nameEl.textContent = product.name || 'بدون اسم';
        if (metaEl) {
            const parts = [];
            if (product.code) parts.push('الكود: ' + product.code);
            if (product.category_name) parts.push(product.category_name);
            if (product.sale_unit_label) parts.push('الوحدة: ' + product.sale_unit_label);
            metaEl.textContent = parts.join(' • ');
        }
        if (qtyInput) qtyInput.value = 1;
        if (unitPriceInput) unitPriceInput.value = getProductPrice(product).toFixed(2);
        if (discountRowInput) discountRowInput.value = 0;
        if (stockEl) stockEl.textContent = product.sale_unit_label ? ('وحدة البيع: ' + product.sale_unit_label) : '';
        
        cartItems.appendChild(clone);
        const allRows = cartItems.querySelectorAll('[data-sales-item]');
        const lastRow = allRows[allRows.length - 1];
        bindRowEvents(lastRow);
        if (emptyState) emptyState.hidden = true;
        recalculateCart();
    }
    
    // ========================
    // 3. البحث المباشر عن العملاء أثناء الكتابة
    // ========================
    const customerSearchResults = document.getElementById('customerSearchResults');
    const customerSearchStatus = document.getElementById('customerSearchStatus');
    let searchTimeout;

    if (quickFullName && customerSearchResults) {
        quickFullName.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 2) {
                customerSearchResults.style.display = 'none';
                customerSearchResults.innerHTML = '';
                if (customerSearchStatus) customerSearchStatus.textContent = '';
                return;
            }
            
            searchTimeout = setTimeout(function() {
                fetch('ajax/customers/search.php?q=' + encodeURIComponent(searchTerm))
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.results && data.results.length > 0) {
                            let html = '';
                            data.results.forEach(function(customer) {
                                html += '<div class="search-result-item" style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0;" data-id="' + customer.id + '" data-full-name="' + customer.full_name + '" data-phone="' + (customer.phone || '') + '" data-category="' + (customer.category || '') + '">';
                                html += '<div style="font-weight: bold; color: #333;">' + customer.full_name + '</div>';
                                if (customer.phone || customer.category) {
                                    html += '<div style="font-size: 0.8rem; color: #666; margin-top: 2px;">';
                                    if (customer.phone) html += '📱 ' + customer.phone;
                                    if (customer.phone && customer.category) html += ' | ';
                                    if (customer.category) html += '📂 ' + customer.category;
                                    html += '</div>';
                                }
                                html += '</div>';
                            });
                            
                            customerSearchResults.innerHTML = html;
                            customerSearchResults.style.display = 'block';
                            
                            if (customerSearchStatus) {
                                customerSearchStatus.textContent = 'تم العثور على ' + data.results.length + ' عميل مشابه';
                                customerSearchStatus.style.color = '#f59e0b';
                            }
                            
                            customerSearchResults.querySelectorAll('.search-result-item').forEach(function(item) {
                                item.addEventListener('click', function() {
                                    if (quickFullName) quickFullName.value = this.dataset.fullName;
                                    if (quickPhone) quickPhone.value = this.dataset.phone || '';
                                    if (quickCategory) quickCategory.value = this.dataset.category || '';
                                    customerSearchResults.style.display = 'none';
                                    if (customerSearchStatus) {
                                        customerSearchStatus.textContent = 'تم اختيار عميل موجود من القائمة';
                                        customerSearchStatus.style.color = '#10b981';
                                    }
                                });
                                
                                item.addEventListener('mouseenter', function() { this.style.background = '#f0f7ff'; });
                                item.addEventListener('mouseleave', function() { this.style.background = 'white'; });
                            });
                        } else {
                            customerSearchResults.style.display = 'none';
                            customerSearchResults.innerHTML = '';
                            if (customerSearchStatus) {
                                customerSearchStatus.textContent = 'لا يوجد عملاء بهذا الاسم - يمكنك إضافة عميل جديد';
                                customerSearchStatus.style.color = '#10b981';
                            }
                        }
                    })
                    .catch(function(error) {
                        console.error('Search error:', error);
                        customerSearchResults.style.display = 'none';
                    });
            }, 300);
        });
        
        document.addEventListener('click', function(e) {
            if (quickFullName && customerSearchResults) {
                if (!quickFullName.contains(e.target) && !customerSearchResults.contains(e.target)) {
                    customerSearchResults.style.display = 'none';
                }
            }
        });
        
        quickFullName.addEventListener('keydown', function(e) {
            const results = customerSearchResults.querySelectorAll('.search-result-item');
            if (results.length === 0 || customerSearchResults.style.display === 'none') return;
            
            const current = customerSearchResults.querySelector('.search-result-item.active');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!current) {
                    results[0].classList.add('active');
                    results[0].style.background = '#e3f2fd';
                } else {
                    const next = current.nextElementSibling;
                    if (next) {
                        current.classList.remove('active');
                        current.style.background = 'white';
                        next.classList.add('active');
                        next.style.background = '#e3f2fd';
                    }
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (current) {
                    const prev = current.previousElementSibling;
                    if (prev) {
                        current.classList.remove('active');
                        current.style.background = 'white';
                        prev.classList.add('active');
                        prev.style.background = '#e3f2fd';
                    }
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (current) {
                    current.click();
                }
            }
        });
    }

    // ========================
    // 4. فتح وإغلاق نافذة العميل السريع
    // ========================
    if (toggleQuickCustomerBtn && quickCustomerPanel) {
        const newToggleBtn = toggleQuickCustomerBtn.cloneNode(true);
        toggleQuickCustomerBtn.parentNode.replaceChild(newToggleBtn, toggleQuickCustomerBtn);
        
        newToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            quickCustomerPanel.hidden = false;
            syncQuickCustomerMarketer();
        });
    }
    
    if (closeQuickCustomerBtn && quickCustomerPanel) {
        const newCloseBtn = closeQuickCustomerBtn.cloneNode(true);
        closeQuickCustomerBtn.parentNode.replaceChild(newCloseBtn, closeQuickCustomerBtn);
        
        newCloseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            quickCustomerPanel.hidden = true;
            if (quickFullName) quickFullName.value = '';
            if (quickPhone) quickPhone.value = '';
            if (quickCategory) quickCategory.value = '';
            if (quickCreditLimit) quickCreditLimit.value = '0';
            if (quickMarketerId) quickMarketerId.value = '';
            if (customerSearchResults) customerSearchResults.style.display = 'none';
            if (customerSearchStatus) customerSearchStatus.textContent = '';
        });
    }
// ========================
// 5. حفظ العميل الجديد مع منع التكرار
// ========================
if (saveQuickCustomerBtn) {
    const newSaveBtn = saveQuickCustomerBtn.cloneNode(true);
    saveQuickCustomerBtn.parentNode.replaceChild(newSaveBtn, saveQuickCustomerBtn);

    newSaveBtn.addEventListener('click', function() {
        const fullName = quickFullName ? quickFullName.value.trim() : '';
        const phone = quickPhone ? quickPhone.value.trim() : '';
        const category = quickCategory ? quickCategory.value.trim() : '';
        const creditLimit = quickCreditLimit ? quickCreditLimit.value : 0;
        const marketerId = quickMarketerId ? quickMarketerId.value : '';

        if (!fullName) {
            showToast('برجاء إدخال اسم العميل', 'error', 'خطأ');
            if (quickFullName) quickFullName.focus();
            return;
        }

        if (!phone) {
            showToast('برجاء إدخال رقم الهاتف', 'error', 'خطأ');
            if (quickPhone) quickPhone.focus();
            return;
        }

        newSaveBtn.disabled = true;
        const originalHtml = newSaveBtn.innerHTML;
        newSaveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري التحقق...';

        fetch('ajax/customers/search.php?q=' + encodeURIComponent(fullName))
            .then(function(response) {
                return response.json();
            })
            .then(function(searchData) {
                if (searchData.results && searchData.results.length > 0) {
                    const exactMatch = searchData.results.find(function(customer) {
                        const sameName =
                            customer.full_name &&
                            customer.full_name.trim().toLowerCase() === fullName.toLowerCase();

                        const samePhone =
                            phone !== '' &&
                            customer.phone &&
                            customer.phone.trim() === phone;

                        return sameName || samePhone;
                    });

                    if (exactMatch) {
                        let warningMessage = 'هذا العميل موجود مسبقاً: ' + exactMatch.full_name;

                        if (exactMatch.phone) {
                            warningMessage += ' | هاتف: ' + exactMatch.phone;
                        }

                        showToast(warningMessage, 'error', 'عميل مكرر');

                        newSaveBtn.disabled = false;
                        newSaveBtn.innerHTML = originalHtml;

                        if (quickFullName) quickFullName.focus();
                        return;
                    }
                }

                saveCustomer();
            })
            .catch(function() {
                saveCustomer();
            });

        function saveCustomer() {
            newSaveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري الحفظ...';

            const formData = new FormData();
            formData.append('full_name', fullName);
            formData.append('phone', phone);
            formData.append('category', category);
            formData.append('credit_limit', creditLimit);
            formData.append('status', 'active');
            formData.append('quick_create', '1');

            if (marketerId) {
                formData.append('marketer_id', marketerId);
            }

            const tokenInput = document.querySelector('form[data-sales-pos] input[name="_token"]');
            if (tokenInput && tokenInput.value) {
                formData.append('_token', tokenInput.value);
            }

            fetch('ajax/customers/save.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(result) {
                if (result.success && result.data && result.data.id) {
                    showToast('تم حفظ العميل بنجاح', 'success', 'نجاح');

                    if (customerSelect) {
                        const option = document.createElement('option');
                        option.value = result.data.id;
                        option.textContent = result.data.full_name || fullName;

                        if (result.data.marketer_id) {
                            option.dataset.marketerId = result.data.marketer_id;
                        }

                        customerSelect.appendChild(option);
                        customerSelect.value = result.data.id;
                        customerSelect.dispatchEvent(new Event('change'));
                    }

                    if (quickFullName) quickFullName.value = '';
                    if (quickPhone) quickPhone.value = '';
                    if (quickCategory) quickCategory.value = '';
                    if (quickCreditLimit) quickCreditLimit.value = '0';
                    if (quickMarketerId) quickMarketerId.value = '';
                    if (quickCustomerPanel) quickCustomerPanel.hidden = true;
                    if (customerSearchResults) customerSearchResults.style.display = 'none';
                    if (customerSearchStatus) customerSearchStatus.textContent = '';

                } else {
                    let errorMessage = result.message || 'حدث خطأ أثناء حفظ العميل';

                    if (
                        errorMessage.includes('Duplicate') ||
                        errorMessage.includes('مكرر') ||
                        errorMessage.includes('موجود')
                    ) {
                        errorMessage = 'هذا العميل موجود مسبقاً ولا يمكن تكراره.';
                    }

                    showToast(errorMessage, 'error', 'خطأ');
                }
            })
            .catch(function(err) {
                console.error('Save customer error:', err);
                showToast('خطأ في الاتصال بالخادم', 'error', 'خطأ');
            })
            .finally(function() {
                newSaveBtn.disabled = false;
                newSaveBtn.innerHTML = originalHtml;
            });
        }
    });
}
    
    // ========================
    // 6. باقي الكود (منتجات، تعديل، فلتر، أحداث)
    // ========================
    if (customerSelect) {
        customerSelect.addEventListener('change', syncCustomerMarketer);
    }
    
    if (marketerSelect) {
        marketerSelect.addEventListener('change', function() {
            syncSaleMode();
            syncQuickCustomerMarketer();
        });
        syncSaleMode();
        syncQuickCustomerMarketer();
    }
    
    // تحميل بيانات التعديل
    const editSaleId = <?= (int) $saleId; ?>;
    const editItems = <?= json_encode($editSaleItems ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const editSale = <?= $editSale ? json_encode($editSale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null'; ?>;
    
    if (editSaleId && editSale && editItems && editItems.length > 0 && cartItems) {
        const invoiceNoInput = document.querySelector('input[name="invoice_no"]');
        if (invoiceNoInput) invoiceNoInput.value = editSale.invoice_no || '';
        if (customerSelect) customerSelect.value = editSale.customer_id || '';
        const branchSelect = document.querySelector('select[name="branch_id"]');
        if (branchSelect) branchSelect.value = editSale.branch_id || '';
        if (warehouseSelect) warehouseSelect.value = editSale.warehouse_id || '';
        if (marketerSelect) marketerSelect.value = editSale.marketer_id || '';
        const pricingSelect = document.querySelector('select[name="pricing_tier"]');
        if (pricingSelect) pricingSelect.value = editSale.pricing_tier || 'wholesale';
        const paymentSelect = document.querySelector('select[name="payment_method"]');
        if (paymentSelect) paymentSelect.value = editSale.payment_method || 'cash';
        const subtotalInput = document.querySelector('input[name="subtotal"]');
        if (subtotalInput) subtotalInput.value = editSale.subtotal || 0;
        const discountInput = document.querySelector('input[name="discount_value"]');
        if (discountInput) discountInput.value = editSale.discount_value || 0;
        const totalInput = document.querySelector('input[name="total_amount"]');
        if (totalInput) totalInput.value = editSale.total_amount || 0;
        const paidInput = document.querySelector('input[name="paid_amount"]');
        if (paidInput) paidInput.value = editSale.paid_amount || 0;
        const dueInput = document.querySelector('input[name="due_amount"]');
        if (dueInput) dueInput.value = editSale.due_amount || 0;
        const notesTextarea = document.querySelector('textarea[name="notes"]');
        if (notesTextarea) notesTextarea.value = editSale.notes || '';
        
        const template = document.getElementById('salesCartRowTemplate');
        if (template) {
            cartItems.querySelectorAll('[data-sales-item]').forEach(function(item) { item.remove(); });
            editItems.forEach(function(item) {
                const clone = template.content.cloneNode(true);
                const row = clone.querySelector('[data-sales-item]');
                if (!row) return;
                const productIdInput = row.querySelector('input[name="items[][product_id]"]');
                const productUnitInput = row.querySelector('input[name="items[][product_unit_id]"]');
                const batchInput = row.querySelector('input[name="items[][batch_id]"]');
                const nameEl = row.querySelector('.sales-cart-item-name');
                const qtyInput = row.querySelector('input[name="items[][quantity]"]');
                const unitPriceInput = row.querySelector('input[name="items[][unit_price]"]');
                const discountRowInput = row.querySelector('input[name="items[][discount_value]"]');
                const totalEl = row.querySelector('.sales-cart-item-total');
                if (productIdInput) productIdInput.value = item.product_id || '';
                if (productUnitInput) productUnitInput.value = item.product_unit_id || '';
                if (batchInput) batchInput.value = item.batch_id || '';
                if (nameEl) nameEl.textContent = item.product_name || ('صنف #' + (item.product_id || ''));
                if (qtyInput) qtyInput.value = item.quantity || 1;
                if (unitPriceInput) unitPriceInput.value = item.unit_price || 0;
                if (discountRowInput) discountRowInput.value = item.discount_value || 0;
                const total = (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)) - parseFloat(item.discount_value || 0);
                if (totalEl) totalEl.textContent = total.toFixed(2);
                cartItems.appendChild(clone);
            });
            if (editItems.length > 0) {
                const emptyState = cartItems.querySelector('[data-sales-empty]');
                if (emptyState) emptyState.hidden = true;
            }
        }
    }
    
    // تحميل المنتجات
    const productsDataEl = document.getElementById('salesProductsData');
    const productsGrid = document.getElementById('salesProductsGrid');
    const productsEmpty = document.getElementById('salesProductsEmpty');
    const productsMoreBtn = document.getElementById('salesProductsMore');
    const productCardTemplate = document.getElementById('salesProductCardTemplate');
    const categoryFilters = document.getElementById('salesCategoryFilters');
    const productSearchInput = document.getElementById('salesProductSearch');
    const pricingTierSelect = document.querySelector('select[name="pricing_tier"]');
    
    let salesProducts = [];
    let filteredProducts = [];
    let renderedCount = 0;
    const renderStep = 24;
    let activeCategory = 'all';
    
    function parseProductsData() {
        if (!productsDataEl) return [];
        try {
            const raw = productsDataEl.textContent ? productsDataEl.textContent.trim() : '[]';
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.error('فشل قراءة بيانات المنتجات:', error);
            return [];
        }
    }
    
    function getProductText(product) {
        return [product.name || '', product.code || '', product.category_name || ''].join(' ').toLowerCase();
    }
    
    function filterProducts() {
        const term = productSearchInput ? productSearchInput.value.trim().toLowerCase() : '';
        filteredProducts = salesProducts.filter(function(product) {
            const matchCategory = activeCategory === 'all' || String(product.category_id || '') === String(activeCategory);
            const matchSearch = term === '' || getProductText(product).includes(term);
            return matchCategory && matchSearch;
        });
        renderedCount = 0;
        if (productsGrid) productsGrid.innerHTML = '';
        renderMoreProducts();
        if (productsEmpty) productsEmpty.hidden = filteredProducts.length > 0;
        if (productsMoreBtn) productsMoreBtn.hidden = filteredProducts.length <= renderStep;
    }
    
    function renderMoreProducts() {
        if (!productsGrid || !productCardTemplate) return;
        const nextItems = filteredProducts.slice(renderedCount, renderedCount + renderStep);
        nextItems.forEach(function(product) {
            const clone = productCardTemplate.content.cloneNode(true);
            const card = clone.querySelector('[data-sales-product]');
            if (!card) return;
            const nameEl = card.querySelector('.sales-product-name');
            const subtitleEl = card.querySelector('.sales-product-subtitle');
            const stockEl = card.querySelector('[data-stock-target]');
            const priceEl = card.querySelector('[data-price-target]');
            if (nameEl) nameEl.textContent = product.name || 'بدون اسم';
            if (subtitleEl) {
                const parts = [];
                if (product.code) parts.push(product.code);
                if (product.category_name) parts.push(product.category_name);
                subtitleEl.textContent = parts.join(' • ');
            }
            if (stockEl) {
                stockEl.textContent = product.sale_unit_label ? ('وحدة البيع: ' + product.sale_unit_label) : 'جاهز للإضافة';
            }
            if (priceEl) priceEl.textContent = getProductPrice(product).toFixed(2);
            card.dataset.product = JSON.stringify(product);
            card.addEventListener('click', function() { addProductToCart(product); });
            productsGrid.appendChild(clone);
        });
        renderedCount += nextItems.length;
        if (productsMoreBtn) productsMoreBtn.hidden = renderedCount >= filteredProducts.length;
    }
    
    if (productsMoreBtn) productsMoreBtn.addEventListener('click', renderMoreProducts);
    if (productSearchInput) productSearchInput.addEventListener('input', filterProducts);
    if (pricingTierSelect) {
        pricingTierSelect.addEventListener('change', function() {
            if (productsGrid) {
                productsGrid.querySelectorAll('[data-sales-product]').forEach(function(card) {
                    try {
                        const product = JSON.parse(card.dataset.product || '{}');
                        const priceEl = card.querySelector('[data-price-target]');
                        if (priceEl) priceEl.textContent = getProductPrice(product).toFixed(2);
                    } catch(e) {}
                });
            }
        });
    }
    if (categoryFilters) {
        categoryFilters.addEventListener('click', function(event) {
            const btn = event.target.closest('[data-category-filter]');
            if (!btn) return;
            activeCategory = btn.getAttribute('data-category-filter') || 'all';
            categoryFilters.querySelectorAll('[data-category-filter]').forEach(function(chip) { chip.classList.remove('active'); });
            btn.classList.add('active');
            filterProducts();
        });
    }
    
    const clearCartBtn = document.querySelector('[data-sales-clear]');
    if (clearCartBtn && cartItems) {
        clearCartBtn.addEventListener('click', function() {
            cartItems.querySelectorAll('[data-sales-item]').forEach(function(item) { item.remove(); });
            recalculateCart();
        });
    }
    
    const discountInput = document.querySelector('input[name="discount_value"]');
    const paidInput = document.querySelector('input[name="paid_amount"]');
    if (discountInput) {
        discountInput.addEventListener('input', recalculateCart);
        discountInput.addEventListener('change', recalculateCart);
    }
    if (paidInput) {
        paidInput.addEventListener('input', recalculateCart);
        paidInput.addEventListener('change', recalculateCart);
    }
    
    salesProducts = parseProductsData();
    if (productsGrid) {
        if (!Array.isArray(salesProducts) || salesProducts.length === 0) {
            const retryKey = 'sales_pos_retry_once';
            if (!sessionStorage.getItem(retryKey)) {
                sessionStorage.setItem(retryKey, '1');
                setTimeout(function() { window.location.reload(); }, 400);
                return;
            } else {
                sessionStorage.removeItem(retryKey);
            }
            productsGrid.innerHTML = '';
            if (productsEmpty) {
                productsEmpty.hidden = false;
                productsEmpty.textContent = 'تعذر تحميل الأصناف. راجع بيانات المنتجات أو تحميل الصفحة.';
            }
            if (productsMoreBtn) productsMoreBtn.hidden = true;
        } else {
            sessionStorage.removeItem('sales_pos_retry_once');
            filterProducts();
        }
    }
    
    const toggleBtn = document.getElementById('toggleFilters');
    const filterPanel = document.getElementById('filterPanel');
    const filterForm = document.getElementById('salesFilterForm');
    if (toggleBtn && filterPanel) {
        const hasActiveFilters = Array.from(filterPanel.querySelectorAll('input, select')).some(function(el) {
            if (el.name === 'module' || el.name === 'search') return false;
            const val = el.value;
            if (el.type === 'text' || el.type === 'number' || el.type === 'date') return val !== '';
            if (el.tagName === 'SELECT') return val !== '0' && val !== '';
            return false;
        });
        if (hasActiveFilters) {
            filterPanel.style.display = 'block';
            toggleBtn.classList.add('active');
        }
        toggleBtn.addEventListener('click', function() {
            const isHidden = filterPanel.style.display === 'none' || filterPanel.style.display === '';
            filterPanel.style.display = isHidden ? 'block' : 'none';
            toggleBtn.classList.toggle('active', isHidden);
        });
    }
    
    const salesForm = document.querySelector('form[data-sales-pos]');
    const pageLoading = document.getElementById('pageLoading');
    const saveBtn = document.getElementById('saveSaleBtn');
    if (salesForm) {
        salesForm.addEventListener('submit', function(e) {
            if (!salesForm.checkValidity()) return;
            e.preventDefault();
            if (pageLoading) pageLoading.hidden = false;
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>جاري الحفظ...</span>';
            }
            const formData = new FormData(salesForm);
            fetch(salesForm.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                if (result.success) {
                    showToast(result.message || 'تم حفظ الفاتورة بنجاح', 'success', 'نجاح');
                    setTimeout(function() { window.location.href = 'index.php?module=sales'; }, 800);
                } else {
                    showToast(result.message || 'حدث خطأ أثناء الحفظ', 'error', 'خطأ');
                    if (pageLoading) pageLoading.hidden = true;
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i><span>حفظ الفاتورة</span>';
                    }
                }
            })
            .catch(function(err) {
                console.error('Submit error:', err);
                showToast('خطأ في الاتصال بالخادم', 'error', 'خطأ');
                if (pageLoading) pageLoading.hidden = true;
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i><span>حفظ الفاتورة</span>';
                }
            });
        });
    }
    
    document.addEventListener('click', function(e) {
        if (e.target.closest('[data-sales-product]')) {
            const card = e.target.closest('.sales-product-card');
            if (card) {
                card.classList.add('is-flash');
                setTimeout(function() { card.classList.remove('is-flash'); }, 600);
            }
        }
    });
    
    if (cartItems) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                if (m.addedNodes.length) {
                    m.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1 && node.classList && node.classList.contains('sales-cart-item')) {
                            node.classList.add('is-new');
                            setTimeout(function() { node.classList.remove('is-new'); }, 800);
                        }
                    });
                }
            });
        });
        observer.observe(cartItems, { childList: true });
    }
});
</script>