<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('debts.collect');

$type = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');

if (!in_array($type, ['customer', 'supplier', 'marketer'], true)) {
    Response::error('نوع الطرف غير صالح');
}

$pdo = Database::connection();
$params = ['search' => "%{$search}%"];

switch ($type) {
    case 'customer':
        $deletedFilter = schema_has_column('customers', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
        $sql = "SELECT id, full_name AS name, code
                FROM customers
                WHERE 1 = 1
                  {$deletedFilter}
                  AND CONCAT_WS(' ', full_name, code) LIKE :search";

        if (!Auth::isSuperAdmin() && schema_has_column('customers', 'branch_id')) {
            $branchIds = Auth::branchIds();
            if (!$branchIds) {
                Response::success('تم تحميل الأطراف', []);
            }

            $placeholders = [];
            foreach ($branchIds as $index => $branchId) {
                $key = 'branch_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (int) $branchId;
            }
            $sql .= ' AND branch_id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY full_name ASC LIMIT 50';
        break;

    case 'supplier':
        $deletedFilter = schema_has_column('suppliers', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
        $sql = "SELECT id, company_name AS name, code
                FROM suppliers
                WHERE 1 = 1
                  {$deletedFilter}
                  AND CONCAT_WS(' ', company_name, code) LIKE :search";

        if (!Auth::isSuperAdmin() && schema_has_column('suppliers', 'branch_id')) {
            $branchIds = Auth::branchIds();
            if (!$branchIds) {
                Response::success('تم تحميل الأطراف', []);
            }

            $placeholders = [];
            foreach ($branchIds as $index => $branchId) {
                $key = 'branch_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (int) $branchId;
            }
            $sql .= ' AND branch_id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY company_name ASC LIMIT 50';
        break;

    case 'marketer':
        $deletedFilter = schema_has_column('marketers', 'deleted_at') ? 'AND m.deleted_at IS NULL' : '';
        $sql = "SELECT DISTINCT m.id, m.full_name AS name, m.code
                FROM marketers m";

        if (!Auth::isSuperAdmin() && schema_table_exists('marketer_branches')) {
            $sql .= ' INNER JOIN marketer_branches mb ON mb.marketer_id = m.id';
        }

        $sql .= "
                WHERE 1 = 1
                  {$deletedFilter}
                  AND CONCAT_WS(' ', m.full_name, m.code) LIKE :search";

        if (!Auth::isSuperAdmin() && schema_table_exists('marketer_branches')) {
            $branchIds = Auth::branchIds();
            if (!$branchIds) {
                Response::success('تم تحميل الأطراف', []);
            }

            $placeholders = [];
            foreach ($branchIds as $index => $branchId) {
                $key = 'branch_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (int) $branchId;
            }
            $sql .= ' AND mb.branch_id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY m.full_name ASC LIMIT 50';
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

Response::success('تم تحميل الأطراف', $stmt->fetchAll());
