<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/stream_helper.php';

$channelId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$format = isset($_GET['format']) ? trim($_GET['format']) : '';
$pipeType = isset($_GET['type']) ? trim($_GET['type']) : '';
$encodedTarget = isset($_GET['t']) ? trim($_GET['t']) : '';
$encodedUrl = isset($_GET['u']) ? trim($_GET['u']) : '';

if ($channelId <= 0 && $encodedTarget === '' && $encodedUrl === '') {
    http_response_code(400);
    exit('Canal inválido');
}

$targetUrl = null;

if ($encodedTarget !== '') {
    $cleanEncoded = str_replace(' ', '+', $encodedTarget);
    $decoded = base64_decode($cleanEncoded, true);
    if ($decoded === false || !preg_match('#^https?://#i', $decoded)) {
        http_response_code(400);
        exit('URL inválida');
    }
    $targetUrl = $decoded;
} elseif ($encodedUrl !== '') {
    $cleanEncoded = str_replace(' ', '+', $encodedUrl);
    $decoded = base64_decode($cleanEncoded, true);
    if ($decoded === false || !preg_match('#^https?://#i', $decoded)) {
        http_response_code(400);
        exit('URL inválida');
    }
    $targetUrl = $decoded;
} else {
    $targetUrl = arenaGetChannelUrl($pdo, $channelId);
    if (!$targetUrl) {
        http_response_code(404);
        exit('Canal não encontrado');
    }
}

function arenaOutputHlsPlaylist(string $targetUrl, int $channelId): void
{
    $fetch = arenaFetchUrl($targetUrl, 512000, false, false);
    if (!$fetch['ok']) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Não foi possível carregar a playlist.');
    }

    $body = $fetch['body'];
    $base = $fetch['final_url'] ?: $targetUrl;

    if (!str_starts_with(ltrim($body), '#EXTM3U')) {
        arenaPipeStream($targetUrl, false);
        exit;
    }

    header('Content-Type: application/vnd.apple.mpegurl');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-cache');
    echo arenaRewriteM3u8($body, $base, $channelId);
    exit;
}

if ($pipeType === 'mp4' || $pipeType === 'mpegts') {
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-cache');
    if ($pipeType === 'mpegts') {
        header('Content-Type: video/mp2t');
        arenaPipeStream($targetUrl, true);
    } else {
        arenaPipeStream($targetUrl, false);
    }
    exit;
}

if ($format === 'hls') {
    arenaOutputHlsPlaylist($targetUrl, $channelId);
}

arenaPipeStream($targetUrl, false);
