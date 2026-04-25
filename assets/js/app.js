document.addEventListener('DOMContentLoaded', () => {
    setupUiShell();
    setupConnectivityBanner();
    registerServiceWorker();
    setupSidebarToggle();
    setupAjaxForms();
    setupDeleteButtons();
    setupDynamicRows();
    setupQuickAccountEntries();
    setupSalesPos();
    setupSmartCalculations();
    setupSalesChart();
    setupFlashAlerts();
});

function setupUiShell() {
    if (!document.querySelector('.app-toast-stack')) {
        const stack = document.createElement('div');
        stack.className = 'app-toast-stack';
        document.body.appendChild(stack);
    }
}

const scriptLoaders = new Map();

async function fetchJson(url, options = {}) {
    const controller = new AbortController();
    const timeoutMs = options.timeoutMs ?? 15000;
    const timeoutId = window.setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(url, {
            ...options,
            signal: controller.signal,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
        });

        const text = await response.text();
        let payload = {};

        if (text) {
            try {
                payload = JSON.parse(text);
            } catch (error) {
                throw new Error('استجابة الخادم غير صالحة.');
            }
        }

        if (!response.ok) {
            throw new Error(payload.message || 'تعذر إكمال الطلب.');
        }

        return payload;
    } catch (error) {
        if (error.name === 'AbortError') {
            throw new Error('انتهت مهلة الاتصال بالخادم.');
        }

        throw error;
    } finally {
        window.clearTimeout(timeoutId);
    }
}

function setupConnectivityBanner() {
    if (document.querySelector('.app-network-banner')) {
        return;
    }

    const banner = document.createElement('div');
    banner.className = 'app-network-banner';
    banner.hidden = true;
    document.body.appendChild(banner);

    const updateStatus = () => {
        if (!navigator.onLine) {
            banner.textContent = 'الاتصال بالإنترنت ضعيف أو غير متاح. سيتم استخدام المحتوى المخزن محليًا عند الإمكان.';
            banner.classList.add('is-offline');
            banner.hidden = false;
            return;
        }

        banner.textContent = 'عاد الاتصال بالإنترنت.';
        banner.classList.remove('is-offline');
        banner.hidden = false;
        window.setTimeout(() => {
            banner.hidden = true;
        }, 2400);
    };

    window.addEventListener('offline', updateStatus);
    window.addEventListener('online', updateStatus);

    if (!navigator.onLine) {
        updateStatus();
    }
}

function registerServiceWorker() {
    const serviceWorkerUrl = window.appConfig?.serviceWorkerUrl;
    if (!serviceWorkerUrl || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register(serviceWorkerUrl).catch(() => {
        });
    });
}

function loadScriptOnce(src) {
    if (!src) {
        return Promise.reject(new Error('Missing script source.'));
    }

    if (scriptLoaders.has(src)) {
        return scriptLoaders.get(src);
    }

    const promise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });

    scriptLoaders.set(src, promise);
    return promise;
}

function setupFlashAlerts() {
    document.querySelectorAll('.alert').forEach((alert, index) => {
        window.setTimeout(() => {
            alert.classList.add('is-visible');
        }, index * 40);

        window.setTimeout(() => {
            alert.classList.add('is-dismissing');
            window.setTimeout(() => alert.remove(), 220);
        }, 4500 + (index * 250));
    });
}

function showToast(message, type = 'info', title = '') {
    const stack = document.querySelector('.app-toast-stack');
    if (!stack || !message) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `app-toast is-${type}`;

    const iconMap = {
        success: 'fa-circle-check',
        danger: 'fa-circle-exclamation',
        info: 'fa-bell',
    };

    const safeTitle = title || (type === 'success' ? 'تمت العملية' : type === 'danger' ? 'تعذر التنفيذ' : 'تنبيه');
    toast.innerHTML = `
        <span class="app-toast-icon"><i class="fa-solid ${iconMap[type] || iconMap.info}"></i></span>
        <div class="app-toast-content">
            <span class="app-toast-title">${escapeHtml(safeTitle)}</span>
            <span class="app-toast-message">${escapeHtml(message)}</span>
        </div>
    `;

    stack.prepend(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));

    window.setTimeout(() => {
        toast.classList.remove('is-visible');
        window.setTimeout(() => toast.remove(), 220);
    }, 3600);
}

function showConfirmDialog(message, options = {}) {
    return new Promise((resolve) => {
        const backdrop = document.createElement('div');
        backdrop.className = 'app-dialog-backdrop';

        const title = options.title || 'تأكيد الإجراء';
        const confirmText = options.confirmText || 'متابعة';
        const cancelText = options.cancelText || 'إلغاء';
        const toneClass = options.tone === 'danger' ? 'btn-danger' : 'btn-primary';

        backdrop.innerHTML = `
            <div class="app-dialog" role="dialog" aria-modal="true">
                <h3 class="app-dialog-title">${escapeHtml(title)}</h3>
                <p class="app-dialog-message">${escapeHtml(message)}</p>
                <div class="app-dialog-actions">
                    <button type="button" class="btn btn-light" data-dialog-cancel>${escapeHtml(cancelText)}</button>
                    <button type="button" class="btn ${toneClass}" data-dialog-confirm>${escapeHtml(confirmText)}</button>
                </div>
            </div>
        `;

        const close = (result) => {
            backdrop.remove();
            resolve(result);
        };

        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) {
                close(false);
            }
        });

        backdrop.querySelector('[data-dialog-cancel]')?.addEventListener('click', () => close(false));
        backdrop.querySelector('[data-dialog-confirm]')?.addEventListener('click', () => close(true));

        document.body.appendChild(backdrop);
        backdrop.querySelector('[data-dialog-confirm]')?.focus();
    });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function setButtonsBusy(form, isBusy) {
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
        button.disabled = isBusy;
        button.classList.toggle('is-loading', isBusy);
    });
}

