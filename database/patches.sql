-- ملف تصليحات قاعدة البيانات
-- تاريخ التطبيق: 2026-04-25
-- الهدف: إصلاح الأعمدة المفقودة والجداول التي تسبب أخطاء "Unknown column" و "Table not found"

-- ============================================================
-- 1. إضافة جدول activity_logs المفقود (يسجل الأنشطة والمستخدمين)
-- ============================================================
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

-- ============================================================
-- 2. إضافة أعمدة مفقودة إلى جدول users
-- ============================================================
SET @stmt := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'users' 
     AND column_name = 'deleted_at') = 0,
    'ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL',
    'SELECT "العمود deleted_at موجود مسبقاً"'
);
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 3. إضافة أعمدة مفقودة إلى جدول sales
-- ============================================================
SET @stmt := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'sales' 
     AND column_name = 'printable_token') = 0,
    'ALTER TABLE sales ADD COLUMN printable_token VARCHAR(255) NULL',
    'SELECT "العمود printable_token موجود مسبقاً"'
);
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'sales' 
     AND column_name = 'sold_by') = 0,
    'ALTER TABLE sales ADD COLUMN sold_by BIGINT UNSIGNED NULL',
    'SELECT "العمود sold_by موجود مسبقاً"'
);
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'sales' 
     AND column_name = 'delivered_by') = 0,
    'ALTER TABLE sales ADD COLUMN delivered_by BIGINT UNSIGNED NULL',
    'SELECT "العمود delivered_by موجود مسبقاً"'
);
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 4. إضافة عمود مفقود إلى جدول purchases
-- ============================================================
SET @stmt := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'purchases' 
     AND column_name = 'purchased_by') = 0,
    'ALTER TABLE purchases ADD COLUMN purchased_by BIGINT UNSIGNED NULL',
    'SELECT "العمود purchased_by موجود مسبقاً"'
);
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 5. فهرس اختياري: تحسين أداء استعلامات البحث في activity_logs
-- ============================================================
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);