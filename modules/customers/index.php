<?php
require_once __DIR__ . '/../../includes/helpers/functions.php';
$pageSubtitle = 'إدارة العملاء والحدود الائتمانية والأسعار الخاصة';
$crud = new CrudService();
$isSuperAdmin = Auth::isSuperAdmin();
$accessibleBranchIds = Auth::branchIds();
$editId = (int) request_input('edit', 0);

// Enforce update permission for customer edit
if ($editId > 0 && !Auth::can('customers.update')) {
    $editId = 0;
}

$currentPage = max(1, (int) request_input('page', 1));
$perPage = (int) app_config('per_page');
$customer = $editId ? $crud->find('customers', $editId) : null;

// Apply branch filtering for non-super admins
$customersFilters = ['full_name' => request_input('search', '')];
if (!$isSuperAdmin) {
    if ($accessibleBranchIds) {
        $customersFilters['branch_id'] = ['in' => $accessibleBranchIds];
    } else {
        $customersFilters['branch_id'] = ['in' => [0]]; // No accessible branches
    }
}
$customersPage = $crud->paginate('customers', $customersFilters, $currentPage, $perPage);
$branches = branches_options();
$marketers = marketers_options();
$branchMap = [];
foreach ($branches as $branchItem) {
    $branchMap[(int) $branchItem['id']] = $branchItem;
}
$marketerMap = [];
foreach ($marketers as $marketerItem) {
    $marketerMap[(int) $marketerItem['id']] = $marketerItem;
}
?>
<div class="grid grid-2">
    <div class="card">
        <h3><?= $customer ? 'تعديل عميل' : 'إضافة عميل'; ?></h3>
        <form action="ajax/customers/save.php" method="post" data-ajax-form>
            <?= csrf_field(); ?>
            <input type="hidden" name="id" value="<?= e((string) ($customer['id'] ?? '')); ?>">
            <div class="form-grid">
<div>
    <label>الكود</label>
    <input 
        name="code" 
        required 
        readonly
        value="<?= e($customer['code'] ?? next_customer_code()); ?>">
    <small class="field-help">يتم توليد الكود تلقائيًا</small>
</div>
                <div><label>اسم العميل</label><input name="full_name" required value="<?= e($customer['full_name'] ?? ''); ?>"></div>
                <div><label>التصنيف</label><input name="category" value="<?= e($customer['category'] ?? ''); ?>"></div>
                <div><label>الهاتف</label><input name="phone" value="<?= e($customer['phone'] ?? ''); ?>"></div>
                <div><label>هاتف بديل</label><input name="alt_phone" value="<?= e($customer['alt_phone'] ?? ''); ?>"></div>
                <div><label>المدينة</label><input name="city" value="<?= e($customer['city'] ?? ''); ?>"></div>
                <div>
                    <label>الفرع</label>
                    <select name="branch_id">
                        <option value="">بدون</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= e((string) $branch['id']); ?>" <?= (string) ($customer['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : ''; ?>><?= e($branch['name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>المسوق المسؤول</label>
                    <select name="marketer_id">
                        <option value="">بدون</option>
                        <?php foreach ($marketers as $marketer): ?>
                            <option value="<?= e((string) $marketer['id']); ?>" <?= (string) ($customer['marketer_id'] ?? '') === (string) $marketer['id'] ? 'selected' : ''; ?>><?= e($marketer['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>السقف الائتماني</label><input type="number" step="0.01" name="credit_limit" value="<?= e($customer['credit_limit'] ?? '0'); ?>"></div>
                <div><label>الرصيد الافتتاحي</label><input type="number" step="0.01" name="opening_balance" value="<?= e($customer['opening_balance'] ?? '0'); ?>"></div>
                <div><label>الرقم الضريبي</label><input name="tax_number" value="<?= e($customer['tax_number'] ?? ''); ?>"></div>
                <div>
                    <label>الحالة</label>
                    <select name="status">
                        <option value="active" <?= ($customer['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>نشط</option>
                        <option value="inactive" <?= ($customer['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>موقوف</option>
                    </select>
                </div>
                <div class="grid content-end">
                    <label><input type="checkbox" name="special_pricing_enabled" value="1" <?= !empty($customer['special_pricing_enabled']) ? 'checked' : ''; ?>> تمكين الأسعار الخاصة</label>
                </div>
            </div>
            <div class="mt-2">
                <label>العنوان</label>
                <input name="address" value="<?= e($customer['address'] ?? ''); ?>">
            </div>
            <div class="mt-2">
                <label>ملاحظات</label>
                <textarea name="notes"><?= e($customer['notes'] ?? ''); ?></textarea>
            </div>
            <div class="mt-2">
                <?php if (Auth::can('customers.create') || Auth::can('customers.update')): ?>
                    <button class="btn btn-primary" type="submit">حفظ العميل</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="card">
        <div class="toolbar">
            <h3>قائمة العملاء</h3>
            <form method="get" class="toolbar-search">
                <input type="hidden" name="module" value="customers">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="بحث" value="<?= e(request_input('search', '')); ?>">
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الكود</th><th>الاسم</th><th>الفرع</th><th>المسوق</th><th>الهاتف</th><th>الحد الائتماني</th><th>إجراءات</th></tr></thead>
                <tbody>
                <?php foreach ($customersPage['data'] as $row): ?>
                    <tr>
                        <td><?= e($row['code']); ?></td>
                        <td><?= e($row['full_name']); ?></td>
                        <td><?= e($branchMap[(int) ($row['branch_id'] ?? 0)]['name_ar'] ?? '-'); ?></td>
                        <td><?= e($marketerMap[(int) ($row['marketer_id'] ?? 0)]['full_name'] ?? '-'); ?></td>
                        <td><?= e($row['phone']); ?></td>
                        <td><?= e(format_currency($row['credit_limit'])); ?></td>
                        <td>
                            <?php if (Auth::can('customers.update')): ?>
                                <a class="btn btn-light" href="index.php?module=customers&edit=<?= e((string) $row['id']); ?>">تعديل</a>
                            <?php endif; ?>
                            <?php if (Auth::can('customers.delete')): ?>
                                <button type="button" class="btn btn-danger" data-id="<?= e((string) $row['id']); ?>" data-delete-url="ajax/customers/delete.php" data-confirm="هل تريد حذف العميل؟">حذف</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($customersPage, $currentPage, $perPage, ['module' => 'customers', 'search' => request_input('search', '')]); ?>
    </div>
</div>
