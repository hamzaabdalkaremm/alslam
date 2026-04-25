<?php
$pageSubtitle = 'متابعة ديون العملاء والموردين وتسجيل التحصيل والسداد بشكل واضح ومنظم';
$debtService = new DebtService();
$customerDebts = $debtService->customerDebts();
$supplierDebts = $debtService->supplierDebts();
$marketerDebts = $debtService->marketerDebts();
$showForm = request_input('show_form', '') === '1';
if ($showForm && !Auth::can('debts.collect')) {
    $showForm = false;
}
$debtView = (string) request_input('debt_view', 'customer');
if (!in_array($debtView, ['customer', 'supplier', 'marketer'], true)) {
    $debtView = 'customer';
}
$debtsPage = max(1, (int) request_input('page', request_input('debts_page', 1)));
$debtsPerPage = 10;

$presetPartyType = (string) request_input('party_type', '');
$presetPartyId = (int) request_input('party_id', 0);
$presetSourceType = (string) request_input('source_type', '');
$presetSourceId = (int) request_input('source_id', 0);
$presetAmount = (string) request_input('amount', '');

$selectedDebts = $debtView === 'supplier' ? $supplierDebts : ($debtView === 'marketer' ? $marketerDebts : $customerDebts);
$selectedTitle = $debtView === 'supplier' ? 'ديون الموردين' : ($debtView === 'marketer' ? 'ديون المسوقين' : 'ديون العملاء');
$selectedIntro = $debtView === 'supplier'
    ? 'يعرض هذا الجدول فواتير الشراء المستحقة على الموردين مع الرصيد المتبقي لكل فاتورة.'
    : ($debtView === 'marketer'
        ? 'يعرض هذا الجدول فواتير البيع المعلقة المرتبطة بالمسوقين مع الرصيد المتبقي لكل فاتورة.'
        : 'يعرض هذا الجدول فواتير البيع المستحقة على العملاء مع الرصيد المتبقي لكل فاتورة.');
$toggleBaseQuery = 'index.php?module=debts';
$selectedDebtsTotal = count($selectedDebts);
$selectedDebtsPageData = [
    'data' => array_slice($selectedDebts, ($debtsPage - 1) * $debtsPerPage, $debtsPerPage),
    'total' => $selectedDebtsTotal,
];
$debtsPaginationQuery = [
    'module' => 'debts',
    'debt_view' => $debtView,
];
if ($showForm) {
    $debtsPaginationQuery['show_form'] = '1';
}
$excelExportUrl = 'ajax/debts/export.php?' . http_build_query([
    'debt_view' => $debtView,
]);
?>

