<?php

class PurchaseService
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
            $stmt = $pdo->prepare(
                'INSERT INTO purchases
                 (branch_id, warehouse_id, invoice_no, supplier_id, purchased_by, purchase_date, status, approval_status, subtotal, discount_value, tax_value, import_costs, total_amount, paid_amount, due_amount, notes)
                 VALUES
                 (:branch_id, :warehouse_id, :invoice_no, :supplier_id, :purchased_by, :purchase_date, :status, :approval_status, :subtotal, :discount_value, :tax_value, :import_costs, :total_amount, :paid_amount, :due_amount, :notes)'
            );
            $stmt->execute([
                'branch_id' => $header['branch_id'] ?? null,
                'warehouse_id' => $header['warehouse_id'] ?? null,
                'invoice_no' => $header['invoice_no'],
                'supplier_id' => $header['supplier_id'] ?? null,
                'purchased_by' => $header['purchased_by'] ?? null,
                'purchase_date' => $header['purchase_date'],
                'status' => $header['status'],
                'approval_status' => $header['approval_status'] ?? 'approved',
                'subtotal' => $header['subtotal'],
                'discount_value' => $header['discount_value'],
                'tax_value' => $header['tax_value'],
                'import_costs' => $header['import_costs'] ?? 0,
                'total_amount' => $header['total_amount'],
                'paid_amount' => $header['paid_amount'],
                'due_amount' => $header['due_amount'],
                'notes' => $header['notes'],
            ]);
            $purchaseId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO purchase_items
                 (purchase_id, product_id, product_unit_id, batch_number, production_date, expiry_date, quantity, unit_cost, line_discount, line_total)
                 VALUES
                 (:purchase_id, :product_id, :product_unit_id, :batch_number, :production_date, :expiry_date, :quantity, :unit_cost, :line_discount, :line_total)'
            );
            $batchStmt = $pdo->prepare(
                'INSERT INTO product_batches
                 (branch_id, warehouse_id, product_id, product_unit_id, batch_number, production_date, expiry_date, quantity_in, quantity_out, unit_cost, source_type, source_id)
                 VALUES
                 (:branch_id, :warehouse_id, :product_id, :product_unit_id, :batch_number, :production_date, :expiry_date, :quantity_in, 0, :unit_cost, :source_type, :source_id)'
            );

            foreach ($items as $item) {
                $normalizedQuantity = $this->inventoryService->resolveInventoryQuantity(
                    (int) $item['product_id'],
                    (float) $item['quantity'],
                    !empty($item['product_unit_id']) ? (int) $item['product_unit_id'] : null,
                    'purchase'
                );

                $item['product_unit_id'] = $normalizedQuantity['product_unit_id'];
                $item['purchase_id'] = $purchaseId;
                $itemStmt->execute($item);

                $batchStmt->execute([
                    'branch_id' => $header['branch_id'] ?? null,
                    'warehouse_id' => $header['warehouse_id'] ?? null,
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'] ?? null,
                    'batch_number' => $item['batch_number'],
                    'production_date' => $item['production_date'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'quantity_in' => $normalizedQuantity['inventory_quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'source_type' => 'purchase',
                    'source_id' => $purchaseId,
                ]);

                $batchId = (int) $pdo->lastInsertId();

                $this->inventoryService->recordMovement([
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'] ?? null,
                    'batch_id' => $batchId,
                    'branch_id' => $header['branch_id'] ?? null,
                    'warehouse_id' => $header['warehouse_id'] ?? null,
                    'movement_type' => 'purchase',
                    'source_type' => 'purchase',
                    'source_id' => $purchaseId,
                    'quantity_in' => $normalizedQuantity['inventory_quantity'],
                    'quantity_out' => 0,
                    'unit_cost' => $item['unit_cost'],
                    'movement_date' => $header['purchase_date'],
                    'notes' => 'فاتورة شراء ' . $header['invoice_no'],
                    'created_by' => $header['purchased_by'],
                ]);
            }

            if ((float) $header['paid_amount'] > 0) {
                (new CashboxService())->addEntry([
                    'branch_id' => $header['branch_id'] ?? null,
                    'entry_type' => 'payment',
                    'reference_type' => 'purchase',
                    'reference_id' => $purchaseId,
                    'entry_date' => $header['purchase_date'],
                    'amount' => $header['paid_amount'],
                    'description' => 'سداد فاتورة شراء ' . $header['invoice_no'],
                    'created_by' => $header['purchased_by'],
                ]);
            }

            (new AccountingService())->autoPostPurchase($purchaseId, $header);

            $pdo->commit();
            log_activity('purchases', 'create', 'إنشاء فاتورة شراء ' . $header['invoice_no'], 'purchases', $purchaseId);
            return $purchaseId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function createReturn(array $header, array $items): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO purchase_returns (purchase_id, supplier_id, return_no, return_date, subtotal, notes, created_by)
                 VALUES (:purchase_id, :supplier_id, :return_no, :return_date, :subtotal, :notes, :created_by)'
            );
            $stmt->execute($header);
            $returnId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO purchase_return_items
                 (purchase_return_id, purchase_item_id, product_id, product_unit_id, quantity, unit_cost, line_total)
                 VALUES
                 (:purchase_return_id, :purchase_item_id, :product_id, :product_unit_id, :quantity, :unit_cost, :line_total)'
            );

            foreach ($items as $item) {
                $purchaseItem = $this->getPurchaseItem((int) $item['purchase_item_id']);
                $resolvedProductUnitId = !empty($item['product_unit_id'])
                    ? (int) $item['product_unit_id']
                    : (!empty($purchaseItem['product_unit_id']) ? (int) $purchaseItem['product_unit_id'] : null);
                $normalizedQuantity = $resolvedProductUnitId !== null
                    ? $this->inventoryService->resolveInventoryQuantity(
                        (int) $item['product_id'],
                        (float) $item['quantity'],
                        $resolvedProductUnitId,
                        'purchase'
                    )
                    : [
                        'product_unit_id' => null,
                        'inventory_quantity' => (float) $item['quantity'],
                    ];

                $item['product_unit_id'] = $normalizedQuantity['product_unit_id'];
                $item['purchase_return_id'] = $returnId;
                $itemStmt->execute($item);
                $batchId = $this->findBatchByPurchase((int) $header['purchase_id'], (int) $item['product_id']);

                if ($batchId) {
                    $stmt = $pdo->prepare('UPDATE product_batches SET quantity_out = quantity_out + :qty WHERE id = :id');
                    $stmt->execute(['qty' => $normalizedQuantity['inventory_quantity'], 'id' => $batchId]);
                }

                $this->inventoryService->recordMovement([
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'] ?? null,
                    'batch_id' => $batchId,
                    'movement_type' => 'purchase_return',
                    'source_type' => 'purchase_return',
                    'source_id' => $returnId,
                    'quantity_in' => 0,
                    'quantity_out' => $normalizedQuantity['inventory_quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'movement_date' => $header['return_date'],
                    'notes' => 'مرتجع شراء ' . $header['return_no'],
                    'created_by' => $header['created_by'],
                ]);

                if ($purchaseItem) {
                    $deleteStmt = $pdo->prepare('DELETE FROM purchase_items WHERE id = :id');
                    $deleteStmt->execute(['id' => $item['purchase_item_id']]);
                }
            }

            $purchaseTotal = (float) ($header['subtotal'] ?? 0);
            if ($purchaseTotal > 0) {
                $inventoryAccountId = (int) setting('default_inventory_account_id', 0);
                $supplierAccountId = (int) setting('default_supplier_account_id', 0);
                $purchaseId = (int) $header['purchase_id'];
                
                $branchId = null;
                $purchaseStmt = $pdo->prepare('SELECT branch_id FROM purchases WHERE id = ? LIMIT 1');
                $purchaseStmt->execute([$purchaseId]);
                $purchaseRow = $purchaseStmt->fetch();
                if ($purchaseRow) {
                    $branchId = (int) ($purchaseRow['branch_id'] ?? 0) ?: null;
                }

                $entries = [];
                if ($inventoryAccountId > 0) {
                    $entries[] = ['account_id' => $inventoryAccountId, 'debit' => 0, 'credit' => $purchaseTotal];
                }
                if ($supplierAccountId > 0) {
                    $entries[] = ['account_id' => $supplierAccountId, 'debit' => $purchaseTotal, 'credit' => 0];
                }

                if (!empty($entries)) {
                    $accountingService = new AccountingService();
                    $accountingService->createJournal([
                        'branch_id' => $branchId,
                        'reference_type' => 'purchase_return',
                        'reference_id' => $returnId,
                        'entry_date' => $header['return_date'],
                        'description' => 'إلغاء شراء ' . $header['return_no'],
                        'created_by' => $header['created_by'],
                    ], $entries);
                }
            }

            $pdo->commit();
            log_activity('purchases', 'return', 'تسجيل مرتجع شراء ' . $header['return_no'], 'purchase_returns', $returnId);
            return $returnId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function findBatchByPurchase(int $purchaseId, int $productId): ?int
    {
        $stmt = Database::connection()->prepare(
            "SELECT id FROM product_batches
             WHERE source_type = 'purchase' AND source_id = :purchase_id AND product_id = :product_id
             ORDER BY id ASC LIMIT 1"
        );
        $stmt->execute(['purchase_id' => $purchaseId, 'product_id' => $productId]);
        $batchId = $stmt->fetchColumn();
        return $batchId ? (int) $batchId : null;
    }

    private function getPurchaseItem(int $purchaseItemId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM purchase_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $purchaseItemId]);
        return $stmt->fetch() ?: null;
    }
}
