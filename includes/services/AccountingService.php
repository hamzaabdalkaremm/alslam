<?php

class AccountingService
{
    public function createJournal(array $header, array $lines): int
    {
        $debitTotal = 0.0;
        $creditTotal = 0.0;
        $preparedLines = [];

        foreach ($lines as $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($accountId <= 0 || ($debit <= 0 && $credit <= 0)) {
                continue;
            }

            $preparedLines[] = [
                'account_id' => $accountId,
                'branch_id' => !empty($line['branch_id']) ? (int) $line['branch_id'] : ($header['branch_id'] ?? null),
                'description' => trim((string) ($line['description'] ?? '')),
                'debit' => $debit,
                'credit' => $credit,
            ];

            $debitTotal += $debit;
            $creditTotal += $credit;
        }

        if (!$preparedLines) {
            throw new RuntimeException('لا يمكن حفظ قيد بدون سطور محاسبية.');
        }

        if (round($debitTotal, 2) !== round($creditTotal, 2)) {
            throw new RuntimeException('القيد غير متوازن. مجموع المدين يجب أن يساوي مجموع الدائن.');
        }

        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO journal_entries
                 (branch_id, entry_no, entry_date, source_type, source_id, description, status, created_by, approved_by, approved_at)
                 VALUES
                 (:branch_id, :entry_no, :entry_date, :source_type, :source_id, :description, :status, :created_by, :approved_by, :approved_at)'
            );
            $stmt->execute([
                'branch_id' => $header['branch_id'] ?? null,
                'entry_no' => $header['entry_no'],
                'entry_date' => $header['entry_date'],
                'source_type' => $header['source_type'] ?? null,
                'source_id' => $header['source_id'] ?? null,
                'description' => $header['description'],
                'status' => $header['status'] ?? 'posted',
                'created_by' => $header['created_by'] ?? Auth::id(),
                'approved_by' => $header['approved_by'] ?? Auth::id(),
                'approved_at' => $header['approved_at'] ?? now_datetime(),
            ]);

            $journalId = (int) $pdo->lastInsertId();
            $lineStmt = $pdo->prepare(
                'INSERT INTO journal_entry_lines
                 (journal_entry_id, account_id, branch_id, description, debit, credit)
                 VALUES
                 (:journal_entry_id, :account_id, :branch_id, :description, :debit, :credit)'
            );

            foreach ($preparedLines as $line) {
                $lineStmt->execute([
                    'journal_entry_id' => $journalId,
                    'account_id' => $line['account_id'],
                    'branch_id' => $line['branch_id'],
                    'description' => $line['description'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $journalId;
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function autoPostSale(int $saleId, array $header): void
    {
        if ((string) setting('enable_auto_journal', '1') !== '1') {
            return;
        }

        $cashAccount = (int) setting('default_cash_account_id', 0);
        $customerAccount = (int) setting('default_customer_account_id', 0);
        $salesAccount = (int) setting('default_sales_account_id', 0);

        if ($salesAccount <= 0 || ($cashAccount <= 0 && $customerAccount <= 0)) {
            return;
        }

        $paid = (float) ($header['paid_amount'] ?? 0);
        $due = (float) ($header['due_amount'] ?? 0);
        $total = (float) ($header['total_amount'] ?? 0);

        $lines = [];
        if ($paid > 0 && $cashAccount > 0) {
            $lines[] = ['account_id' => $cashAccount, 'debit' => $paid, 'credit' => 0];
        }
        if ($due > 0 && $customerAccount > 0) {
            $lines[] = ['account_id' => $customerAccount, 'debit' => $due, 'credit' => 0];
        }
        $lines[] = ['account_id' => $salesAccount, 'debit' => 0, 'credit' => $total];

        $this->createJournal([
            'branch_id' => $header['branch_id'] ?? null,
            'entry_no' => next_reference('journal_prefix', 'JRN'),
            'entry_date' => $header['sale_date'] ?? now_datetime(),
            'source_type' => 'sale',
            'source_id' => $saleId,
            'description' => 'قيد تلقائي لفاتورة البيع ' . ($header['invoice_no'] ?? ''),
            'created_by' => $header['sold_by'] ?? Auth::id(),
        ], $lines);
    }

    public function autoPostPurchase(int $purchaseId, array $header): void
    {
        if ((string) setting('enable_auto_journal', '1') !== '1') {
            return;
        }

        $inventoryAccount = (int) setting('default_inventory_account_id', 0);
        $cashAccount = (int) setting('default_cash_account_id', 0);
        $supplierAccount = (int) setting('default_supplier_account_id', 0);

        if ($inventoryAccount <= 0 || ($cashAccount <= 0 && $supplierAccount <= 0)) {
            return;
        }

        $paid = (float) ($header['paid_amount'] ?? 0);
        $due = (float) ($header['due_amount'] ?? 0);
        $total = (float) ($header['total_amount'] ?? 0);

        $lines = [
            ['account_id' => $inventoryAccount, 'debit' => $total, 'credit' => 0],
        ];
        if ($paid > 0 && $cashAccount > 0) {
            $lines[] = ['account_id' => $cashAccount, 'debit' => 0, 'credit' => $paid];
        }
        if ($due > 0 && $supplierAccount > 0) {
            $lines[] = ['account_id' => $supplierAccount, 'debit' => 0, 'credit' => $due];
        }

        $this->createJournal([
            'branch_id' => $header['branch_id'] ?? null,
            'entry_no' => next_reference('journal_prefix', 'JRN'),
            'entry_date' => $header['purchase_date'] ?? now_datetime(),
            'source_type' => 'purchase',
            'source_id' => $purchaseId,
            'description' => 'قيد تلقائي لفاتورة الشراء ' . ($header['invoice_no'] ?? ''),
            'created_by' => $header['purchased_by'] ?? Auth::id(),
        ], $lines);
    }

    public function autoPostExpense(int $expenseId, array $header): void
    {
        if ((string) setting('enable_auto_journal', '1') !== '1') {
            return;
        }

        $expenseAccount = !empty($header['account_id']) ? (int) $header['account_id'] : (int) setting('default_expense_account_id', 0);
        $creditAccount = ($header['payment_method'] ?? 'cash') === 'bank'
            ? (int) setting('default_bank_account_id', 0)
            : (int) setting('default_cash_account_id', 0);

        if ($expenseAccount <= 0 || $creditAccount <= 0) {
            return;
        }

        $amount = (float) ($header['amount'] ?? 0);
        if ($amount <= 0) {
            return;
        }

        $this->createJournal([
            'branch_id' => $header['branch_id'] ?? null,
            'entry_no' => next_reference('journal_prefix', 'JRN'),
            'entry_date' => ($header['expense_date'] ?? date('Y-m-d')) . ' 12:00:00',
            'source_type' => 'expense',
            'source_id' => $expenseId,
            'description' => 'قيد تلقائي للمصروف ' . ($header['title'] ?? ''),
            'created_by' => $header['created_by'] ?? Auth::id(),
        ], [
            ['account_id' => $expenseAccount, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $creditAccount, 'debit' => 0, 'credit' => $amount],
        ]);
    }
}