<div class="card">
    <div class="toolbar">
        <div>
            <h3>الديون والتحصيل</h3>
            <p class="card-intro">اختر نوع القائمة التي تريد متابعتها، ثم افتح نموذج التحصيل أو السداد عند الحاجة.</p>
        </div>
        <div class="flex gap-1 flex-wrap">
            <?php if (Auth::can('debts.export')): ?>
                <a class="btn btn-light" href="<?= e($excelExportUrl); ?>">تنزيل Excel بكل التفاصيل</a>
            <?php endif; ?>
            <?php if (Auth::can('debts.collect')): ?>
                <a class="btn <?= $showForm ? 'btn-light' : 'btn-primary'; ?>" href="<?= e($toggleBaseQuery . '&show_form=1&debt_view=' . $debtView); ?>">تسجيل تحصيل / سداد</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="toolbar">
        <div>
            <a class="btn <?= $debtView === 'customer' ? 'btn-primary' : 'btn-light'; ?>" href="<?= e($toggleBaseQuery . '&debt_view=customer' . ($showForm ? '&show_form=1' : '')); ?>">ديون العملاء</a>
            <a class="btn <?= $debtView === 'supplier' ? 'btn-primary' : 'btn-light'; ?>" href="<?= e($toggleBaseQuery . '&debt_view=supplier' . ($showForm ? '&show_form=1' : '')); ?>">ديون الموردين</a>
            <a class="btn <?= $debtView === 'marketer' ? 'btn-primary' : 'btn-light'; ?>" href="<?= e($toggleBaseQuery . '&debt_view=marketer' . ($showForm ? '&show_form=1' : '')); ?>">ديون المسوقين</a>
        </div>
        <?php if ($showForm): ?>
            <div>
                <a class="btn btn-light" href="<?= e($toggleBaseQuery . '&debt_view=' . $debtView); ?>">إغلاق النموذج</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-2">
    <div class="toolbar">
        <div>
            <h3><?= e($selectedTitle); ?></h3>
            <p class="card-intro"><?= e($selectedIntro); ?> يعرض الجدول 10 سجلات في كل صفحة مع أزرار التالي والسابق والتنقل بين الصفحات.</p>
        </div>
        <div class="muted">إجمالي السجلات: <?= e((string) $selectedDebtsTotal); ?></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>الفاتورة</th>
                    <th><?= $debtView === 'supplier' ? 'المورد' : ($debtView === 'marketer' ? 'المسوق' : 'العميل'); ?></th>
                    <th>التاريخ</th>
                    <th>الإجمالي</th>
                    <th>المسدّد</th>
                    <th>المتبقي</th>
                    <th>الإجراء</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($selectedDebtsPageData['data']): ?>
                <?php foreach ($selectedDebtsPageData['data'] as $debt): ?>
                    <tr>
                        <td><?= e($debt['invoice_no']); ?></td>
                        <td><?= e($debt['party_name'] ?? '-'); ?></td>
                        <td><?= e($debtView === 'supplier' ? $debt['purchase_date'] : $debt['sale_date']); ?></td>
                        <td><?= e(format_currency((float) ($debt['total_amount'] ?? 0))); ?></td>
                        <td><?= e(format_currency((float) ($debt['paid_amount'] ?? 0))); ?></td>
                        <td><?= e(format_currency((float) ($debt['due_amount'] ?? 0))); ?></td>
                        <td>
                            <?php if ($debtView === 'supplier'): ?>
                                <a href="index.php?module=debts&show_form=1&debt_view=supplier&party_type=supplier&party_id=<?= e($debt['supplier_id'] ?? ''); ?>&source_type=purchase&source_id=<?= e($debt['id']); ?>&amount=<?= e(format_input_number($debt['due_amount'], 2)); ?>" class="btn btn-light">سداد</a>
                            <?php elseif ($debtView === 'marketer'): ?>
                                <a href="index.php?module=debts&show_form=1&debt_view=marketer&party_type=marketer&party_id=<?= e($debt['marketer_id'] ?? ''); ?>&source_type=sale&source_id=<?= e($debt['id']); ?>&amount=<?= e(format_input_number($debt['due_amount'], 2)); ?>" class="btn btn-light">تحصيل</a>
                            <?php else: ?>
                                <a href="index.php?module=debts&show_form=1&debt_view=customer&party_type=customer&party_id=<?= e($debt['customer_id'] ?? ''); ?>&source_type=sale&source_id=<?= e($debt['id']); ?>&amount=<?= e(format_input_number($debt['due_amount'], 2)); ?>" class="btn btn-light">تحصيل</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="muted">لا توجد بيانات ديون حالية في هذه القائمة.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($selectedDebtsPageData, $debtsPage, $debtsPerPage, $debtsPaginationQuery); ?>
</div>

