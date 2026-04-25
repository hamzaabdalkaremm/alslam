<?php
require_once __DIR__ . '/../../config/bootstrap.php';

Auth::requireLogin();
Auth::requirePermission('sales.return');
CSRF::verifyRequest();

try {
    $pdo = Database::connection();

    $saleId = (int) ($_POST['sale_id'] ?? 0);
    $invoiceNo = trim((string) ($_POST['invoice_no'] ?? ''));

    if ($saleId <= 0 && $invoiceNo !== '') {
        $stmt = $pdo->prepare('SELECT id FROM sales WHERE invoice_no = :invoice_no LIMIT 1');
        $stmt->execute(['invoice_no' => $invoiceNo]);
        $saleId = (int) ($stmt->fetchColumn() ?: 0);
    }

    if ($saleId <= 0) {
        Response::error('رقم فاتورة البيع غير صالح.');
    }

    $saleStmt = $pdo->prepare(
        'SELECT id, invoice_no, customer_id, payment_method, total_amount, paid_amount, due_amount
         FROM sales
         WHERE id = :id
         LIMIT 1'
    );
    $saleStmt->execute(['id' => $saleId]);
    $sale = $saleStmt->fetch();

    if (!$sale) {
        Response::error('فاتورة البيع الأصلية غير موجودة.');
    }

    $returnNo = trim((string) ($_POST['return_no'] ?? ''));
    if ($returnNo === '') {
        Response::error('رقم المرتجع مطلوب.');
    }

    $rawItems = $_POST['items'] ?? [];
    if (!is_array($rawItems) || empty($rawItems)) {
        Response::error('يجب إضافة عناصر المرتجع.');
    }

    $preparedItems = [];
    $subtotal = 0.0;

    foreach ($rawItems as $item) {
        $saleItemId = (int) ($item['sale_item_id'] ?? 0);
        $productId = (int) ($item['product_id'] ?? 0);
        $quantity = (float) ($item['quantity'] ?? 0);
        $productUnitId = !empty($item['product_unit_id']) ? (int) $item['product_unit_id'] : null;
        $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : 0.0;

        if ($saleItemId <= 0 && $productId <= 0 && $quantity <= 0) {
            continue;
        }

        if ($saleItemId <= 0) {
            Response::error('يوجد عنصر مرتجع بدون sale_item_id صحيح.');
        }

        if ($productId <= 0) {
            Response::error('يوجد عنصر مرتجع بدون product_id صحيح.');
        }

        if ($quantity <= 0) {
            Response::error('كمية المرتجع يجب أن تكون أكبر من صفر.');
        }

        if ($unitPrice < 0) {
            Response::error('سعر العنصر غير صالح.');
        }

        $lineTotal = round($quantity * $unitPrice, 2);
        $subtotal += $lineTotal;

        $preparedItems[] = [
            'sale_item_id' => $saleItemId,
            'product_id' => $productId,
            'product_unit_id' => $productUnitId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ];
    }

    if (empty($preparedItems)) {
        Response::error('يجب إضافة عناصر مرتجع صحيحة.');
    }

    $returnDate = trim((string) ($_POST['return_date'] ?? ''));
    if ($returnDate === '') {
        $returnDate = now_datetime();
    }

    $returnId = (new SalesService())->createReturn([
        'sale_id' => $saleId,
        'customer_id' => !empty($_POST['customer_id']) ? (int) $_POST['customer_id'] : (!empty($sale['customer_id']) ? (int) $sale['customer_id'] : null),
        'return_no' => $returnNo,
        'return_date' => $returnDate,
        'subtotal' => round($subtotal, 2),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'created_by' => Auth::id(),
    ], $preparedItems);

    Response::success('تم حفظ مرتجع البيع وتحديث الحسابات بنجاح.', [
        'id' => $returnId,
        'sale_id' => $saleId,
        'invoice_no' => $sale['invoice_no'] ?? null,
        'payment_method' => $sale['payment_method'] ?? null,
        'return_subtotal' => round($subtotal, 2),
    ]);
} catch (RuntimeException $exception) {
    Response::error($exception->getMessage());
} catch (Throwable $exception) {
    Response::error('تعذر حفظ مرتجع البيع.');
}
