<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission(!empty($_POST['id']) ? 'suppliers.update' : 'suppliers.create');
CSRF::verifyRequest();

$errors = Validator::validate($_POST, ['code' => 'required', 'company_name' => 'required']);
if ($errors) {
    Response::error('تحقق من بيانات المورد.', 422, $errors);
}

$id = (int) ($_POST['id'] ?? 0);
$payload = [
    'branch_id' => !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : null,
    'code' => trim($_POST['code']),
    'company_name' => trim($_POST['company_name']),
    'contact_name' => trim($_POST['contact_name'] ?? ''),
    'phone' => trim($_POST['phone'] ?? ''),
    'alt_phone' => trim($_POST['alt_phone'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'city' => trim($_POST['city'] ?? ''),
    'address' => trim($_POST['address'] ?? ''),
    'tax_number' => trim($_POST['tax_number'] ?? ''),
    'opening_balance' => (float) ($_POST['opening_balance'] ?? 0),
    'status' => trim((string) ($_POST['status'] ?? 'active')),
    'notes' => trim($_POST['notes'] ?? ''),
];

if ($payload['branch_id'] !== null && !Auth::canAccessBranch($payload['branch_id'])) {
    Response::error('لا تملك صلاحية ربط المورد بهذا الفرع.', 403);
}

if (!$id) {
    $payload['created_by'] = Auth::id();
}

$recordId = (new CrudService())->save('suppliers', $payload, $id ?: null);
log_activity('suppliers', $id ? 'update' : 'create', ($id ? 'تحديث' : 'إضافة') . ' مورد', 'suppliers', $recordId);
Response::success('تم حفظ المورد.');
