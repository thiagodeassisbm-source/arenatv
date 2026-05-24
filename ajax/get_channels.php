<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 28;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sportsOnly = isset($_GET['sports_only']) ? intval($_GET['sports_only']) : 0;
    $group = isset($_GET['group']) ? trim($_GET['group']) : '';
    
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    $whereClauses = [];
    $params = [];

    if (!empty($search)) {
        $whereClauses[] = "(name LIKE ? OR group_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($sportsOnly == 1) {
        $whereClauses[] = "is_sports = 1";
    }

    if ($group !== '') {
        if ($group === 'Sem Categoria') {
            $whereClauses[] = "(group_name IS NULL OR TRIM(group_name) = '')";
        } else {
            $whereClauses[] = "group_name = ?";
            $params[] = $group;
        }
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = "WHERE " . implode(" AND ", $whereClauses);
    }

    // 1. Contar total de registros
    $countSql = "SELECT COUNT(*) FROM channels $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $limit);

    // 2. Buscar registros paginados (ordenados por categoria e nome)
    $selectSql = "SELECT * FROM channels $whereSql ORDER BY COALESCE(NULLIF(TRIM(group_name), ''), 'Sem Categoria') ASC, is_sports DESC, name ASC LIMIT $limit OFFSET $offset";
    $selectStmt = $pdo->prepare($selectSql);
    $selectStmt->execute($params);
    $channels = $selectStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'channels' => $channels,
        'pagination' => [
            'current_page' => $page,
            'limit' => $limit,
            'total_items' => (int)$totalItems,
            'total_pages' => (int)$totalPages
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar canais: ' . $e->getMessage()
    ]);
}
?>
