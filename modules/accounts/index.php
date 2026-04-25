<?php
$crud = new CrudService();
$editId = (int) request_input('edit', 0);
$showForm = $editId > 0 || request_input('show_form', '') === '1';

// Enforce create/update permissions for accounts
if ($editId > 0 && !Auth::can('accounts.update')) {
    $editId = 0;
    $showForm = false;
} elseif ($showForm && $editId == 0 && !Auth::can('accounts.create')) {
    $showForm = false;
}

$account = $editId ? $crud->find('accounts', $editId) : null;
$branches = branches_options();
$accounts = Database::connection()->query(
    "SELECT a.*, parent.name AS parent_name
     FROM accounts a
     LEFT JOIN accounts parent ON parent.id = a.parent_id
     WHERE a.deleted_at IS NULL
     ORDER BY a.code ASC"
)->fetchAll();
$journalEntries = Database::connection()->query(
    "SELECT je.*, b.name_ar AS branch_name
     FROM journal_entries je
     LEFT JOIN branches b ON b.id = je.branch_id
     ORDER BY je.entry_date DESC, je.id DESC
     LIMIT 15"
)->fetchAll();
?>

<div class="card">
    <div class="toolbar">
        <div>
            <h3>شجرة الحسابات</h3>
            <p class="card-intro">إدارة الحسابات المحاسبية وربطها بالشجرة المالية الخاصة بالشركة والفروع.</p>
        </div>
        <div>
            <?php if ($showForm): ?>
                <a class="btn btn-light" href="index.php?module=accounts">إغلاق النموذج</a>
            <?php else: ?>
                <?php if (Auth::can('accounts.create')): ?>
                    <a class="btn btn-primary" href="index.php?module=accounts&show_form=1">إضافة حساب</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>الكود</th><th>الحساب</th><th>الأب</th><th>النوع</th><th>الحركة</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php foreach ($accounts as $row): ?>
                 <tr>
                     <td><?= e($row['code']); ?></td>
                     <td><?= e($row['name']); ?></td>
                     <td><?= e($row['parent_name'] ?: '-'); ?></td>
                     <td><?= e($row['account_type']); ?></td>
                     <td><?= e((int) $row['accepts_entries'] === 1 ? 'يقبل الحركة' : 'تجميعي'); ?></td>
                    <td>
                          <?php if (Auth::can('accounts.update')): ?>
                              <a class="btn btn-light" href="index.php?module=accounts&edit=<?= e((string) $row['id']); ?>">تعديل</a>
                          <?php endif; ?>
                          <?php if ((int) $row['accepts_entries'] === 1): ?>
                           <?php if (Auth::can('accounts.entry')): ?>
                               <button class="btn btn-success btn-sm" 
                                       data-account-id="<?= e($row['id']); ?>" 
                                       data-account-name="<?= e($row['name']); ?>"
                                       data-entry-type="receipt"
                                       data-quick-entry>
                                   استلام
                               </button>
                               <button class="btn btn-danger btn-sm" 
                                       data-account-id="<?= e($row['id']); ?>" 
                                       data-account-name="<?= e($row['name']); ?>"
                                       data-entry-type="payment"
                                       data-quick-entry>
                                   دفع
                               </button>
                           <?php endif; ?>
                          <?php endif; ?>
                      </td>
                 </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($showForm): ?>
    <div class="card mt-2">
        <div class="toolbar">
            <div>
                <h3><?= $account ? 'تعديل حساب' : 'إضافة حساب في الشجرة'; ?></h3>
                <p class="card-intro">أدخل بيانات الحساب وحدد نوعه وموقعه داخل الشجرة قبل الحفظ.</p>
            </div>
        </div>
        <form action="ajax/accounts/save.php" method="post" data-ajax-form data-reset="false">
            <?= csrf_field(); ?>
            <input type="hidden" name="id" value="<?= e((string) ($account['id'] ?? '')); ?>">
            <div class="form-grid">
                <div><label>كود الحساب</label><input name="code" required value="<?= e($account['code'] ?? ''); ?>"></div>
                <div><label>اسم الحساب</label><input name="name" required value="<?= e($account['name'] ?? ''); ?>"></div>
                <div><label>الاسم الإنجليزي</label><input name="name_en" value="<?= e($account['name_en'] ?? ''); ?>"></div>
                <div>
                    <label>نوع الحساب</label>
                    <select name="account_type">
                        <?php foreach (['asset' => 'أصول', 'liability' => 'التزامات', 'equity' => 'حقوق الملكية', 'revenue' => 'إيرادات', 'expense' => 'مصروفات'] as $value => $label): ?>
                            <option value="<?= e($value); ?>" <?= ($account['account_type'] ?? 'asset') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>الحساب الأب</label>
                    <select name="parent_id">
                        <option value="">بدون</option>
                        <?php foreach ($accounts as $row): ?>
                            <option value="<?= e((string) $row['id']); ?>" <?= (string) ($account['parent_id'] ?? '') === (string) $row['id'] ? 'selected' : ''; ?>><?= e($row['code'] . ' - ' . $row['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>الفرع</label>
                    <select name="branch_id">
                        <option value="">عام على مستوى الشركة</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= e((string) $branch['id']); ?>" <?= (string) ($account['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : ''; ?>><?= e($branch['name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="checkbox-grid mt-2">
                <label><input type="checkbox" name="is_group" value="1" <?= !empty($account['is_group']) ? 'checked' : ''; ?>> حساب تجميعي</label>
                <label><input type="checkbox" name="accepts_entries" value="1" <?= !isset($account['accepts_entries']) || (int) $account['accepts_entries'] === 1 ? 'checked' : ''; ?>> يقبل الحركة</label>
            </div>
            <div class="mt-2"><label>ملاحظات</label><textarea name="notes"><?= e($account['notes'] ?? ''); ?></textarea></div>
            <div class="mt-2">
                <button class="btn btn-primary" type="submit">حفظ الحساب</button>
                <a class="btn btn-light" href="index.php?module=accounts">إلغاء</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="card mt-2">
    <div class="toolbar">
        <div>
            <h3>إضافة قيد يومي</h3>
            <p class="card-intro">يدعم النظام القيود اليدوية إلى جانب القيود التلقائية الناتجة من العمليات.</p>
        </div>
    </div>
    <form action="ajax/accounts/journal-save.php" method="post" data-ajax-form data-reset="false">
        <?= csrf_field(); ?>
        <div class="form-grid">
            <div><label>رقم القيد</label><input name="entry_no" value="<?= e(next_reference('journal_prefix', 'JRN')); ?>" required></div>
            <div><label>التاريخ</label><input type="datetime-local" name="entry_date" value="<?= e(date('Y-m-d\TH:i')); ?>" required></div>
            <div>
                <label>الفرع</label>
                <select name="branch_id">
                    <option value="">عام</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= e((string) $branch['id']); ?>"><?= e($branch['name_ar']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mt-2"><label>الوصف</label><input name="description" required></div>
        <div class="invoice-items mt-2" id="journalLines">
            <div class="row">
                <div>
                    <label>الحساب</label>
                    <select name="lines[0][account_id]" required>
                        <option value="">اختيار</option>
                        <?php foreach (accounts_options() as $accountOption): ?>
                            <option value="<?= e((string) $accountOption['id']); ?>"><?= e($accountOption['code'] . ' - ' . $accountOption['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>مدين</label><input type="number" step="0.01" name="lines[0][debit]" value="0"></div>
                <div><label>دائن</label><input type="number" step="0.01" name="lines[0][credit]" value="0"></div>
                <div><label>بيان</label><input name="lines[0][description]"></div>
                <div class="align-self-end"><button class="btn btn-danger" type="button" data-remove-row>حذف</button></div>
            </div>
        </div>
        <template id="journalRowTemplate">
            <div class="row">
                <div>
                    <label>الحساب</label>
                    <select name="lines[][account_id]" required>
                        <option value="">اختيار</option>
                        <?php foreach (accounts_options() as $accountOption): ?>
                            <option value="<?= e((string) $accountOption['id']); ?>"><?= e($accountOption['code'] . ' - ' . $accountOption['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>مدين</label><input type="number" step="0.01" name="lines[][debit]" value="0"></div>
                <div><label>دائن</label><input type="number" step="0.01" name="lines[][credit]" value="0"></div>
                <div><label>بيان</label><input name="lines[][description]"></div>
                <div class="align-self-end"><button class="btn btn-danger" type="button" data-remove-row>حذف</button></div>
            </div>
        </template>
        <div class="toolbar mt-2">
            <button class="btn btn-light" type="button" data-add-row data-target="#journalLines" data-template="#journalRowTemplate">إضافة سطر</button>
            <?php if (Auth::can('accounts.journal')): ?>
                <button class="btn btn-primary" type="submit">ترحيل القيد</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card mt-2">
    <h3>آخر القيود اليومية</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>رقم القيد</th><th>التاريخ</th><th>الفرع</th><th>الوصف</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($journalEntries as $entry): ?>
                <tr>
                    <td><?= e($entry['entry_no']); ?></td>
                    <td><?= e($entry['entry_date']); ?></td>
                    <td><?= e($entry['branch_name'] ?: 'عام'); ?></td>
                    <td><?= e($entry['description']); ?></td>
                    <td><span class="badge success"><?= e($entry['status']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
