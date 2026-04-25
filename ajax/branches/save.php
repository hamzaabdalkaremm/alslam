<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission(!empty($_POST['id']) ? 'branches.update' : 'branches.create');
CSRF::verifyRequest();

$errors = Validator::validate($_POST, ['code' => 'required', 'name_ar' => 'required']);
if ($errors) {
    Response::error('تحقق من بيانات الفرع.', 422, $errors);
}

$id = (int) ($_POST['id'] ?? 0);
if ($id === 0 && !Auth::isSuperAdmin()) {
    Response::error('إضافة فروع جديدة متاحة للمسؤول الرئيسي فقط.', 403);
}

if ($id > 0 && !Auth::canAccessBranch($id)) {
    Response::error('لا تملك صلاحية تعديل هذا الفرع.', 403);
}

$payload = [
    'company_id' => 1,
    'code' => trim((string) ($_POST['code'] ?? '')),
    'name_ar' => trim((string) ($_POST['name_ar'] ?? '')),
    'name_en' => trim((string) ($_POST['name_en'] ?? '')),
    'city' => trim((string) ($_POST['city'] ?? '')),
    'address' => trim((string) ($_POST['address'] ?? '')),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'manager_name' => trim((string) ($_POST['manager_name'] ?? '')),
    'status' => trim((string) ($_POST['status'] ?? 'active')),
    'opening_date' => !empty($_POST['opening_date']) ? $_POST['opening_date'] : null,
    'notes' => trim((string) ($_POST['notes'] ?? '')),
];

try {
    $branchId = (new CrudService())->save('branches', $payload, $id ?: null);
} catch (Throwable $e) {
    Response::error('تعذر حفظ الفرع.');
}

log_activity('branches', $id ? 'update' : 'create', $id ? 'تعديل فرع' : 'إضافة فرع', 'branches', $branchId);
Response::success('تم حفظ بيانات الفرع.', ['id' => $branchId]);
