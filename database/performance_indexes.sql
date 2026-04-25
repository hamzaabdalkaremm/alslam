-- Apply after taking a database backup.
-- These guarded statements create only the most impactful missing indexes.

SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE sales ADD INDEX idx_sales_branch_date_deleted (branch_id, sale_date, deleted_at)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name AND table_name = 'sales' AND index_name = 'idx_sales_branch_date_deleted'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE sales ADD INDEX idx_sales_customer_due_deleted (customer_id, due_amount, deleted_at)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name AND table_name = 'sales' AND index_name = 'idx_sales_customer_due_deleted'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE sales ADD INDEX idx_sales_marketer_due_deleted (marketer_id, due_amount, deleted_at)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name AND table_name = 'sales' AND index_name = 'idx_sales_marketer_due_deleted'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE purchases ADD INDEX idx_purchases_branch_date_deleted (branch_id, purchase_date, deleted_at)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name AND table_name = 'purchases' AND index_name = 'idx_purchases_branch_date_deleted'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE purchases ADD INDEX idx_purchases_supplier_due_deleted (supplier_id, due_amount, deleted_at)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name AND table_name = 'purchases' AND index_name = 'idx_purchases_supplier_due_deleted'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE stock_movements ADD INDEX idx_stock_movements_product_warehouse_date (product_id, warehouse_id, movement_date)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name AND table_name = 'stock_movements' AND index_name = 'idx_stock_movements_product_warehouse_date'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE product_batches ADD INDEX idx_product_batches_product_warehouse_deleted (product_id, warehouse_id, deleted_at)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name AND table_name = 'product_batches' AND index_name = 'idx_product_batches_product_warehouse_deleted'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE activity_logs ADD INDEX idx_activity_logs_created_branch_user (created_at, branch_id, user_id)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema = @db_name AND table_name = 'activity_logs' AND index_name = 'idx_activity_logs_created_branch_user'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
