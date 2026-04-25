<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('debts.collect');

$partyType = $_GET['party_type'] ?? '';
$partyId = (int) ($_GET['party_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

if (!in_array($partyType, ['customer', 'supplier', 'marketer'], true)) {
    Response::error('نوع الطرف غير صالح للفاتورة');
}

if ($partyId <= 0) {
    Response::error('معرف الطرف غير صالح');
}

$pdo = Database::connection();
$params = [
    'party_id' => $partyId,
    'search' => "%{$search}%",
];

switch ($partyType) {
    case 'customer':
        $deletedFilter = schema_has_column('sales', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
        $sql = "SELECT id, invoice_no, sale_date AS date, total_amount, paid_amount, due_amount
                FROM sales
                WHERE customer_id = :party_id
                  {$deletedFilter}
                  AND due_amount > 0
                  AND invoice_no LIKE :search";

        if (!Auth::isSuperAdmin() && schema_has_column('sales', 'branch_id')) {
            $branchIds = Auth::branchIds();
            if (!$branchIds) {
                Response::success('تم تحميل الفواتير', []);
            }

            $placeholders = [];
            foreach ($branchIds as $index => $branchId) {
                $key = 'branch_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (int) $branchId;
            }
            $sql .= ' AND branch_id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY sale_date DESC LIMIT 50';
        break;

    case 'supplier':
        $deletedFilter = schema_has_column('purchases', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
        $sql = "SELECT id, invoice_no, purchase_date AS date, total_amount, paid_amount, due_amount
                FROM purchases
                WHERE supplier_id = :party_id
                  {$deletedFilter}
                  AND due_amount > 0
                  AND invoice_no LIKE :search";

        if (!Auth::isSuperAdmin() && schema_has_column('purchases', 'branch_id')) {
            $branchIds = Auth::branchIds();
            if (!$branchIds) {
                Response::success('تم تحميل الفواتير', []);
            }

            $placeholders = [];
            foreach ($branchIds as $index => $branchId) {
                $key = 'branch_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (int) $branchId;
            }
            $sql .= ' AND branch_id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY purchase_date DESC LIMIT 50';
        break;

    case 'marketer':
        if (!schema_has_column('sales', 'marketer_id')) {
            Response::success('تم تحميل الفواتير', []);
        }

        $deletedFilter = schema_has_column('sales', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
        $sql = "SELECT id, invoice_no, sale_date AS date, total_amount, paid_amount, due_amount
                FROM sales
                WHERE marketer_id = :party_id
                  {$deletedFilter}
                  AND due_amount > 0
                  AND invoice_no LIKE :search";

        if (!Auth::isSuperAdmin() && schema_has_column('sales', 'branch_id')) {
            $branchIds = Auth::branchIds();
            if (!$branchIds) {
                Response::success('تم تحميل الفواتير', []);
            }

            $placeholders = [];
            foreach ($branchIds as $index => $branchId) {
                $key = 'branch_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (int) $branchId;
            }
            $sql .= ' AND branch_id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY sale_date DESC LIMIT 50';
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

Response::success('تم تحميل الفواتير', $stmt->fetchAll());
