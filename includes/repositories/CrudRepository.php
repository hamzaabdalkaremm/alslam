<?php

class CrudRepository
{
    public function paginate(string $table, array $filters = [], int $limit = 15, int $offset = 0, string $orderBy = 'id DESC'): array
    {
        $isSuperAdmin = Auth::isSuperAdmin();
        $accessibleBranchIds = Auth::branchIds();

        $pdo = Database::connection();
        $where = ['deleted_at IS NULL'];
        $params = [];

        $skipBranchFilter = in_array($table, ['products', 'customers', 'suppliers'], true);

        if ($skipBranchFilter && !$isSuperAdmin) {
            if (!isset($filters['branch_id'])) {
                if ($accessibleBranchIds) {
                    $branchPlaceholders = [];

                    foreach ($accessibleBranchIds as $index => $branchId) {
                        $key = 'branch_' . $index;
                        $branchPlaceholders[] = ':' . $key;
                        $params[$key] = (int) $branchId;
                    }

                    $where[] = '(branch_id IS NULL OR branch_id = 0 OR branch_id IN (' . implode(',', $branchPlaceholders) . '))';
                } else {
                    $where[] = '1 = 0';
                }
            }
        }

        foreach ($filters as $column => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if ($skipBranchFilter && $column === 'branch_id') {
                continue;
            }

            if (is_array($value)) {
                if (!array_key_exists('in', $value) && !array_key_exists('eq', $value) && !array_key_exists('like', $value)) {
                    continue;
                }

                if (array_key_exists('in', $value)) {
                    $items = array_values(array_filter(
                        (array) $value['in'],
                        static fn ($item): bool => $item !== '' && $item !== null && !is_array($item)
                    ));

                    if (!$items) {
                        $where[] = '1 = 0';
                        continue;
                    }

                    $placeholders = [];
                    foreach ($items as $index => $item) {
                        $key = $column . '_in_' . $index;
                        $placeholders[] = ':' . $key;
                        $params[$key] = is_scalar($item) ? $item : '';
                    }

                    $where[] = $column . ' IN (' . implode(', ', $placeholders) . ')';
                    continue;
                }

                if (array_key_exists('eq', $value)) {
                    $where[] = $column . ' = :' . $column;
                    $params[$column] = is_scalar($value['eq']) ? $value['eq'] : '';
                    continue;
                }

                if (array_key_exists('like', $value)) {
                    $where[] = $column . ' LIKE :' . $column;
                    $params[$column] = '%' . (is_scalar($value['like']) ? $value['like'] : '') . '%';
                    continue;
                }
            }

            if (is_scalar($value)) {
                $where[] = $column . ' LIKE :' . $column;
                $params[$column] = '%' . $value . '%';
            }
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$whereClause}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue(is_int($key) ? ($key + 1) : (':' . $key), $value);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$whereClause} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue(is_int($key) ? ($key + 1) : (':' . $key), $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    public function find(string $table, int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM {$table} WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function save(string $table, array $data, ?int $id = null): int
    {
        $pdo = Database::connection();

        if ($id) {
            $columns = [];
            foreach ($data as $column => $value) {
                $columns[] = "{$column} = :{$column}";
            }
            $data['id'] = $id;
            $stmt = $pdo->prepare("UPDATE {$table} SET " . implode(', ', $columns) . " WHERE id = :id");
            $stmt->execute($data);
            return $id;
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn ($column) => ':' . $column, $columns);
        $stmt = $pdo->prepare(
            "INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($data);

        return (int) $pdo->lastInsertId();
    }

    public function softDelete(string $table, int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE {$table} SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
}