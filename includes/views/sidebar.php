<?php
$modules = modules_for_sidebar();
$company = company_profile();
$logoUrl = company_logo_url();
$user = Auth::user() ?? [];
?>
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-panel">
        <div class="brand">
            <div class="brand-mark">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= e($logoUrl); ?>" alt="<?= e($company['name']); ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <i class="fa-solid fa-building-shield"></i>
                <?php endif; ?>
            </div>
            <div class="brand-copy">
                <span class="brand-badge">ERP</span>
                <strong><?= e($company['name']); ?></strong>
                <span>لوحة تشغيل إدارية حديثة</span>
            </div>
        </div>
        <div class="sidebar-section-label">الوحدات الرئيسية</div>
        <nav class="sidebar-nav" aria-label="التنقل الرئيسي">
            <?php foreach ($modules as $key => $module): ?>
                <a href="index.php?module=<?= e($key); ?>" class="nav-link <?= e(selected_module_class($key)); ?>" title="<?= e($module['label']); ?>">
                    <span class="nav-link-icon"><?= module_icon_svg($key); ?></span>
                    <span class="nav-link-label"><?= e($module['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <small>المستخدم الحالي</small>
            <strong><?= e($user['full_name'] ?? ''); ?></strong>
            <span><?= e($user['role_name'] ?? ''); ?></span>
        </div>
    </div>
</aside>
