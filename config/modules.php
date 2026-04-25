<?php

return [
    'dashboard' => ['label' => 'لوحة التحكم', 'icon' => 'fa-chart-line', 'permission' => 'dashboard.view'],
    'branches' => ['label' => 'الفروع', 'icon' => 'fa-building', 'permission' => 'branches.view'],
    'marketers' => ['label' => 'المسوقون', 'icon' => 'fa-user-tie', 'permission' => 'marketers.view'],
    'products' => ['label' => 'المنتجات', 'icon' => 'fa-boxes-stacked', 'permission' => 'products.view'],
    'inventory' => ['label' => 'المخزون', 'icon' => 'fa-warehouse', 'permission' => 'inventory.view'],
    'sales' => ['label' => 'المبيعات', 'icon' => 'fa-cash-register', 'permission' => 'sales.view'],
    'purchases' => ['label' => 'المشتريات', 'icon' => 'fa-cart-plus', 'permission' => 'purchases.view'],
    'customers' => ['label' => 'العملاء', 'icon' => 'fa-users', 'permission' => 'customers.view'],
    'suppliers' => ['label' => 'الموردون', 'icon' => 'fa-truck-field', 'permission' => 'suppliers.view'],
    'debts' => ['label' => 'الديون والتحصيل', 'icon' => 'fa-hand-holding-dollar', 'permission' => 'debts.view'],
    'expenses' => ['label' => 'المصروفات', 'icon' => 'fa-file-invoice-dollar', 'permission' => 'expenses.view'],
    'cashbox' => ['label' => 'الخزينة', 'icon' => 'fa-vault', 'permission' => 'cashbox.view'],
    'accounts' => ['label' => 'الحسابات', 'icon' => 'fa-sitemap', 'permission' => 'accounts.view'],
    'returns' => ['label' => 'المرتجعات', 'icon' => 'fa-rotate-left', 'permission' => 'sales.return'],
    'reports' => ['label' => 'التقارير', 'icon' => 'fa-chart-pie', 'permission' => 'reports.view'],
    'users' => ['label' => 'المستخدمون والصلاحيات', 'icon' => 'fa-user-shield', 'permission' => 'users.roles'],
    'settings' => ['label' => 'الإعدادات', 'icon' => 'fa-gear', 'permission' => 'settings.view'],
];
