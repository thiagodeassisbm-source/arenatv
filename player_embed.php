<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/stream_helper.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = null;
$strategies = [];
$channelName = 'Canal';
$channelUrl = '';

if ($id <= 0) {
    $error = 'Canal inválido.';
} else {
    $stmt = $pdo->prepare('SELECT name, url FROM channels WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$channel || empty($channel['url'])) {
        $error = 'Canal não encontrado.';
    } else {
        $channelName = $channel['name'];
        $channelUrl = trim($channel['url']);
        $strategies = arenaBuildPlayStrategies($id, $channelUrl);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($channelName); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; height: 100%; background: #000; overflow: hidden; }
        #player-video {
            width: 100%;
            height: 100%;
            display: block;
            background: #000;
            object-fit: contain;
        }
        .player-error, .player-status {
            color: #f87171;
            font-family: system-ui, sans-serif;
            font-size: 13px;
            padding: 16px;
            text-align: center;
            line-height: 1.5;
        }
        .player-status { color: #a78bfa; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mpegts.js@1.7.3/dist/mpegts.js"></script>
    <script src="assets/js/channel-player.js"></script>
</head>
<body>
<?php if ($error): ?>
    <p class="player-error"><?php echo htmlspecialchars($error); ?></p>
<?php else: ?>
    <p id="player-status" class="player-status">Sintonizando...</p>
    <video id="player-video" controls autoplay playsinline style="display:none"></video>
    <script>
    (function () {
        const strategies = <?php echo json_encode($strategies, JSON_UNESCAPED_SLASHES); ?>;
        const video = document.getElementById('player-video');
        const statusEl = document.getElementById('player-status');

        if (!strategies.length) {
            statusEl.textContent = 'Nenhuma estratégia de reprodução disponível.';
            statusEl.className = 'player-error';
            return;
        }

        video.style.display = 'block';
        statusEl.style.display = 'none';

        ArenaChannelPlayer.start(video, strategies, {
            onTrying(s, n, total) {
                statusEl.style.display = 'block';
                statusEl.className = 'player-status';
                statusEl.textContent = 'Sintonizando (' + n + '/' + total + ')...';
            },
            onPlaying() {
                statusEl.style.display = 'none';
                video.style.display = 'block';
            },
            onFailed() {
                statusEl.style.display = 'block';
                statusEl.className = 'player-error';
                statusEl.textContent = 'Não foi possível sintonizar. O link pode estar expirado — reimporte a lista M3U.';
            },
        });
    })();
    </script>
<?php endif; ?>
</body>
</html>
