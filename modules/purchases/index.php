<?php
require_once __DIR__ . '/../../includes/helpers/functions.php';
$pageSubtitle = 'فواتير الشراء والإدخال المباشر للمخزون والدفعات';
$view = (string) request_input('view', 'list');
$branches = branches_options();
$warehouses = warehouses_options();
$isSuperAdmin = Auth::isSuperAdmin();
$accessibleBranchIds = Auth::branchIds();
$suppliers = Database::connection()->query("SELECT id, company_name FROM suppliers WHERE deleted_at IS NULL ORDER BY company_name ASC")->fetchAll();

// Enforce permission for purchase form
if ($view === 'form') {
    Auth::requirePermission('purchases.create');
}

// Apply branch filtering for non-super admins
$productsWhere = 'WHERE deleted_at IS NULL';
$productsParams = [];
if (!$isSuperAdmin) {
    if ($accessibleBranchIds) {
        $placeholders = [];
        foreach ($accessibleBranchIds as $index => $branchId) {
            $placeholder = ':branch_' . $index;
            $placeholders[] = $placeholder;
            $productsParams['branch_' . $index] = (int) $branchId;
        }
        $productsWhere .= ' AND (branch_id IS NULL OR branch_id = 0 OR branch_id IN (' . implode(', ', $placeholders) . '))';
    } else {
        $productsWhere .= ' AND 1 = 0'; // No accessible branches
    }
}

$products = Database::connection()->prepare(
    "SELECT id, name FROM products {$productsWhere} ORDER BY name ASC"
);
$products->execute($productsParams);
$products = $products->fetchAll();
$recentPurchases = Database::connection()->query(
    "SELECT p.*, s.company_name AS supplier_name, b.name_ar AS branch_name
     FROM purchases p
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     LEFT JOIN branches b ON b.id = p.branch_id
     WHERE p.deleted_at IS NULL
     ORDER BY p.purchase_date DESC, p.id DESC
     LIMIT 20"
)->fetchAll();
?>

