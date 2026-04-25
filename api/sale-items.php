<?php
require_once __DIR__ . '/../config/bootstrap.php';

Auth::requireLogin();

try {
    $saleId = (int) ($_GET['sale_id'] ?? $_GET['id'] ?? 0);
    if ($saleId <= 0) {
        Response::json([
            'success' => false,
            'message' => 'رقم فاتورة البيع غير صالح.'
        ]);
        return;
    }

    $pdo = Database::connection();

    $tableCheck = $pdo->query("SHOW TABLES LIKE 'sale_items'");
    if ($tableCheck->rowCount() === 0) {
        Response::json([
            'success' => false,
            'message' => 'جدول sale_items غير موجود.'
        ]);
        return;
    }

    $returnTableCheck = $pdo->query("SHOW TABLES LIKE 'sale_return_items'");
    if ($returnTableCheck->rowCount() === 0) {
        Response::json([
            'success' => false,
            'message' => 'جدول sale_return_items غير موجود.'
        ]);
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT 
            si.id,
            si.sale_id,
            si.product_id,
            si.product_unit_id,
            si.batch_id,
            si.quantity,
            si.unit_price,
            si.line_total,
            COALESCE(SUM(sri.quantity), 0) AS returned_quantity
         FROM sale_items si
         LEFT JOIN sale_return_items sri 
            ON sri.sale_item_id = si.id
         WHERE si.sale_id = :sale_id
         GROUP BY 
            si.id,
            si.sale_id,
            si.product_id,
            si.product_unit_id,
            si.batch_id,
            si.quantity,
            si.unit_price,
            si.line_total
         ORDER BY si.id ASC"
    );
    $stmt->execute(['sale_id' => $saleId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        Response::json([
            'success' => true,
            'data' => [],
            'message' => 'لا توجد أصناف لهذه الفاتورة.'
        ]);
        return;
    }

    $productIds = array_values(array_filter(array_unique(array_map(
        static fn ($row) => (int) ($row['product_id'] ?? 0),
        $items
    ))));

    $productNames = [];
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $productStmt = $pdo->prepare(
            "SELECT id, name
             FROM products
             WHERE id IN ($placeholders)"
        );
        $productStmt->execute($productIds);

        foreach ($productStmt->fetchAll(PDO::FETCH_ASSOC) as $product) {
            $productNames[(int) $product['id']] = $product['name'];
        }
    }

    foreach ($items as &$item) {
        $productId = (int) $item['product_id'];

        $item['id'] = (int) $item['id'];
        $item['sale_id'] = (int) $item['sale_id'];
        $item['product_id'] = $productId;
        $item['product_unit_id'] = $item['product_unit_id'] !== null ? (int) $item['product_unit_id'] : null;
        $item['batch_id'] = $item['batch_id'] !== null ? (int) $item['batch_id'] : null;
        $item['quantity'] = (float) $item['quantity'];
        $item['unit_price'] = (float) $item['unit_price'];
        $item['line_total'] = (float) $item['line_total'];
        $item['returned_quantity'] = (float) $item['returned_quantity'];
        $item['product_name'] = $productNames[$productId] ?? ('صنف #' . $productId);
    }
    unset($item);

    Response::json([
        'success' => true,
        'data' => $items
    ]);
} catch (Throwable $e) {
    Response::json([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}