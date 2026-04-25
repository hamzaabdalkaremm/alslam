<?php
$pageSubtitle = 'إدارة الأصناف والأسعار والوحدات والباركود';
require_once __DIR__ . '/../../includes/helpers/functions.php';
$crud = new CrudService();
$isSuperAdmin = Auth::isSuperAdmin();
$accessibleBranchIds = Auth::branchIds();
$view = (string) request_input('view', 'list');
$editId = (int) request_input('edit', 0);

if ($editId > 0) {
    Auth::requireBranchAccess($editId);
}

$product = $editId ? $crud->find('products', $editId) : null;

// Enforce create/update permissions for product form
if ($view === 'form') {
    if ($editId > 0 && !Auth::can('products.update')) {
        Auth::requirePermission('products.update');
    } elseif ($editId === 0 && !Auth::can('products.create')) {
        Auth::requirePermission('products.create');
    }
}




$nextProductCode = $product['code'] ?? next_product_code();
$currentPage = max(1, (int) request_input('page', 1));
$perPage = (int) app_config('per_page');
$filters = ['name' => request_input('search', '')];

// Apply branch filtering for non-super admins
if (!$isSuperAdmin) {
    if ($accessibleBranchIds) {
        $filters['branch_id'] = ['in' => $accessibleBranchIds];
    } else {
        $filters['branch_id'] = ['in' => [0]]; // No accessible branches
    }
}


$productsPage = $crud->paginate('products', $filters, $currentPage, $perPage);





