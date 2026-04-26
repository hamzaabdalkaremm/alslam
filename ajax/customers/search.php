<?php

require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

Auth::requireLogin();

$q = trim((string) ($_GET['q'] ?? ''));

if ($q === '') {
    echo json_encode([
        'success' => true,
        'results' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = Database::connection();

    if (!schema_table_exists('customers')) {
        echo json_encode([
            'success' => false,
            'results' => [],
            'message' => 'جدول العملاء غير موجود'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nameColumn = null;

    if (schema_has_column('customers', 'full_name')) {
        $nameColumn = 'full_name';
    } elseif (schema_has_column('customers', 'name')) {
        $nameColumn = 'name';
    }

    if (!$nameColumn) {
        echo json_encode([
            'success' => false,
            'results' => [],
            'message' => 'لا يوجد عمود اسم للعميل'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $select = [
        'id',
        $nameColumn . ' AS full_name'
    ];

    $select[] = schema_has_column('customers', 'phone')
        ? 'phone'
        : "'' AS phone";

    $select[] = schema_has_column('customers', 'category')
        ? 'category'
        : "'' AS category";

    $select[] = schema_has_column('customers', 'marketer_id')
        ? 'marketer_id'
        : "NULL AS marketer_id";

    $where = [];
    $params = [];

    if (schema_has_column('customers', 'deleted_at')) {
        $where[] = 'deleted_at IS NULL';
    }

    $where[] = "(
        {$nameColumn} LIKE :q
        OR {$nameColumn} = :exact
        " . (schema_has_column('customers', 'phone') ? " OR phone LIKE :q OR phone = :exact " : "") . "
    )";

    $params['q'] = '%' . $q . '%';
    $params['exact'] = $q;

    $sql = "
        SELECT " . implode(', ', $select) . "
        FROM customers
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$nameColumn} ASC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'results' => [],
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}