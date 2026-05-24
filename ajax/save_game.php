<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    // Pegar inputs
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $channel_id = isset($_POST['channel_id']) ? intval($_POST['channel_id']) : 0;
    
    // Normalizar preço
    $priceRaw = isset($_POST['price']) ? trim($_POST['price']) : '0';
    $priceRaw = str_replace(['R$', ' '], '', $priceRaw); // remover R$ e espaços
    $priceRaw = str_replace(',', '.', $priceRaw); // mudar vírgula para ponto se houver
    $price = floatval($priceRaw);
    
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'ativo';
    $external_link = isset($_POST['external_link']) ? trim($_POST['external_link']) : '';
    $game_date = isset($_POST['game_date']) && $_POST['game_date'] !== '' ? trim($_POST['game_date']) : null;

    // Validação básica
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'O nome do jogo é obrigatório.']);
        exit;
    }

    if ($channel_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Por favor, selecione um canal para este jogo.']);
        exit;
    }

    // Verificar se o canal existe e é esportivo
    $stmt = $pdo->prepare("SELECT id FROM channels WHERE id = ?");
    $stmt->execute([$channel_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'O canal selecionado não é válido.']);
        exit;
    }

    if ($id > 0) {
        // Modo Edição
        $stmt = $pdo->prepare("UPDATE games SET name = ?, description = ?, channel_id = ?, game_date = ?, price = ?, status = ?, external_link = ? WHERE id = ?");
        $stmt->execute([$name, $description, $channel_id, $game_date, $price, $status, $external_link, $id]);
        $message = "Jogo editado com sucesso!";
    } else {
        // Modo Criação
        $stmt = $pdo->prepare("INSERT INTO games (name, description, channel_id, game_date, price, status, external_link) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $channel_id, $game_date, $price, $status, $external_link]);
        $id = $pdo->lastInsertId();
        $message = "Jogo cadastrado para venda com sucesso!";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'game_id' => $id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao salvar jogo: ' . $e->getMessage()
    ]);
}
?>