<?php if ($showForm): ?>
    <div class="card mt-2">
        <div class="toolbar">
            <div>
                <h3>تسجيل تحصيل / سداد</h3>
                <p class="card-intro">اختر نوع الطرف ثم الطرف ثم الفاتورة، وبعدها أدخل مبلغ التحصيل أو السداد مع ملخص واضح قبل الحفظ.</p>
            </div>
        </div>

        <div class="debt-flow" id="debtFlow">
            <div class="debt-flow-steps">
                <div class="debt-flow-step is-active" data-step-indicator="1">
                    <span>1</span>
                    <strong>نوع الطرف</strong>
                </div>
                <div class="debt-flow-step" data-step-indicator="2">
                    <span>2</span>
                    <strong>الطرف</strong>
                </div>
                <div class="debt-flow-step" data-step-indicator="3">
                    <span>3</span>
                    <strong>الفاتورة</strong>
                </div>
                <div class="debt-flow-step" data-step-indicator="4">
                    <span>4</span>
                    <strong>الحفظ</strong>
                </div>
            </div>

            <div class="debt-flow-summary">
                <div class="debt-summary-pill">
                    <small>نوع الحركة</small>
                    <strong id="summary_action">لم يحدد بعد</strong>
                </div>
                <div class="debt-summary-pill">
                    <small>الطرف المحدد</small>
                    <strong id="summary_party">لم يتم اختيار طرف</strong>
                </div>
                <div class="debt-summary-pill">
                    <small>الفاتورة المحددة</small>
                    <strong id="summary_invoice">لم يتم اختيار فاتورة</strong>
                </div>
                <div class="debt-summary-pill">
                    <small>المتبقي</small>
                    <strong id="summary_due">0 ل.د</strong>
                </div>
            </div>

            <div class="form-step debt-step-card" id="step-1">
                <h4>الخطوة 1: اختر نوع الطرف</h4>
                <p class="card-intro">حدد ما إذا كانت الحركة تحصيلًا من عميل أو سدادًا لمورد.</p>
                <div class="debt-type-grid">
                    <button type="button" class="debt-type-card" data-party-type-card="customer">
                        <span class="debt-type-card-icon"><i class="fa-solid fa-user"></i></span>
                        <strong>عميل</strong>
                        <small>تحصيل مبالغ من فواتير البيع</small>
                    </button>
                    <button type="button" class="debt-type-card" data-party-type-card="supplier">
                        <span class="debt-type-card-icon"><i class="fa-solid fa-truck-field"></i></span>
                        <strong>مورد</strong>
                        <small>سداد مبالغ لفواتير الشراء</small>
                    </button>
                    <button type="button" class="debt-type-card" data-party-type-card="marketer">
                        <span class="debt-type-card-icon"><i class="fa-solid fa-bullhorn"></i></span>
                        <strong>مسوق</strong>
                        <small>تحصيل من فواتير البيع المرتبطة بالمسوق</small>
                    </button>
                </div>
                <div class="form-grid mt-2">
                    <div>
                        <label>نوع الطرف</label>
                        <select name="party_type" id="party_type" required onchange="loadParties()">
                            <option value="">اختر نوع الطرف</option>
                            <option value="customer" <?= $presetPartyType === 'customer' ? 'selected' : ''; ?>>عميل</option>
                            <option value="supplier" <?= $presetPartyType === 'supplier' ? 'selected' : ''; ?>>مورد</option>
                            <option value="marketer" <?= $presetPartyType === 'marketer' ? 'selected' : ''; ?>>مسوق</option>
                        </select>
                    </div>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-primary" id="go_step_2_btn" disabled onclick="nextStep(2)">التالي</button>
                </div>
            </div>

            <div class="form-step debt-step-card hidden" id="step-2">
                <h4>الخطوة 2: اختر الطرف</h4>
                <p class="card-intro">ابحث بالاسم أو الكود، ثم اضغط على الطرف المطلوب من القائمة.</p>
                <div class="form-grid">
                    <div>
                        <label>بحث عن الطرف</label>
                        <input type="text" id="party_search" placeholder="ابحث باسم الطرف أو الكود" onkeyup="searchParties()">
                    </div>
                    <div>
                        <label>الأطراف المتاحة</label>
                        <div id="party_list" class="debt-choice-list">
                            <div class="muted">اختر نوع الطرف أولًا ليتم تحميل القائمة.</div>
                        </div>
                    </div>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-light" onclick="prevStep(1)">السابق</button>
                    <button type="button" class="btn btn-primary" id="select_party_btn" disabled onclick="nextStep(3)">التالي</button>
                </div>
            </div>

            <div class="form-step debt-step-card hidden" id="step-3">
                <h4>الخطوة 3: اختر الفاتورة</h4>
                <p class="card-intro">يعرض النظام الفواتير المستحقة للطرف المختار فقط حتى يكون الاختيار أسرع وأوضح.</p>
                <div class="form-grid">
                    <div>
                        <label>بحث عن الفاتورة</label>
                        <input type="text" id="invoice_search" placeholder="ابحث برقم الفاتورة" onkeyup="searchInvoices()">
                    </div>
                    <div>
                        <label>الفواتير المستحقة</label>
                        <div id="invoice_list" class="debt-choice-list">
                            <div class="muted">اختر الطرف أولًا ليتم تحميل الفواتير المستحقة.</div>
                        </div>
                    </div>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-light" onclick="prevStep(2)">السابق</button>
                    <button type="button" class="btn btn-primary" id="select_invoice_btn" disabled onclick="nextStep(4)">التالي</button>
                </div>
            </div>

            <div class="form-step debt-step-card hidden" id="step-4">
                <h4>الخطوة 4: راجع البيانات ثم احفظ</h4>
                <div class="debt-review-grid">
                    <div class="debt-review-panel">
                        <div class="form-grid">
                            <div>
                                <label>تاريخ الحركة</label>
                                <input type="datetime-local" name="payment_date" id="payment_date" value="<?= e(date('Y-m-d\TH:i')); ?>" required>
                            </div>
                            <div>
                                <label>المبلغ</label>
                                <input type="number" step="0.01" name="amount" id="amount" value="<?= e($presetAmount !== '' ? $presetAmount : '0'); ?>" required min="0">
                                <small class="field-help">الحد الأقصى المتاح لهذه الفاتورة: <span id="max_amount">0 ل.د</span></small>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label>ملاحظات</label>
                            <textarea name="notes" id="notes" rows="3" placeholder="أي بيان إضافي لهذه الحركة"></textarea>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-light" onclick="prevStep(3)">السابق</button>
                            <button type="button" class="btn btn-primary" id="submit_payment_btn">حفظ الحركة</button>
                        </div>
                    </div>
                    <aside class="debt-review-aside">
                        <h5>ملخص العملية</h5>
                        <div class="debt-review-item">
                            <small>نوع العملية</small>
                            <strong id="review_action">-</strong>
                        </div>
                        <div class="debt-review-item">
                            <small>الطرف</small>
                            <strong id="review_party">-</strong>
                        </div>
                        <div class="debt-review-item">
                            <small>الفاتورة</small>
                            <strong id="review_invoice">-</strong>
                        </div>
                        <div class="debt-review-item">
                            <small>المبلغ المستحق</small>
                            <strong id="review_due">0 ل.د</strong>
                        </div>
                    </aside>
                </div>
            </div>

            <form action="ajax/debts/collect.php" method="post" data-ajax-form id="actual_form">
                <?= csrf_field(); ?>
                <input type="hidden" name="party_type" id="actual_party_type">
                <input type="hidden" name="party_id" id="actual_party_id">
                <input type="hidden" name="source_type" id="actual_source_type">
                <input type="hidden" name="source_id" id="actual_source_id">
                <input type="hidden" name="payment_date">
                <input type="hidden" name="amount">
                <input type="hidden" name="notes">
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
let selectedPartyType = <?= json_encode($presetPartyType, JSON_UNESCAPED_UNICODE); ?>;
let selectedPartyId = <?= json_encode($presetPartyId > 0 ? (string) $presetPartyId : '', JSON_UNESCAPED_UNICODE); ?>;
let selectedPartyName = '';
let selectedSourceType = <?= json_encode($presetSourceType, JSON_UNESCAPED_UNICODE); ?>;
let selectedSourceId = <?= json_encode($presetSourceId > 0 ? (string) $presetSourceId : '', JSON_UNESCAPED_UNICODE); ?>;
let selectedInvoiceNo = '';
let maxAmount = 0;
let currentStep = 1;

