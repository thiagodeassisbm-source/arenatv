<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    // Validar input
    $channelId = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($channelId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de canal inválido.']);
        exit;
    }

    // Buscar canal atual
    $stmt = $pdo->prepare("SELECT is_sports FROM channels WHERE id = ?");
    $stmt->execute([$channelId]);
    $channel = $stmt->fetch();

    if (!$channel) {
        echo json_encode(['success' => false, 'error' => 'Canal não encontrado.']);
        exit;
    }

    // Alternar status
    $newStatus = ($channel['is_sports'] == 1) ? 0 : 1;
    
    $updateStmt = $pdo->prepare("UPDATE channels SET is_sports = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $channelId]);

    echo json_encode([
        'success' => true,
        'id' => $channelId,
        'is_sports' => $newStatus,
        'message' => $newStatus ? 'Canal adicionado aos Esportes!' : 'Canal removido dos Esportes.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao alternar status do canal: ' . $e->getMessage()
    ]);
}
?>
