<?php
require_once __DIR__ . '/config/bootstrap.php';

if (Auth::check()) {
    redirect('index.php');
}

if (is_post()) {
    if (!CSRF::validate($_POST['_token'] ?? null)) {
        flash('error', 'رمز الحماية غير صالح.');
        redirect('login.php');
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    try {
        Database::connection();
    } catch (Throwable $e) {
        $message = 'تعذر الاتصال بقاعدة البيانات. راجع بيانات الاستضافة في config/database.local.php.';
        if ((bool) app_config('debug')) {
            $message .= ' السبب: ' . $e->getMessage();
        }
        flash('error', $message);
        redirect('login.php');
    }

    if (Auth::attempt($username, $password)) {
        redirect('index.php?module=dashboard');
    }

    flash('error', 'بيانات الدخول غير صحيحة.');
    redirect('login.php');
}

$company = company_profile();
$logoUrl = company_logo_url();
$fontAwesomeUrl = vendor_asset('vendor/fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');
$appCssUrl = asset('css/app.css');
$serviceWorkerPath = __DIR__ . '/service-worker.js';
$serviceWorkerUrl = is_file($serviceWorkerPath) ? 'service-worker.js?v=' . filemtime($serviceWorkerPath) : '';
$offlinePath = __DIR__ . '/offline.html';
$offlineUrl = is_file($offlinePath) ? 'offline.html?v=' . filemtime($offlinePath) : '';
$registerUrl = is_file(__DIR__ . '/register.php') ? 'register.php' : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2b79d3">
    <title>تسجيل الدخول - <?= e($company['name']); ?></title>
    <link rel="preload" href="<?= e($appCssUrl); ?>" as="style">
    <?php if ($fontAwesomeUrl !== '' && str_starts_with($fontAwesomeUrl, 'https://')): ?>
        <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <?php endif; ?>
    <?php if ($fontAwesomeUrl !== ''): ?>
        <link rel="stylesheet" href="<?= e($fontAwesomeUrl); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e($appCssUrl); ?>">
</head>
<body class="login-page">
    <div class="login-wrapper">
        <section class="login-showcase">
            <span class="login-badge">نظام ERP مبسط وعملي</span>
            <h1><?= e($company['name']); ?></h1>
            <p>منصة عربية حديثة لإدارة الفروع والمبيعات والمشتريات والمخزون والحسابات والصلاحيات.</p>
            <div class="login-company-card">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= e($logoUrl); ?>" alt="<?= e($company['name']); ?>" class="login-company-logo" decoding="async" fetchpriority="high">
                <?php endif; ?>
                <div>
                    <strong><?= e($company['phone']); ?></strong>
                    <span><?= e($company['email']); ?></span>
                    <span><?= e($company['address']); ?></span>
                </div>
            </div>
            <div class="login-feature-list">
                <div class="login-feature">
                    <span class="login-feature-icon"><i class="fa-solid fa-building"></i></span>
                    <div>
                        <strong>إدارة شركة أم وفروع</strong>
                        <span>ربط كل فرع بمخازنه ومستخدميه وحركته التشغيلية والمالية.</span>
                    </div>
                </div>
                <div class="login-feature">
                    <span class="login-feature-icon"><i class="fa-solid fa-sitemap"></i></span>
                    <div>
                        <strong>شجرة حسابات وقيود</strong>
                        <span>دعم ربط العمليات اليومية بالقيود المحاسبية والتقارير المالية.</span>
                    </div>
                </div>
                <div class="login-feature">
                    <span class="login-feature-icon"><i class="fa-solid fa-user-shield"></i></span>
                    <div>
                        <strong>صلاحيات دقيقة وآمنة</strong>
                        <span>أدوار متعددة وصلاحيات حسب الوحدة والفرع ونوع العملية.</span>
                    </div>
                </div>
            </div>
        </section>
        <form class="login-card" method="post">
            <div class="login-brand">
                <span class="login-badge subtle">تسجيل الدخول</span>
                <h2>ابدأ جلسة العمل</h2>
                <p>أدخل بيانات الحساب للانتقال إلى لوحة التحكم التشغيلية.</p>
            </div>
            <?= csrf_field(); ?>
            <?php if ($error = flash('error')): ?>
                <div class="alert alert-danger"><?= e($error); ?></div>
            <?php endif; ?>
            <label>اسم المستخدم</label>
            <input type="text" name="username" required value="<?= e(old('username')); ?>">
            <label>كلمة المرور</label>
            <input type="password" name="password" required>
            <button type="submit" class="btn btn-primary btn-block">دخول</button>
            <?php if ($registerUrl !== ''): ?>
                <a href="<?= e($registerUrl); ?>" class="btn btn-light btn-block mt-1">إنشاء مستخدم جديد</a>
            <?php endif; ?>
        </form>
    </div>
    <script>
    window.appConfig = {
        currentModule: 'login',
        serviceWorkerUrl: '<?= e($serviceWorkerUrl); ?>',
        offlineUrl: '<?= e($offlineUrl); ?>',
        chartScriptUrl: ''
    };
    </script>
    <script src="<?= e(asset('js/app.js')); ?>" defer></script>
</body>
</html>
