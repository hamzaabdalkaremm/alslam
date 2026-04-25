<?php

class DashboardService
{
    private DashboardRepository $repository;

    public function __construct()
    {
        $this->repository = new DashboardRepository();
    }

    public function data(int $activitiesPage = 1, int $activitiesPerPage = 15): array
    {
        $cacheKey = $this->cacheKey($activitiesPage, $activitiesPerPage);
        $cached = $this->readCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $stats = [
            'today_sales' => 0,
            'today_purchases' => 0,
            'today_expenses' => 0,
            'customers_due' => 0,
            'suppliers_due' => 0,
            'collections_total' => 0,
            'branches_count' => 0,
            'marketers_count' => 0,
            'customers_count' => 0,
            'suppliers_count' => 0,
            'users_count' => 0,
            'product_count' => 0,
            'net_profit' => 0,
        ];
        $lowStock = [];
        $overdueDebts = [];
        $topProducts = [];
        $topMarketers = [];
        $bestBranches = [];
        $activities = [];
        $activitiesPageData = ['data' => [], 'total' => 0, 'page' => $activitiesPage, 'per_page' => $activitiesPerPage];
        $salesChart = [];

        try {
            $stats = $this->repository->stats();
        } catch (Throwable $e) {
        }

        try {
            $lowStock = $this->repository->lowStock();
        } catch (Throwable $e) {
        }

        try {
            $overdueDebts = $this->repository->overdueDebts();
        } catch (Throwable $e) {
        }

        try {
            $topProducts = $this->repository->topProducts();
        } catch (Throwable $e) {
        }

        try {
            $topMarketers = $this->repository->topMarketers();
        } catch (Throwable $e) {
        }

        try {
            $bestBranches = $this->repository->bestBranches();
        } catch (Throwable $e) {
        }

        try {
            $activitiesPageData = $this->repository->latestActivities($activitiesPage, $activitiesPerPage);
            $activities = $activitiesPageData['data'] ?? [];
        } catch (Throwable $e) {
        }

        try {
            $salesChart = $this->repository->monthlySalesChart();
        } catch (Throwable $e) {
        }

        $payload = [
            'stats' => $stats,
            'low_stock' => $lowStock,
            'overdue_debts' => $overdueDebts,
            'top_products' => $topProducts,
            'top_marketers' => $topMarketers,
            'best_branches' => $bestBranches,
            'activities' => $activities,
            'activities_page' => $activitiesPageData,
            'sales_chart' => $salesChart,
        ];

        $this->writeCache($cacheKey, $payload);

        return $payload;
    }

    private function cacheKey(int $activitiesPage, int $activitiesPerPage): string
    {
        return sha1(json_encode([
            'user' => Auth::id(),
            'role' => Auth::roleSlug(),
            'branches' => Auth::branchIds(),
            'page' => $activitiesPage,
            'per_page' => $activitiesPerPage,
        ], JSON_UNESCAPED_UNICODE));
    }

    private function cacheFile(string $cacheKey): string
    {
        $cachePath = (string) app_config('cache_path');
        return rtrim($cachePath, '/\\') . DIRECTORY_SEPARATOR . 'dashboard-' . $cacheKey . '.json';
    }

    private function readCache(string $cacheKey): ?array
    {
        $ttl = max(0, (int) app_config('dashboard_cache_ttl'));
        if ($ttl === 0) {
            return null;
        }

        $cacheFile = $this->cacheFile($cacheKey);
        if (!is_file($cacheFile)) {
            return null;
        }

        if ((time() - filemtime($cacheFile)) > $ttl) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($cacheFile), true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeCache(string $cacheKey, array $payload): void
    {
        $cachePath = (string) app_config('cache_path');
        if ($cachePath === '') {
            return;
        }

        if (!is_dir($cachePath)) {
            @mkdir($cachePath, 0775, true);
        }

        @file_put_contents(
            $this->cacheFile($cacheKey),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
