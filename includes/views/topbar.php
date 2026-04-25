<?php
$company = company_profile();
$activeBranches = branches_options();
$moduleConfig = modules_config(current_module()) ?? [];
$moduleIcon = $moduleConfig['icon'] ?? 'fa-chart-pie';
$logoUrl = company_logo_url();
$branchMap = [];
foreach ($activeBranches as $branchRow) {
    $branchMap[(int) $branchRow['id']] = $branchRow;
}
$defaultBranch = $branchMap[Auth::defaultBranchId() ?? 0] ?? null;
$todayDate = date('Y-m-d');
$todayTime = date('H:i');
?>
<main class="main-content">
    <header class="topbar">
        <div class="topbar-start">
            <button type="button" class="sidebar-toggle btn btn-light" id="sidebarToggle" aria-label="تبديل القائمة الجانبية" aria-expanded="true" aria-controls="appSidebar">
                <i class="fa-solid fa-bars"></i>
                <span class="sidebar-toggle-label">القائمة</span>
            </button>
            <div class="page-meta">
                <span class="page-eyebrow">
                    <i class="fa-solid <?= e($moduleIcon); ?>"></i>
                    <span><?= e($company['name']); ?></span>
                </span>
                <h1><?= e($pageTitle ?? 'لوحة التحكم'); ?></h1>
                <p><?= e($pageSubtitle ?? 'نظام ERP احترافي لإدارة الجملة'); ?></p>
            </div>
        </div>
        <div class="topbar-actions">
            <?php if ($logoUrl !== ''): ?>
                <div class="topbar-company-mark">
                    <img src="<?= e($logoUrl); ?>" alt="<?= e($company['name']); ?>" loading="lazy" decoding="async">
                </div>
            <?php endif; ?>
            <?php if ($defaultBranch): ?>
                <div class="topbar-chip">
                    <i class="fa-solid fa-location-dot"></i>
                    <span><?= e($defaultBranch['name_ar']); ?></span>
                </div>
            <?php endif; ?>
            <div class="topbar-chip topbar-chip-accent">
                <i class="fa-regular fa-calendar"></i>
                <span><?= e($todayDate); ?></span>
            </div>
            <div class="topbar-chip">
                <i class="fa-regular fa-clock"></i>
                <span><?= e($todayTime); ?></span>
            </div>
            <div class="user-badge">
                <span><?= e(Auth::user()['full_name'] ?? ''); ?></span>
                <small><?= e(Auth::user()['role_name'] ?? ''); ?></small>
            </div>
            <a class="btn btn-light" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> خروج</a>
        </div>
    </header>
    <?php if ($successMessage = flash('success')): ?>
        <div class="alert alert-success"><?= e($successMessage); ?></div>
    <?php endif; ?>
    <?php if ($errorMessage = flash('error')): ?>
        <div class="alert alert-danger"><?= e($errorMessage); ?></div>
    <?php endif; ?>
    <script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type=number]').forEach(function (input) {
        
        // منع التغيير بالسكروول
        input.addEventListener('wheel', function (e) {
            e.preventDefault();
        });

        // حل إضافي: منع الأسهم ↑ ↓
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                e.preventDefault();
            }
        });

        // حل احتياطي: إزالة التركيز عند السكروول
        input.addEventListener('focus', function () {
            input.addEventListener('wheel', preventScroll, { passive: false });
        });

        input.addEventListener('blur', function () {
            input.removeEventListener('wheel', preventScroll);
        });

        function preventScroll(e) {
            e.preventDefault();
        }

    });
});
</script>
