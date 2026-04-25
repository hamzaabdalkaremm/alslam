<?php
require_once __DIR__ . '/../config/bootstrap.php';

Auth::requireLogin();

try {
    $lookup = trim((string) ($_GET['id'] ?? $_GET['sale_id'] ?? $_GET['invoice_no'] ?? ''));
    if ($lookup === '') {
        Response::json([
            'success' => false,
            'message' => 'رقم الفاتورة أو معرفها مطلوب.'
        ]);
        return;
    }

    $pdo = Database::connection();

    if (ctype_digit($lookup)) {
        $stmt = $pdo->prepare(
            "SELECT
                s.*,
                c.full_name AS customer_name,
                b.name_ar AS branch_name,
                m.full_name AS marketer_name,
                w.name AS warehouse_name
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN branches b ON b.id = s.branch_id
             LEFT JOIN marketers m ON m.id = s.marketer_id
             LEFT JOIN warehouses w ON w.id = s.warehouse_id
             WHERE s.id = :lookup
             LIMIT 1"
        );
        $stmt->execute(['lookup' => (int) $lookup]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT
                s.*,
                c.full_name AS customer_name,
                b.name_ar AS branch_name,
                m.full_name AS marketer_name,
                w.name AS warehouse_name
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN branches b ON b.id = s.branch_id
             LEFT JOIN marketers m ON m.id = s.marketer_id
             LEFT JOIN warehouses w ON w.id = s.warehouse_id
             WHERE s.invoice_no = :lookup
             LIMIT 1"
        );
        $stmt->execute(['lookup' => $lookup]);
    }

    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        Response::json([
            'success' => false,
            'message' => 'لم يتم العثور على الفاتورة.'
        ]);
        return;
    }

    $sale['id'] = (int) $sale['id'];
    $sale['branch_id'] = $sale['branch_id'] !== null ? (int) $sale['branch_id'] : null;
    $sale['warehouse_id'] = $sale['warehouse_id'] !== null ? (int) $sale['warehouse_id'] : null;
    $sale['marketer_id'] = $sale['marketer_id'] !== null ? (int) $sale['marketer_id'] : null;
    $sale['customer_id'] = $sale['customer_id'] !== null ? (int) $sale['customer_id'] : null;
    $sale['sold_by'] = $sale['sold_by'] !== null ? (int) $sale['sold_by'] : null;
    $sale['delivered_by'] = isset($sale['delivered_by']) && $sale['delivered_by'] !== null ? (int) $sale['delivered_by'] : null;

    $sale['subtotal'] = isset($sale['subtotal']) ? (float) $sale['subtotal'] : 0.0;
    $sale['discount_value'] = isset($sale['discount_value']) ? (float) $sale['discount_value'] : 0.0;
    $sale['tax_value'] = isset($sale['tax_value']) ? (float) $sale['tax_value'] : 0.0;
    $sale['total_amount'] = isset($sale['total_amount']) ? (float) $sale['total_amount'] : 0.0;
    $sale['paid_amount'] = isset($sale['paid_amount']) ? (float) $sale['paid_amount'] : 0.0;
    $sale['due_amount'] = isset($sale['due_amount']) ? (float) $sale['due_amount'] : 0.0;

    Response::json([
        'success' => true,
        'data' => $sale
    ]);
} catch (Throwable $e) {
    Response::json([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}