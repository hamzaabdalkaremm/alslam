<?php
require_once __DIR__ . '/../../config/bootstrap.php';

Auth::requireLogin();
CSRF::verifyRequest();

$id = (int) ($_POST['id'] ?? 0);
$isQuickCreate = !empty($_POST['quick_create']);

if ($id > 0) {
    if (!Auth::can('customers.update')) {
        Response::error('ليس لديك صلاحية تعديل العملاء.', 403);
    }
} else {
    $canCreateCustomer = Auth::can('customers.create');
    $canQuickCreateFromSales = $isQuickCreate && Auth::can('sales.create');

    if (!$canCreateCustomer && !$canQuickCreateFromSales) {
        Response::error('ليس لديك صلاحية إضافة عميل جديد.', 403);
    }
}

$incomingCode = trim((string) ($_POST['code'] ?? ''));
if ($incomingCode === '' && $id === 0) {
    $_POST['code'] = next_customer_code();
}

$errors = Validator::validate($_POST, ['full_name' => 'required']);
if ($errors) {
    Response::error('تحقق من بيانات العميل.', 422, $errors);
}

$code = trim((string) ($_POST['code'] ?? ''));
if ($code === '' && $isQuickCreate) {
    $code = generateCustomerCode();
}

if ($code === '') {
    Response::error('كود العميل مطلوب.', 422);
}

if (customerCodeExists($code, $id > 0 ? $id : null)) {
    if ($isQuickCreate && $id === 0) {
        $code = generateCustomerCode();
    } else {
        Response::error('كود العميل مستخدم من قبل.', 422);
    }
}

$payload = [
    'branch_id' => !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : null,
    'marketer_id' => !empty($_POST['marketer_id']) ? (int) $_POST['marketer_id'] : null,
    'code' => $code,
    'full_name' => trim((string) ($_POST['full_name'] ?? '')),
    'category' => trim((string) ($_POST['category'] ?? '')),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'alt_phone' => trim((string) ($_POST['alt_phone'] ?? '')),
    'city' => trim((string) ($_POST['city'] ?? '')),
    'address' => trim((string) ($_POST['address'] ?? '')),
    'tax_number' => trim((string) ($_POST['tax_number'] ?? '')),
    'credit_limit' => (float) ($_POST['credit_limit'] ?? 0),
    'opening_balance' => (float) ($_POST['opening_balance'] ?? 0),
    'status' => trim((string) ($_POST['status'] ?? 'active')),
    'special_pricing_enabled' => !empty($_POST['special_pricing_enabled']) ? 1 : 0,
    'notes' => trim((string) ($_POST['notes'] ?? '')),
];

if ($payload['branch_id'] !== null && !Auth::canAccessBranch($payload['branch_id'])) {
    Response::error('لا تملك صلاحية ربط العميل بهذا الفرع.', 403);
}

if ($id === 0) {
    $payload['created_by'] = Auth::id();
}

try {
    $customerId = (new CrudService())->save('customers', $payload, $id ?: null);
} catch (Throwable $exception) {
    if ($isQuickCreate && stripos($exception->getMessage(), 'Duplicate entry') !== false) {
        try {
            $payload['code'] = generateCustomerCode();
            $customerId = (new CrudService())->save('customers', $payload, $id ?: null);
        } catch (Throwable $retryException) {
            ErrorHandler::log($retryException);
            Response::error('تعذر حفظ العميل السريع.', 500);
        }
    } else {
        ErrorHandler::log($exception);
        Response::error('تعذر حفظ العميل.', 500);
    }
}

log_activity(
    'customers',
    $id > 0 ? 'update' : 'create',
    $id > 0 ? 'تعديل عميل' : 'إضافة عميل',
    'customers',
    $customerId
);

Response::success('تم حفظ العميل.', [
    'id' => $customerId,
    'full_name' => $payload['full_name'],
    'code' => $payload['code'],
    'marketer_id' => $payload['marketer_id'],
]);
function generateCustomerCode(): string
{
    do {
        $code = 'CUST-' . date('YmdHis') . random_int(10, 99);
    } while (customerCodeExists($code));

    return $code;
}

function customerCodeExists(string $code, ?int $ignoreId = null): bool
{
    $sql = 'SELECT id FROM customers WHERE code = :code';
    $params = ['code' => $code];

    if ($ignoreId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $ignoreId;
    }

    $sql .= ' LIMIT 1';

    $stmt = Database::connection()->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}