function updateActionLabels() {
    const actionLabel = selectedPartyType === 'supplier'
        ? 'سداد لمورد'
        : (selectedPartyType === 'customer'
            ? 'تحصيل من عميل'
            : (selectedPartyType === 'marketer' ? 'تحصيل من مسوق' : 'لم يحدد بعد'));
    const summaryAction = document.getElementById('summary_action');
    const reviewAction = document.getElementById('review_action');
    const submitButton = document.getElementById('submit_payment_btn');
    const stepTwoButton = document.getElementById('go_step_2_btn');

    if (summaryAction) summaryAction.textContent = actionLabel;
    if (reviewAction) reviewAction.textContent = actionLabel;
    if (submitButton) submitButton.textContent = selectedPartyType === 'supplier' ? 'حفظ السداد' : 'حفظ التحصيل';
    if (stepTwoButton) stepTwoButton.disabled = !selectedPartyType;

    document.querySelectorAll('[data-party-type-card]').forEach((card) => {
        card.classList.toggle('is-selected', card.dataset.partyTypeCard === selectedPartyType);
    });
}

function updateSelectionSummary() {
    const partyText = selectedPartyName || 'لم يتم اختيار طرف';
    const invoiceText = selectedInvoiceNo || 'لم يتم اختيار فاتورة';
    const dueText = formatCurrency(maxAmount);

    if (document.getElementById('summary_party')) document.getElementById('summary_party').textContent = partyText;
    if (document.getElementById('summary_invoice')) document.getElementById('summary_invoice').textContent = invoiceText;
    if (document.getElementById('summary_due')) document.getElementById('summary_due').textContent = dueText;
    if (document.getElementById('review_party')) document.getElementById('review_party').textContent = selectedPartyName || '-';
    if (document.getElementById('review_invoice')) document.getElementById('review_invoice').textContent = selectedInvoiceNo || '-';
    if (document.getElementById('review_due')) document.getElementById('review_due').textContent = dueText;
}

