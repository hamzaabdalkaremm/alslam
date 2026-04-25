SET NAMES utf8mb4;

/*
 |----------------------------------------------------------------------
 | ERP extension migration for:
 | مجموعة السلام لاستيراد المواد الغدائية
 |----------------------------------------------------------------------
 | This script upgrades the current wholesale system into a branch-aware,
 | finance-aware ERP-lite structure without dropping live data.
 */

CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200) NULL,
    logo_path VARCHAR(255) NULL,
    stamp_path VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    website VARCHAR(150) NULL,
    address VARCHAR(255) NULL,
    commercial_register VARCHAR(100) NULL,
    tax_number VARCHAR(100) NULL,
    invoice_footer TEXT NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'LYD',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    name_ar VARCHAR(180) NOT NULL,
    name_en VARCHAR(180) NULL,
    city VARCHAR(120) NULL,
    address VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    manager_name VARCHAR(150) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    opening_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_branches_company_id (company_id),
    KEY idx_branches_deleted_at (deleted_at),
    CONSTRAINT fk_branches_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    manager_name VARCHAR(150) NULL,
    address VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_warehouses_branch_id (branch_id),
    CONSTRAINT fk_warehouses_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    address VARCHAR(255) NULL,
    national_id VARCHAR(100) NULL,
    commission_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
    marketer_type VARCHAR(50) NOT NULL DEFAULT 'sales_rep',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    employment_date DATE NULL,
    avatar_path VARCHAR(255) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketer_branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    marketer_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_marketer_branch (marketer_id, branch_id),
    CONSTRAINT fk_marketer_branches_marketer FOREIGN KEY (marketer_id) REFERENCES marketers(id) ON DELETE CASCADE,
    CONSTRAINT fk_marketer_branches_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    is_allowed TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_permission (user_id, permission_id),
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_branch (user_id, branch_id),
    CONSTRAINT fk_user_branches_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_branches_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    parent_id BIGINT UNSIGNED NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    name_en VARCHAR(180) NULL,
    account_type VARCHAR(30) NOT NULL,
    account_group VARCHAR(60) NULL,
    level_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_group TINYINT(1) NOT NULL DEFAULT 0,
    accepts_entries TINYINT(1) NOT NULL DEFAULT 1,
    system_key VARCHAR(100) NULL UNIQUE,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_accounts_parent_id (parent_id),
    KEY idx_accounts_branch_id (branch_id),
    CONSTRAINT fk_accounts_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_accounts_parent FOREIGN KEY (parent_id) REFERENCES accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_accounts_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NULL,
    entry_no VARCHAR(60) NOT NULL UNIQUE,
    entry_date DATETIME NOT NULL,
    source_type VARCHAR(60) NULL,
    source_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'posted',
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_journal_entries_branch_id (branch_id),
    KEY idx_journal_entries_source (source_type, source_id),
    CONSTRAINT fk_journal_entries_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_entry_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_journal_entry_lines_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_journal_entry_lines_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_opening_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    fiscal_year YEAR NOT NULL,
    debit_opening DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit_opening DECIMAL(14,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_account_balance_year (account_id, branch_id, fiscal_year),
    CONSTRAINT fk_account_balances_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cashboxes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NULL,
    account_id BIGINT UNSIGNED NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NULL,
    account_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    account_name VARCHAR(150) NULL,
    account_number VARCHAR(100) NULL,
    iban VARCHAR(100) NULL,
    swift_code VARCHAR(50) NULL,
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branch_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_no VARCHAR(60) NOT NULL UNIQUE,
    from_branch_id BIGINT UNSIGNED NOT NULL,
    to_branch_id BIGINT UNSIGNED NOT NULL,
    from_warehouse_id BIGINT UNSIGNED NULL,
    to_warehouse_id BIGINT UNSIGNED NULL,
    transfer_date DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'completed',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branch_transfer_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_transfer_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_unit_id BIGINT UNSIGNED NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_branch_transfer_items_transfer FOREIGN KEY (branch_transfer_id) REFERENCES branch_transfers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NULL,
    cashbox_id BIGINT UNSIGNED NULL,
    bank_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    marketer_id BIGINT UNSIGNED NULL,
    receipt_no VARCHAR(60) NOT NULL UNIQUE,
    receipt_date DATETIME NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NULL,
    cashbox_id BIGINT UNSIGNED NULL,
    bank_id BIGINT UNSIGNED NULL,
    supplier_id BIGINT UNSIGNED NULL,
    payment_no VARCHAR(60) NOT NULL UNIQUE,
    payment_date DATETIME NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    marketer_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    source_type VARCHAR(60) NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    commission_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'due',
    due_date DATE NULL,
    paid_date DATE NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_commissions_marketer_id (marketer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    branch_id BIGINT UNSIGNED NULL,
    module_key VARCHAR(100) NULL,
    severity VARCHAR(30) NOT NULL DEFAULT 'info',
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    reference_type VARCHAR(60) NULL,
    reference_id BIGINT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NULL,
    assigned_to BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    priority VARCHAR(30) NOT NULL DEFAULT 'medium',
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    due_date DATE NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE roles ADD COLUMN IF NOT EXISTS is_system TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS default_branch_id BIGINT UNSIGNED NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS title VARCHAR(120) NULL AFTER password_hash;
ALTER TABLE activity_logs ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER user_id;
ALTER TABLE expense_categories ADD COLUMN IF NOT EXISTS account_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS marketer_id BIGINT UNSIGNED NULL AFTER branch_id;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL AFTER alt_phone;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER opening_balance;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER alt_phone;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL AFTER email;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER opening_balance;
ALTER TABLE products ADD COLUMN IF NOT EXISTS inventory_account_id BIGINT UNSIGNED NULL AFTER base_unit_id;
ALTER TABLE products ADD COLUMN IF NOT EXISTS revenue_account_id BIGINT UNSIGNED NULL AFTER inventory_account_id;
ALTER TABLE products ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER image_path;
ALTER TABLE product_batches ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE product_batches ADD COLUMN IF NOT EXISTS warehouse_id BIGINT UNSIGNED NULL AFTER branch_id;
ALTER TABLE sales ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE sales ADD COLUMN IF NOT EXISTS warehouse_id BIGINT UNSIGNED NULL AFTER branch_id;
ALTER TABLE sales ADD COLUMN IF NOT EXISTS marketer_id BIGINT UNSIGNED NULL AFTER warehouse_id;
ALTER TABLE sales ADD COLUMN IF NOT EXISTS approval_status VARCHAR(30) NOT NULL DEFAULT 'approved' AFTER status;
ALTER TABLE sales ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) NOT NULL DEFAULT 'cash' AFTER pricing_tier;
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS warehouse_id BIGINT UNSIGNED NULL AFTER branch_id;
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS approval_status VARCHAR(30) NOT NULL DEFAULT 'approved' AFTER status;
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS import_costs DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER tax_value;
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS account_id BIGINT UNSIGNED NULL AFTER expense_category_id;
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS reference_no VARCHAR(60) NULL AFTER account_id;
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'approved' AFTER payment_method;
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS approved_by BIGINT UNSIGNED NULL AFTER status;
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER approved_by;
ALTER TABLE cashbox_entries ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE cashbox_entries ADD COLUMN IF NOT EXISTS cashbox_id BIGINT UNSIGNED NULL AFTER branch_id;
ALTER TABLE cashbox_entries ADD COLUMN IF NOT EXISTS account_id BIGINT UNSIGNED NULL AFTER cashbox_id;
ALTER TABLE debt_collections ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE debt_collections ADD COLUMN IF NOT EXISTS marketer_id BIGINT UNSIGNED NULL AFTER branch_id;
ALTER TABLE debt_collections ADD COLUMN IF NOT EXISTS cashbox_id BIGINT UNSIGNED NULL AFTER marketer_id;
ALTER TABLE debt_collections ADD COLUMN IF NOT EXISTS bank_id BIGINT UNSIGNED NULL AFTER cashbox_id;
ALTER TABLE sale_returns ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE purchase_returns ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS warehouse_id BIGINT UNSIGNED NULL AFTER branch_id;
ALTER TABLE inventory_adjustments ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE inventory_adjustments ADD COLUMN IF NOT EXISTS warehouse_id BIGINT UNSIGNED NULL AFTER branch_id;
ALTER TABLE inventory_adjustments ADD COLUMN IF NOT EXISTS approval_status VARCHAR(30) NOT NULL DEFAULT 'approved' AFTER adjustment_date;
ALTER TABLE inventory_counts ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE inventory_counts ADD COLUMN IF NOT EXISTS warehouse_id BIGINT UNSIGNED NULL AFTER branch_id;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    branch_id BIGINT UNSIGNED NULL,
    module_key VARCHAR(100) NULL,
    action_key VARCHAR(100) NULL,
    description TEXT NOT NULL,
    reference_table VARCHAR(100) NULL,
    reference_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_logs_user_id (user_id),
    KEY idx_activity_logs_branch_id (branch_id),
    KEY idx_activity_logs_module_action (module_key, action_key),
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
ALTER TABLE sales ADD COLUMN IF NOT EXISTS printable_token VARCHAR(255) NULL;
ALTER TABLE sales ADD COLUMN IF NOT EXISTS sold_by BIGINT UNSIGNED NULL;
ALTER TABLE sales ADD COLUMN IF NOT EXISTS delivered_by BIGINT UNSIGNED NULL;
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS purchased_by BIGINT UNSIGNED NULL;

INSERT IGNORE INTO companies (id, code, name_ar, name_en, phone, email, address, commercial_register, tax_number, invoice_footer, currency_code, is_active)
VALUES (1, 'SALAM-GROUP', 'مجموعة السلام لاستيراد المواد الغدائية', 'Al Salam Food Import Group', '0912345678', 'info@alsalam-group.ly', 'طرابلس - ليبيا', 'CR-2026-001', 'TAX-2026-001', 'شكراً لتعاملكم معنا.', 'LYD', 1);

INSERT IGNORE INTO branches (id, company_id, code, name_ar, city, address, phone, email, manager_name, status, opening_date, notes)
VALUES
    (1, 1, 'BR-HQ', 'الفرع الرئيسي', 'طرابلس', 'طرابلس', '0911000001', 'hq@alsalam-group.ly', 'المدير العام', 'active', '2024-01-01', 'المقر الرئيسي'),
    (2, 1, 'BR-BEN', 'فرع بنغازي', 'بنغازي', 'بنغازي', '0911000002', 'benghazi@alsalam-group.ly', 'مدير الفرع', 'active', '2024-06-15', 'الفرع الشرقي');

INSERT IGNORE INTO warehouses (id, branch_id, code, name, manager_name, address, status, notes)
VALUES
    (1, 1, 'WH-HQ-01', 'مخزن الفرع الرئيسي', 'أمين المخزن الرئيسي', 'طرابلس', 'active', 'المخزن المركزي'),
    (2, 2, 'WH-BEN-01', 'مخزن بنغازي', 'أمين مخزن بنغازي', 'بنغازي', 'active', 'مخزن الفرع الشرقي');

INSERT IGNORE INTO roles (id, name, slug, description, is_system) VALUES
    (1, 'Super Admin', 'super_admin', 'صلاحيات كاملة على النظام', 1),
    (2, 'المدير العام', 'general_manager', 'إدارة وتشغيل شامل على مستوى الشركة', 1),
    (3, 'المدير المالي', 'finance_manager', 'إدارة مالية ومحاسبية كاملة', 1),
    (4, 'المحاسب', 'accountant', 'قيود وتقارير وحركات مالية', 1),
    (5, 'مدير الفرع', 'branch_manager', 'إدارة عمليات الفرع والتقارير', 1),
    (6, 'أمين المخزن', 'storekeeper', 'إدارة المخزون والمخازن', 1),
    (7, 'موظف المبيعات', 'sales_employee', 'فواتير البيع والعملاء', 1),
    (8, 'المسوق', 'marketer', 'متابعة العملاء والتحصيل والعمولات', 1),
    (9, 'المراقب', 'observer', 'عرض التقارير فقط', 1);

INSERT IGNORE INTO permissions (module_key, action_key, label) VALUES
    ('branches', 'view', 'عرض الفروع'),
    ('branches', 'create', 'إضافة فرع'),
    ('branches', 'update', 'تعديل فرع'),
    ('branches', 'delete', 'تعطيل فرع'),
    ('marketers', 'view', 'عرض المسوقين'),
    ('marketers', 'create', 'إضافة مسوق'),
    ('marketers', 'update', 'تعديل مسوق'),
    ('marketers', 'delete', 'تعطيل مسوق'),
    ('accounts', 'view', 'عرض شجرة الحسابات'),
    ('accounts', 'create', 'إضافة حساب'),
    ('accounts', 'update', 'تعديل حساب'),
    ('accounts', 'delete', 'تعطيل حساب'),
    ('accounts', 'journal', 'إضافة قيد يومي'),
    ('users', 'view', 'عرض المستخدمين'),
    ('users', 'roles', 'إدارة الأدوار'),
    ('reports', 'export', 'تصدير التقارير'),
    ('sales', 'approve', 'اعتماد المبيعات'),
    ('purchases', 'approve', 'اعتماد المشتريات'),
    ('expenses', 'approve', 'اعتماد المصروفات');

INSERT IGNORE INTO user_role_permissions (role_id, permission_id)
SELECT 1, p.id
FROM permissions p
WHERE p.module_key IN ('branches', 'marketers', 'accounts', 'users')
   OR (p.module_key IN ('sales', 'purchases', 'expenses') AND p.action_key = 'approve');

INSERT IGNORE INTO accounts (id, company_id, branch_id, parent_id, code, name, account_type, account_group, level_no, is_group, accepts_entries, system_key, status) VALUES
    (1, 1, NULL, NULL, '1000', 'الأصول', 'asset', 'assets', 1, 1, 0, NULL, 'active'),
    (2, 1, NULL, 1, '1100', 'الأصول المتداولة', 'asset', 'current_assets', 2, 1, 0, NULL, 'active'),
    (3, 1, NULL, 2, '1110', 'الصندوق الرئيسي', 'asset', 'cash', 3, 0, 1, 'cash_main', 'active'),
    (4, 1, NULL, 2, '1120', 'البنك الرئيسي', 'asset', 'bank', 3, 0, 1, 'bank_main', 'active'),
    (5, 1, NULL, 2, '1130', 'العملاء', 'asset', 'receivables', 3, 0, 1, 'customers_receivable', 'active'),
    (6, 1, NULL, 2, '1140', 'المخزون', 'asset', 'inventory', 3, 0, 1, 'inventory_main', 'active'),
    (7, 1, NULL, NULL, '2000', 'الالتزامات', 'liability', 'liabilities', 1, 1, 0, NULL, 'active'),
    (8, 1, NULL, 7, '2100', 'الموردون', 'liability', 'payables', 2, 0, 1, 'suppliers_payable', 'active'),
    (9, 1, NULL, NULL, '4000', 'الإيرادات', 'revenue', 'revenue', 1, 1, 0, NULL, 'active'),
    (10, 1, NULL, 9, '4100', 'إيرادات المبيعات', 'revenue', 'sales_revenue', 2, 0, 1, 'sales_revenue', 'active'),
    (11, 1, NULL, NULL, '5000', 'المصروفات', 'expense', 'expenses', 1, 1, 0, NULL, 'active'),
    (12, 1, NULL, 11, '5100', 'مصروفات تشغيلية', 'expense', 'operating_expenses', 2, 0, 1, 'operating_expenses', 'active'),
    (13, 1, NULL, 11, '5110', 'مصروفات نقل', 'expense', 'transport_expenses', 2, 0, 1, 'transport_expenses', 'active'),
    (14, 1, NULL, 11, '5120', 'مصروفات رواتب', 'expense', 'salary_expenses', 2, 0, 1, 'salary_expenses', 'active'),
    (15, 1, NULL, 11, '5130', 'مصروفات عمولات المسوقين', 'expense', 'commission_expenses', 2, 0, 1, 'commission_expenses', 'active');

INSERT IGNORE INTO cashboxes (id, branch_id, account_id, code, name, opening_balance, is_active) VALUES
    (1, 1, 3, 'CASH-HQ', 'خزينة الفرع الرئيسي', 0, 1),
    (2, 2, 3, 'CASH-BEN', 'خزينة فرع بنغازي', 0, 1);

INSERT IGNORE INTO banks (id, branch_id, account_id, name, account_name, account_number, opening_balance, is_active) VALUES
    (1, 1, 4, 'مصرف الجمهورية', 'مجموعة السلام', '0011223344', 0, 1);

INSERT IGNORE INTO store_settings (setting_key, setting_value, setting_group) VALUES
    ('company_name', 'مجموعة السلام لاستيراد المواد الغدائية', 'branding'),
    ('company_phone', '0912345678', 'branding'),
    ('company_email', 'info@alsalam-group.ly', 'branding'),
    ('company_address', 'طرابلس - ليبيا', 'branding'),
    ('company_register', 'CR-2026-001', 'branding'),
    ('company_tax_number', 'TAX-2026-001', 'branding'),
    ('invoice_footer', 'شكراً لتعاملكم معنا. جميع الأسعار قابلة للمراجعة حسب سياسة الشركة.', 'printing'),
    ('currency', 'د.ل', 'finance'),
    ('currency_code', 'LYD', 'finance'),
    ('default_branch_id', '1', 'general'),
    ('default_cash_account_id', '3', 'finance'),
    ('default_bank_account_id', '4', 'finance'),
    ('default_inventory_account_id', '6', 'finance'),
    ('default_sales_account_id', '10', 'finance'),
    ('default_customer_account_id', '5', 'finance'),
    ('default_supplier_account_id', '8', 'finance'),
    ('default_expense_account_id', '12', 'finance'),
    ('enable_auto_journal', '1', 'finance'),
    ('invoice_prefix_sales', 'SAL', 'numbering'),
    ('invoice_prefix_purchase', 'PUR', 'numbering'),
    ('journal_prefix', 'JRN', 'numbering'),
    ('receipt_prefix', 'RCV', 'numbering'),
    ('payment_prefix', 'PAY', 'numbering');
