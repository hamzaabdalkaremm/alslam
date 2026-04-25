<?php

class InventoryService
{
    public function availableStockForProduct(int $productId, ?int $warehouseId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(quantity_in - quantity_out), 0)
                FROM stock_movements
                WHERE product_id = :product_id';
        $params = ['product_id' => $productId];

        if ($warehouseId !== null) {
            $sql .= ' AND warehouse_id = :warehouse_id';
            $params['warehouse_id'] = $warehouseId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (float) $stmt->fetchColumn();
    }

    public function availableStockForBatch(int $batchId): float
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(quantity_in - quantity_out, 0)
             FROM product_batches
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $batchId]);

        return (float) $stmt->fetchColumn();
    }

    public function stockBalance(?int $productId = null, ?int $limit = null, ?int $offset = null, ?string $search = null, ?int $warehouseId = null): array
    {
        $params = [];
        $movementFilters = [];

        if ($warehouseId !== null) {
            $movementFilters[] = 'warehouse_id = :warehouse_id';
            $params['warehouse_id'] = $warehouseId;
        }

        // Restrict movements to accessible warehouses/branches when viewing all warehouses
        if ($warehouseId === null && !Auth::isSuperAdmin() && schema_has_column('stock_movements', 'branch_id')) {
            $branchIds = Auth::branchIds();
            if (!empty($branchIds)) {
                $branchPlaceholders = [];
                foreach ($branchIds as $index => $branchId) {
                    $key = 'branch_' . $index;
                    $branchPlaceholders[] = ':' . $key;
                    $params[$key] = (int) $branchId;
                }
                $movementFilters[] = 'branch_id IN (' . implode(', ', $branchPlaceholders) . ')';
            } else {
                // No accessible branches, ensure no results
                $movementFilters[] = '1 = 0';
            }
        }

        $movementWhere = $movementFilters ? 'WHERE ' . implode(' AND ', $movementFilters) : '';

        $sql = "SELECT p.id,
                       p.name,
                       p.code,
                       p.barcode,
                       COALESCE(sm.stock_balance, 0) AS stock_balance,
                       p.min_stock_alert
                FROM products p
                LEFT JOIN (
                    SELECT product_id, COALESCE(SUM(quantity_in - quantity_out), 0) AS stock_balance
                    FROM stock_movements
                    {$movementWhere}
                    GROUP BY product_id
                ) sm ON sm.product_id = p.id
                WHERE p.deleted_at IS NULL";

        if (!Auth::isSuperAdmin() && schema_has_column('products', 'branch_id')) {
            $branchIds = Auth::branchIds();
            if ($branchIds) {
                $branchPlaceholders = [];
                foreach ($branchIds as $index => $branchId) {
                    $key = 'branch_' . $index;
                    $branchPlaceholders[] = ':' . $key;
                    $params[$key] = (int) $branchId;
                }
                $sql .= ' AND (p.branch_id IS NULL OR p.branch_id = 0 OR p.branch_id IN (' . implode(', ', $branchPlaceholders) . '))';
            } else {
                $sql .= ' AND 1 = 0';
            }
        }

        if ($productId) {
            $sql .= ' AND p.id = :product_id';
            $params['product_id'] = $productId;
        }

        if ($search) {
            $sql .= ' AND (p.name LIKE :search OR p.code LIKE :search OR p.barcode LIKE :search)';
            $params['search'] = "%{$search}%";
        }

        $sql .= ' ORDER BY p.name ASC';

        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
            $params['limit'] = $limit;
        }

        if ($offset !== null) {
            if ($limit === null) {
                $sql .= ' LIMIT 18446744073709551615';
            }
            $sql .= ' OFFSET :offset';
            $params['offset'] = $offset;
        }

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $paramKey = is_int($key) ? ($key + 1) : (':' . $key);
            $paramType = (is_int($value) || ctype_digit((string) $value)) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($paramKey, $value, $paramType);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function resolveInventoryQuantity(int $productId, float $quantity, ?int $productUnitId = null, string $context = 'inventory'): array
    {
        $unit = $this->getProductUnit($productId, $productUnitId, $context);
        $unitsPerBase = (float) ($unit['units_per_base'] ?? 1);

        if ($unitsPerBase <= 0) {
            $unitsPerBase = 1;
        }

        return [
            'product_unit_id' => isset($unit['id']) ? (int) $unit['id'] : null,
            'quantity' => $quantity,
            'inventory_quantity' => round($quantity * $unitsPerBase, 3),
            'units_per_base' => $unitsPerBase,
        ];
    }

    public function stockCard(int $productId, ?int $warehouseId = null): array
    {
        $sql = "SELECT sm.*, pb.batch_number
                FROM stock_movements sm
                LEFT JOIN product_batches pb ON pb.id = sm.batch_id
                WHERE sm.product_id = :product_id";
        $params = ['product_id' => $productId];

        if ($warehouseId !== null) {
            $sql .= ' AND sm.warehouse_id = :warehouse_id';
            $params['warehouse_id'] = $warehouseId;
        }

        $sql .= ' ORDER BY sm.movement_date DESC, sm.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function createAdjustment(array $header, array $items): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $columns = ['adjustment_no', 'adjustment_date', 'reason', 'notes', 'created_by'];
            $placeholders = [':adjustment_no', ':adjustment_date', ':reason', ':notes', ':created_by'];
            $headerParams = [
                'adjustment_no' => $header['adjustment_no'],
                'adjustment_date' => $header['adjustment_date'],
                'reason' => $header['reason'],
                'notes' => $header['notes'] ?? '',
                'created_by' => $header['created_by'],
            ];

            if (schema_has_column('inventory_adjustments', 'branch_id')) {
                $columns[] = 'branch_id';
                $placeholders[] = ':branch_id';
                $headerParams['branch_id'] = $header['branch_id'] ?? null;
            }

            if (schema_has_column('inventory_adjustments', 'warehouse_id')) {
                $columns[] = 'warehouse_id';
                $placeholders[] = ':warehouse_id';
                $headerParams['warehouse_id'] = $header['warehouse_id'] ?? null;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO inventory_adjustments (' . implode(', ', $columns) . ')
                 VALUES (' . implode(', ', $placeholders) . ')'
            );
            $stmt->execute($headerParams);
            $adjustmentId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO inventory_adjustment_items
                 (inventory_adjustment_id, product_id, product_unit_id, batch_id, system_quantity, actual_quantity, difference_quantity, unit_cost)
                 VALUES
                 (:inventory_adjustment_id, :product_id, :product_unit_id, :batch_id, :system_quantity, :actual_quantity, :difference_quantity, :unit_cost)'
            );

            foreach ($items as $item) {
                $itemStmt->execute([
                    'inventory_adjustment_id' => $adjustmentId,
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'] ?: null,
                    'batch_id' => $item['batch_id'] ?: null,
                    'system_quantity' => $item['system_quantity'],
                    'actual_quantity' => $item['actual_quantity'],
                    'difference_quantity' => $item['difference_quantity'],
                    'unit_cost' => $item['unit_cost'],
                ]);

                $this->recordMovement([
                    'branch_id' => $header['branch_id'] ?? null,
                    'warehouse_id' => $item['warehouse_id'] ?? $header['warehouse_id'] ?? null,
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'] ?: null,
                    'batch_id' => $item['batch_id'] ?: null,
                    'movement_type' => 'adjustment',
                    'source_type' => 'inventory_adjustment',
                    'source_id' => $adjustmentId,
                    'quantity_in' => max(0, (float) $item['difference_quantity']),
                    'quantity_out' => abs(min(0, (float) $item['difference_quantity'])),
                    'unit_cost' => $item['unit_cost'],
                    'movement_date' => $header['adjustment_date'],
                    'notes' => $header['reason'],
                    'created_by' => $header['created_by'],
                ]);
            }

            $pdo->commit();
            log_activity('inventory', 'adjust', 'Inventory adjustment ' . $header['adjustment_no'], 'inventory_adjustments', $adjustmentId);

            return $adjustmentId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function createDamage(array $payload): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $warehouseId = (int) ($payload['warehouse_id'] ?? 0);
            $productId = (int) ($payload['product_id'] ?? 0);
            $quantity = (float) ($payload['quantity'] ?? 0);

            if ($warehouseId <= 0) {
                throw new RuntimeException('يجب اختيار المخزن.');
            }

            if ($productId <= 0) {
                throw new RuntimeException('يجب اختيار الصنف.');
            }

            if ($quantity <= 0) {
                throw new RuntimeException('الكمية يجب أن تكون أكبر من صفر.');
            }

            $warehouse = $this->getWarehouse($warehouseId);
            if (!$warehouse) {
                throw new RuntimeException('المخزن غير موجود.');
            }

            $product = $this->getProduct($productId);
            if (!$product) {
                throw new RuntimeException('الصنف غير موجود.');
            }

            $availableStock = $this->availableStockForProduct($productId, $warehouseId);
            if ($availableStock < $quantity) {
                throw new RuntimeException('الكمية التالفة أكبر من الرصيد المتاح في المخزن. الرصيد المتاح: ' . $availableStock);
            }

            $movementId = $this->recordMovement([
                'branch_id' => (int) ($warehouse['branch_id'] ?? 0),
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'product_unit_id' => null,
                'batch_id' => null,
                'movement_type' => 'damage',
                'source_type' => 'damaged_stock',
                'source_id' => 0,
                'quantity_in' => 0,
                'quantity_out' => $quantity,
                'unit_cost' => (float) ($payload['unit_cost'] ?? 0),
                'movement_date' => $payload['damage_date'],
                'notes' => trim((string) ($payload['notes'] ?? 'تسجيل تالف')),
                'created_by' => $payload['created_by'] ?? Auth::id(),
            ]);

            $pdo->commit();
            log_activity('inventory', 'damage', 'تسجيل تالف للصنف ' . ($product['name'] ?? ''), 'stock_movements', $movementId);

            return $movementId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function recordMovement(array $movement): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO stock_movements
             (branch_id, warehouse_id, product_id, product_unit_id, batch_id, movement_type, source_type, source_id, quantity_in, quantity_out, unit_cost, movement_date, notes, created_by)
             VALUES
             (:branch_id, :warehouse_id, :product_id, :product_unit_id, :batch_id, :movement_type, :source_type, :source_id, :quantity_in, :quantity_out, :unit_cost, :movement_date, :notes, :created_by)'
        );
        $stmt->execute([
            'branch_id' => $movement['branch_id'] ?? null,
            'warehouse_id' => $movement['warehouse_id'] ?? null,
            'product_id' => $movement['product_id'],
            'product_unit_id' => $movement['product_unit_id'] ?? null,
            'batch_id' => $movement['batch_id'] ?? null,
            'movement_type' => $movement['movement_type'],
            'source_type' => $movement['source_type'],
            'source_id' => $movement['source_id'] ?? null,
            'quantity_in' => $movement['quantity_in'] ?? 0,
            'quantity_out' => $movement['quantity_out'] ?? 0,
            'unit_cost' => $movement['unit_cost'] ?? 0,
            'movement_date' => $movement['movement_date'],
            'notes' => $movement['notes'] ?? null,
            'created_by' => $movement['created_by'] ?? Auth::id(),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function consumeBatch(int $batchId, float $quantity): void
    {
        $stmt = Database::connection()->prepare('UPDATE product_batches SET quantity_out = quantity_out + :qty WHERE id = :id');
        $stmt->execute(['qty' => $quantity, 'id' => $batchId]);
    }

    public function increaseBatch(int $batchId, float $quantity): void
    {
        $stmt = Database::connection()->prepare('UPDATE product_batches SET quantity_out = GREATEST(0, quantity_out - :qty) WHERE id = :id');
        $stmt->execute(['qty' => $quantity, 'id' => $batchId]);
    }

    public function createTransfer(array $header, array $items): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $sourceWarehouseId = (int) $header['source_warehouse_id'];
            $destWarehouseId = (int) $header['destination_warehouse_id'];

            $sourceWarehouse = $this->getWarehouse($sourceWarehouseId);
            $destWarehouse = $this->getWarehouse($destWarehouseId);

            if (!$sourceWarehouse || !$destWarehouse) {
                throw new RuntimeException('Invalid warehouse selection.');
            }

            $productReservations = [];
            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $quantity = (float) ($item['quantity'] ?? 0);

                if ($productId <= 0 || $quantity <= 0) {
                    continue;
                }

                $productReservations[$productId] = ($productReservations[$productId] ?? 0) + $quantity;
            }

            foreach ($productReservations as $productId => $requestedQuantity) {
                $availableStock = $this->availableStockForProduct((int) $productId, $sourceWarehouseId);

                if ($requestedQuantity > $availableStock) {
                    $product = $this->getProduct((int) $productId);
                    $productName = $product['name'] ?? ('Product #' . $productId);

                    throw new RuntimeException(
                        sprintf(
                            'Cannot transfer product "%s". Available stock in the source warehouse is %.3f only.',
                            $productName,
                            $availableStock
                        )
                    );
                }
            }

            $branchId = (int) ($sourceWarehouse['branch_id'] ?? $destWarehouse['branch_id'] ?? null);
            $transferId = 0;
            $tableExists = false;

            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO inventory_transfers (transfer_no, transfer_date, source_warehouse_id, destination_warehouse_id, branch_id, notes, created_by, status)
                     VALUES (:transfer_no, :transfer_date, :source_warehouse_id, :destination_warehouse_id, :branch_id, :notes, :created_by, :status)'
                );
                $stmt->execute([
                    'transfer_no' => $header['transfer_no'],
                    'transfer_date' => $header['transfer_date'],
                    'source_warehouse_id' => $sourceWarehouseId,
                    'destination_warehouse_id' => $destWarehouseId,
                    'branch_id' => $branchId,
                    'notes' => $header['notes'] ?? '',
                    'created_by' => $header['created_by'],
                    'status' => 'completed',
                ]);
                $transferId = (int) $pdo->lastInsertId();

                $itemStmt = $pdo->prepare(
                    'INSERT INTO inventory_transfer_items (transfer_id, product_id, product_unit_id, quantity)
                     VALUES (:transfer_id, :product_id, :product_unit_id, :quantity)'
                );

                foreach ($items as $item) {
                    $itemStmt->execute([
                        'transfer_id' => $transferId,
                        'product_id' => $item['product_id'],
                        'product_unit_id' => $item['product_unit_id'] ?? null,
                        'quantity' => $item['quantity'],
                    ]);
                }

                $tableExists = true;
            } catch (Throwable $tableError) {
                error_log('Transfer table error (can be ignored if tables do not exist yet): ' . $tableError->getMessage());
                $transferId = 0;
            }

            foreach ($items as $item) {
                $this->recordMovement([
                    'branch_id' => $branchId,
                    'warehouse_id' => $sourceWarehouseId,
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'] ?? null,
                    'movement_type' => 'transfer_out',
                    'source_type' => 'inventory_transfer',
                    'source_id' => $transferId ?: 0,
                    'quantity_in' => 0,
                    'quantity_out' => $item['quantity'],
                    'movement_date' => $header['transfer_date'],
                    'notes' => 'Transfer to warehouse ' . ($destWarehouse['name'] ?? 'N/A'),
                    'created_by' => $header['created_by'],
                ]);

                $this->recordMovement([
                    'branch_id' => $branchId,
                    'warehouse_id' => $destWarehouseId,
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'] ?? null,
                    'movement_type' => 'transfer_in',
                    'source_type' => 'inventory_transfer',
                    'source_id' => $transferId ?: 0,
                    'quantity_in' => $item['quantity'],
                    'quantity_out' => 0,
                    'movement_date' => $header['transfer_date'],
                    'notes' => 'Transfer from warehouse ' . ($sourceWarehouse['name'] ?? 'N/A'),
                    'created_by' => $header['created_by'],
                ]);
            }

            $pdo->commit();

            if ($tableExists) {
                log_activity('inventory', 'transfer', 'Stock transfer ' . $header['transfer_no'], 'inventory_transfers', $transferId);
            }

            return $transferId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Transfer Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getWarehouse(int $warehouseId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, name, branch_id FROM warehouses WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $warehouseId]);

        return $stmt->fetch() ?: null;
    }

    private function getProduct(int $productId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, name FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $productId]);

        return $stmt->fetch() ?: null;
    }

    private function getProductUnit(int $productId, ?int $productUnitId = null, string $context = 'inventory'): array
    {
        $params = ['product_id' => $productId];
        $sql = 'SELECT id, units_per_base
                FROM product_units
                WHERE product_id = :product_id';

        if ($productUnitId !== null) {
            $sql .= ' AND id = :product_unit_id';
            $params['product_unit_id'] = $productUnitId;
        } else {
            $sql .= ' ORDER BY ';
            $sql .= match ($context) {
                'sale' => 'is_default_sale_unit DESC, is_default_purchase_unit DESC, id ASC',
                'purchase' => 'is_default_purchase_unit DESC, is_default_sale_unit DESC, id ASC',
                default => 'CASE WHEN units_per_base = 1 THEN 1 ELSE 0 END DESC, is_default_sale_unit DESC, is_default_purchase_unit DESC, id ASC',
            };
            $sql .= ' LIMIT 1';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $unit = $stmt->fetch();

        if ($unit) {
            return $unit;
        }

        return [
            'id' => $productUnitId,
            'units_per_base' => 1,
        ];
    }
}
