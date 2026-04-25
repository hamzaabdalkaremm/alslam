<?php

class CashboxService
{
    public function addEntry(array $payload): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cashbox_entries (branch_id, cashbox_id, account_id, entry_type, reference_type, reference_id, entry_date, amount, description, created_by)
             VALUES (:branch_id, :cashbox_id, :account_id, :entry_type, :reference_type, :reference_id, :entry_date, :amount, :description, :created_by)'
        );
        $stmt->execute([
            'branch_id' => $payload['branch_id'] ?? null,
            'cashbox_id' => $payload['cashbox_id'] ?? null,
            'account_id' => $payload['account_id'] ?? null,
            'entry_type' => $payload['entry_type'],
            'reference_type' => $payload['reference_type'] ?? null,
            'reference_id' => $payload['reference_id'] ?? null,
            'entry_date' => $payload['entry_date'],
            'amount' => $payload['amount'],
            'description' => $payload['description'],
            'created_by' => $payload['created_by'] ?? Auth::id(),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function dailySummary(string $date): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN entry_type = 'receipt' THEN amount ELSE 0 END), 0) AS receipts,
                COALESCE(SUM(CASE WHEN entry_type = 'payment' THEN amount ELSE 0 END), 0) AS payments
             FROM cashbox_entries
             WHERE DATE(entry_date) = :entry_date AND deleted_at IS NULL"
        );
        $stmt->execute(['entry_date' => $date]);

        $summary = $stmt->fetch() ?: ['receipts' => 0, 'payments' => 0];
        $summary['balance'] = (float) $summary['receipts'] - (float) $summary['payments'];

        return $summary;
    }

    public function closeDay(array $payload): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cashbox_closures
             (closing_date, opening_balance, cash_in, cash_out, expected_balance, actual_balance, variance, notes, closed_by)
             VALUES
             (:closing_date, :opening_balance, :cash_in, :cash_out, :expected_balance, :actual_balance, :variance, :notes, :closed_by)'
        );
        $stmt->execute($payload);
        $closureId = (int) Database::connection()->lastInsertId();
        log_activity('cashbox', 'manage', 'إقفال يومية الخزينة', 'cashbox_closures', $closureId);
        return $closureId;
    }

    public function recentEntries(): array
    {
        return Database::connection()->query(
            "SELECT * FROM cashbox_entries WHERE deleted_at IS NULL ORDER BY entry_date DESC, id DESC LIMIT 20"
        )->fetchAll();
    }
}
