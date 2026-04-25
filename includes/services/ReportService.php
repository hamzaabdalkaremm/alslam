<?php

class ReportService
{
    public function salesByDate(string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT invoice_no, sale_date, total_amount, paid_amount, due_amount
             FROM sales
             WHERE DATE(sale_date) BETWEEN :date_from AND :date_to AND deleted_at IS NULL
             ORDER BY sale_date DESC"
        );
        $stmt->execute(['date_from' => $from, 'date_to' => $to]);

        return $stmt->fetchAll();
    }

    public function purchasesByDate(string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT invoice_no, purchase_date, total_amount, paid_amount, due_amount
             FROM purchases
             WHERE DATE(purchase_date) BETWEEN :date_from AND :date_to AND deleted_at IS NULL
             ORDER BY purchase_date DESC"
        );
        $stmt->execute(['date_from' => $from, 'date_to' => $to]);

        return $stmt->fetchAll();
    }

    public function profitSummary(string $from, string $to): array
    {
        $sales = Database::connection()->prepare(
            "SELECT COALESCE(SUM(si.line_total), 0)
             FROM sale_items si
             INNER JOIN sales s ON s.id = si.sale_id
             WHERE DATE(s.sale_date) BETWEEN :date_from AND :date_to AND s.deleted_at IS NULL"
        );
        $sales->execute(['date_from' => $from, 'date_to' => $to]);

        $costs = Database::connection()->prepare(
            "SELECT COALESCE(SUM(pi.line_total), 0)
             FROM purchase_items pi
             INNER JOIN purchases p ON p.id = pi.purchase_id
             WHERE DATE(p.purchase_date) BETWEEN :date_from AND :date_to AND p.deleted_at IS NULL"
        );
        $costs->execute(['date_from' => $from, 'date_to' => $to]);

        $expenses = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM expenses
             WHERE expense_date BETWEEN :date_from AND :date_to AND deleted_at IS NULL"
        );
        $expenses->execute(['date_from' => $from, 'date_to' => $to]);

        $salesValue = (float) $sales->fetchColumn();
        $costValue = (float) $costs->fetchColumn();
        $expensesValue = (float) $expenses->fetchColumn();

        return [
            'sales' => $salesValue,
            'costs' => $costValue,
            'expenses' => $expensesValue,
            'gross_profit' => $salesValue - $costValue,
            'net_profit' => $salesValue - $costValue - $expensesValue,
        ];
    }

    public function productsExportRows(): array
    {
        $params = [];
        $columns = [
            'p.id',
            'p.code',
            'p.name',
        ];
        $joins = [];
        $where = ['p.deleted_at IS NULL'];

        if (schema_has_column('products', 'branch_id')) {
            if (schema_table_exists('branches')) {
                $joins[] = 'LEFT JOIN branches b ON b.id = p.branch_id';
                $columns[] = "COALESCE(b.name_ar, '') AS branch_name";
            } else {
                $columns[] = "COALESCE(p.branch_id, '') AS branch_name";
            }

            if (!Auth::isSuperAdmin()) {
                $branchIds = Auth::branchIds();
                if ($branchIds) {
                    $placeholders = [];
                    foreach ($branchIds as $index => $branchId) {
                        $key = 'branch_' . $index;
                        $placeholders[] = ':' . $key;
                        $params[$key] = (int) $branchId;
                    }
                    $where[] = 'p.branch_id IN (' . implode(', ', $placeholders) . ')';
                } else {
                    $where[] = '1 = 0';
                }
            }
        } else {
            $columns[] = "'' AS branch_name";
        }

        if (schema_has_column('products', 'category_id') && schema_table_exists('product_categories')) {
            $joins[] = 'LEFT JOIN product_categories pc ON pc.id = p.category_id';
            $columns[] = "COALESCE(pc.name, '') AS category_name";
        } else {
            $columns[] = "'' AS category_name";
        }

        if (schema_has_column('products', 'base_unit_id') && schema_table_exists('units')) {
            $joins[] = 'LEFT JOIN units bu ON bu.id = p.base_unit_id';
            $columns[] = "COALESCE(bu.name, '') AS base_unit_name";
        } else {
            $columns[] = "'' AS base_unit_name";
        }

        $optionalColumns = [
            'brand',
            'barcode',
            'cost_price',
            'wholesale_price',
            'half_wholesale_price',
            'retail_price',
            'min_stock_alert',
            'shelf_location',
            'notes',
            'sell_by_piece',
            'sell_by_carton',
            'is_active',
            'created_at',
            'updated_at',
        ];

        foreach ($optionalColumns as $column) {
            if (schema_has_column('products', $column)) {
                $columns[] = 'p.' . $column;
            } else {
                $columns[] = 'NULL AS ' . $column;
            }
        }

        if (schema_table_exists('stock_movements')) {
            $joins[] = 'LEFT JOIN (
                    SELECT product_id, COALESCE(SUM(quantity_in - quantity_out), 0) AS stock_balance
                    FROM stock_movements
                    GROUP BY product_id
                ) sm ON sm.product_id = p.id';
            $columns[] = 'COALESCE(sm.stock_balance, 0) AS stock_balance';
        } else {
            $columns[] = '0 AS stock_balance';
        }

        if (schema_table_exists('product_units')) {
            $hasUnitsTable = schema_table_exists('units');
            $unitLabelExpression = $hasUnitsTable
                ? "COALESCE(NULLIF(pu.label, ''), unit_ref.name, 'Unit')"
                : "COALESCE(NULLIF(pu.label, ''), 'Unit')";

            $unitJoin = "LEFT JOIN (
                    SELECT pu.product_id,
                           GROUP_CONCAT(
                               CONCAT(
                                   {$unitLabelExpression},
                                   ' | Barcode: ', COALESCE(pu.barcode, ''),
                                   ' | Purchase: ', COALESCE(pu.purchase_price, 0),
                                   ' | Wholesale: ', COALESCE(pu.wholesale_price, 0),
                                   ' | HalfWholesale: ', COALESCE(pu.half_wholesale_price, 0),
                                   ' | Retail: ', COALESCE(pu.retail_price, 0),
                                   CASE WHEN pu.is_default_sale_unit = 1 THEN ' | DefaultSale' ELSE '' END,
                                   CASE WHEN pu.is_default_purchase_unit = 1 THEN ' | DefaultPurchase' ELSE '' END
                               )
                               SEPARATOR '\n'
                           ) AS units_summary
                    FROM product_units pu";

            if ($hasUnitsTable) {
                $unitJoin .= ' LEFT JOIN units unit_ref ON unit_ref.id = pu.unit_id';
            }

            $unitJoin .= ' GROUP BY pu.product_id
                ) pu_summary ON pu_summary.product_id = p.id';

            $joins[] = $unitJoin;
            $columns[] = "COALESCE(pu_summary.units_summary, '') AS units_summary";
        } else {
            $columns[] = "'' AS units_summary";
        }

        $sql = 'SELECT ' . implode(",\n       ", $columns) . '
                FROM products p
                ' . implode("\n                ", $joins) . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY p.name ASC';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
