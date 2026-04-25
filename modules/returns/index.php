<?php
require_once __DIR__ . '/../../config/bootstrap.php';

Auth::requireLogin();
Auth::requirePermission('sales.return');

$pageTitle = 'مرتجع بيع';
$csrfToken = CSRF::token();

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$returnNo = 'SR-' . date('YmdHis');
?>
<div class="container-fluid py-4" dir="rtl">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3 class="mb-1">مرتجع بيع</h3>
                    <div class="text-muted">ابحث عن الفاتورة ثم حدّد الأصناف والكميات المراد ترجيعها.</div>
                </div>
                <span class="badge bg-primary">Sales Return</span>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">رقم الفاتورة أو المعرّف</label>
                    <input type="text" id="saleLookup" class="form-control" placeholder="مثال: SAL-20260420 أو 15">
                </div>

                <div class="col-md-3">
                    <label class="form-label">رقم المرتجع</label>
                    <input type="text" id="returnNo" class="form-control" value="<?= h($returnNo) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">ملاحظات</label>
                    <input type="text" id="returnNotes" class="form-control" placeholder="سبب المرتجع">
                </div>

                <div class="col-md-2 d-grid">
                    <button type="button" class="btn btn-primary" id="loadSaleBtn">جلب الفاتورة</button>
                </div>
            </div>

            <div id="alertArea" class="mt-3"></div>
        </div>
    </div>

    <div id="invoiceSection" class="d-none">
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h5 class="mb-3">بيانات الفاتورة</h5>
                        <div id="invoiceMeta"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="text-muted small mb-1">رقم الفاتورة</div>
                                <div class="fw-bold" id="summaryInvoiceNo">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="text-muted small mb-1">العميل</div>
                                <div class="fw-bold" id="summaryCustomer">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="text-muted small mb-1">إجمالي الفاتورة</div>
                                <div class="fw-bold" id="summaryTotal">0.00</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 h-100 border-success">
                            <div class="card-body">
                                <div class="text-muted small mb-1">إجمالي المرتجع</div>
                                <div class="fw-bold text-success fs-5" id="summaryReturnTotal">0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">أصناف الفاتورة</h5>
                        <div class="text-muted">أدخل فقط الكمية المراد ترجيعها.</div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="resetQtyBtn">تصفير الكميات</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle text-center mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th class="text-end">الصنف</th>
                                <th>الكمية الأصلية</th>
                                <th>تم ترجيعه</th>
                                <th>المتاح</th>
                                <th>كمية المرتجع</th>
                                <th>السعر</th>
                                <th>الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody"></tbody>
                    </table>
                </div>

                <div id="emptyItemsState" class="text-center text-muted py-4 d-none">
                    لا توجد أصناف قابلة للترجيع.
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form id="returnForm">
                    <input type="hidden" name="_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="sale_id" id="formSaleId">
                    <input type="hidden" name="customer_id" id="formCustomerId">
                    <input type="hidden" name="invoice_no" id="formInvoiceNo">

                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">رقم المرتجع</label>
                            <input type="text" name="return_no" id="formReturnNo" class="form-control" value="<?= h($returnNo) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">ملاحظات</label>
                            <input type="text" name="notes" id="formNotes" class="form-control" placeholder="مثال: تلف في العبوة أو خطأ في التسليم">
                        </div>

                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-success btn-lg" id="saveReturnBtn">حفظ مرتجع البيع</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const loadSaleBtn = document.getElementById('loadSaleBtn');
    const saleLookupInput = document.getElementById('saleLookup');
    const returnNoInput = document.getElementById('returnNo');
    const returnNotesInput = document.getElementById('returnNotes');
    const alertArea = document.getElementById('alertArea');
    const invoiceSection = document.getElementById('invoiceSection');
    const invoiceMeta = document.getElementById('invoiceMeta');
    const itemsTableBody = document.getElementById('itemsTableBody');
    const emptyItemsState = document.getElementById('emptyItemsState');
    const summaryInvoiceNo = document.getElementById('summaryInvoiceNo');
    const summaryCustomer = document.getElementById('summaryCustomer');
    const summaryTotal = document.getElementById('summaryTotal');
    const summaryReturnTotal = document.getElementById('summaryReturnTotal');
    const formSaleId = document.getElementById('formSaleId');
    const formCustomerId = document.getElementById('formCustomerId');
    const formInvoiceNo = document.getElementById('formInvoiceNo');
    const formReturnNo = document.getElementById('formReturnNo');
    const formNotes = document.getElementById('formNotes');
    const returnForm = document.getElementById('returnForm');
    const saveReturnBtn = document.getElementById('saveReturnBtn');
    const resetQtyBtn = document.getElementById('resetQtyBtn');

    let currentSale = null;

    function showAlert(type, message) {
        alertArea.innerHTML = `<div class="alert alert-${type} mb-0">${escapeHtml(message)}</div>`;
    }

    function clearAlert() {
        alertArea.innerHTML = '';
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function formatMoney(value) {
        const number = Number(value || 0);
        return number.toFixed(2);
    }

    function setLoading(state) {
        document.body.style.pointerEvents = state ? 'none' : '';
        document.body.style.opacity = state ? '0.75' : '';
    }
function buildApiUrl(path) {
    return path;
}

    async function fetchJson(url) {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'تعذر الاتصال بالخادم.');
        }

        return result;
    }

    function buildSaleMeta(sale) {
        const rows = [
            ['المعرف', sale.id ?? '-'],
            ['رقم الفاتورة', sale.invoice_no ?? '-'],
            ['العميل', sale.customer_name ?? sale.customer_id ?? '-'],
            ['التاريخ', sale.sale_date ?? '-'],
            ['المخزن', sale.warehouse_name ?? sale.warehouse_id ?? '-'],
            ['الفرع', sale.branch_name ?? sale.branch_id ?? '-'],
            ['طريقة الدفع', sale.payment_method ?? '-'],
            ['الإجمالي', formatMoney(sale.total_amount ?? 0)],
            ['المدفوع', formatMoney(sale.paid_amount ?? 0)],
            ['المتبقي', formatMoney(sale.due_amount ?? 0)]
        ];

        invoiceMeta.innerHTML = rows.map(([label, value]) => {
            return `
                <div class="mb-2">
                    <strong>${escapeHtml(label)}:</strong>
                    <span>${escapeHtml(String(value))}</span>
                </div>
            `;
        }).join('');
    }

    function renderItems(items) {
        itemsTableBody.innerHTML = '';
        let visibleRows = 0;

        items.forEach((item, index) => {
            const originalQty = Number(item.quantity || 0);
            const returnedQty = Number(item.returned_quantity || 0);
            const availableQty = Math.max(0, originalQty - returnedQty);

            if (availableQty <= 0) {
                return;
            }

            visibleRows++;

            const tr = document.createElement('tr');
            tr.dataset.saleItemId = item.id;
            tr.dataset.productId = item.product_id;
            tr.dataset.productUnitId = item.product_unit_id || '';
            tr.dataset.unitPrice = item.unit_price || 0;
            tr.dataset.maxQty = availableQty;

            tr.innerHTML = `
                <td>${index + 1}</td>
                <td class="text-end">
                    <div class="fw-bold">${escapeHtml(item.product_name || ('صنف #' + item.product_id))}</div>
                    <div class="small text-muted">ID: ${escapeHtml(String(item.product_id || ''))}</div>
                </td>
                <td>${formatMoney(originalQty)}</td>
                <td>${formatMoney(returnedQty)}</td>
                <td>${formatMoney(availableQty)}</td>
                <td>
                    <input
                        type="number"
                        class="form-control form-control-sm return-qty"
                        min="0"
                        max="${availableQty}"
                        step="0.001"
                        value="0"
                    >
                </td>
                <td>${formatMoney(item.unit_price || 0)}</td>
                <td class="line-total fw-bold text-primary">0.00</td>
            `;

            itemsTableBody.appendChild(tr);
        });

        emptyItemsState.classList.toggle('d-none', visibleRows > 0);
        recalculateTotals();
    }

    function recalculateTotals() {
        let grandTotal = 0;

        itemsTableBody.querySelectorAll('tr').forEach((tr) => {
            const qtyInput = tr.querySelector('.return-qty');
            const unitPrice = Number(tr.dataset.unitPrice || 0);
            const maxQty = Number(tr.dataset.maxQty || 0);

            let qty = Number(qtyInput.value || 0);

            if (qty < 0) {
                qty = 0;
                qtyInput.value = '0';
            }

            if (qty > maxQty) {
                qty = maxQty;
                qtyInput.value = String(maxQty);
            }

            const lineTotal = qty * unitPrice;
            tr.querySelector('.line-total').textContent = formatMoney(lineTotal);
            grandTotal += lineTotal;
        });

        summaryReturnTotal.textContent = formatMoney(grandTotal);
    }

    async function loadSale() {
        clearAlert();

        const lookup = saleLookupInput.value.trim();
        if (!lookup) {
            showAlert('danger', 'أدخل رقم الفاتورة أو المعرّف أولًا.');
            return;
        }

        setLoading(true);

        try {
            const saleUrl = `${buildApiUrl('api/sale-data.php')}?id=${encodeURIComponent(lookup)}`;
            const itemsUrl = `${buildApiUrl('api/sale-items.php')}?sale_id=${encodeURIComponent(lookup)}`;

            const saleResponse = await fetchJson(saleUrl);
            if (!saleResponse || saleResponse.success !== true || !saleResponse.data) {
                throw new Error(saleResponse.message || 'لم يتم العثور على الفاتورة.');
            }

            currentSale = saleResponse.data;

            const resolvedSaleId = currentSale.id || 0;
            if (!resolvedSaleId) {
                throw new Error('تعذر تحديد معرف الفاتورة الداخلي.');
            }

            const itemsResponse = await fetchJson(`${buildApiUrl('api/sale-items.php')}?sale_id=${encodeURIComponent(resolvedSaleId)}`);
            if (!itemsResponse || itemsResponse.success !== true) {
                throw new Error(itemsResponse.message || 'تعذر جلب أصناف الفاتورة.');
            }

            const items = Array.isArray(itemsResponse.data) ? itemsResponse.data : [];

            invoiceSection.classList.remove('d-none');
            buildSaleMeta(currentSale);

            summaryInvoiceNo.textContent = currentSale.invoice_no || '-';
            summaryCustomer.textContent = currentSale.customer_name || currentSale.customer_id || '-';
            summaryTotal.textContent = formatMoney(currentSale.total_amount || 0);
            summaryReturnTotal.textContent = '0.00';

            formSaleId.value = String(currentSale.id || '');
            formCustomerId.value = String(currentSale.customer_id || '');
            formInvoiceNo.value = String(currentSale.invoice_no || '');
            formReturnNo.value = returnNoInput.value.trim();
            formNotes.value = returnNotesInput.value.trim();

            renderItems(items);

            console.log('Loaded sale object:', currentSale);
            console.log('Resolved formSaleId:', formSaleId.value);

            showAlert('success', 'تم جلب الفاتورة بنجاح.');
        } catch (error) {
            invoiceSection.classList.add('d-none');
            currentSale = null;
            showAlert('danger', error.message || 'حدث خطأ أثناء جلب الفاتورة.');
        } finally {
            setLoading(false);
        }
    }

    function collectReturnItems() {
        const items = [];

        itemsTableBody.querySelectorAll('tr').forEach((tr) => {
            const qty = Number(tr.querySelector('.return-qty').value || 0);
            if (qty <= 0) {
                return;
            }

            const unitPrice = Number(tr.dataset.unitPrice || 0);

            items.push({
                sale_item_id: Number(tr.dataset.saleItemId || 0),
                product_id: Number(tr.dataset.productId || 0),
                product_unit_id: tr.dataset.productUnitId ? Number(tr.dataset.productUnitId) : '',
                quantity: qty,
                unit_price: unitPrice,
                line_total: Number((qty * unitPrice).toFixed(2))
            });
        });

        return items;
    }

    async function submitReturn(event) {
        event.preventDefault();
        clearAlert();

        if (!currentSale) {
            showAlert('danger', 'يجب جلب الفاتورة أولًا.');
            return;
        }

        if (!formSaleId.value || Number(formSaleId.value) <= 0) {
            showAlert('danger', 'تعذر تحديد معرف الفاتورة الداخلي. أعد جلب الفاتورة أولًا.');
            return;
        }

        const returnNo = formReturnNo.value.trim();
        if (!returnNo) {
            showAlert('danger', 'رقم المرتجع مطلوب.');
            formReturnNo.focus();
            return;
        }

        const items = collectReturnItems();
        if (!items.length) {
            showAlert('danger', 'حدد صنفًا واحدًا على الأقل بكمية أكبر من صفر.');
            return;
        }

        setLoading(true);
        saveReturnBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('_token', returnForm.querySelector('[name="_token"]').value);
            formData.append('sale_id', formSaleId.value);
            formData.append('customer_id', formCustomerId.value);
            formData.append('invoice_no', formInvoiceNo.value);
            formData.append('return_no', returnNo);
            formData.append('notes', formNotes.value.trim());

            items.forEach((item, index) => {
                formData.append(`items[${index}][sale_item_id]`, item.sale_item_id);
                formData.append(`items[${index}][product_id]`, item.product_id);
                formData.append(`items[${index}][product_unit_id]`, item.product_unit_id);
                formData.append(`items[${index}][quantity]`, item.quantity);
                formData.append(`items[${index}][unit_price]`, item.unit_price);
                formData.append(`items[${index}][line_total]`, item.line_total);
            });

            const response = await fetch(buildApiUrl('ajax/sales/return.php'), {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (!response.ok || !result || result.success === false) {
                throw new Error(result.message || 'تعذر حفظ مرتجع البيع.');
            }

            showAlert('success', result.message || 'تم حفظ مرتجع البيع بنجاح.');

            const newReturnNo = 'SR-' + new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14);
            returnNoInput.value = newReturnNo;
            formReturnNo.value = newReturnNo;
            returnNotesInput.value = '';
            formNotes.value = '';

            await loadSale();
        } catch (error) {
            showAlert('danger', error.message || 'حدث خطأ أثناء حفظ مرتجع البيع.');
        } finally {
            setLoading(false);
            saveReturnBtn.disabled = false;
        }
    }

    loadSaleBtn.addEventListener('click', loadSale);

    saleLookupInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadSale();
        }
    });

    returnNoInput.addEventListener('input', () => {
        formReturnNo.value = returnNoInput.value.trim();
    });

    returnNotesInput.addEventListener('input', () => {
        formNotes.value = returnNotesInput.value;
    });

    itemsTableBody.addEventListener('input', (e) => {
        if (e.target.classList.contains('return-qty')) {
            recalculateTotals();
        }
    });

    resetQtyBtn.addEventListener('click', () => {
        itemsTableBody.querySelectorAll('.return-qty').forEach((input) => {
            input.value = '0';
        });
        recalculateTotals();
    });

    returnForm.addEventListener('submit', submitReturn);
})();
</script>