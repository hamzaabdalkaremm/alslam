<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('expenses.create');
CSRF::verifyRequest();

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    Response::error('اسم التصنيف مطلوب.');
}

$categoryId = (new CrudService())->save('expense_categories', [
    'name' => $name,
    'description' => trim($_POST['description'] ?? ''),
]);
log_activity('expenses', 'create', 'إضافة تصنيف مصروف', 'expense_categories', $categoryId);
Response::success('تمت إضافة التصنيف.');
