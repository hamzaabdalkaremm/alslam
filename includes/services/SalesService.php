<?php

class SalesService
{
    private InventoryService $inventoryService;

    public function __construct()
    {
        $this->inventoryService = new InventoryService();
    }

    public function create(array $header, array $items): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $warehouseId = !empty($header['warehouse_id']) ? (int) $header['warehouse_id'] : null;
            $this->assertStockAvailability($items, $warehouseId);

            $stmt = $pdo->prepare(
                'INSERT INTO sales
                 (
                    branch_id,
                    warehouse_id,
                    marketer_id,
                    sale_mode,
                    delivery_status,
                    delivered_by,
                    invoice_no,
                    customer_id,
                    sold_by,
                    sale_date,
                    status,
                    approval_status,
                    pricing_tier,
                    payment_method,
                    subtotal,
                    discount_value,
                    tax_value,
                    total_amount,
                    paid_amount,
                    due_amount,
                    notes,
                    printable_token
                 )
                 VALUES
                 (
                    :branch_id,
                    :warehouse_id,
                    :marketer_id,
                    :sale_mode,
                    :delivery_status,
                    :delivered_by,
                    :invoice_no,
                    :customer_id,
                    :sold_by,
                    :sale_date,
                    :status,
                    :approval_status,
                    :pricing_tier,
                    :payment_method,
                    :subtotal,
                    :discount_value,
                    :tax_value,
                    :total_amount,
                    :paid_amount,
                    :due_amount,
                    :notes,
                    :printable_token
                 )'
            );

            $stmt->execute([
                'branch_id' => $header['branch_id'] ?? null,
                'warehouse_id' => $header['warehouse_id'] ?? null,
                'marketer_id' => $header['marketer_id'] ?? null,
                'sale_mode' => $header['sale_mode'] ?? 'vehicle_sale',
                'delivery_status' => $header['delivery_status'] ?? 'delivered',
                'delivered_by' => $header['delivered_by'] ?? null,
                'invoice_no' => $header['invoice_no'],
                'customer_id' => $header['customer_id'] ?? null,
                'sold_by' => $header['sold_by'],
                'sale_date' => $header['sale_date'],
                'status' => $header['status'],
                'approval_status' => $header['approval_status'] ?? 'approved',
                'pricing_tier' => $header['pricing_tier'],
                'payment_method' => $header['payment_method'] ?? 'cash',
                'subtotal' => $header['subtotal'],
                'discount_value' => $header['discount_value'],
                'tax_value' => $header['tax_value'],
                'total_amount' => $header['total_amount'],
                'paid_amount' => $header['paid_amount'],
                'due_amount' => $header['due_amount'],
                'notes' => $header['notes'],
                'printable_token' => $header['printable_token'],
            ]);

