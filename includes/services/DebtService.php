<?php

class DebtService
{
    private function branchFilter(string $qualifiedColumn, string $prefix = 'branch'): array
    {
        if (Auth::isSuperAdmin()) {
            return ['', []];
        }

        $branchIds = Auth::branchIds();
        if (empty($branchIds)) {
            return [' AND 1 = 0', []];
        }

        $placeholders = [];
        $params = [];
        $index = 0;
        foreach ($branchIds as $branchId) {
            $key = $prefix . '_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $branchId;
            $index++;
        }

        return [' AND ' . $qualifiedColumn . ' IN (' . implode(', ', $placeholders) . ')', $params];
    }

    public function customerDebts(): array
    {
        $salesDeletedFilter = schema_has_column('sales', 'deleted_at') ? 'AND s.deleted_at IS NULL' : '';
        [$branchFilter, $params] = $this->branchFilter('s.branch_id', 'sales_branch');
        $sql = "SELECT s.id, s.customer_id, s.invoice_no, s.sale_date, s.total_amount, s.paid_amount, s.due_amount, c.full_name AS party_name
                FROM sales s
                LEFT JOIN customers c ON c.id = s.customer_id
                WHERE s.due_amount > 0 {$salesDeletedFilter}{$branchFilter}
                ORDER BY s.sale_date DESC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function supplierDebts(): array
    {
        $purchasesDeletedFilter = schema_has_column('purchases', 'deleted_at') ? 'AND p.deleted_at IS NULL' : '';
        [$branchFilter, $params] = $this->branchFilter('p.branch_id', 'purchase_branch');
        $sql = "SELECT p.id, p.supplier_id, p.invoice_no, p.purchase_date, p.total_amount, p.paid_amount, p.due_amount, s.company_name AS party_name
                FROM purchases p
                LEFT JOIN suppliers s ON s.id = p.supplier_id
                WHERE p.due_amount > 0 {$purchasesDeletedFilter}{$branchFilter}
                ORDER BY p.purchase_date DESC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function marketerDebts(): array
    {
        if (!schema_has_column('sales', 'marketer_id')) {
            return [];
        }

        $salesDeletedFilter = schema_has_column('sales', 'deleted_at') ? 'AND s.deleted_at IS NULL' : '';
        [$branchFilter, $params] = $this->branchFilter('s.branch_id', 'marketer_branch');
        $sql = "SELECT s.id, s.marketer_id, s.invoice_no, s.sale_date, s.total_amount, s.paid_amount, s.due_amount, m.full_name AS party_name
                FROM sales s
                LEFT JOIN marketers m ON m.id = s.marketer_id
                WHERE s.due_amount > 0
                  AND s.marketer_id IS NOT NULL
                  {$salesDeletedFilter}{$branchFilter}
                ORDER BY s.sale_date DESC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function collect(array $payload): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $resolvedPartyType = $payload['party_type'];
            $resolvedPartyId = (int) $payload['party_id'];
            $marketerId = null;

            if (($payload['party_type'] ?? '') === 'marketer') {
                $marketerId = (int) $payload['party_id'];

                if (($payload['source_type'] ?? '') !== 'sale' || !schema_has_column('sales', 'customer_id')) {
                    throw new RuntimeException('تحصيل ديون المسوقين يتطلب فاتورة بيع مرتبطة بعميل.');
                }

                $saleId = (int) ($payload['source_id'] ?? 0);
                if ($saleId <= 0) {
                    throw new RuntimeException('الفاتورة المرتبطة بالتحصيل غير صالحة.');
                }

                [$branchFilter, $branchParams] = $this->branchFilter('branch_id', 'collect_branch');
                $saleLookup = $pdo->prepare(
                    'SELECT customer_id, marketer_id
                     FROM sales
                     WHERE id = :sale_id' . $branchFilter . '
                     LIMIT 1'
                );
                $saleLookup->execute(array_merge(['sale_id' => $saleId], $branchParams));
                $saleRow = $saleLookup->fetch() ?: null;

                $customerId = (int) ($saleRow['customer_id'] ?? 0);
                if ($customerId <= 0) {
                    throw new RuntimeException('لا يمكن تحصيل دين المسوق بدون عميل مرتبط بالفاتورة.');
                }

                if ((int) ($saleRow['marketer_id'] ?? 0) > 0) {
                    $marketerId = (int) $saleRow['marketer_id'];
                }

                $resolvedPartyType = 'customer';
                $resolvedPartyId = $customerId;
            }

            $columns = ['party_type', 'party_id', 'source_type', 'source_id', 'payment_date', 'amount', 'notes', 'created_by'];
            $placeholders = [':party_type', ':party_id', ':source_type', ':source_id', ':payment_date', ':amount', ':notes', ':created_by'];
            $params = [
                'party_type' => $resolvedPartyType,
                'party_id' => $resolvedPartyId,
                'source_type' => $payload['source_type'],
                'source_id' => $payload['source_id'],
                'payment_date' => $payload['payment_date'],
                'amount' => $payload['amount'],
                'notes' => $payload['notes'] ?? '',
                'created_by' => $payload['created_by'],
            ];

            if (schema_has_column('debt_collections', 'marketer_id')) {
                if ($marketerId === null && ($payload['source_type'] ?? '') === 'sale' && schema_has_column('sales', 'marketer_id')) {
                    $saleId = (int) ($payload['source_id'] ?? 0);
                    if ($saleId > 0) {
                        [$branchFilter, $branchParams] = $this->branchFilter('branch_id', 'marketer_lookup');
                        $marketerStmt = $pdo->prepare(
                            'SELECT marketer_id
                             FROM sales
                             WHERE id = :sale_id' . $branchFilter . '
                             LIMIT 1'
                        );
                        $marketerStmt->execute(array_merge(['sale_id' => $saleId], $branchParams));
                        $marketerId = (int) ($marketerStmt->fetchColumn() ?: 0);
                    }
                }

                if ($marketerId > 0) {
                    $columns[] = 'marketer_id';
                    $placeholders[] = ':marketer_id';
                    $params['marketer_id'] = $marketerId;
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO debt_collections (' . implode(', ', $columns) . ')
                 VALUES (' . implode(', ', $placeholders) . ')'
            );
            $stmt->execute($params);
            $collectionId = (int) $pdo->lastInsertId();

            if ($payload['source_type'] === 'sale') {
                $update = $pdo->prepare('UPDATE sales SET paid_amount = paid_amount + :amount, due_amount = GREATEST(0, due_amount - :amount) WHERE id = :id');
                $update->execute(['amount' => $payload['amount'], 'id' => $payload['source_id']]);
                (new CashboxService())->addEntry([
                    'entry_type' => 'receipt',
                    'reference_type' => 'debt_collection',
                    'reference_id' => $collectionId,
                    'entry_date' => $payload['payment_date'],
                    'amount' => $payload['amount'],
                    'description' => 'تحصيل دين عميل',
                    'created_by' => $payload['created_by'],
                ]);
            } else {
                $update = $pdo->prepare('UPDATE purchases SET paid_amount = paid_amount + :amount, due_amount = GREATEST(0, due_amount - :amount) WHERE id = :id');
                $update->execute(['amount' => $payload['amount'], 'id' => $payload['source_id']]);
                (new CashboxService())->addEntry([
                    'entry_type' => 'payment',
                    'reference_type' => 'debt_settlement',
                    'reference_id' => $collectionId,
                    'entry_date' => $payload['payment_date'],
                    'amount' => $payload['amount'],
                    'description' => 'سداد دين مورد',
                    'created_by' => $payload['created_by'],
                ]);
            }

            $pdo->commit();
            log_activity('debts', 'collect', 'تسجيل حركة دين/تحصيل', 'debt_collections', $collectionId);
            return $collectionId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
