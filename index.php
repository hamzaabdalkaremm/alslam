<?php
require_once __DIR__ . '/config/bootstrap.php';
Auth::requireLogin();

$module = current_module();
if (!isset($_GET['module']) || $_GET['module'] === '') {
    $module = 'dashboard';
}
$modules = modules_config();

if (!isset($modules[$module])) {
    $module = 'dashboard';
}

Auth::requirePermission($modules[$module]['permission']);

$moduleFile = __DIR__ . '/modules/' . $module . '/index.php';
$pageTitle = $modules[$module]['label'];
$pageSubtitles = [
    'dashboard' => 'لوحة تنفيذية موحدة لإدارة الشركة الأم والفروع والمؤشرات المالية والتجارية',
    'branches' => 'إدارة الفروع والمخازن وربط كل فرع بموظفيه وحركته التشغيلية',
    'marketers' => 'إدارة المسوقين والعمولات والعملاء والتحصيلات المرتبطة بهم',
    'products' => 'إدارة الأصناف والأسعار والوحدات والباركود وربطها بالحسابات',
    'inventory' => 'مراقبة الرصيد وكرت الصنف والجرد والتسويات والتحويلات',
    'sales' => 'فواتير البيع وربطها بالفرع والمسوق والمخزن والتحصيل',
    'purchases' => 'فواتير الشراء وربطها بالمورد والفرع والمخزن والتكلفة',
    'customers' => 'إدارة العملاء والسقوف الائتمانية وربطهم بالفروع والمسوقين',
    'suppliers' => 'إدارة الموردين وربطهم بالفروع والمشتريات والمستحقات',
    'debts' => 'متابعة الديون والتحصيلات حسب العميل والمورد والمسوق والفرع',
    'expenses' => 'إدارة المصروفات وربطها بالفروع والحسابات والتصنيفات',
    'cashbox' => 'الخزائن وسندات القبض والصرف والحركة اليومية',
    'accounts' => 'شجرة الحسابات والقيود اليومية والربط المحاسبي التلقائي',
    'returns' => 'مرتجعات البيع والشراء مع التحديث الفوري للمخزون',
    'reports' => 'تقارير تشغيلية ومالية قابلة للطباعة والتصدير',
    'users' => 'إدارة المستخدمين والأدوار والصلاحيات والفروع المسموح بها',
    'settings' => 'الهوية الرسمية للشركة وإعدادات النظام والطباعة والترقيم',
];
$pageSubtitle = $pageSubtitles[$module] ?? 'تشغيل وإدارة البيانات اليومية';

require_once __DIR__ . '/includes/views/header.php';
require_once __DIR__ . '/includes/views/sidebar.php';
require_once __DIR__ . '/includes/views/topbar.php';
require_once $moduleFile;
require_once __DIR__ . '/includes/views/footer.php';
