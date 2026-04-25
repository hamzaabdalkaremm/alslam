<?php
$pageSubtitle = 'تسجيل المصروفات ومتابعة التصنيفات والتكلفة اليومية';
$crud = new CrudService();
$categories = Database::connection()->query("SELECT id, name FROM expense_categories WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
$branches = branches_options();
$accounts = accounts_options();
$expenses = Database::connection()->query(
    "SELECT e.*, ec.name AS category_name, b.name_ar AS branch_name, a.name AS account_name
     FROM expenses e
     LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
     LEFT JOIN branches b ON b.id = e.branch_id
     LEFT JOIN accounts a ON a.id = e.account_id
     WHERE e.deleted_at IS NULL
     ORDER BY e.expense_date DESC, e.id DESC
     LIMIT 30"
)->fetchAll();
?>
<div class="grid grid-2">
    <div class="card">
        <h3>إضافة مصروف</h3>
        <form action="ajax/expenses/save.php" method="post" data-ajax-form>
            <?= csrf_field(); ?>
            <div class="form-grid">
                <div>
                    <label>التصنيف</label>
                    <select name="expense_category_id">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']); ?>"><?= e($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>التاريخ</label><input type="date" name="expense_date" value="<?= e(date('Y-m-d')); ?>"></div>
                <div><label>العنوان</label><input name="title" required></div>
                <div><label>القيمة</label><input type="number" step="0.01" name="amount" required value="0"></div>
                <div>
                    <label>الفرع</label>
                    <select name="branch_id">
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= e((string) $branch['id']); ?>" <?= (string) $branch['id'] === (string) Auth::defaultBranchId() ? 'selected' : ''; ?>><?= e($branch['name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>الحساب المالي</label>
                    <select name="account_id">
                        <option value="">افتراضي</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= e((string) $account['id']); ?>"><?= e($account['code'] . ' - ' . $account['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>طريقة الدفع</label>
                    <select name="payment_method">
                        <option value="cash">نقداً</option>
                        <option value="bank">تحويل</option>
                        <option value="cheque">صك</option>
                    </select>
                </div>
            </div>
            <div class="mt-2">
                <label>ملاحظات</label>
                <textarea name="notes"></textarea>
            </div>
            <div class="mt-2">
                <?php if (Auth::can('expenses.create')): ?>
                    <button class="btn btn-primary" type="submit">حفظ المصروف</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="card">
        <h3>إضافة تصنيف مصروف</h3>
        <form action="ajax/expenses/category-save.php" method="post" data-ajax-form>
            <?= csrf_field(); ?>
            <div class="form-grid">
                <div><label>اسم التصنيف</label><input name="name" required></div>
                <div class="col-span-2"><label>الوصف</label><input name="description"></div>
            </div>
            <div class="mt-2">
                <?php if (Auth::can('expenses.create')): ?>
                    <button class="btn btn-light" type="submit">إضافة التصنيف</button>
                <?php endif; ?>
            </div>
        </form>
        <div class="table-wrap mt-2">
            <table>
                <thead><tr><th>التصنيف</th><th>الوصف</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr><td><?= e($category['name']); ?></td><td class="muted">-</td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-2">
    <h3>آخر المصروفات</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>التاريخ</th><th>الفرع</th><th>العنوان</th><th>التصنيف</th><th>الحساب</th><th>القيمة</th><th>طريقة الدفع</th></tr></thead>
            <tbody>
            <?php foreach ($expenses as $expense): ?>
                <tr>
                    <td><?= e($expense['expense_date']); ?></td>
                    <td><?= e($expense['branch_name'] ?? '-'); ?></td>
                    <td><?= e($expense['title']); ?></td>
                    <td><?= e($expense['category_name'] ?? '-'); ?></td>
                    <td><?= e($expense['account_name'] ?? '-'); ?></td>
                    <td><?= e(format_currency($expense['amount'])); ?></td>
                    <td><?= e($expense['payment_method']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