function updateStepIndicators() {
    document.querySelectorAll('[data-step-indicator]').forEach((element) => {
        const step = Number(element.dataset.stepIndicator);
        element.classList.toggle('is-active', step === currentStep);
        element.classList.toggle('is-complete', step < currentStep);
    });
}

function nextStep(step) {
    document.querySelectorAll('.form-step').forEach((stepEl) => {
        stepEl.style.display = 'none';
    });
    document.getElementById(`step-${step}`).style.display = 'block';
    currentStep = step;
    updateStepIndicators();
}

function prevStep(step) {
    document.querySelectorAll('.form-step').forEach((stepEl) => {
        stepEl.style.display = 'none';
    });
    document.getElementById(`step-${step}`).style.display = 'block';
    currentStep = step;
    updateStepIndicators();

    if (step < 3) {
        document.getElementById('invoice_list').innerHTML = '<div class="muted">اختر الطرف أولًا ليتم تحميل الفواتير المستحقة.</div>';
        document.getElementById('select_invoice_btn').disabled = true;
        document.getElementById('max_amount').textContent = '0 ل.د';
        selectedSourceId = '';
        selectedInvoiceNo = '';
        maxAmount = 0;
    }

    if (step < 2) {
        document.getElementById('party_list').innerHTML = '<div class="muted">اختر نوع الطرف أولًا ليتم تحميل القائمة.</div>';
        document.getElementById('select_party_btn').disabled = true;
        selectedPartyId = '';
        selectedPartyName = '';
    }

    updateSelectionSummary();
}