function setupSidebarToggle() {
    const appShell = document.querySelector('.app-shell');
    const toggleButton = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (!appShell || !toggleButton) {
        return;
    }

    const mobileMedia = window.matchMedia('(max-width: 980px)');
    const storedState = localStorage.getItem('sidebarCollapsed');
    const syncToggleState = () => {
        if (mobileMedia.matches) {
            toggleButton.setAttribute('aria-expanded', appShell.classList.contains('sidebar-open-mobile') ? 'true' : 'false');
            return;
        }

        toggleButton.setAttribute('aria-expanded', appShell.classList.contains('sidebar-collapsed') ? 'false' : 'true');
    };

    if (storedState === '1' && !mobileMedia.matches) {
        appShell.classList.add('sidebar-collapsed');
    }
    syncToggleState();

    toggleButton.addEventListener('click', () => {
        if (mobileMedia.matches) {
            appShell.classList.toggle('sidebar-open-mobile');
            syncToggleState();
            return;
        }

        appShell.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', appShell.classList.contains('sidebar-collapsed') ? '1' : '0');
        syncToggleState();
    });

    overlay?.addEventListener('click', () => {
        appShell.classList.remove('sidebar-open-mobile');
        syncToggleState();
    });

    mobileMedia.addEventListener('change', (event) => {
        appShell.classList.remove('sidebar-open-mobile');
        if (event.matches) {
            appShell.classList.remove('sidebar-collapsed');
        } else if (localStorage.getItem('sidebarCollapsed') === '1') {
            appShell.classList.add('sidebar-collapsed');
        }
        syncToggleState();
    });
}

function setupAjaxForms() {
    document.querySelectorAll('[data-ajax-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            normalizeDynamicRowNames(form);
            setButtonsBusy(form, true);

            const formData = new FormData(form);
            if (!formData.has('_token')) {
                formData.append('_token', window.appConfig.csrfToken);
            }

            try {
                const result = await fetchJson(form.action, { method: 'POST', body: formData });

                if (result.status !== 'success') {
                    showToast(result.message || 'تعذر تنفيذ العملية.', 'danger');
                    return;
                }

                const isSaleCreate = form.action.includes('ajax/sales/create.php');
                if (isSaleCreate) {
                    showToast(result.message || 'تم حفظ الفاتورة.', 'success');
                    const shouldPrint = await showConfirmDialog('تم حفظ الفاتورة بنجاح. هل ترغب في طباعة الفاتورة الآن؟', {
                        title: 'الفاتورة جاهزة',
                        confirmText: 'طباعة',
                        cancelText: 'إغلاق',
                    });

                    if (shouldPrint && result.data?.print_url) {
                        window.open(result.data.print_url, '_blank', 'noopener');
                    }
                } else {
                    showToast(result.message || 'تم حفظ البيانات بنجاح.', 'success');
                }

                if (form.dataset.reset !== 'false') {
                    form.reset();
                }
                if (form.dataset.reload !== 'false') {
                    window.location.reload();
                }
            } catch (error) {
                showToast(error.message || 'حدث خطأ في الاتصال بالخادم.', 'danger');
            } finally {
                setButtonsBusy(form, false);
            }
        });
    });
}

function normalizeDynamicRowNames(form) {
    const collections = ['.dynamic-rows', '.invoice-items', '.product-unit-rows', '.sales-cart-items'];

    collections.forEach((selector) => {
        form.querySelectorAll(selector).forEach((collection) => {
            collection.querySelectorAll('.row').forEach((row, rowIndex) => {
                row.querySelectorAll('[name]').forEach((field) => {
                    const originalName = field.getAttribute('name');
                    if (!originalName || !originalName.includes('[][')) {
                        return;
                    }

                    field.setAttribute('name', originalName.replace('[][', `[${rowIndex}][`));
                });
            });
        });
    });
}

function setupDeleteButtons() {
    document.querySelectorAll('[data-delete-url]').forEach((button) => {
        button.addEventListener('click', async () => {
            const confirmText = button.dataset.confirm || 'هل تريد تنفيذ هذه العملية؟';
            const approved = await showConfirmDialog(confirmText, {
                title: 'تأكيد الحذف',
                confirmText: 'حذف',
                cancelText: 'إلغاء',
                tone: 'danger',
            });

            if (!approved) {
                return;
            }

            const body = new FormData();
            body.append('_token', window.appConfig.csrfToken);
            body.append('id', button.dataset.id);

            button.disabled = true;
            button.classList.add('is-loading');

            try {
                const result = await fetchJson(button.dataset.deleteUrl, { method: 'POST', body });
                showToast(result.message || 'تم تنفيذ العملية.', result.status === 'success' ? 'success' : 'danger');
                if (result.status === 'success') {
                    window.location.reload();
                }
            } catch (error) {
                showToast(error.message || 'تعذر تنفيذ العملية.', 'danger');
            } finally {
                button.disabled = false;
                button.classList.remove('is-loading');
            }
        });
    });
}

function setupDynamicRows() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-add-row]');
        if (button) {
            event.preventDefault();

            const targetSelector = button.dataset.target || button.getAttribute('data-add-row');
            const templateSelector = button.dataset.template;

            const target = document.querySelector(targetSelector);
            const template = document.querySelector(templateSelector);

            if (!target || !template) {
                console.warn('Dynamic row target/template not found.', {
                    targetSelector,
                    templateSelector
                });
                return;
            }

            const clone = template.content.cloneNode(true);
            target.appendChild(clone);
            document.dispatchEvent(new CustomEvent('dynamic-row:added', { detail: { target } }));
            return;
        }

        const removeButton = event.target.closest('[data-remove-row]');
        if (removeButton) {
            event.preventDefault();

            const row = removeButton.closest('.row, tr');
            const parent = row?.parentElement;
            row?.remove();
            document.dispatchEvent(new CustomEvent('dynamic-row:removed', { detail: { target: parent } }));
        }
    });
}

