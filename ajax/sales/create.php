<?php
require_once __DIR__ . '/../../config/bootstrap.php';

Auth::requireLogin();
CSRF::verifyRequest();

$saleId = (int) ($_POST['id'] ?? 0);
$isUpdate = $saleId > 0;

if ($isUpdate) {
    Auth::requirePermission('sales.update');
} else {
    Auth::requirePermission('sales.create');
}

$items = $_POST['items'] ?? [];
if (!$items) {
    Response::error('يجب إضافة أصناف إلى الفاتورة.');
}

$preparedItems = [];
foreach ($items as $item) {
    if (empty($item['product_id'])) {
        continue;
    }

    $quantity = (float) ($item['quantity'] ?? 0);
    if ($quantity <= 0) {
        Response::error('يجب أن تكون كمية البيع أكبر من صفر.');
    }

    $unitPrice = (float) ($item['unit_price'] ?? 0);
    $discount = (float) ($item['discount_value'] ?? 0);
    $lineTotal = ($quantity * $unitPrice) - $discount;

    $preparedItems[] = [
        'product_id' => (int) $item['product_id'],
        'product_unit_id' => !empty($item['product_unit_id']) ? (int) $item['product_unit_id'] : null,
        'batch_id' => !empty($item['batch_id']) ? (int) $item['batch_id'] : null,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'discount_value' => $discount,
        'tax_value' => 0,
        'line_total' => $lineTotal,
    ];
}

if (!$preparedItems) {
    Response::error('يجب إضافة أصناف صحيحة إلى الفاتورة.');
}

try {
    $pdo = Database::connection();

    $branchId = !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : Auth::defaultBranchId();
    if (!Auth::canAccessBranch($branchId)) {
        Response::error('لا تملك صلاحية البيع على هذا الفرع.', 403);
    }

    $warehouseId = !empty($_POST['warehouse_id']) ? (int) $_POST['warehouse_id'] : null;
    $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'cash'));
    $customerId = !empty($_POST['customer_id']) ? (int) $_POST['customer_id'] : null;
    $marketerId = !empty($_POST['marketer_id']) ? (int) $_POST['marketer_id'] : null;
    
    $subtotal = (float) ($_POST['subtotal'] ?? 0);
    $discountValue = (float) ($_POST['discount_value'] ?? 0);
    $totalAmount = (float) ($_POST['total_amount'] ?? 0);
    $paidAmount = (float) ($_POST['paid_amount'] ?? 0);
    $dueAmount = (float) ($_POST['due_amount'] ?? 0);

    if ($paymentMethod === 'deferred' && $customerId === null) {
        Response::error('البيع الآجل يجب أن يُنسب إلى عميل محدد.');
    }

    $warehouseId = !empty($_POST['warehouse_id']) ? (int) $_POST['warehouse_id'] : null;
    $saleMode = 'vehicle_sale';
    $deliveryStatus = 'delivered';
    $deliveredBy = null;

    if ($marketerId !== null) {
        $stmt = $pdo->prepare("
            SELECT id, marketer_type, default_warehouse_id, status, deleted_at
            FROM marketers
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $marketerId]);
        $marketer = $stmt->fetch();

        if (!$marketer || !empty($marketer['deleted_at']) || ($marketer['status'] ?? '') !== 'active') {
            Response::error('المسوق المحدد غير صالح.');
        }

        if ($marketer['marketer_type'] === 'marketer') {
            $saleMode = 'vehicle_sale';
            $deliveryStatus = 'delivered';

            if (empty($marketer['default_warehouse_id'])) {
                Response::error('هذا المسوق لا يملك مخزن سيارة مرتبطًا.');
            }

            $warehouseId = (int) $marketer['default_warehouse_id'];
        } elseif ($marketer['marketer_type'] === 'delegate') {
            $saleMode = 'delegate_order';
            $deliveryStatus = 'pending';

            if ($warehouseId === null) {
                Response::error('طلبية المندوب تحتاج اختيار مخزن التجهيز.');
            }
        } else {
            Response::error('نوع المسوق غير مدعوم.');
        }
    } else {
        if ($warehouseId === null) {
            Response::error('يجب اختيار المخزن.');
        }
    }

    $warehouseStmt = $pdo->prepare("
        SELECT id, branch_id, deleted_at, status
        FROM warehouses
        WHERE id = :id
        LIMIT 1
    ");
    $warehouseStmt->execute(['id' => $warehouseId]);
    $warehouse = $warehouseStmt->fetch();

    if (!$warehouse || !empty($warehouse['deleted_at']) || ($warehouse['status'] ?? '') !== 'active') {
        Response::error('المخزن المحدد غير صالح.');
    }

    if (!Auth::canAccessBranch((int) $warehouse['branch_id'])) {
        Response::error('لا تملك صلاحية استخدام هذا المخزن.', 403);
    }

    if ($paymentMethod === 'deferred') {
        $paidAmount = 0;
        $dueAmount = $totalAmount;
    } else {
        $paidAmount = $totalAmount;
        $dueAmount = 0;
    }

    if ($isUpdate) {
        $saleId = (new SalesService())->update([
            'id' => $saleId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'marketer_id' => $marketerId,
            'sale_mode' => $saleMode,
            'delivery_status' => $deliveryStatus,
            'delivered_by' => $deliveredBy,
            'invoice_no' => trim((string) ($_POST['invoice_no'] ?? '')),
            'customer_id' => $customerId,
            'sold_by' => Auth::id(),
            'sale_date' => now_datetime(),
            'status' => 'completed',
            'approval_status' => 'approved',
            'pricing_tier' => trim((string) ($_POST['pricing_tier'] ?? 'wholesale')),
            'payment_method' => $paymentMethod,
            'subtotal' => $subtotal,
            'discount_value' => $discountValue,
            'tax_value' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ], $preparedItems);
    } else {
        $saleId = (new SalesService())->create([
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'marketer_id' => $marketerId,
            'sale_mode' => $saleMode,
            'delivery_status' => $deliveryStatus,
            'delivered_by' => $deliveredBy,
            'invoice_no' => trim((string) ($_POST['invoice_no'] ?? '')),
            'customer_id' => $customerId,
            'sold_by' => Auth::id(),
            'sale_date' => now_datetime(),
            'status' => 'completed',
            'approval_status' => 'approved',
            'pricing_tier' => trim((string) ($_POST['pricing_tier'] ?? 'wholesale')),
            'payment_method' => $paymentMethod,
            'subtotal' => $subtotal,
            'discount_value' => $discountValue,
            'tax_value' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'printable_token' => bin2hex(random_bytes(8)),
        ], $preparedItems);
    }
} catch (RuntimeException $exception) {
    Response::error($exception->getMessage());
} catch (Throwable $exception) {
    Response::error('تعذر حفظ الفاتورة.');
}

Response::success('تم حفظ الفاتورة بنجاح', [
    'id' => $saleId,
    'print_url' => 'api/print-sale.php?id=' . $saleId,
]);