            $saleId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO sale_items
                 (
                    sale_id,
                    product_id,
                    product_unit_id,
                    batch_id,
                    quantity,
                    unit_price,
                    discount_value,
                    tax_value,
                    line_total
                 )
                 VALUES
                 (
                    :sale_id,
                    :product_id,
                    :product_unit_id,
                    :batch_id,
                    :quantity,
                    :unit_price,
                    :discount_value,
                    :tax_value,
                    :line_total
                 )'
            );

            foreach ($items as $item) {
                $normalizedQuantity = $this->inventoryService->resolveInventoryQuantity(
                    (int) $item['product_id'],
                    (float) $item['quantity'],
                    !empty($item['product_unit_id']) ? (int) $item['product_unit_id'] : null,
                    'sale'
                );

                $item['product_unit_id'] = $normalizedQuantity['product_unit_id'];
                $item['sale_id'] = $saleId;
                $itemStmt->execute($item);

                if (!empty($item['batch_id'])) {
                    $this->inventoryService->consumeBatch((int) $item['batch_id'], $normalizedQuantity['inventory_quantity']);
                }

                $this->inventoryService->recordMovement([
                    'branch_id' => $header['branch_id'] ?? null,
                    'warehouse_id' => $header['warehouse_id'] ?? null,
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'] ?: null,
                    'batch_id' => $item['batch_id'] ?: null,
                    'movement_type' => 'sale',
                    'source_type' => 'sale',
                    'source_id' => $saleId,
                    'quantity_in' => 0,
                    'quantity_out' => $normalizedQuantity['inventory_quantity'],
                    'unit_cost' => $item['unit_price'],
                    'movement_date' => $header['sale_date'],
                    'notes' => 'فاتورة بيع ' . $header['invoice_no'],
                    'created_by' => $header['sold_by'],
                ]);
            }

            if ((float) ($header['paid_amount'] ?? 0) > 0) {
                (new CashboxService())->addEntry([
                    'branch_id' => $header['branch_id'] ?? null,
                    'entry_type' => 'receipt',
                    'reference_type' => 'sale',
                    'reference_id' => $saleId,
                    'entry_date' => $header['sale_date'],
                    'amount' => $header['paid_amount'],
                    'description' => 'قبض من فاتورة بيع ' . $header['invoice_no'] . ' - ' . $this->paymentMethodLabel($header['payment_method'] ?? 'cash'),
                    'created_by' => $header['sold_by'],
                ]);
            }

            (new AccountingService())->autoPostSale($saleId, $header);

            $pdo->commit();

            log_activity(
                'sales',
                'create',
                'إنشاء فاتورة بيع ' . $header['invoice_no'],
                'sales',
                $saleId
            );

            return $saleId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function suspend(int $saleId): void
    {
        $stmt = Database::connection()->prepare("UPDATE sales SET status = 'suspended' WHERE id = :id");
        $stmt->execute(['id' => $saleId]);
    }

    public function createReturn(array $header, array $items): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $sale = $this->getSale((int) ($header['sale_id'] ?? 0));
            if (!$sale) {
                throw new RuntimeException('فاتورة البيع الأصلية غير موجودة.');
            }

            $preparedItems = $this->validateAndPrepareReturnItems($sale, $items);

            $returnSubtotal = 0.0;
            foreach ($preparedItems as $preparedItem) {
                $returnSubtotal += (float) $preparedItem['line_total'];
            }

            $returnHeader = [
                'sale_id' => (int) $sale['id'],
                'customer_id' => $header['customer_id'] ?? $sale['customer_id'] ?? null,
                'return_no' => trim((string) ($header['return_no'] ?? '')),
                'return_date' => $header['return_date'] ?? date('Y-m-d'),
                'subtotal' => $returnSubtotal,
                'notes' => $header['notes'] ?? null,
                'created_by' => $header['created_by'] ?? Auth::id(),
            ];

            if ($returnHeader['return_no'] === '') {
                throw new RuntimeException('رقم المرتجع مطلوب.');
            }

            $this->assertSaleReturnNumberUnique($returnHeader['return_no']);

            $stmt = $pdo->prepare(
                'INSERT INTO sale_returns
                 (
                     sale_id,
                     customer_id,
                     return_no,
                     return_date,
                     subtotal,
                     notes,
                     created_by
                 )
                 VALUES
                 (
                     :sale_id,
                     :customer_id,
                     :return_no,
                     :return_date,
                     :subtotal,
                     :notes,
                     :created_by
                 )'
            );
            $stmt->execute($returnHeader);

            $returnId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO sale_return_items
                 (
                     sale_return_id,
                     sale_item_id,
                     product_id,
                     product_unit_id,
                     quantity,
                     unit_price,
                     line_total
                 )
                 VALUES
                 (
                     :sale_return_id,
                     :sale_item_id,
                     :product_id,
                     :product_unit_id,
                     :quantity,
                     :unit_price,
                     :line_total
                 )'
            );

            foreach ($preparedItems as $preparedItem) {
                $itemStmt->execute([
                    'sale_return_id' => $returnId,
                    'sale_item_id' => $preparedItem['sale_item_id'],
                    'product_id' => $preparedItem['product_id'],
                    'product_unit_id' => $preparedItem['product_unit_id'],
                    'quantity' => $preparedItem['quantity'],
                    'unit_price' => $preparedItem['unit_price'],
                    'line_total' => $preparedItem['line_total'],
                ]);

                // === تأثير على المخزون ===
                if (!empty($preparedItem['batch_id'])) {
                    $this->inventoryService->increaseBatch(
                        (int) $preparedItem['batch_id'],
                        (float) $preparedItem['inventory_quantity']
                    );
                }

                // تحديد تكلفة الوحدة من الدفعة إذا كانت متوفرة
                $unitCost = $preparedItem['unit_price']; // افتراضي: سعر البيع
                if (!empty($preparedItem['batch_id'])) {
                    $costStmt = $pdo->prepare('SELECT unit_cost FROM product_batches WHERE id = :id LIMIT 1');
                    $costStmt->execute(['id' => $preparedItem['batch_id']]);
                    $batchCost = (float) ($costStmt->fetchColumn() ?: 0);
                    if ($batchCost > 0) {
                        $unitCost = $batchCost;
                    }
                }

                $this->inventoryService->recordMovement([
                    'branch_id' => $sale['branch_id'] ?? null,
                    'warehouse_id' => $sale['warehouse_id'] ?? null,
                    'product_id' => $preparedItem['product_id'],
                    'product_unit_id' => $preparedItem['product_unit_id'] ?: null,
                    'batch_id' => $preparedItem['batch_id'] ?: null,
                    'movement_type' => 'sale_return',
                    'source_type' => 'sale_return',
                    'source_id' => $returnId,
                    'quantity_in' => $preparedItem['inventory_quantity'],
                    'quantity_out' => 0,
                    'unit_cost' => $unitCost,
                    'movement_date' => $returnHeader['return_date'],
                    'notes' => 'مرتجع بيع ' . $returnHeader['return_no'],
                    'created_by' => $returnHeader['created_by'],
                ]);
            }

            // === تأثير على الفاتورة الأصلية ===
            $this->applyReturnToSaleTotals($sale, $returnSubtotal);

            // === تأثير على الخزينة ===
            $this->createReturnCashboxEntry($sale, $returnHeader, $returnSubtotal, $returnId);

            // === تأثير محاسبي (قيود) ===
            $this->postSaleReturnAccounting($sale, $returnId, $returnHeader, $returnSubtotal);

            $pdo->commit();

            log_activity(
                'sales',
                'return',
                'تسجيل مرتجع بيع ' . $returnHeader['return_no'],
                'sale_returns',
                $returnId
            );

            return $returnId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    private function getSale(int $saleId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM sales WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $saleId]);

        return $stmt->fetch() ?: null;
    }

    private function getSaleItem(int $saleItemId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM sale_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $saleItemId]);

        return $stmt->fetch() ?: null;
    }

    private function validateAndPrepareReturnItems(array $sale, array $items): array
    {
        if (empty($items)) {
            throw new RuntimeException('لا توجد أصناف في المرتجع.');
        }

        $preparedItems = [];
        $sameRequestReservations = [];

        foreach ($items as $item) {
            $saleItemId = (int) ($item['sale_item_id'] ?? 0);
            $requestedQuantity = (float) ($item['quantity'] ?? 0);

            if ($saleItemId <= 0) {
                throw new RuntimeException('يوجد سطر مرتجع بدون sale_item_id صحيح.');
            }

            if ($requestedQuantity <= 0) {
                throw new RuntimeException('كمية المرتجع يجب أن تكون أكبر من صفر.');
            }

            $saleItem = $this->getSaleItem($saleItemId);
            if (!$saleItem) {
                throw new RuntimeException('أحد أصناف الفاتورة الأصلية غير موجود.');
            }

            if ((int) $saleItem['sale_id'] !== (int) $sale['id']) {
                throw new RuntimeException('يوجد صنف لا يتبع فاتورة البيع المحددة.');
            }

            $resolvedProductUnitId = !empty($item['product_unit_id'])
                ? (int) $item['product_unit_id']
                : (!empty($saleItem['product_unit_id']) ? (int) $saleItem['product_unit_id'] : null);

            $normalizedQuantity = $resolvedProductUnitId !== null
                ? $this->inventoryService->resolveInventoryQuantity(
                    (int) $saleItem['product_id'],
                    $requestedQuantity,
                    $resolvedProductUnitId,
                    'sale'
                )
                : [
                    'product_unit_id' => null,
                    'inventory_quantity' => $requestedQuantity,
                ];

            $alreadyReturned = $this->returnedQuantityForSaleItem($saleItemId);
            $reservedInCurrentRequest = $sameRequestReservations[$saleItemId] ?? 0.0;
            $originalQuantity = (float) $saleItem['quantity'];
            $availableToReturn = $originalQuantity - $alreadyReturned - $reservedInCurrentRequest;

            if ($availableToReturn <= 0) {
                throw new RuntimeException('هذا الصنف تم ترجيعه بالكامل سابقًا.');
            }

            if ($requestedQuantity > $availableToReturn) {
                throw new RuntimeException(
                    sprintf(
                        'الكمية المطلوبة للصنف رقم #%d أكبر من المسموح. المتاح للترجيع %.3f فقط.',
                        $saleItemId,
                        $availableToReturn
                    )
                );
            }

            $lineTotal = round($requestedQuantity * (float) $saleItem['unit_price'], 2);

            $preparedItems[] = [
                'sale_item_id' => $saleItemId,
                'product_id' => (int) $saleItem['product_id'],
                'product_unit_id' => $normalizedQuantity['product_unit_id'],
                'batch_id' => !empty($saleItem['batch_id']) ? (int) $saleItem['batch_id'] : null,
                'quantity' => $requestedQuantity,
                'inventory_quantity' => (float) $normalizedQuantity['inventory_quantity'],
                'unit_price' => (float) $saleItem['unit_price'],
                'line_total' => $lineTotal,
            ];

            $sameRequestReservations[$saleItemId] = $reservedInCurrentRequest + $requestedQuantity;
        }

        return $preparedItems;
    }

    private function returnedQuantityForSaleItem(int $saleItemId): float
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(quantity), 0)
             FROM sale_return_items
             WHERE sale_item_id = :sale_item_id'
        );
        $stmt->execute(['sale_item_id' => $saleItemId]);

        return (float) $stmt->fetchColumn();
    }

    private function calculateReturnFinancialImpact(array $sale, float $returnTotal): array
    {
        if ($returnTotal <= 0) {
            throw new InvalidArgumentException('مبلغ المرتجع يجب أن يكون أكبر من صفر.');
        }

        $originalSubtotal = (float) ($sale['subtotal'] ?? 0);
        $originalTotal    = (float) ($sale['total_amount'] ?? 0);
        $originalPaid     = (float) ($sale['paid_amount'] ?? 0);
        $originalDue      = (float) ($sale['due_amount'] ?? 0);
        $paymentMethod    = (string) ($sale['payment_method'] ?? 'cash');

        $returnTotal = round($returnTotal, 2);

        $newSubtotal = round($originalSubtotal - $returnTotal, 2);
        $newTotal    = round($originalTotal - $returnTotal, 2);
        if ($newSubtotal < 0) $newSubtotal = 0;
        if ($newTotal < 0)    $newTotal = 0;

        $remainingReturn = $returnTotal;
        $cashReduction   = 0.0;
        $dueReduction    = 0.0;

        if ($originalPaid > 0) {
            $fromPaid   = min($originalPaid, $remainingReturn);
            $cashReduction = round($fromPaid, 2);
            $remainingReturn -= $fromPaid;
        }

        if ($remainingReturn > 0) {
            $fromDue           = min($originalDue, $remainingReturn);
            $dueReduction      = round($fromDue, 2);
            $remainingReturn  -= $fromDue;
        }

        $newPaid = round($originalPaid - $cashReduction, 2);
        $newDue  = round(($originalDue + $originalPaid - $returnTotal) - $newPaid, 2);
        if ($newPaid < 0) $newPaid = 0;
        if ($newDue < 0) $newDue = 0;

        return [
            'new_subtotal'   => $newSubtotal,
            'new_total'      => $newTotal,
            'new_paid'       => $newPaid,
            'new_due'        => $newDue,
            'cash_reduction' => $cashReduction,
            'due_reduction'  => $dueReduction,
            'payment_method' => $paymentMethod,
        ];
    }

    private function applyReturnToSaleTotals(array $sale, float $returnSubtotal): void
    {
        $saleId = (int) $sale['id'];
        $impact = $this->calculateReturnFinancialImpact($sale, $returnSubtotal);

        $stmt = Database::connection()->prepare(
            'UPDATE sales
             SET subtotal = :subtotal,
                 total_amount = :total_amount,
                 paid_amount = :paid_amount,
                 due_amount = :due_amount
             WHERE id = :id'
        );

        $stmt->execute([
            'subtotal' => $impact['new_subtotal'],
            'total_amount' => $impact['new_total'],
            'paid_amount' => $impact['new_paid'],
            'due_amount' => $impact['new_due'],
            'id' => $saleId,
        ]);
    }

    private function postSaleReturnAccounting(array $sale, int $returnId, array $returnHeader, float $returnTotal): void
    {
        if ($returnTotal <= 0) {
            return;
        }

        $cashAccountId = (int) setting('default_cash_account_id', 0);
        $customerAccountId = (int) setting('default_customer_account_id', 0);
        $salesAccountId = (int) setting('default_sales_account_id', 0);
        $costOfGoodsSoldAccountId = (int) setting('default_cogs_account_id', 0);
        $inventoryAccountId = (int) setting('default_inventory_account_id', 0);

        if ($salesAccountId <= 0) {
            // إذا لم يكن هناك حساب مبيعات، لا نستطيع تسجيل القيد
            return;
        }

        $impact = $this->calculateReturnFinancialImpact($sale, $returnTotal);
        $cashPart = (float) $impact['cash_reduction'];
        $receivablePart = (float) $impact['due_reduction'];
        $paymentMethod = $impact['payment_method'];

        // حساب تكلفة البضاعة المباعة (COGS) للمرتجع
        $cogsAmount = $this->calculateReturnCOGS($returnId, (int) $sale['id']);

        $entries = [];

        // 1. عكس حساب المبيعات (مدين - لأننا نرجع مبيعات)
        $entries[] = [
            'account_id' => $salesAccountId,
            'debit' => $returnTotal,
            'credit' => 0,
            'description' => 'عكس إيراد مبيعات مرتجع: ' . $returnHeader['return_no'],
        ];

        // 2. إعادة المخزون (مدين لزيادة المخزون)
        if ($cogsAmount > 0 && $inventoryAccountId > 0) {
            $entries[] = [
                'account_id' => $inventoryAccountId,
                'debit' => $cogsAmount,
                'credit' => 0,
                'description' => 'إعادة قيد مخزون مرتجع: ' . $returnHeader['return_no'],
            ];
        }

        // 3. عكس تكلفة البضاعة المباعة (دائن - لأننا نرجع تكلفة)
        if ($cogsAmount > 0 && $costOfGoodsSoldAccountId > 0) {
            $entries[] = [
                'account_id' => $costOfGoodsSoldAccountId,
                'debit' => 0,
                'credit' => $cogsAmount,
                'description' => 'عكس تكلفة بضاعة مباعة مرتجعة: ' . $returnHeader['return_no'],
            ];
        }

        // 4. التأثير على الخزينة أو العملاء (دائن)
        if ($cashPart > 0 && $cashAccountId > 0) {
            $entries[] = [
                'account_id' => $cashAccountId,
                'debit' => 0,
                'credit' => $cashPart,
                'description' => 'تخفيض نقدي لمرتجع بيع مرتبط بـ ' . ($sale['invoice_no'] ?? ''),
            ];
        }

        // 5. التأثير على المدينين (دائن)
        if ($receivablePart > 0 && $customerAccountId > 0) {
            $entries[] = [
                'account_id' => $customerAccountId,
                'debit' => 0,
                'credit' => $receivablePart,
                'description' => 'تخفيض مدين/أجل لمرتجع بيع مرتبط بـ ' . ($sale['invoice_no'] ?? ''),
            ];
        }

        // التحقق من توازن القيد (المدين = الدائن)
        $totalDebit = array_sum(array_column($entries, 'debit'));
        $totalCredit = array_sum(array_column($entries, 'credit'));
        
        if (abs($totalDebit - $totalCredit) > 0.01) {
            // إضافة اختلاف صغير إلى حساب الخزينة أو حساب التعديلات
            $adjustmentAccount = $cashAccountId > 0 ? $cashAccountId : ($customerAccountId > 0 ? $customerAccountId : $salesAccountId);
            if ($totalDebit > $totalCredit) {
                $entries[] = [
                    'account_id' => $adjustmentAccount,
                    'debit' => 0,
                    'credit' => round($totalDebit - $totalCredit, 2),
                    'description' => 'تسوية اختلاف مرتجع: ' . $returnHeader['return_no'],
                ];
            } else {
                $entries[] = [
                    'account_id' => $adjustmentAccount,
                    'debit' => round($totalCredit - $totalDebit, 2),
                    'credit' => 0,
                    'description' => 'تسوية اختلاف مرتجع: ' . $returnHeader['return_no'],
                ];
            }
        }

        if (count($entries) >= 2) {
            (new AccountingService())->createJournal([
                'branch_id' => $sale['branch_id'] ?? null,
                'entry_no' => next_reference('journal_prefix', 'JRN'),
                'entry_date' => $returnHeader['return_date'],
                'source_type' => 'sale_return',
                'source_id' => $returnId,
                'description' => 'مرتجع بيع رقم ' . $returnHeader['return_no'] . ' - مرتبط بـ ' . ($sale['invoice_no'] ?? ''),
                'created_by' => $returnHeader['created_by'] ?? Auth::id(),
            ], $entries);
        }
    }

    private function assertSaleReturnNumberUnique(string $returnNo): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT id
             FROM sale_returns
             WHERE return_no = :return_no
             LIMIT 1'
        );
        $stmt->execute(['return_no' => $returnNo]);

        if ($stmt->fetch()) {
            throw new RuntimeException('رقم المرتجع مستخدم من قبل.');
        }
    }

    private function assertStockAvailability(array $items, ?int $warehouseId = null): void
    {
        $batchReservations = [];
        $productReservations = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $batchId = !empty($item['batch_id']) ? (int) $item['batch_id'] : null;
            $quantity = (float) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $product = $this->getProduct($productId);
            $normalizedQuantity = $this->inventoryService->resolveInventoryQuantity(
                $productId,
                $quantity,
                !empty($item['product_unit_id']) ? (int) $item['product_unit_id'] : null,
                'sale'
            );
            $inventoryQuantity = $normalizedQuantity['inventory_quantity'];
            $productName = $product['name'] ?? ('الصنف #' . $productId);

            if ($batchId) {
                $availableBatch = $this->availableStockForBatchInWarehouse($batchId, $warehouseId);
                $reservedBatch = $batchReservations[$batchId] ?? 0.0;

                if (($reservedBatch + $inventoryQuantity) > $availableBatch) {
                    throw new RuntimeException(
                        sprintf(
                            'لا يمكن بيع الصنف "%s". الكمية المتاحة في الدفعة المحددة داخل هذا المخزن هي %.3f فقط.',
                            $productName,
                            $availableBatch
                        )
                    );
                }

                $batchReservations[$batchId] = $reservedBatch + $inventoryQuantity;
                continue;
            }

            $key = ($warehouseId ?? 0) . ':' . $productId;
            $availableProduct = $this->availableStockForProductInWarehouse($productId, $warehouseId);
            $reservedProduct = $productReservations[$key] ?? 0.0;

            if (($reservedProduct + $inventoryQuantity) > $availableProduct) {
                throw new RuntimeException(
                    sprintf(
                        'لا يمكن بيع الصنف "%s". الكمية المتاحة في هذا المخزن هي %.3f فقط.',
                        $productName,
                        $availableProduct
                    )
                );
            }

            $productReservations[$key] = $reservedProduct + $inventoryQuantity;
        }
    }

    private function availableStockForProductInWarehouse(int $productId, ?int $warehouseId): float
    {
        return $this->inventoryService->availableStockForProduct($productId, $warehouseId);
    }

    private function availableStockForBatchInWarehouse(int $batchId, ?int $warehouseId): float
    {
        $sql = '
            SELECT COALESCE(quantity_in - quantity_out, 0)
            FROM product_batches
            WHERE id = :id
              AND deleted_at IS NULL
        ';

        $params = ['id' => $batchId];

        if ($warehouseId !== null) {
            $sql .= ' AND warehouse_id = :warehouse_id';
            $params['warehouse_id'] = $warehouseId;
        }

        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $value = $stmt->fetchColumn();

        return $value !== false ? (float) $value : 0.0;
    }

    private function getProduct(int $productId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, name FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $productId]);

        return $stmt->fetch() ?: null;
    }

    private function paymentMethodLabel(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'card' => 'بطاقة',
            'bank' => 'حوالة',
            'cheque' => 'صك',
            'deferred' => 'آجل',
            default => 'نقدي',
        };
    }


    private function calculateReturnCOGS(int $returnId, int $saleId): float
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT SUM(sri.quantity * COALESCE(pb.unit_cost, si.unit_price)) as total_cogs
             FROM sale_return_items sri
             JOIN sale_items si ON si.id = sri.sale_item_id
             LEFT JOIN product_batches pb ON pb.id = si.batch_id
             WHERE sri.sale_return_id = :return_id'
        );
        $stmt->execute(['return_id' => $returnId]);
        $result = $stmt->fetch();

        return (float) ($result['total_cogs'] ?? 0.0);
    }

    private function createReturnCashboxEntry(array $sale, array $returnHeader, float $returnSubtotal, int $returnId): void
    {
        $impact = $this->calculateReturnFinancialImpact($sale, $returnSubtotal);
        $cashPart = (float) $impact['cash_reduction'];

        if ($cashPart <= 0) {
            return;
        }

        $branchId = !empty($sale['branch_id']) ? (int) $sale['branch_id'] : null;

        $description = 'مرتجع مرتبط بفاتورة البيع ' . ($sale['invoice_no'] ?? '') . ' / رقم المرتجع ' . $returnHeader['return_no'];

        (new CashboxService())->addEntry([
            'branch_id' => $branchId,
            'entry_type' => 'payment',
            'reference_type' => 'sale_return',
            'reference_id' => $returnId,
            'entry_date' => $returnHeader['return_date'],
            'amount' => $cashPart,
            'description' => $description,
            'created_by' => $returnHeader['created_by'] ?? Auth::id(),
        ]);
    }
}