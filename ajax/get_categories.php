<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $sportsOnly = isset($_GET['sports_only']) ? (int) $_GET['sports_only'] : 0;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $where = [];
    $params = [];

    if ($sportsOnly === 1) {
        $where[] = 'is_sports = 1';
    }
    if ($search !== '') {
        $where[] = '(name LIKE ? OR group_name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT COALESCE(NULLIF(TRIM(group_name), ''), 'Sem Categoria') AS category_name,
                   COUNT(*) AS total
            FROM channels
            $whereSql
            GROUP BY category_name
            ORDER BY category_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'categories' => $categories,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao listar categorias: ' . $e->getMessage(),
    ]);
}
