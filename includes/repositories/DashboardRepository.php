<?php

class DashboardRepository
{
    public function stats(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            "SELECT
                (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE sale_date >= CURDATE() AND sale_date < CURDATE() + INTERVAL 1 DAY AND deleted_at IS NULL) AS today_sales,
                (SELECT COALESCE(SUM(total_amount), 0) FROM purchases WHERE purchase_date >= CURDATE() AND purchase_date < CURDATE() + INTERVAL 1 DAY AND deleted_at IS NULL) AS today_purchases,
                (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = CURDATE() AND deleted_at IS NULL) AS today_expenses,
                (SELECT COALESCE(SUM(due_amount), 0) FROM sales WHERE due_amount > 0 AND deleted_at IS NULL) AS customers_due,
                (SELECT COALESCE(SUM(due_amount), 0) FROM purchases WHERE due_amount > 0 AND deleted_at IS NULL) AS suppliers_due,
                (SELECT COALESCE(SUM(amount), 0) FROM debt_collections) AS collections_total,
                (SELECT COUNT(*) FROM branches WHERE deleted_at IS NULL) AS branches_count,
                (SELECT COUNT(*) FROM marketers WHERE deleted_at IS NULL) AS marketers_count,
                (SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL) AS customers_count,
                (SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL) AS suppliers_count,
                (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS users_count,
                (SELECT COUNT(*) FROM products WHERE deleted_at IS NULL) AS product_count"
        );

        $result = array_map('floatval', $stmt->fetch() ?: []);
        $result['net_profit'] = $result['today_sales'] - $result['today_purchases'] - $result['today_expenses'];

        return $result;
    }

    public function lowStock(): array
    {
        $sql = "SELECT p.id, p.name, p.min_stock_alert,
                       COALESCE(SUM(sm.quantity_in - sm.quantity_out), 0) AS stock_balance
                FROM products p
                LEFT JOIN stock_movements sm ON sm.product_id = p.id
                WHERE p.deleted_at IS NULL
                GROUP BY p.id, p.name, p.min_stock_alert
                HAVING stock_balance <= p.min_stock_alert
                ORDER BY stock_balance ASC
                LIMIT 8";

        return Database::connection()->query($sql)->fetchAll();
    }

    public function overdueDebts(): array
    {
        $sql = "SELECT s.invoice_no, s.sale_date, s.due_amount, c.full_name AS customer_name, b.name_ar AS branch_name
                FROM sales s
                LEFT JOIN customers c ON c.id = s.customer_id
                LEFT JOIN branches b ON b.id = s.branch_id
                WHERE s.deleted_at IS NULL AND s.due_amount > 0
                ORDER BY s.sale_date ASC
                LIMIT 8";
        return Database::connection()->query($sql)->fetchAll();
    }

    public function topProducts(): array
    {
        $sql = "SELECT p.name, COALESCE(SUM(si.quantity), 0) AS sold_quantity, COALESCE(SUM(si.line_total), 0) AS sold_total
                FROM sale_items si
                INNER JOIN products p ON p.id = si.product_id
                INNER JOIN sales s ON s.id = si.sale_id AND s.deleted_at IS NULL
                GROUP BY p.id, p.name
                ORDER BY sold_quantity DESC
                LIMIT 5";
        return Database::connection()->query($sql)->fetchAll();
    }

    public function topMarketers(): array
    {
        $sql = "SELECT m.full_name,
                       COALESCE(s.sales_total, 0) AS sales_total,
                       COALESCE(dc.collections_total, 0) AS collections_total
                FROM marketers m
                LEFT JOIN (
                    SELECT marketer_id, COALESCE(SUM(total_amount), 0) AS sales_total
                    FROM sales
                    WHERE deleted_at IS NULL AND marketer_id IS NOT NULL
                    GROUP BY marketer_id
                ) s ON s.marketer_id = m.id
                LEFT JOIN (
                    SELECT marketer_id, COALESCE(SUM(amount), 0) AS collections_total
                    FROM debt_collections
                    WHERE marketer_id IS NOT NULL
                    GROUP BY marketer_id
                ) dc ON dc.marketer_id = m.id
                WHERE m.deleted_at IS NULL
                ORDER BY sales_total DESC
                LIMIT 5";
        return Database::connection()->query($sql)->fetchAll();
    }

    public function bestBranches(): array
    {
        $sql = "SELECT b.name_ar,
                       COALESCE(s.sales_total, 0) AS sales_total,
                       COALESCE(p.purchases_total, 0) AS purchases_total,
                       COALESCE(e.expenses_total, 0) AS expenses_total
                FROM branches b
                LEFT JOIN (
                    SELECT branch_id, COALESCE(SUM(total_amount), 0) AS sales_total
                    FROM sales
                    WHERE deleted_at IS NULL
                    GROUP BY branch_id
                ) s ON s.branch_id = b.id
                LEFT JOIN (
                    SELECT branch_id, COALESCE(SUM(total_amount), 0) AS purchases_total
                    FROM purchases
                    WHERE deleted_at IS NULL
                    GROUP BY branch_id
                ) p ON p.branch_id = b.id
                LEFT JOIN (
                    SELECT branch_id, COALESCE(SUM(amount), 0) AS expenses_total
                    FROM expenses
                    WHERE deleted_at IS NULL
                    GROUP BY branch_id
                ) e ON e.branch_id = b.id
                WHERE b.deleted_at IS NULL
                ORDER BY sales_total DESC
                LIMIT 5";
        return Database::connection()->query($sql)->fetchAll();
    }

    public function latestActivities(int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $pdo = Database::connection();

        $total = (int) $pdo->query(
            "SELECT COUNT(*)
             FROM activity_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)"
        )->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT al.*, u.full_name, b.name_ar AS branch_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN branches b ON b.id = al.branch_id
             WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
             ORDER BY al.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function monthlySalesChart(): array
    {
        $sql = "SELECT DATE_FORMAT(sale_date, '%Y-%m') AS month_key, COALESCE(SUM(total_amount), 0) AS total_amount
                FROM sales
                WHERE deleted_at IS NULL AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY month_key
                ORDER BY month_key ASC";
        return Database::connection()->query($sql)->fetchAll();
    }
}
