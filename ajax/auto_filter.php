<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    // Palavras-chave otimizadas para canais esportivos
    $sportsKeywords = [
        'esporte', 'sport', 'espn', 'premiere', 'combate', 'futebol', 'dazn', 'champions', 
        'copa', 'tnt sports', 'cazetv', 'cazé', 'bandsports', 'fox sports', 'arena', 'gol',
        'fc', 'telecine action', 'ufc', 'conmebol', 'copa', 'libertadores', 'brasileirao', 'laliga', 'premier league'
    ];

    // Buscar todos os canais
    $stmt = $pdo->query("SELECT id, name, group_name FROM channels");
    $channels = $stmt->fetchAll();

    $sportsIds = [];
    $nonSportsIds = [];

    foreach ($channels as $chan) {
        $searchStr = mb_strtolower($chan['name'] . ' ' . $chan['group_name']);
        $isSport = false;
        
        foreach ($sportsKeywords as $keyword) {
            if (strpos($searchStr, $keyword) !== false) {
                $isSport = true;
                break;
            }
        }

        if ($isSport) {
            $sportsIds[] = $chan['id'];
        } else {
            $nonSportsIds[] = $chan['id'];
        }
    }

    $pdo->beginTransaction();

    // Resetar todos para 0 primeiro (para caso queiram refiltrar)
    if (!empty($channels)) {
        $pdo->exec("UPDATE channels SET is_sports = 0");
    }

    // Atualizar os esportivos para 1 em blocos
    if (!empty($sportsIds)) {
        $chunks = array_chunk($sportsIds, 500);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare("UPDATE channels SET is_sports = 1 WHERE id IN ($placeholders)");
            $stmt->execute($chunk);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Filtro inteligente aplicado com sucesso!',
        'stats' => [
            'total_channels' => count($channels),
            'sports_channels' => count($sportsIds)
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao rodar filtro inteligente: ' . $e->getMessage()
    ]);
}
?>
