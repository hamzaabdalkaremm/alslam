<?php
$pageSubtitle = 'الهوية الرسمية للشركة وإعدادات الطباعة والترقيم والربط المحاسبي';
$settings = settings_map();
$company = company_profile();
?>
<div class="grid grid-2">
    <div class="card">
        <h3>البيانات الرسمية للشركة</h3>
        <form action="ajax/settings/save.php" method="post" enctype="multipart/form-data" data-ajax-form data-reset="false">
            <?= csrf_field(); ?>
            <div class="form-grid">
                <div><label>اسم الشركة</label><input name="settings[company_name]" value="<?= e($company['name']); ?>"></div>
                <div><label>الاسم الإنجليزي</label><input name="settings[company_name_en]" value="<?= e($company['name_en']); ?>"></div>
                <div><label>الهاتف</label><input name="settings[company_phone]" value="<?= e($company['phone']); ?>"></div>
                <div><label>البريد</label><input name="settings[company_email]" value="<?= e($company['email']); ?>"></div>
                <div><label>السجل التجاري</label><input name="settings[company_register]" value="<?= e($company['commercial_register']); ?>"></div>
                <div><label>الرقم الضريبي</label><input name="settings[company_tax_number]" value="<?= e($company['tax_number']); ?>"></div>
                <div class="col-span-3"><label>العنوان</label><input name="settings[company_address]" value="<?= e($company['address']); ?>"></div>
                <div><label>الشعار</label><input type="file" name="company_logo" accept=".png,.jpg,.jpeg,.webp,.svg"></div>
                <div><label>الختم أو التوقيع</label><input type="file" name="company_stamp" accept=".png,.jpg,.jpeg,.webp,.svg"></div>
                <div><label>العملة</label><input name="settings[currency]" value="<?= e($settings['currency'] ?? 'د.ل'); ?>"></div>
            </div>
            <div class="mt-2">
                <label>رسالة تذييل الفاتورة</label>
                <textarea name="settings[invoice_footer]"><?= e($company['invoice_footer']); ?></textarea>
            </div>
            <div class="mt-2">
                <?php if (Auth::can('settings.update')): ?>
                    <button class="btn btn-primary" type="submit">حفظ البيانات الرسمية</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>إعدادات تشغيلية ومالية</h3>
        <form action="ajax/settings/save.php" method="post" data-ajax-form data-reset="false">
            <?= csrf_field(); ?>
            <div class="form-grid">
                <div><label>نسبة الضريبة</label><input name="settings[tax_rate]" value="<?= e($settings['tax_rate'] ?? '0'); ?>"></div>
                <div><label>الفرع الافتراضي</label><input name="settings[default_branch_id]" value="<?= e($settings['default_branch_id'] ?? '1'); ?>"></div>
                <div><label>تنبيه الحد الأدنى</label><input name="settings[low_stock_threshold]" value="<?= e($settings['low_stock_threshold'] ?? '10'); ?>"></div>
                <div><label>بادئة فواتير البيع</label><input name="settings[invoice_prefix_sales]" value="<?= e($settings['invoice_prefix_sales'] ?? 'SAL'); ?>"></div>
                <div><label>بادئة فواتير الشراء</label><input name="settings[invoice_prefix_purchase]" value="<?= e($settings['invoice_prefix_purchase'] ?? 'PUR'); ?>"></div>
                <div><label>بادئة القيود</label><input name="settings[journal_prefix]" value="<?= e($settings['journal_prefix'] ?? 'JRN'); ?>"></div>
                <div><label>حساب الصندوق الافتراضي</label><input name="settings[default_cash_account_id]" value="<?= e($settings['default_cash_account_id'] ?? ''); ?>"></div>
                <div><label>حساب البنك الافتراضي</label><input name="settings[default_bank_account_id]" value="<?= e($settings['default_bank_account_id'] ?? ''); ?>"></div>
                <div><label>حساب المخزون الافتراضي</label><input name="settings[default_inventory_account_id]" value="<?= e($settings['default_inventory_account_id'] ?? ''); ?>"></div>
                <div><label>حساب المبيعات الافتراضي</label><input name="settings[default_sales_account_id]" value="<?= e($settings['default_sales_account_id'] ?? ''); ?>"></div>
                <div><label>حساب العملاء الافتراضي</label><input name="settings[default_customer_account_id]" value="<?= e($settings['default_customer_account_id'] ?? ''); ?>"></div>
                <div><label>حساب الموردين الافتراضي</label><input name="settings[default_supplier_account_id]" value="<?= e($settings['default_supplier_account_id'] ?? ''); ?>"></div>
            </div>
            <div class="checkbox-grid mt-2">
                <label><input type="checkbox" name="settings[enable_auto_journal]" value="1" <?= ($settings['enable_auto_journal'] ?? '1') === '1' ? 'checked' : ''; ?>> تفعيل الربط المحاسبي التلقائي</label>
            </div>
            <div class="mt-2">
                <?php if (Auth::can('settings.update')): ?>
                    <button class="btn btn-light" type="submit">حفظ الإعدادات التشغيلية</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card mt-2">
        <div class="toolbar">
            <div>
                <h3>تصدير بيانات المنصة</h3>
                <p class="card-intro">تحميل جميع حركات المخزون والمنصة كاملة كملف إكسل</p>
            </div>
        </div>
        <div class="form-grid" style="max-width: 600px;">
            <?php if (Auth::can('reports.view')): ?>
            <div class="flex gap-2 items-end">
                <a class="btn btn-primary" href="<?= e('api/all-movements-export.php'); ?>" target="_blank">
                    <i class="fa-solid fa-download"></i> تحميل كل الحركات Excel (كامل المنصة)
                </a>
                <small class="muted">يتطلب صلاحية تقارير. الملف يحتوي على جميع حركات الشراء، البيع، التسويات، النقل، التالف، والمرتجع.</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
