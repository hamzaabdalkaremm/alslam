<?php
$company = company_profile();
$appName = $company['name'] ?: app_config('name');
$currentModule = current_module();
$needsCharts = $currentModule === 'dashboard';
$fontAwesomeUrl = vendor_asset('vendor/fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');
$appCssUrl = asset('css/app.css');
$chartScriptUrl = $needsCharts
    ? vendor_asset('vendor/chartjs/chart.umd.min.js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js')
    : '';
$bodyClass = 'app-body module-' . preg_replace('/[^a-z0-9_-]+/i', '-', $currentModule);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2b79d3">
    <title><?= e($pageTitle ?? $appName); ?></title>
    <link rel="preload" href="<?= e($appCssUrl); ?>" as="style">
    <?php if ($fontAwesomeUrl !== '' && str_starts_with($fontAwesomeUrl, 'https://')): ?>
        <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <?php endif; ?>
    <?php if ($chartScriptUrl !== '' && str_starts_with($chartScriptUrl, 'https://')): ?>
        <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <?php endif; ?>
    <?php if ($fontAwesomeUrl !== ''): ?>
        <link rel="stylesheet" href="<?= e($fontAwesomeUrl); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e($appCssUrl); ?>">
</head>
<body class="<?= e($bodyClass); ?>">
<div class="app-shell">
<div class="sidebar-overlay" id="sidebarOverlay"></div>
