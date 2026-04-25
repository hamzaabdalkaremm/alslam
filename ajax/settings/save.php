<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('settings.update');
CSRF::verifyRequest();

$settings = $_POST['settings'] ?? [];
if (!$settings && empty($_FILES['company_logo']['name']) && empty($_FILES['company_stamp']['name'])) {
    Response::error('لا توجد إعدادات للحفظ.');
}

$company = company_profile();
$logoPath = handleBrandUpload('company_logo', (string) ($company['logo_path'] ?? ''), 'logo');
$stampPath = handleBrandUpload('company_stamp', (string) ($company['stamp_path'] ?? ''), 'stamp');

if ($logoPath !== null) {
    $settings['logo_path'] = $logoPath;
}
if ($stampPath !== null) {
    $settings['stamp_path'] = $stampPath;
}

$pdo = Database::connection();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        'INSERT INTO store_settings (setting_key, setting_value, setting_group, updated_by)
         VALUES (:setting_key, :setting_value, :setting_group, :updated_by)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
    );

    foreach ($settings as $key => $value) {
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $value,
            'setting_group' => resolveSettingGroup($key),
            'updated_by' => Auth::id(),
        ]);
    }

    $companyStmt = $pdo->prepare(
        'INSERT INTO companies
         (id, code, name_ar, name_en, logo_path, stamp_path, phone, email, address, commercial_register, tax_number, invoice_footer, currency_code, is_active)
         VALUES
         (1, :code, :name_ar, :name_en, :logo_path, :stamp_path, :phone, :email, :address, :commercial_register, :tax_number, :invoice_footer, :currency_code, 1)
         ON DUPLICATE KEY UPDATE
             name_ar = VALUES(name_ar),
             name_en = VALUES(name_en),
             logo_path = VALUES(logo_path),
             stamp_path = VALUES(stamp_path),
             phone = VALUES(phone),
             email = VALUES(email),
             address = VALUES(address),
             commercial_register = VALUES(commercial_register),
             tax_number = VALUES(tax_number),
             invoice_footer = VALUES(invoice_footer),
             currency_code = VALUES(currency_code)'
    );
    $companyStmt->execute([
        'code' => 'SALAM-GROUP',
        'name_ar' => trim((string) ($settings['company_name'] ?? $company['name'] ?? app_config('name'))),
        'name_en' => trim((string) ($settings['company_name_en'] ?? $company['name_en'] ?? '')),
        'logo_path' => $settings['logo_path'] ?? ($company['logo_path'] ?? ''),
        'stamp_path' => $settings['stamp_path'] ?? ($company['stamp_path'] ?? ''),
        'phone' => trim((string) ($settings['company_phone'] ?? $company['phone'] ?? '')),
        'email' => trim((string) ($settings['company_email'] ?? $company['email'] ?? '')),
        'address' => trim((string) ($settings['company_address'] ?? $company['address'] ?? '')),
        'commercial_register' => trim((string) ($settings['company_register'] ?? $company['commercial_register'] ?? '')),
        'tax_number' => trim((string) ($settings['company_tax_number'] ?? $company['tax_number'] ?? '')),
        'invoice_footer' => trim((string) ($settings['invoice_footer'] ?? $company['invoice_footer'] ?? '')),
        'currency_code' => trim((string) ($settings['currency_code'] ?? setting('currency_code', 'LYD'))),
    ]);

    $pdo->commit();
    settings_map(true);
    company_profile(true);
    log_activity('settings', 'update', 'تحديث إعدادات الشركة والنظام');
    Response::success('تم حفظ الإعدادات.');
} catch (Throwable $e) {
    $pdo->rollBack();
    Response::error('تعذر حفظ الإعدادات.');
}

function resolveSettingGroup(string $key): string
{
    return match (true) {
        str_contains($key, 'company_'), $key === 'logo_path', $key === 'stamp_path' => 'branding',
        str_contains($key, 'invoice'), str_contains($key, 'print'), str_contains($key, 'receipt_prefix'), str_contains($key, 'payment_prefix') => 'printing',
        str_contains($key, 'account'), $key === 'enable_auto_journal', $key === 'currency', $key === 'currency_code', $key === 'tax_rate' => 'finance',
        str_contains($key, 'prefix'), $key === 'journal_prefix' => 'numbering',
        default => 'general',
    };
}

function handleBrandUpload(string $field, string $existingPath, string $prefix): ?string
{
    if (empty($_FILES[$field]['name'])) {
        return null;
    }

    if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }

    $extension = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
    if (!in_array($extension, $allowed, true)) {
        Response::error('صيغة الملف غير مدعومة. المسموح: PNG, JPG, JPEG, WEBP, SVG.');
    }

    $directory = app_config('upload_path') . DIRECTORY_SEPARATOR . 'branding';
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $fileName = $prefix . '-' . date('YmdHis') . '.' . $extension;
    $target = $directory . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        Response::error('تعذر رفع الملف.');
    }

    if ($existingPath !== '' && str_starts_with($existingPath, 'assets/uploads/branding/')) {
        $absolute = app_config('base_path') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $existingPath);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    return 'assets/uploads/branding/' . $fileName;
}
