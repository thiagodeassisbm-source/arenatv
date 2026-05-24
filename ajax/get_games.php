<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    // Buscar todos os jogos cadastrados com dados do canal associado
    $sql = "SELECT g.*, c.name AS channel_name, c.logo AS channel_logo 
            FROM games g 
            LEFT JOIN channels c ON g.channel_id = c.id 
            ORDER BY g.created_at DESC";
            
    $stmt = $pdo->query($sql);
    $games = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'games' => $games
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar jogos: ' . $e->getMessage()
    ]);
}
?>
