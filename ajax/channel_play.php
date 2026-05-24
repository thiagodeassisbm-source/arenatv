<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/stream_helper.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        throw new Exception('Canal inválido.');
    }

    $url = arenaGetChannelUrl($pdo, $id);
    if (!$url) {
        throw new Exception('Canal não encontrado ou sem URL.');
    }

    $strategies = arenaBuildPlayStrategies($id, $url, true);
    $streamType = arenaGuessStreamMode($url);
    $playUrl = $strategies[0]['url'] ?? arenaGetAbsoluteProxyUrl($id, arenaEncodeStreamTarget($url), 'mpegts');

    echo json_encode([
        'success' => true,
        'play_url' => $playUrl,
        'stream_type' => $streamType,
        'original_url' => $url,
        'strategies' => $strategies,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