function markSelectedChoice(listSelector, element) {
    document.querySelectorAll(`${listSelector} .debt-choice-item`).forEach((item) => {
        item.classList.remove('is-selected');
    });
    element.classList.add('is-selected');
}

function loadParties() {
    selectedPartyType = document.getElementById('party_type').value;
    const partyList = document.getElementById('party_list');
    const selectPartyBtn = document.getElementById('select_party_btn');

    updateActionLabels();

    if (!selectedPartyType) {
        partyList.innerHTML = '<div class="muted">اختر نوع الطرف أولًا ليتم تحميل القائمة.</div>';
        selectPartyBtn.disabled = true;
        return;
    }

    partyList.innerHTML = '<div class="muted">جاري تحميل الأطراف...</div>';

    fetch(`ajax/debts/get_parties.php?type=${selectedPartyType}&search=${encodeURIComponent(document.getElementById('party_search')?.value || '')}`)
        .then((response) => response.json())
        .then((data) => {
            if (data.status === 'success') {
                renderPartyList(data.data || []);
                selectPartyBtn.disabled = true;
            } else {
                partyList.innerHTML = `<div class="muted">${escapeHtml(data.message || 'تعذر تحميل الأطراف.')}</div>`;
            }
        })
        .catch(() => {
            partyList.innerHTML = '<div class="muted">حدث خطأ في الاتصال بالخادم.</div>';
        });
}

function searchParties() {
    loadParties();
}

function renderPartyList(parties) {
    const partyList = document.getElementById('party_list');

    if (!parties.length) {
        partyList.innerHTML = '<div class="muted">لا توجد نتائج مطابقة.</div>';
        return;
    }

    partyList.innerHTML = '';

    parties.forEach((party) => {
        const partyElement = document.createElement('button');
        partyElement.type = 'button';
        partyElement.className = 'debt-choice-item';
        partyElement.innerHTML = `
            <strong>${escapeHtml(party.name)}</strong>
            <small>الكود: ${escapeHtml(party.code || '-')}</small>
        `;
        partyElement.addEventListener('click', () => selectParty(party, partyElement, false));
        partyList.appendChild(partyElement);

        if (String(party.id) === String(selectedPartyId)) {
            selectParty(party, partyElement, true);
        }
    });
}

function selectParty(party, element, preserveInvoice) {
    selectedPartyId = String(party.id);
    selectedPartyName = party.name;
    selectedSourceType = selectedPartyType === 'supplier' ? 'purchase' : 'sale';

    if (!preserveInvoice) {
        selectedSourceId = '';
        selectedInvoiceNo = '';
        maxAmount = 0;
    }

    markSelectedChoice('#party_list', element);
    document.getElementById('select_party_btn').disabled = false;
    updateSelectionSummary();
    loadInvoices();
}

function loadInvoices() {
    if (!selectedPartyType || !selectedPartyId) {
        return;
    }

    const invoiceList = document.getElementById('invoice_list');
    const selectInvoiceBtn = document.getElementById('select_invoice_btn');

    invoiceList.innerHTML = '<div class="muted">جاري تحميل الفواتير...</div>';
    selectInvoiceBtn.disabled = true;

    fetch(`ajax/debts/get_invoices.php?party_type=${selectedPartyType}&party_id=${selectedPartyId}&search=${encodeURIComponent(document.getElementById('invoice_search')?.value || '')}`)
        .then((response) => response.json())
        .then((data) => {
            if (data.status === 'success') {
                renderInvoiceList(data.data || []);
            } else {
                invoiceList.innerHTML = `<div class="muted">${escapeHtml(data.message || 'تعذر تحميل الفواتير.')}</div>`;
            }
        })
        .catch(() => {
            invoiceList.innerHTML = '<div class="muted">حدث خطأ في الاتصال بالخادم.</div>';
        });
}

function searchInvoices() {
    loadInvoices();
}

