<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de jogo inválido.']);
        exit;
    }

    // Verificar se o jogo existe
    $stmt = $pdo->prepare("SELECT id FROM games WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Jogo não encontrado ou já foi excluído.']);
        exit;
    }

    // Deletar
    $stmt = $pdo->prepare("DELETE FROM games WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Jogo excluído com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao excluir jogo: ' . $e->getMessage()
    ]);
}
?>