$categories = Database::connection()->query("SELECT id, name FROM product_categories WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
$units = Database::connection()->query("SELECT id, name FROM units ORDER BY id ASC")->fetchAll();
$defaultBaseUnitId = $product['base_unit_id'] ?? '';

if ($defaultBaseUnitId === '') {
    foreach ($units as $unit) {
        $unitName = trim((string) ($unit['name'] ?? ''));
        if ($unitName === 'كرتونة' || strtolower($unitName) === 'carton') {
            $defaultBaseUnitId = (string) $unit['id'];
            break;
        }
    }
}

$productUnits = [];
if ($editId) {
    $stmt = Database::connection()->prepare(
        "SELECT * FROM product_units WHERE product_id = :product_id ORDER BY is_default_sale_unit DESC, id ASC"
    );
    $stmt->execute(['product_id' => $editId]);
    $productUnits = $stmt->fetchAll();
}

if (!$productUnits) {
    $productUnits[] = [
        'unit_id' => $defaultBaseUnitId,
        'label' => 'الوحدة الأساسية',
        'units_per_base' => 1,
        'barcode' => $product['barcode'] ?? '',
        'purchase_price' => $product['cost_price'] ?? 0,
        'wholesale_price' => $product['wholesale_price'] ?? 0,
        'half_wholesale_price' => $product['half_wholesale_price'] ?? 0,
        'retail_price' => $product['retail_price'] ?? 0,
        'is_default_sale_unit' => 1,
        'is_default_purchase_unit' => 1,
    ];
}
?>

<?php if ($view === 'form'): ?>
    <div class="card">
        <div class="toolbar">
            <div>
                <h3><?= $product ? 'تعديل منتج' : 'إضافة منتج'; ?></h3>
                <p class="card-intro">أدخل بيانات المنتج العامة هنا، ثم عرّف وحدات البيع والشراء والباركودات المتعددة في القسم السفلي. الكميات لا تُدخل من هذه الشاشة، بل تُدار من المخزون والمشتريات والمبيعات.</p>
            </div>
            <a class="btn btn-light" href="index.php?module=products">العودة إلى القائمة</a>
        </div>

        <form action="ajax/products/save.php" method="post" data-ajax-form>
            <?= csrf_field(); ?>
            <input type="hidden" name="id" value="<?= e((string) ($product['id'] ?? '')); ?>">

            <div class="product-form-layout">
                <section class="product-panel">
                    <h4>بيانات التعريف</h4>
                    <p>الحقول الأساسية التي تعرف المنتج داخل النظام والقوائم.</p>
<div class="grid">
<div>
<label>الكود الداخلي</label>
<input name="code" required value="<?= e($nextProductCode); ?>">
<small class="field-help">يولد تلقائياً بدءاً من 00001، ويمكن تعديله عند الحاجة.</small>
</div>
<div>
<label>اسم المنتج</label>
<input name="name" required value="<?= e($product['name'] ?? ''); ?>">
<small class="field-help">الاسم التجاري الظاهر في الفواتير والقوائم.</small>
</div>
<div>
<label>التصنيف</label>
<select name="category_id">
<option value="">بدون تصنيف</option>
<?php foreach ($categories as $category): ?>
<option value="<?= e((string) $category['id']); ?>" <?= (string) ($product['category_id'] ?? '') === (string) $category['id'] ? 'selected' : ''; ?>><?= e($category['name']); ?></option>
<?php endforeach; ?>
</select>
<small class="field-help">يساعدك في فرز المنتجات وإخراج تقارير حسب النوع.</small>
</div>
<div>
<label>الوحدة الأساسية</label>
<select name="base_unit_id">
<option value="">اختيار</option>
<?php foreach ($units as $unit): ?>
<option value="<?= e((string) $unit['id']); ?>" <?= (string) $defaultBaseUnitId === (string) $unit['id'] ? 'selected' : ''; ?>><?= e($unit['name']); ?></option>
<?php endforeach; ?>
</select>
<small class="field-help">أصغر وحدة يعتمد عليها المخزون، مثل حبة أو كيلو.</small>
</div>
</div>
</section>

<section class="product-panel">
<h4>الفرع</h4>
<p>اختر الفرع الذي يتبع له هذا المنتج.</p>
<div class="grid">
<div>
<label>الفرع</label>
<select name="branch_id">
<option value="">الفرع الافتراضي</option>
<?php foreach (branches_options() as $branch): ?>
<option value="<?= e((string) $branch['id']); ?>" <?= (!empty($product['branch_id']) && (string) $product['branch_id'] === (string) $branch['id']) ? 'selected' : ''; ?>><?= e($branch['name_ar']); ?></option>
<?php endforeach; ?>
</select>
<small class="field-help">المنتجات ستظهر فقط للمسؤولين عن هذا الفرع.</small>
</div>
</div>
</section>

                <section class="product-panel">
                    <h4>بيانات تجارية</h4>
                    <p>بيانات تساعد في البيع والشراء والتصنيف العملي للمنتج.</p>
                    <div class="grid">
                        <div>
                            <label>الماركة / الشركة</label>
                            <input name="brand" value="<?= e($product['brand'] ?? ''); ?>">
                            <small class="field-help">اسم الماركة أو الشركة المنتجة إن وجد.</small>
                        </div>
                        <div>
                            <label>سعر شراء افتراضي</label>
                            <input type="number" step="0.01" name="cost_price" value="<?= e(format_input_number($product['cost_price'] ?? '0', 2)); ?>">
                            <small class="field-help">آخر أو افتراضي سعر شراء. الأسعار التفصيلية لكل وحدة تُسجل في قسم الوحدات بالأسفل.</small>
                        </div>
                        <div>
                            <label>سعر بيع جملة افتراضي</label>
                            <input type="number" step="0.01" name="wholesale_price" value="<?= e(format_input_number($product['wholesale_price'] ?? '0', 2)); ?>">
                            <small class="field-help">سعر الجملة الافتراضي للمنتج كمرجع سريع.</small>
                        </div>
                        <div>
                            <label>سعر نصف الجملة الافتراضي</label>
                            <input type="number" step="0.01" name="half_wholesale_price" value="<?= e(format_input_number($product['half_wholesale_price'] ?? '0', 2)); ?>">
                            <small class="field-help">يستخدم للحالات بين الجملة والمفرد.</small>
                        </div>
                        <div>
                            <label>سعر بيع مفرد افتراضي</label>
                            <input type="number" step="0.01" name="retail_price" value="<?= e(format_input_number($product['retail_price'] ?? '0', 2)); ?>">
                            <small class="field-help">سعر المفرد الافتراضي للمنتج كمرجع سريع.</small>
                        </div>
                    </div>
                </section>

                <section class="product-panel">
                    <h4>مخزني وتشغيلي</h4>
                    <p>إعدادات مساعدة للمخزون وطريقة استخدام المنتج داخل المتجر.</p>
                    <div class="grid">
                        <div>
                            <label>موقع الرف</label>
                            <input name="shelf_location" value="<?= e($product['shelf_location'] ?? ''); ?>">
                            <small class="field-help">مكان تخزين الصنف داخل المستودع أو الرف.</small>
                        </div>
                        <div>
                            <label>حد تنبيه المخزون</label>
                            <input type="number" step="1" min="0" name="min_stock_alert" value="<?= e((string) (int) ($product['min_stock_alert'] ?? 0)); ?>">
                            <small class="field-help">إذا وصل الرصيد إلى هذا الحد أو أقل، يظهر تنبيه في النظام.</small>
                        </div>
                        <div class="checkbox-grid">
                            <label><input type="checkbox" name="sell_by_piece" value="1" <?= (int) ($product['sell_by_piece'] ?? 0) === 1 ? 'checked' : ''; ?>> بيع بالحبة</label>
                            <label><input type="checkbox" name="sell_by_carton" value="1" <?= !isset($product['sell_by_carton']) || (int) $product['sell_by_carton'] === 1 ? 'checked' : ''; ?>> بيع بالكرتونة</label>
                            <label><input type="checkbox" name="is_active" value="1" <?= !isset($product['is_active']) || (int) $product['is_active'] === 1 ? 'checked' : ''; ?>> منتج فعال</label>
                        </div>
                    </div>
                </section>
            </div>

            <div class="form-section">
                <h4>وحدات البيع والشراء والباركودات</h4>
                <p>يمكنك تعريف أكثر من باركود للمنتج نفسه عبر الوحدات المختلفة مثل: حبة، علبة، كرتونة. كل وحدة يمكن أن تحمل سعر شراء وسعر بيع مستقلين.</p>

                <div class="product-unit-rows" id="productUnitRows">
                    <?php foreach ($productUnits as $index => $productUnit): ?>
                        <div class="row">
                            <div>
                                <label>الوحدة</label>
                                <select name="units[<?= e((string) $index); ?>][unit_id]" required>
                                    <option value="">اختيار</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?= e((string) $unit['id']); ?>" <?= (string) ($productUnit['unit_id'] ?? '') === (string) $unit['id'] ? 'selected' : ''; ?>><?= e($unit['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="field-help">مثل حبة أو كرتونة أو علبة.</small>
                            </div>
                            <div>
                                <label>اسم العرض</label>
                                <input name="units[<?= e((string) $index); ?>][label]" value="<?= e($productUnit['label'] ?? ''); ?>" placeholder="مثال: كرتونة 24 حبة">
                                <small class="field-help">اسم يظهر للمستخدم عند اختيار هذه الوحدة.</small>
                            </div>
                            <div>
                                <label>عددها من الأساسية</label>
                                <input type="number" step="0.001" name="units[<?= e((string) $index); ?>][units_per_base]" value="<?= e(format_input_number($productUnit['units_per_base'] ?? 1, 3)); ?>">
                                <small class="field-help">مثال: الكرتونة = 24 من الوحدة الأساسية.</small>
                            </div>
                            <div>
                                <label>الباركود</label>
                                <input name="units[<?= e((string) $index); ?>][barcode]" value="<?= e($productUnit['barcode'] ?? ''); ?>">
                                <small class="field-help">يمكن تركه فارغاً أو إدخال باركود مختلف لكل وحدة.</small>
                            </div>
                            <div>
                                <label>سعر الشراء</label>
                                <input type="number" step="0.01" name="units[<?= e((string) $index); ?>][purchase_price]" value="<?= e(format_input_number($productUnit['purchase_price'] ?? 0, 2)); ?>">
                                <small class="field-help">تكلفة شراء هذه الوحدة من المورد.</small>
                            </div>
                            <div>
                                <label>سعر الجملة</label>
                                <input type="number" step="0.01" name="units[<?= e((string) $index); ?>][wholesale_price]" value="<?= e(format_input_number($productUnit['wholesale_price'] ?? 0, 2)); ?>">
                                <small class="field-help">سعر البيع بالجملة لهذه الوحدة.</small>
                            </div>
                            <div>
                                <label>نصف الجملة</label>
                                <input type="number" step="0.01" name="units[<?= e((string) $index); ?>][half_wholesale_price]" value="<?= e(format_input_number($productUnit['half_wholesale_price'] ?? 0, 2)); ?>">
                                <small class="field-help">سعر نصف الجملة لهذه الوحدة.</small>
                            </div>
                            <div>
                                <label>سعر المفرد</label>
                                <input type="number" step="0.01" name="units[<?= e((string) $index); ?>][retail_price]" value="<?= e(format_input_number($productUnit['retail_price'] ?? 0, 2)); ?>">
                                <small class="field-help">سعر البيع النهائي لهذه الوحدة.</small>
                                <label><input type="checkbox" name="units[<?= e((string) $index); ?>][is_default_sale_unit]" value="1" <?= !empty($productUnit['is_default_sale_unit']) ? 'checked' : ''; ?>> افتراضي للبيع</label>
                                <label><input type="checkbox" name="units[<?= e((string) $index); ?>][is_default_purchase_unit]" value="1" <?= !empty($productUnit['is_default_purchase_unit']) ? 'checked' : ''; ?>> افتراضي للشراء</label>
                            </div>
                            <div class="align-self-end">
                                <button class="btn btn-danger" type="button" data-remove-row>حذف</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <template id="productUnitRowTemplate">
                    <div class="row">
                        <div>
                            <label>الوحدة</label>
                            <select name="units[][unit_id]" required>
                                <option value="">اختيار</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= e((string) $unit['id']); ?>"><?= e($unit['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-help">مثل حبة أو كرتونة أو علبة.</small>
                        </div>
                        <div>
                            <label>اسم العرض</label>
                            <input name="units[][label]" placeholder="مثال: كرتونة 24 حبة">
                            <small class="field-help">اسم يظهر للمستخدم عند اختيار هذه الوحدة.</small>
                        </div>
                        <div>
                            <label>عددها من الأساسية</label>
                            <input type="number" step="0.001" name="units[][units_per_base]" value="1">
                            <small class="field-help">مثال: الكرتونة = 24 من الوحدة الأساسية.</small>
                        </div>
                        <div>
                            <label>الباركود</label>
                            <input name="units[][barcode]">
                            <small class="field-help">يمكن إدخال باركود مستقل لكل وحدة.</small>
                        </div>
                        <div>
                            <label>سعر الشراء</label>
                            <input type="number" step="0.01" name="units[][purchase_price]" value="0">
                            <small class="field-help">تكلفة شراء هذه الوحدة من المورد.</small>
                        </div>
                        <div>
                            <label>سعر الجملة</label>
                            <input type="number" step="0.01" name="units[][wholesale_price]" value="0">
                            <small class="field-help">سعر البيع بالجملة لهذه الوحدة.</small>
                        </div>
                        <div>
                            <label>نصف الجملة</label>
                            <input type="number" step="0.01" name="units[][half_wholesale_price]" value="0">
                            <small class="field-help">سعر نصف الجملة لهذه الوحدة.</small>
                        </div>
                        <div>
                            <label>سعر المفرد</label>
                            <input type="number" step="0.01" name="units[][retail_price]" value="0">
                            <small class="field-help">سعر البيع النهائي لهذه الوحدة.</small>
                            <label><input type="checkbox" name="units[][is_default_sale_unit]" value="1"> افتراضي للبيع</label>
                            <label><input type="checkbox" name="units[][is_default_purchase_unit]" value="1"> افتراضي للشراء</label>
                        </div>
                        <div class="align-self-end">
                            <button class="btn btn-danger" type="button" data-remove-row>حذف</button>
                        </div>
                    </div>
                </template>

                <button class="btn btn-light" type="button" data-add-row data-target="#productUnitRows" data-template="#productUnitRowTemplate">إضافة وحدة / باركود</button>
            </div>

            <div class="form-section">
                <h4>ملاحظات المنتج</h4>
                <p>أي معلومات إضافية مثل نوع التغليف أو تعليمات التخزين أو ملاحظات البيع.</p>
                <label>ملاحظات</label>
                <textarea name="notes"><?= e($product['notes'] ?? ''); ?></textarea>
            </div>

            <div class="mt-2">
                <?php if (Auth::can('products.create') || Auth::can('products.update')): ?>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> حفظ المنتج</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="card">
        <div class="toolbar">
            <div>
                <h3>قائمة المنتجات</h3>
                <p class="card-intro">هذه الصفحة مخصصة لاستعراض المنتجات فقط. إضافة المنتج أو تعديله تتم من صفحة مستقلة.</p>
            </div>
            <?php if (Auth::can('products.create')): ?>
                <a class="btn btn-primary" href="index.php?module=products&view=form">إضافة منتج</a>
            <?php endif; ?>
        </div>

        <div class="toolbar">
            <form method="get" class="toolbar-search">
                <input type="hidden" name="module" value="products">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="بحث باسم المنتج أو الكود" value="<?= e(request_input('search', '')); ?>">
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead><tr><th>الكود</th><th>الاسم</th><th>شراء</th><th>جملة</th><th>مفرد</th><th>الحالة</th><th>إجراءات</th></tr></thead>
                <tbody>
                <?php foreach ($productsPage['data'] as $row): ?>
                    <tr>
                        <td><?= e($row['code']); ?></td>
                        <td><?= e($row['name']); ?></td>
                        <td><?= e(format_currency($row['cost_price'])); ?></td>
                        <td><?= e(format_currency($row['wholesale_price'])); ?></td>
                        <td><?= e(format_currency($row['retail_price'])); ?></td>
                        <td><span class="badge <?= (int) $row['is_active'] === 1 ? 'success' : 'warning'; ?>"><?= (int) $row['is_active'] === 1 ? 'فعال' : 'موقف'; ?></span></td>
                        <td>
                            <a class="btn btn-light" href="index.php?module=products&view=form&edit=<?= e((string) $row['id']); ?>">تعديل</a>
                            <button type="button" class="btn btn-danger" data-id="<?= e((string) $row['id']); ?>" data-delete-url="ajax/products/delete.php" data-confirm="هل تريد حذف المنتج؟">حذف</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($productsPage, $currentPage, $perPage, ['module' => 'products', 'search' => request_input('search', '')]); ?>
    </div>
<?php endif; ?>