function renderInvoiceList(invoices) {
    const invoiceList = document.getElementById('invoice_list');

    if (!invoices.length) {
        invoiceList.innerHTML = '<div class="muted">لا توجد فواتير مستحقة لهذا الطرف.</div>';
        return;
    }

    invoiceList.innerHTML = '';

    invoices.forEach((invoice) => {
        const invoiceElement = document.createElement('button');
        invoiceElement.type = 'button';
        invoiceElement.className = 'debt-choice-item debt-choice-item-invoice';
        invoiceElement.innerHTML = `
            <strong>${escapeHtml(invoice.invoice_no)}</strong>
            <small>التاريخ: ${escapeHtml(invoice.date)}</small>
            <small>المتبقي: ${formatCurrency(invoice.due_amount)}</small>
        `;
        invoiceElement.addEventListener('click', () => selectInvoice(invoice, invoiceElement));
        invoiceList.appendChild(invoiceElement);

        if (String(invoice.id) === String(selectedSourceId)) {
            selectInvoice(invoice, invoiceElement);
        }
    });
}

function selectInvoice(invoice, element) {
    selectedSourceId = String(invoice.id);
    selectedInvoiceNo = invoice.invoice_no;
    maxAmount = parseFloat(invoice.due_amount) || 0;

    markSelectedChoice('#invoice_list', element);
    document.getElementById('select_invoice_btn').disabled = false;
    document.getElementById('max_amount').textContent = formatCurrency(maxAmount);

    const amountField = document.getElementById('amount');
    if (amountField && (!amountField.value || parseFloat(amountField.value) === 0)) {
        amountField.value = maxAmount;
    }

    updateSelectionSummary();
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatCurrency(amount) {
    const value = parseFloat(amount || 0);
    const formatted = Number.isInteger(value) ? String(value) : value.toFixed(2).replace(/\.?0+$/, '');
    return `${formatted} ل.د`;
}

function showToast(message, type = 'info') {
    alert(type === 'danger' ? `خطأ: ${message}` : message);
}

document.addEventListener('DOMContentLoaded', function() {
    const submitButton = document.getElementById('submit_payment_btn');
    const actualForm = document.getElementById('actual_form');
    const partyTypeField = document.getElementById('party_type');

    document.querySelectorAll('[data-party-type-card]').forEach((button) => {
        button.addEventListener('click', function () {
            partyTypeField.value = this.dataset.partyTypeCard;
            loadParties();
        });
    });

    updateActionLabels();
    updateSelectionSummary();
    updateStepIndicators();

    if (partyTypeField && partyTypeField.value) {
        loadParties();
    }

    if (submitButton && actualForm) {
        submitButton.addEventListener('click', function() {
            if (!selectedPartyType || !selectedPartyId) {
                showToast('يرجى اختيار الطرف.', 'danger');
                return;
            }

            if (!selectedSourceType || !selectedSourceId) {
                showToast('يرجى اختيار الفاتورة.', 'danger');
                return;
            }

            const amount = parseFloat(document.getElementById('amount').value) || 0;
            if (amount <= 0) {
                showToast('يرجى إدخال مبلغ صحيح.', 'danger');
                return;
            }

            if (amount > maxAmount) {
                showToast(`المبلغ لا يمكن أن يتجاوز المتبقي وهو ${formatCurrency(maxAmount)}.`, 'danger');
                return;
            }

            document.getElementById('actual_party_type').value = selectedPartyType;
            document.getElementById('actual_party_id').value = selectedPartyId;
            document.getElementById('actual_source_type').value = selectedSourceType;
            document.getElementById('actual_source_id').value = selectedSourceId;
            actualForm.querySelector('input[name="payment_date"]').value = document.getElementById('payment_date').value;
            actualForm.querySelector('input[name="amount"]').value = amount;
            actualForm.querySelector('input[name="notes"]').value = document.getElementById('notes').value;
            actualForm.requestSubmit();
        });
    }
});
</script>