function setupSmartCalculations() {
    document.querySelectorAll('form[data-auto-calc]').forEach((form) => {
        const type = form.dataset.autoCalc;
        const recalculate = () => {
            if (type === 'sale') {
                recalculateSaleForm(form);
                return;
            }

            if (type === 'purchase') {
                recalculatePurchaseForm(form);
            }
        };

        form.addEventListener('input', recalculate);
        form.addEventListener('change', recalculate);
        document.addEventListener('dynamic-row:added', recalculate);
        document.addEventListener('dynamic-row:removed', recalculate);
        recalculate();
    });
}

function recalculateSaleForm(form) {
    const subtotalField = form.querySelector('[name="subtotal"]');
    const discountField = form.querySelector('[name="discount_value"]');
    const totalField = form.querySelector('[name="total_amount"]');
    const paidField = form.querySelector('[name="paid_amount"]');
    const dueField = form.querySelector('[name="due_amount"]');
    const paymentMethodField = form.querySelector('[name="payment_method"]');

    if (!subtotalField || !discountField || !totalField || !paidField || !dueField) {
        return;
    }

    let subtotal = 0;
    form.querySelectorAll('#salesItems .row').forEach((row) => {
        const quantity = parseNumber(row.querySelector('[name*="[quantity]"]')?.value);
        const price = parseNumber(row.querySelector('[name*="[unit_price]"]')?.value);
        const lineDiscount = parseNumber(row.querySelector('[name*="[discount_value]"]')?.value);
        const lineTotal = Math.max(0, (quantity * price) - lineDiscount);
        subtotal += lineTotal;

        const totalLabel = row.querySelector('.sales-cart-item-total');
        if (totalLabel) {
            totalLabel.textContent = formatAmount(lineTotal);
        }
    });

    const headerDiscount = parseNumber(discountField.value);
    const total = Math.max(0, subtotal - headerDiscount);

    subtotalField.value = formatAmount(subtotal);
    totalField.value = formatAmount(total);
    syncSalePaymentFields(paymentMethodField, paidField, dueField, total);
}

function recalculatePurchaseForm(form) {
    const subtotalField = form.querySelector('[name="subtotal"]');
    const discountField = form.querySelector('[name="discount_value"]');
    const importCostsField = form.querySelector('[name="import_costs"]');
    const totalField = form.querySelector('[name="total_amount"]');
    const paidField = form.querySelector('[name="paid_amount"]');
    const dueField = form.querySelector('[name="due_amount"]');

    if (!subtotalField || !discountField || !totalField || !paidField || !dueField) {
        return;
    }

    let subtotal = 0;
    form.querySelectorAll('#purchaseItems .row').forEach((row) => {
        const quantity = parseNumber(row.querySelector('[name*="[quantity]"]')?.value);
        const cost = parseNumber(row.querySelector('[name*="[unit_cost]"]')?.value);
        subtotal += quantity * cost;
    });

    const headerDiscount = parseNumber(discountField.value);
    const importCosts = parseNumber(importCostsField?.value);
    const total = Math.max(0, subtotal - headerDiscount + importCosts);
    const paid = parseNumber(paidField.value);
    const due = Math.max(0, total - paid);

    subtotalField.value = formatAmount(subtotal);
    totalField.value = formatAmount(total);
    dueField.value = formatAmount(due);
}

function parseNumber(value) {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

function formatAmount(value) {
    return parseNumber(value).toFixed(2);
}

function syncSalePaymentFields(paymentMethodField, paidField, dueField, total) {
    if (!paidField || !dueField) {
        return;
    }

    const paymentMethod = paymentMethodField?.value || 'cash';

    if (paymentMethod === 'deferred') {
        paidField.value = '0.00';
        paidField.setAttribute('readonly', 'readonly');
    } else if (paymentMethod === 'cash') {
        paidField.value = formatAmount(total);
        paidField.setAttribute('readonly', 'readonly');
    } else {
        paidField.removeAttribute('readonly');
        if (parseNumber(paidField.value) > total) {
            paidField.value = formatAmount(total);
        }
    }

    dueField.value = formatAmount(Math.max(0, total - parseNumber(paidField.value)));
}

function setupQuickAccountEntries() {
    document.querySelectorAll('[data-account-entry]').forEach((button) => {
        button.addEventListener('click', async () => {
            const dialog = document.querySelector(button.dataset.dialogTarget || '');
            if (!dialog) {
                return;
            }

            dialog.hidden = false;
            dialog.classList.add('is-visible');

            const closeDialog = () => {
                dialog.classList.remove('is-visible');
                dialog.hidden = true;
            };

            dialog.querySelectorAll('[data-close-dialog]').forEach((closeButton) => {
                closeButton.addEventListener('click', closeDialog, { once: true });
            });

            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    closeDialog();
                }
            }, { once: true });

            const form = dialog.querySelector('form');
            if (!form) {
                return;
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                setButtonsBusy(form, true);

                const formData = new FormData(form);
                if (!formData.has('_token')) {
                    formData.append('_token', window.appConfig.csrfToken);
                }

                try {
                    const result = await fetchJson(form.action, { method: 'POST', body: formData });

                    if (result.status !== 'success') {
                        showToast(result.message || 'تعذر حفظ البيانات.', 'danger');
                        return;
                    }

                    showToast(result.message || 'تم حفظ البيانات.', 'success');
                    const selectSelector = button.dataset.selectTarget;
                    if (selectSelector && result.data?.id && result.data?.name) {
                        const select = document.querySelector(selectSelector);
                        if (select) {
                            const option = document.createElement('option');
                            option.value = result.data.id;
                            option.textContent = result.data.name;
                            option.selected = true;
                            select.appendChild(option);
                            select.dispatchEvent(new Event('change'));
                        }
                    }

                    closeDialog();
                    form.reset();
                } catch (error) {
                    showToast('حدث خطأ أثناء الحفظ.', 'danger');
                } finally {
                    setButtonsBusy(form, false);
                }
            }, { once: true });
        });
    });
}

