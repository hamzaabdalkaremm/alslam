    </main>
</div>
<?php
$serviceWorkerPath = __DIR__ . '/../../service-worker.js';
$serviceWorkerUrl = is_file($serviceWorkerPath) ? 'service-worker.js?v=' . filemtime($serviceWorkerPath) : '';
$offlinePath = __DIR__ . '/../../offline.html';
$offlineUrl = is_file($offlinePath) ? 'offline.html?v=' . filemtime($offlinePath) : '';
$chartScriptUrl = current_module() === 'dashboard'
    ? vendor_asset('vendor/chartjs/chart.umd.min.js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js')
    : '';
?>
<script>
window.appConfig = {
    csrfToken: '<?= e(CSRF::token()); ?>',
    currentModule: '<?= e(current_module()); ?>',
    serviceWorkerUrl: '<?= e($serviceWorkerUrl); ?>',
    offlineUrl: '<?= e($offlineUrl); ?>',
    chartScriptUrl: '<?= e($chartScriptUrl); ?>'
};
</script>
<script src="<?= e(asset('js/app.js')); ?>" defer></script>
</body>
</html>