<?php if ($view === 'form'): ?>
    <div class="card">
        <div class="toolbar">
            <div>
                <h3>إنشاء فاتورة شراء</h3>
                <p class="card-intro">أدخل بيانات فاتورة الشراء هنا، وسيتم إدخال الكميات إلى المخزون مباشرة عند الحفظ. كل سطر يمثل صنفاً وارداً من المورد مع تكلفته وبيانات دفعته.</p>
            </div>
            <a class="btn btn-light" href="index.php?module=purchases">العودة إلى آخر الفواتير</a>
        </div>

        <form action="ajax/purchases/create.php" method="post" data-ajax-form data-auto-calc="purchase">
            <?= csrf_field(); ?>

            <div class="product-form-layout">
                <section class="product-panel">
                    <h4>بيانات الفاتورة</h4>
                    <p>البيانات الرئيسية التي تعرف عملية الشراء داخل النظام.</p>
                    <div class="grid">
                        <div>
                            <label>رقم الفاتورة</label>
                            <input name="invoice_no" value="<?= e(next_reference('invoice_prefix_purchase', 'PUR')); ?>" required>
                            <small class="field-help">رقم مرجعي لفاتورة الشراء للبحث والمراجعة لاحقاً.</small>
                        </div>
                        <div>
                            <label>المورد</label>
                            <select name="supplier_id">
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= e((string) $supplier['id']); ?>"><?= e($supplier['company_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-help">اختر المورد الذي صدرت منه فاتورة الشراء.</small>
                        </div>
                        <div>
                            <label>الفرع</label>
                            <select name="branch_id">
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= e((string) $branch['id']); ?>" <?= (string) $branch['id'] === (string) Auth::defaultBranchId() ? 'selected' : ''; ?>><?= e($branch['name_ar']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-help">الفاتورة ستسجل على هذا الفرع.</small>
                        </div>
                        <div>
                            <label>المخزن</label>
                            <select name="warehouse_id">
                                <option value="">بدون</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?= e((string) $warehouse['id']); ?>"><?= e($warehouse['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-help">يتم إدخال الأصناف للمخزن المحدد عند الحفظ.</small>
                        </div>
                        <div>
                            <label>تاريخ ووقت الشراء</label>
                            <input type="datetime-local" name="purchase_date" value="<?= e(date('Y-m-d\TH:i')); ?>">
                            <small class="field-help">وقت تسجيل الفاتورة أو وقت الاستلام الفعلي للبضاعة.</small>
                        </div>
                    </div>
                </section>

                <section class="product-panel">
                    <h4>الإجماليات المالية</h4>
                    <p>ملخص القيم المالية الخاصة بالفاتورة ومقدار ما تم سداده.</p>
                    <div class="grid">
                        <div>
                            <label>الإجمالي الفرعي</label>
                            <input type="number" step="0.01" name="subtotal" value="0" readonly>
                            <small class="field-help">مجموع تكلفة الأصناف قبل الخصم العام على الفاتورة.</small>
                        </div>
                        <div>
                            <label>الخصم</label>
                            <input type="number" step="0.01" name="discount_value" value="0">
                            <small class="field-help">أي خصم عام من المورد على كامل فاتورة الشراء.</small>
                        </div>
                        <div>
                            <label>الإجمالي النهائي</label>
                            <input type="number" step="0.01" name="total_amount" value="0" readonly>
                            <small class="field-help">القيمة النهائية المستحقة بعد الخصم.</small>
                        </div>
                        <div>
                            <label>مصاريف استيراد</label>
                            <input type="number" step="0.01" name="import_costs" value="0">
                            <small class="field-help">يمكن استخدامها لاحقاً لتوزيع تكلفة الشحن والجمارك على الأصناف.</small>
                        </div>
                        <div>
                            <label>المدفوع</label>
                            <input type="number" step="0.01" name="paid_amount" value="0">
                            <small class="field-help">المبلغ الذي تم دفعه الآن للمورد.</small>
                        </div>
                        <div>
                            <label>المتبقي</label>
                            <input type="number" step="0.01" name="due_amount" value="0" readonly>
                            <small class="field-help">المبلغ المتبقي كدين للمورد بعد تسجيل الفاتورة.</small>
                        </div>
                    </div>
                </section>

                <section class="product-panel">
                    <h4>ملاحظات التشغيل</h4>
                    <p>بيانات وصفية إضافية تساعد في التتبع والمراجعة لاحقاً.</p>
                    <div class="grid">
                        <div>
                            <label>ملاحظات</label>
                            <textarea name="notes"></textarea>
                            <small class="field-help">اكتب ملاحظات مثل حالة الاستلام أو تفاصيل الاتفاق أو أي تنبيه خاص بهذه الفاتورة.</small>
                        </div>
                    </div>
                </section>
            </div>

            <div class="form-section">
                <h4>أصناف فاتورة الشراء</h4>
                <p>أدخل كل صنف تم شراؤه مع كميته وتكلفته ورقم الدفعة وتاريخ الصلاحية. هذه البيانات هي التي ترفع المخزون فعلياً داخل النظام.</p>

                <div class="invoice-items purchase-items mt-2" id="purchaseItems">
                    <div class="row">
                        <div>
                            <label>الصنف</label>
                            <select name="items[0][product_id]" required>
                                <option value="">اختيار الصنف</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= e((string) $product['id']); ?>"><?= e($product['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-help">المنتج الذي تم استلامه من المورد وسيضاف إلى المخزون.</small>
                        </div>
                        <div>
                            <label>الكمية</label>
                            <input type="number" step="0.001" name="items[0][quantity]" placeholder="الكمية" value="1">
                            <small class="field-help">عدد الوحدات أو الوزن أو الكمية الفعلية المستلمة.</small>
                        </div>
                        <div>
                            <label>التكلفة</label>
                            <input type="number" step="0.01" name="items[0][unit_cost]" placeholder="التكلفة" value="0">
                            <small class="field-help">سعر شراء الوحدة الواحدة من هذا الصنف.</small>
                        </div>
                        <div>
                            <label>رقم الدفعة</label>
                            <input type="text" name="items[0][batch_number]" placeholder="رقم الدفعة">
                            <small class="field-help">رقم تتبع الدفعة الواردة من المورد أو المصنع.</small>
                        </div>
                        <div>
                            <label>تاريخ الإنتاج</label>
                            <input type="date" name="items[0][production_date]">
                            <small class="field-help">يسجل تاريخ إنتاج الدفعة إذا كان متوفراً على العبوة.</small>
                        </div>
                        <div>
                            <label>تاريخ الصلاحية</label>
                            <input type="date" name="items[0][expiry_date]">
                            <small class="field-help">يستخدم لتتبع الأصناف القريبة من الانتهاء والتنبيهات.</small>
                        </div>
                        <div class="align-self-end">
                            <button class="btn btn-danger" type="button" data-remove-row>حذف</button>
                        </div>
                    </div>
                </div>

                <template id="purchaseRowTemplate">
                    <div class="row">
                        <div>
                            <label>الصنف</label>
                            <select name="items[][product_id]" required>
                                <option value="">اختيار الصنف</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= e((string) $product['id']); ?>"><?= e($product['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-help">المنتج الذي تم استلامه من المورد وسيضاف إلى المخزون.</small>
                        </div>
                        <div>
                            <label>الكمية</label>
                            <input type="number" step="0.001" name="items[][quantity]" placeholder="الكمية" value="1">
                            <small class="field-help">عدد الوحدات أو الوزن أو الكمية الفعلية المستلمة.</small>
                        </div>
                        <div>
                            <label>التكلفة</label>
                            <input type="number" step="0.01" name="items[][unit_cost]" placeholder="التكلفة" value="0">
                            <small class="field-help">سعر شراء الوحدة الواحدة من هذا الصنف.</small>
                        </div>
                        <div>
                            <label>رقم الدفعة</label>
                            <input type="text" name="items[][batch_number]" placeholder="رقم الدفعة">
                            <small class="field-help">رقم تتبع الدفعة الواردة من المورد أو المصنع.</small>
                        </div>
                        <div>
                            <label>تاريخ الإنتاج</label>
                            <input type="date" name="items[][production_date]">
                            <small class="field-help">يسجل تاريخ إنتاج الدفعة إذا كان متوفراً على العبوة.</small>
                        </div>
                        <div>
                            <label>تاريخ الصلاحية</label>
                            <input type="date" name="items[][expiry_date]">
                            <small class="field-help">يستخدم لتتبع الأصناف القريبة من الانتهاء والتنبيهات.</small>
                        </div>
                        <div class="align-self-end">
                            <button class="btn btn-danger" type="button" data-remove-row>حذف</button>
                        </div>
                    </div>
                </template>

                <div class="toolbar mt-2">
                    <button class="btn btn-light" type="button" data-add-row data-target="#purchaseItems" data-template="#purchaseRowTemplate">إضافة سطر صنف</button>
                    <?php if (Auth::can('purchases.create') || Auth::can('purchases.update')): ?>
                        <button class="btn btn-primary" type="submit">حفظ فاتورة الشراء</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="card">
        <div class="toolbar">
            <div>
                <h3>آخر فواتير الشراء</h3>
                <p class="card-intro">هذه الصفحة مخصصة لمراجعة آخر فواتير الشراء فقط. إنشاء فاتورة شراء جديدة يتم من صفحة مستقلة.</p>
            </div>
            <?php if (Auth::can('purchases.create')): ?>
                <a class="btn btn-primary" href="index.php?module=purchases&view=form">إنشاء فاتورة شراء</a>
            <?php endif; ?>
        </div>

        <div class="table-wrap">
            <table>
                <thead><tr><th>الفاتورة</th><th>التاريخ</th><th>الفرع</th><th>المورد</th><th>الإجمالي</th><th>المتبقي</th><th>الحالة</th></tr></thead>
                <tbody>
                <?php foreach ($recentPurchases as $purchase): ?>
                    <tr>
                        <td><?= e($purchase['invoice_no']); ?></td>
                        <td><?= e($purchase['purchase_date']); ?></td>
                        <td><?= e($purchase['branch_name'] ?? '-'); ?></td>
                        <td><?= e($purchase['supplier_name'] ?? '-'); ?></td>
                        <td><?= e(format_currency($purchase['total_amount'])); ?></td>
                        <td><?= e(format_currency($purchase['due_amount'])); ?></td>
                        <td><span class="badge <?= $purchase['status'] === 'completed' ? 'success' : 'warning'; ?>"><?= e($purchase['status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
