<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $stats = dbDedupeChannels($pdo);

    echo json_encode([
        'success' => true,
        'message' => $stats['removed'] > 0
            ? "Removidos {$stats['removed']} canais duplicados."
            : 'Nenhum duplicado encontrado.',
        'stats' => $stats,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao remover duplicados: ' . $e->getMessage(),
    ]);
}