function setupSalesPos() {
    const form = document.querySelector('[data-sales-pos]');
    if (!form) {
        return;
    }

    document.addEventListener('wheel', (e) => {
        if (e.target.matches('input[type="number"]')) {
            e.preventDefault();
        }
    }, { passive: false });

    const productGrid = form.querySelector('#salesProductsGrid');
    const productTemplate = document.getElementById('salesProductCardTemplate');
    const rowTemplate = document.getElementById('salesCartRowTemplate');
    const cartItems = form.querySelector('#salesItems');
    const productsJsonElement = document.getElementById('salesProductsData');
    const categoryFilters = form.querySelector('#salesCategoryFilters');
    const loadMoreButton = form.querySelector('#salesProductsMore');
    const emptyProductsState = form.querySelector('#salesProductsEmpty');
    const customerField = form.querySelector('[name="customer_id"]');
    const paymentMethodField = form.querySelector('[name="payment_method"]');
    const warehouseField = form.querySelector('[name="warehouse_id"]');
    const branchField = form.querySelector('[name="branch_id"]');
    const marketerField = form.querySelector('[name="marketer_id"]');
    const pricingTierField = form.querySelector('[name="pricing_tier"]');
    const clearCartButton = form.querySelector('[data-sales-clear]');
    const quickCustomerToggleButton = form.querySelector('[data-toggle-customer-quick-form]');
    const quickCustomerCloseButton = form.querySelector('[data-close-customer-quick-form]');
    const quickCustomerPanel = form.querySelector('[data-customer-quick-panel]');
    const saveQuickCustomerButton = form.querySelector('[data-save-quick-customer]');
    const quickCustomerInputs = {
        fullName: form.querySelector('[name="quick_customer_full_name"]'),
        phone: form.querySelector('[name="quick_customer_phone"]'),
        category: form.querySelector('[name="quick_customer_category"]'),
        creditLimit: form.querySelector('[name="quick_customer_credit_limit"]'),
    };

    if (!productGrid || !productTemplate || !rowTemplate || !cartItems || !productsJsonElement) {
        return;
    }

    const allProducts = parseSalesProductsData(productsJsonElement.textContent);
    let visibleCount = 12;
    form._salesStockMap = {};

    renderSalesProductSkeletons(productGrid);
    window.setTimeout(async () => {
        renderSalesProductCards(form, allProducts, productTemplate, productGrid, emptyProductsState, loadMoreButton, visibleCount);
        await refreshSalesProductStocks(form);
    }, 80);

    const rerenderProducts = async () => {
        renderSalesProductCards(form, allProducts, productTemplate, productGrid, emptyProductsState, loadMoreButton, visibleCount);
        await refreshSalesProductStocks(form);
    };

    form.querySelector('#salesProductSearch')?.addEventListener('input', () => {
        visibleCount = 12;
        void rerenderProducts();
    });

    categoryFilters?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-category-filter]');
        if (!button) {
            return;
        }

        categoryFilters.querySelectorAll('[data-category-filter]').forEach((filterButton) => {
            filterButton.classList.toggle('active', filterButton === button);
        });

        visibleCount = 12;
        void rerenderProducts();
    });

    loadMoreButton?.addEventListener('click', () => {
        visibleCount += 12;
        void rerenderProducts();
    });

    productGrid.addEventListener('click', async (event) => {
        const card = event.target.closest('[data-sales-product]');
        if (!card) {
            return;
        }

        const warehouseId = resolveSalesWarehouseId(form);
        if (!warehouseId) {
            showToast('اختر المخزن أولاً لعرض الرصيد وإضافة المنتج إلى السلة.', 'danger');
            return;
        }

        if (card.dataset.stockLoading === '1') {
            await refreshSalesProductStocks(form);
        }

        addSalesProductToCart(form, card, rowTemplate, cartItems);
        toggleSalesEmptyState(form);
        syncSalesCartStockInfo(form);
        recalculateSaleForm(form);
    });

    cartItems.addEventListener('input', (event) => {
        const row = event.target.closest('[data-sales-item]');
        if (row) {
            enforceSalesCartRowStock(row);
            syncSalesCartStockInfo(form);
        }
        toggleSalesEmptyState(form);
        recalculateSaleForm(form);
    });

    cartItems.addEventListener('change', (event) => {
        const row = event.target.closest('[data-sales-item]');
        if (row) {
            enforceSalesCartRowStock(row);
            syncSalesCartStockInfo(form);
        }
        toggleSalesEmptyState(form);
        recalculateSaleForm(form);
    });

    document.addEventListener('dynamic-row:removed', () => {
        toggleSalesEmptyState(form);
        syncSalesCartStockInfo(form);
        recalculateSaleForm(form);
    });

    document.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-row]');
        if (!removeButton || !cartItems.contains(removeButton)) {
            return;
        }

        window.setTimeout(() => {
            toggleSalesEmptyState(form);
            syncSalesCartStockInfo(form);
            recalculateSaleForm(form);
        }, 0);
    });

    pricingTierField?.addEventListener('change', () => {
        syncSalesCartPrices(form);
        syncSalesCartStockInfo(form);
        void rerenderProducts();
        recalculateSaleForm(form);
    });

    paymentMethodField?.addEventListener('change', () => {
        syncSaleCustomerRequirement(form);
        recalculateSaleForm(form);
    });

    customerField?.addEventListener('change', () => {
        syncSaleCustomerRequirement(form);

        const selectedOption = customerField.options[customerField.selectedIndex];
        const optionMarketerId = selectedOption?.getAttribute('data-marketer-id') || '';

        if (marketerField && optionMarketerId) {
            marketerField.value = optionMarketerId;
            marketerField.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    warehouseField?.addEventListener('change', () => {
        form.dataset.selectedWarehouse = resolveSalesWarehouseId(form);
        void refreshSalesProductStocks(form, true);
    });

    branchField?.addEventListener('change', () => {
        form.dataset.selectedBranch = branchField.value || '';
    });

    marketerField?.addEventListener('change', () => {
        window.setTimeout(() => {
            form.dataset.selectedWarehouse = resolveSalesWarehouseId(form);
            void refreshSalesProductStocks(form, true);
        }, 0);
    });

    clearCartButton?.addEventListener('click', () => {
        cartItems.querySelectorAll('[data-sales-item]').forEach((row) => row.remove());
        toggleSalesEmptyState(form);
        syncSalesCartStockInfo(form);
        recalculateSaleForm(form);
    });

    quickCustomerToggleButton?.addEventListener('click', () => {
        const shouldOpen = quickCustomerPanel?.hidden ?? true;
        setQuickCustomerPanelState(quickCustomerPanel, shouldOpen);
    });

    quickCustomerCloseButton?.addEventListener('click', () => {
        setQuickCustomerPanelState(quickCustomerPanel, false);
    });

    saveQuickCustomerButton?.addEventListener('click', async () => {
        await handleQuickCustomerSave({
            form,
            customerField,
            quickCustomerPanel,
            quickCustomerInputs,
            saveQuickCustomerButton,
        });
    });

    toggleSalesEmptyState(form);
    syncSaleCustomerRequirement(form);
    syncSalesCartStockInfo(form);
    recalculateSaleForm(form);
    window.setTimeout(() => {
        form.dataset.selectedWarehouse = resolveSalesWarehouseId(form);
        form.dataset.selectedBranch = branchField?.value || '';
        void refreshSalesProductStocks(form, true);
    }, 0);
}

function syncSaleCustomerRequirement(form) {
    const paymentMethodField = form.querySelector('[name="payment_method"]');
    const customerField = form.querySelector('[name="customer_id"]');
    const quickCustomerHint = form.querySelector('[data-customer-required-hint]');

    if (!paymentMethodField || !customerField) {
        return;
    }

    const isDeferred = paymentMethodField.value === 'deferred';
    customerField.required = isDeferred;

    if (quickCustomerHint) {
        quickCustomerHint.hidden = !isDeferred;
    }
}

async function handleQuickCustomerSave({
    form,
    customerField,
    quickCustomerPanel,
    quickCustomerInputs,
    saveQuickCustomerButton,
}) {
    const fullName = quickCustomerInputs.fullName?.value.trim() || '';
    const phone = quickCustomerInputs.phone?.value.trim() || '';
    const marketerField = form.querySelector('[name="marketer_id"]');
    const marketerId = marketerField?.value || '';
    const branchId = form.querySelector('[name="branch_id"]')?.value || '';

    if (!fullName) {
        showToast('يرجى إدخال اسم العميل.', 'danger');
        quickCustomerInputs.fullName?.focus();
        return;
    }

    if (!phone) {
        showToast('يرجى إدخال رقم هاتف العميل.', 'danger');
        quickCustomerInputs.phone?.focus();
        return;
    }

    const formData = new FormData();
    formData.append('_token', window.appConfig.csrfToken);
    formData.append('quick_create', '1');
    formData.append('branch_id', branchId);
    formData.append('marketer_id', marketerId);
    formData.append('full_name', fullName);
    formData.append('phone', phone);
    formData.append('category', quickCustomerInputs.category?.value.trim() || '');
    formData.append('status', 'active');
    formData.append('credit_limit', quickCustomerInputs.creditLimit?.value || '0');

    saveQuickCustomerButton.disabled = true;
    saveQuickCustomerButton.classList.add('is-loading');

    try {
        const result = await fetchJson('ajax/customers/save.php', {
            method: 'POST',
            body: formData
        });

        if (result.status !== 'success' || !result.data?.id) {
            showToast(result.message || 'تعذر حفظ العميل.', 'danger');
            return;
        }

        let option = customerField.querySelector(`option[value="${String(result.data.id)}"]`);
        if (!option) {
            option = document.createElement('option');
            option.value = String(result.data.id);
            customerField.appendChild(option);
        }

        option.textContent = result.data.full_name || fullName;
        option.setAttribute('data-marketer-id', result.data.marketer_id || marketerId || '');
        option.selected = true;

        customerField.value = String(result.data.id);
        customerField.dispatchEvent(new Event('change', { bubbles: true }));

        if (marketerField && (result.data.marketer_id || marketerId)) {
            marketerField.value = String(result.data.marketer_id || marketerId);
            marketerField.dispatchEvent(new Event('change', { bubbles: true }));
        }

        resetQuickCustomerFields(quickCustomerPanel);
        setQuickCustomerPanelState(quickCustomerPanel, false);
        syncSaleCustomerRequirement(form);
        showToast(result.message || 'تم حفظ العميل.', 'success');
    } catch (error) {
        showToast(error.message || 'حدث خطأ أثناء حفظ العميل.', 'danger');
    } finally {
        saveQuickCustomerButton.disabled = false;
        saveQuickCustomerButton.classList.remove('is-loading');
    }
}

function setQuickCustomerPanelState(panel, shouldOpen) {
    if (panel) {
        panel.hidden = !shouldOpen;
    }
}

function resetQuickCustomerFields(panel) {
    if (!panel) {
        return;
    }

    panel.querySelectorAll('input').forEach((field) => {
        field.value = field.name === 'quick_customer_credit_limit' ? '0' : '';
    });
}

function resolveSalesWarehouseId(form) {
    return form.querySelector('[name="warehouse_id"]')?.value || '';
}

function normalizeSalesUnitFactor(value) {
    const factor = parseNumber(value);
    return factor > 0 ? factor : 1;
}

function getSalesAvailableStockRaw(source) {
    if (!source) {
        return 0;
    }

    if ('dataset' in source) {
        return parseNumber(source.dataset.availableStockRaw);
    }

    return parseNumber(source.availableStockRaw);
}

function getSalesAvailableQuantity(source) {
    if (!source) {
        return 0;
    }

    const unitFactor = 'dataset' in source
        ? normalizeSalesUnitFactor(source.dataset.saleUnitsPerBase)
        : normalizeSalesUnitFactor(source.saleUnitsPerBase);

    return getSalesAvailableStockRaw(source) / unitFactor;
}

function buildSalesStockText(availableQuantity, unitLabel) {
    const formattedQuantity = formatQuantity(availableQuantity);
    return unitLabel ? `${formattedQuantity} ${unitLabel}` : formattedQuantity;
}

function setSalesProductCardStockState(form, card, options = {}) {
    const badge = card.querySelector('[data-stock-target]');
    if (!badge) {
        return;
    }

    badge.classList.remove('is-loading', 'is-warning', 'is-danger', 'is-success');
    card.classList.remove('is-out-of-stock');

    if (!resolveSalesWarehouseId(form)) {
        badge.textContent = 'اختر المخزن لعرض الرصيد';
        badge.classList.add('is-warning');
        card.dataset.stockLoading = '0';
        return;
    }

    if (options.loading) {
        badge.textContent = 'جاري تحديث المخزون...';
        badge.classList.add('is-loading');
        card.dataset.stockLoading = '1';
        return;
    }

    card.dataset.stockLoading = '0';

    if (options.error) {
        badge.textContent = options.message || 'تعذر تحميل الرصيد';
        badge.classList.add('is-danger');
        return;
    }

    const availableQuantity = Math.max(0, getSalesAvailableQuantity(card));
    const unitLabel = card.dataset.saleUnitLabel || '';

    if (availableQuantity <= 0) {
        badge.textContent = 'نفد المخزون';
        badge.classList.add('is-danger');
        card.classList.add('is-out-of-stock');
        return;
    }

    badge.textContent = `المتوفر: ${buildSalesStockText(availableQuantity, unitLabel)}`;
    badge.classList.add('is-success');
}

async function refreshSalesProductStocks(form, validateCart = false) {
    const warehouseId = resolveSalesWarehouseId(form);
    const cards = Array.from(form.querySelectorAll('[data-sales-product]'));
    const rows = Array.from(form.querySelectorAll('[data-sales-item]'));
    const productIds = Array.from(new Set([
        ...cards.map((card) => card.dataset.productId).filter(Boolean),
        ...rows.map((row) => row.dataset.productId).filter(Boolean),
    ]));

    if (!cards.length && !rows.length) {
        return;
    }

    if (!warehouseId) {
        cards.forEach((card) => {
            delete card.dataset.availableStockRaw;
            setSalesProductCardStockState(form, card);
        });
        rows.forEach((row) => {
            row.dataset.availableStockRaw = '0';
        });
        syncSalesCartStockInfo(form);
        if (validateCart) {
            validateAllSalesCartRows(form, { silent: true });
        }
        return;
    }

    const requestToken = `${warehouseId}:${Date.now()}:${Math.random()}`;
    form.dataset.salesStockRequestToken = requestToken;
    cards.forEach((card) => setSalesProductCardStockState(form, card, { loading: true }));

    try {
        const result = await fetchJson(`api/product-stock-list.php?warehouse_id=${encodeURIComponent(warehouseId)}&ids=${encodeURIComponent(productIds.join(','))}`);
        if (form.dataset.salesStockRequestToken !== requestToken) {
            return;
        }

        const stocks = result.data?.stocks || {};
        form._salesStockMap = stocks;

        cards.forEach((card) => {
            const stock = stocks[card.dataset.productId] || {};
            card.dataset.availableStockRaw = String(parseNumber(stock.stock_balance));
            setSalesProductCardStockState(form, card);
        });

        rows.forEach((row) => {
            const stock = stocks[row.dataset.productId] || {};
            row.dataset.availableStockRaw = String(parseNumber(stock.stock_balance));
        });

        syncSalesCartStockInfo(form);
        if (validateCart) {
            validateAllSalesCartRows(form, { silent: false });
        }
    } catch (error) {
        if (form.dataset.salesStockRequestToken !== requestToken) {
            return;
        }

        cards.forEach((card) => {
            setSalesProductCardStockState(form, card, {
                error: true,
                message: error.message || 'تعذر تحميل الرصيد',
            });
        });

        if (validateCart) {
            syncSalesCartStockInfo(form);
        }

        showToast(error.message || 'تعذر تحديث رصيد المخزون.', 'danger');
    }
}

function syncSalesCartStockInfo(form) {
    form.querySelectorAll('[data-sales-item]').forEach((row) => {
        const stockLabel = row.querySelector('.sales-cart-item-stock');
        if (!stockLabel) {
            return;
        }

        const availableQuantity = Math.max(0, getSalesAvailableQuantity(row));
        const unitLabel = row.dataset.saleUnitLabel || '';
        const requestedQuantity = parseNumber(row.querySelector('[name*="[quantity]"]')?.value);

        stockLabel.textContent = `المتوفر: ${buildSalesStockText(availableQuantity, unitLabel)} | المطلوب: ${buildSalesStockText(requestedQuantity, unitLabel)}`;
        stockLabel.classList.toggle('is-danger', requestedQuantity > availableQuantity + 0.0001 || availableQuantity <= 0);
    });
}

function enforceSalesCartRowStock(row, options = {}) {
    const quantityField = row.querySelector('[name*="[quantity]"]');
    if (!quantityField) {
        return true;
    }

    const availableQuantity = Math.max(0, getSalesAvailableQuantity(row));
    const requestedQuantity = parseNumber(quantityField.value);
    const productName = row.dataset.productName || 'هذا الصنف';
    const unitLabel = row.dataset.saleUnitLabel || '';

    if (requestedQuantity <= 0) {
        return true;
    }

    if (availableQuantity <= 0) {
        if (options.removeIfUnavailable) {
            row.remove();
        } else {
            quantityField.value = '0';
        }

        if (!options.silent) {
            showToast(`الصنف "${productName}" غير متوفر في المخزن المحدد.`, 'danger');
        }

        return false;
    }

    if (requestedQuantity > availableQuantity + 0.0001) {
        quantityField.value = formatQuantity(availableQuantity);

        if (!options.silent) {
            showToast(`لا يمكن تجاوز الكمية المتوفرة للصنف "${productName}". المتاح: ${buildSalesStockText(availableQuantity, unitLabel)}`, 'danger');
        }

        return false;
    }

    return true;
}

function validateAllSalesCartRows(form, options = {}) {
    Array.from(form.querySelectorAll('[data-sales-item]')).forEach((row) => {
        enforceSalesCartRowStock(row, {
            silent: options.silent,
            removeIfUnavailable: true,
        });
    });

    toggleSalesEmptyState(form);
    syncSalesCartStockInfo(form);
    recalculateSaleForm(form);
}

function addSalesProductToCart(form, card, rowTemplate, cartItems) {
    const productId = card.dataset.productId;
    const existingRow = cartItems.querySelector(`[data-sales-item][data-product-id="${productId}"]`);
    const availableQuantity = Math.max(0, getSalesAvailableQuantity(card));

    if (availableQuantity <= 0) {
        showToast(`الصنف "${card.dataset.productName}" غير متوفر في المخزن المحدد.`, 'danger');
        return;
    }

    if (existingRow) {
        const quantityField = existingRow.querySelector('[name*="[quantity]"]');
        quantityField.value = formatQuantity(parseNumber(quantityField.value) + 1);
        enforceSalesCartRowStock(existingRow);
        return;
    }

    const clone = rowTemplate.content.cloneNode(true);
    const row = clone.querySelector('[data-sales-item]');
    const pricingTier = form.querySelector('[name="pricing_tier"]')?.value || 'wholesale';

    row.dataset.productId = productId;
    row.dataset.productName = card.dataset.productName;
    row.dataset.productCode = card.dataset.productCode || '';
    row.dataset.categoryName = card.dataset.categoryName || '';
    row.dataset.wholesalePrice = card.dataset.wholesalePrice || '0';
    row.dataset.halfWholesalePrice = card.dataset.halfWholesalePrice || '0';
    row.dataset.retailPrice = card.dataset.retailPrice || '0';
    row.dataset.saleUnitsPerBase = card.dataset.saleUnitsPerBase || '1';
    row.dataset.saleUnitLabel = card.dataset.saleUnitLabel || '';
    row.dataset.availableStockRaw = card.dataset.availableStockRaw || '0';

    row.querySelector('[name*="[product_id]"]').value = productId;
    row.querySelector('[name*="[product_unit_id]"]').value = card.dataset.defaultSaleUnitId || '';
    row.querySelector('.sales-cart-item-name').textContent = card.dataset.productName;
    row.querySelector('.sales-cart-item-meta').textContent = buildSalesMeta(card.dataset.productCode, card.dataset.categoryName);
    row.querySelector('[name*="[unit_price]"]').value = formatAmount(getSalesPriceForTier(card.dataset, pricingTier));

    cartItems.appendChild(clone);
}

function syncSalesCartPrices(form) {
    const pricingTier = form.querySelector('[name="pricing_tier"]')?.value || 'wholesale';
    form.querySelectorAll('[data-sales-item]').forEach((row) => {
        const unitPriceField = row.querySelector('[name*="[unit_price]"]');
        if (!unitPriceField) {
            return;
        }

        const price = getSalesPriceForTier(row.dataset, pricingTier);
        unitPriceField.value = formatAmount(price);
        const stockLabel = row.querySelector('.sales-cart-item-stock');
        if (stockLabel) {
            stockLabel.textContent = `السعر الحالي: ${formatAmount(price)}`;
        }
    });
}

function getFilteredSalesProducts(form, allProducts) {
    const searchValue = (form.querySelector('#salesProductSearch')?.value || '').trim().toLowerCase();
    const activeCategory = form.querySelector('#salesCategoryFilters .active')?.dataset.categoryFilter || 'all';

    return allProducts.filter((product) => {
        const categoryId = String(product.category_id ?? 'uncategorized');
        const matchesCategory = activeCategory === 'all' || categoryId === activeCategory;
        const haystack = `${product.name || ''} ${product.code || ''} ${product.category_name || ''}`.toLowerCase();
        return matchesCategory && (!searchValue || haystack.includes(searchValue));
    });
}

function renderSalesProductCards(form, allProducts, productTemplate, productGrid, emptyProductsState, moreButton, visibleCount) {
    const pricingTier = form.querySelector('[name="pricing_tier"]')?.value || 'wholesale';
    const filteredProducts = getFilteredSalesProducts(form, allProducts);
    const productsToRender = filteredProducts.slice(0, visibleCount);
    const fragment = document.createDocumentFragment();
    const cachedStocks = form._salesStockMap || {};

    productGrid.innerHTML = '';

    productsToRender.forEach((product) => {
        const clone = productTemplate.content.cloneNode(true);
        const card = clone.querySelector('[data-sales-product]');
        const priceTarget = clone.querySelector('[data-price-target]');

        card.dataset.productId = String(product.id);
        card.dataset.productName = product.name || '';
        card.dataset.productCode = product.code || '';
        card.dataset.categoryId = String(product.category_id ?? 'uncategorized');
        card.dataset.categoryName = product.category_name || 'بدون تصنيف';
        card.dataset.wholesalePrice = String(product.wholesale_price ?? 0);
        card.dataset.halfWholesalePrice = String(product.half_wholesale_price ?? 0);
        card.dataset.retailPrice = String(product.retail_price ?? 0);
        card.dataset.defaultSaleUnitId = String(product.default_sale_unit_id ?? '');
        card.dataset.saleUnitsPerBase = String(product.sale_units_per_base ?? 1);
        card.dataset.saleUnitLabel = product.sale_unit_label || '';
        card.dataset.availableStockRaw = String(parseNumber(cachedStocks[String(product.id)]?.stock_balance));

        clone.querySelector('.sales-product-name').textContent = product.name || '';
        clone.querySelector('.sales-product-subtitle').textContent = product.category_name || 'بدون تصنيف';
        if (priceTarget) {
            priceTarget.textContent = formatAmount(getSalesPriceForTier(card.dataset, pricingTier));
        }

        setSalesProductCardStockState(form, card, resolveSalesWarehouseId(form) ? { loading: true } : {});

        fragment.appendChild(clone);
    });

    productGrid.appendChild(fragment);

    if (emptyProductsState) {
        emptyProductsState.hidden = filteredProducts.length !== 0;
    }

    if (moreButton) {
        moreButton.hidden = filteredProducts.length <= visibleCount;
    }
}

function renderSalesProductSkeletons(productGrid, count = 8) {
    productGrid.innerHTML = '';

    for (let index = 0; index < count; index += 1) {
        const skeleton = document.createElement('div');
        skeleton.className = 'sales-product-skeleton';
        skeleton.setAttribute('aria-hidden', 'true');
        skeleton.innerHTML = `
            <span class="skeleton-block skeleton-icon"></span>
            <span class="skeleton-block skeleton-title"></span>
            <span class="skeleton-block skeleton-subtitle"></span>
            <div class="sales-product-footer">
                <span class="skeleton-block skeleton-price"></span>
                <span class="skeleton-block skeleton-action"></span>
            </div>
        `;
        productGrid.appendChild(skeleton);
    }
}

function toggleSalesEmptyState(form) {
    const emptyState = form.querySelector('[data-sales-empty]');
    const hasItems = form.querySelectorAll('[data-sales-item]').length > 0;
    if (emptyState) {
        emptyState.style.display = hasItems ? 'none' : 'grid';
    }
}

function getSalesPriceForTier(dataset, pricingTier) {
    if (pricingTier === 'retail') {
        return parseNumber(dataset.retailPrice);
    }
    if (pricingTier === 'half_wholesale') {
        return parseNumber(dataset.halfWholesalePrice);
    }
    return parseNumber(dataset.wholesalePrice);
}

function buildSalesMeta(code, category) {
    if (code && category) {
        return `${category} - ${code}`;
    }
    return category || code || 'بدون تفاصيل إضافية';
}

function formatQuantity(value) {
    return parseNumber(value).toFixed(3).replace(/\.?0+$/, '');
}

function parseSalesProductsData(raw) {
    try {
        const parsed = JSON.parse(raw || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        return [];
    }
}

async function setupSalesChart() {
    const chartCanvas = document.getElementById('salesChart');
    if (!chartCanvas) {
        return;
    }

    if (typeof Chart === 'undefined') {
        try {
            await loadScriptOnce(window.appConfig?.chartScriptUrl || '');
        } catch (error) {
        }
    }

    if (typeof Chart === 'undefined') {
        const fallback = document.createElement('div');
        fallback.className = 'alert alert-info';
        fallback.innerHTML = '<span>تعذر تحميل الرسم البياني حالياً. البيانات الأساسية في الجداول ما زالت متاحة.</span>';
        chartCanvas.replaceWith(fallback);
        return;
    }

    const labels = JSON.parse(chartCanvas.dataset.labels || '[]');
    const totals = JSON.parse(chartCanvas.dataset.totals || '[]');

    new Chart(chartCanvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'المبيعات',
                data: totals,
                borderColor: '#3f8cff',
                backgroundColor: 'rgba(63, 140, 255, 0.12)',
                fill: true,
                tension: 0.38,
                borderWidth: 3,
                pointRadius: 0,
                pointHoverRadius: 5,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(180, 198, 220, 0.18)' },
                    ticks: { color: '#7b8ca6' },
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#7b8ca6' },
                },
            },
        },
    });
}