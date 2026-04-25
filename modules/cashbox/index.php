<?php
$pageSubtitle = 'سندات القبض والصرف والحركة اليومية وإقفال الخزينة';
$cashboxService = new CashboxService();
$daily = $cashboxService->dailySummary(date('Y-m-d'));
$entries = $cashboxService->recentEntries();
?>
<div class="grid grid-3">
    <div class="card stat-card">
        <div class="stat-label">مقبوضات اليوم</div>
        <div class="stat-value"><?= e(format_currency($daily['receipts'])); ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">مدفوعات اليوم</div>
        <div class="stat-value"><?= e(format_currency($daily['payments'])); ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">الرصيد اليومي</div>
        <div class="stat-value"><?= e(format_currency($daily['balance'])); ?></div>
    </div>
</div>

<div class="grid grid-2 mt-2">
    <div class="card">
        <h3>سند قبض / صرف</h3>
        <form action="ajax/cashbox/entry.php" method="post" data-ajax-form>
            <?= csrf_field(); ?>
            <div class="form-grid">
                <div>
                    <label>نوع الحركة</label>
                    <select name="entry_type">
                        <option value="receipt">قبض</option>
                        <option value="payment">صرف</option>
                    </select>
                </div>
                <div><label>التاريخ</label><input type="datetime-local" name="entry_date" value="<?= e(date('Y-m-d\TH:i')); ?>"></div>
                <div><label>القيمة</label><input type="number" step="0.01" name="amount" value="0"></div>
            </div>
            <div class="mt-2"><label>الوصف</label><input name="description" required></div>
            <div class="mt-2">
                <?php if (Auth::can('cashbox.entry')): ?>
                    <button class="btn btn-primary" type="submit">تسجيل الحركة</button>
                <?php else: ?>
                    <small class="muted">ليس لديك صلاحية تسجيل حركات الخزينة</small>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="card">
        <h3>إقفال يومي</h3>
        <form action="ajax/cashbox/close_day.php" method="post" data-ajax-form>
            <?= csrf_field(); ?>
            <div class="form-grid">
                <div><label>تاريخ الإقفال</label><input type="date" name="closing_date" value="<?= e(date('Y-m-d')); ?>"></div>
                <div><label>الرصيد الافتتاحي</label><input type="number" step="0.01" name="opening_balance" value="0"></div>
                <div><label>الرصيد الفعلي</label><input type="number" step="0.01" name="actual_balance" value="<?= e((string) $daily['balance']); ?>"></div>
            </div>
            <div class="mt-2"><label>ملاحظات</label><textarea name="notes"></textarea></div>
            <div class="mt-2">
                <?php if (Auth::can('cashbox.close_day')): ?>
                    <button class="btn btn-light" type="submit">إقفال اليوم</button>
                <?php else: ?>
                    <small class="muted">ليس لديك صلاحية إقفال اليومية</small>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card mt-2">
    <h3>آخر حركات الخزينة</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>النوع</th><th>التاريخ</th><th>القيمة</th><th>الوصف</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><span class="badge <?= $entry['entry_type'] === 'receipt' ? 'success' : 'warning'; ?>"><?= $entry['entry_type'] === 'receipt' ? 'قبض' : 'صرف'; ?></span></td>
                    <td><?= e($entry['entry_date']); ?></td>
                    <td><?= e(format_currency($entry['amount'])); ?></td>
                    <td><?= e($entry['description']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
