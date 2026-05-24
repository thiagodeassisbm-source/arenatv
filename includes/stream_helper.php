<?php
/**
 * Helpers para reproduzir streams IPTV via proxy local (evita CORS no navegador).
 */

define('ARENA_STREAM_UA', 'VLC/3.0.20 LibVLC/3.0.20');

function arenaStreamHeadersForUrl(string $url): array
{
    $headers = [
        'User-Agent: ' . ARENA_STREAM_UA,
        'Accept: */*',
        'Connection: keep-alive',
    ];
    $parts = parse_url($url);
    if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        $headers[] = 'Referer: ' . $origin . '/';
        $headers[] = 'Origin: ' . $origin;
    }
    return $headers;
}

function arenaStreamCurlOpts(string $url = ''): array
{
    return [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => $url !== '' ? arenaStreamHeadersForUrl($url) : [
            'User-Agent: ' . ARENA_STREAM_UA,
            'Accept: */*',
        ],
    ];
}

function arenaValidateStreamResponse(array $fetch): ?string
{
    if (!$fetch['ok']) {
        return $fetch['error'] ?? 'Não foi possível conectar ao servidor do canal.';
    }
    $code = (int)($fetch['info']['http_code'] ?? 0);
    if ($code >= 400) {
        return "Servidor retornou HTTP {$code}. O link pode estar expirado ou offline.";
    }
    $ct = strtolower($fetch['content_type'] ?? ($fetch['info']['content_type'] ?? ''));
    $body = ltrim($fetch['body'] ?? '');
    if ($body === '' && $code === 200 && (str_contains($ct, 'octet-stream') || str_contains($ct, 'mp2t'))) {
        return null;
    }
    if (str_contains($ct, 'text/html') || str_starts_with($body, '<')) {
        return 'O servidor não enviou vídeo (resposta HTML). Teste o mesmo link no VLC.';
    }
    return null;
}

function arenaResolveUrl(string $base, string $relative): string
{
    if (preg_match('#^https?://#i', $relative)) {
        return $relative;
    }
    $parts = parse_url($base);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return $relative;
    }
    $scheme = $parts['scheme'];
    $host = $parts['host'];
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '/';

    if (str_starts_with($relative, '/')) {
        return $scheme . '://' . $host . $port . $relative;
    }

    $dir = preg_replace('#/[^/]*$#', '/', $path);
    if ($dir === '') {
        $dir = '/';
    }

    return $scheme . '://' . $host . $port . $dir . $relative;
}

function arenaFetchUrl(string $url, int $maxBytes = 0, bool $headOnly = false, bool $useRange = true): array
{
    $ch = curl_init($url);
    $opts = arenaStreamCurlOpts($url);
    $opts[CURLOPT_URL] = $url;
    $opts[CURLOPT_RETURNTRANSFER] = false;
    $opts[CURLOPT_HEADER] = false;

    if ($maxBytes === 0 && !$headOnly) {
        $maxBytes = 300000; // ~300 KB limit
    }

    if ($headOnly) {
        $opts[CURLOPT_NOBODY] = true;
    } elseif ($maxBytes > 0 && $useRange) {
        $opts[CURLOPT_RANGE] = '0-' . ($maxBytes - 1);
    }

    $body = '';
    $headersRaw = '';

    $opts[CURLOPT_HEADERFUNCTION] = static function ($ch, string $headerLine) use (&$headersRaw) {
        $headersRaw .= $headerLine;
        return strlen($headerLine);
    };

    if (!$headOnly) {
        $opts[CURLOPT_WRITEFUNCTION] = static function ($ch, string $chunk) use (&$body, $maxBytes) {
            $body .= $chunk;
            if ($maxBytes > 0 && strlen($body) >= $maxBytes) {
                return 0; // Abort download once maxBytes is reached
            }
            return strlen($chunk);
        };
    }

    curl_setopt_array($ch, $opts);
    $ok = curl_exec($ch);
    $err = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($ok === false && !empty($body)) {
        $ok = true;
    }

    if ($ok === false) {
        return ['ok' => false, 'error' => $err ?: 'Falha ao conectar', 'body' => '', 'info' => $info];
    }

    return [
        'ok' => true,
        'body' => $body,
        'headers' => $headersRaw,
        'info' => $info,
        'final_url' => $info['url'] ?? $url,
        'content_type' => $info['content_type'] ?? '',
    ];
}

function arenaDetectStreamType(string $url, string $body, string $contentType): string
{
    $ct = strtolower($contentType);
    $body = $body ?? '';

    if (str_contains($ct, 'mpegurl') || str_contains($ct, 'm3u')) {
        return 'hls';
    }
    if (str_starts_with(ltrim($body), '#EXTM3U')) {
        return 'hls';
    }
    if (preg_match('/\.m3u8(\?|$)/i', $url)) {
        return 'hls';
    }

    if (strlen($body) >= 8 && substr($body, 4, 4) === 'ftyp') {
        return 'mp4';
    }
    if (str_contains($ct, 'video/mp4') || str_contains($ct, 'mp4')) {
        return 'mp4';
    }

    if (strlen($body) > 0 && ord($body[0]) === 0x47) {
        return 'mpegts';
    }
    if (str_contains($ct, 'mp2t') || str_contains($ct, 'octet-stream')) {
        return 'mpegts';
    }
    if (preg_match('#/ts(\?|$)#i', $url)) {
        return 'mpegts';
    }

    return 'mpegts';
}

function arenaRewriteM3u8(string $playlist, string $baseUrl, int $channelId): string
{
    $base = $baseUrl;
    $self = 'ajax/stream_proxy.php';
    $out = [];

    foreach (preg_split('/\r\n|\n|\r/', $playlist) as $line) {
        $trim = trim($line);
        if ($trim === '') {
            $out[] = '';
            continue;
        }
        if ($trim[0] === '#') {
            if (stripos($trim, '#EXT-X-STREAM-INF') === 0 || stripos($trim, '#EXTINF') === 0) {
                $out[] = $line;
                continue;
            }
            $out[] = $line;
            continue;
        }
        $abs = arenaResolveUrl($base, $trim);
        $out[] = $self . '?id=' . $channelId . '&t=' . arenaEncodeStreamTarget($abs);
    }

    return implode("\n", $out);
}

function arenaGetChannelUrl(PDO $pdo, int $channelId): ?string
{
    $stmt = $pdo->prepare('SELECT url FROM channels WHERE id = ? LIMIT 1');
    $stmt->execute([$channelId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['url'])) {
        return null;
    }
    return trim($row['url']);
}

function arenaGuessStreamMode(string $url): string
{
    if (preg_match('#\.m3u8|/m3u8|mpegurl#i', $url)) {
        return 'hls';
    }
    if (preg_match('#/ts(\?|$)|\.ts(\?|$)|\/live\/#i', $url)) {
        return 'mpegts';
    }
    if (preg_match('#\.mp4|\/movie\/|\/play\/|\/vod\/#i', $url)) {
        return 'mp4';
    }
    return 'mpegts';
}

function arenaUrlVariants(string $url): array
{
    $list = [trim($url)];
    if (preg_match('#/ts$#i', $url)) {
        $list[] = preg_replace('#/ts$#i', '', $url);
        $list[] = preg_replace('#/ts$#i', '.m3u8', $url);
        $list[] = preg_replace('#/ts$#i', '/m3u8', $url);
    }
    $base = rtrim($url, '/');
    if (!preg_match('#\.m3u8(\?|$)#i', $base)) {
        $list[] = $base . '.m3u8';
    }
    return array_values(array_unique(array_filter($list)));
}

function arenaEncodeStreamTarget(string $url): string
{
    return rawurlencode(base64_encode($url));
}

function arenaGetAbsoluteProxyUrl(int $channelId, string $enc, string $mode = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = rtrim($scriptDir, '/');
    if ($scriptDir === '/') {
        $scriptDir = '';
    }
    $url = "{$scheme}://{$host}" . $scriptDir . "/ajax/stream_proxy.php?id={$channelId}&t={$enc}";
    if ($mode !== '') {
        $url .= "&type={$mode}";
    }
    return $url;
}

function arenaBuildPlayStrategies(int $channelId, string $url, bool $fastMode = true): array
{
    $variants = arenaUrlVariants($url);
    $probe = $fastMode ? [
        'stream_type' => arenaGuessStreamMode($url),
        'final_url' => $url,
        'content_type' => '',
        'error' => null,
    ] : arenaProbeChannelStream($url);
    $finalUrl = $probe['final_url'] ?? $url;
    if ($finalUrl !== $url && preg_match('#^https?://#i', $finalUrl)) {
        array_unshift($variants, $finalUrl);
    }
    $variants = array_values(array_unique($variants));

    $strategies = [];
    $seen = [];
    $preferredMode = $probe['stream_type'] ?? arenaGuessStreamMode($url);

    foreach ($variants as $variantUrl) {
        $hint = ($variantUrl === $finalUrl && $preferredMode) ? $preferredMode : arenaGuessStreamMode($variantUrl);
        $enc = arenaEncodeStreamTarget($variantUrl);
        $modes = $hint === 'hls'
            ? ['mpegts', 'hls', 'mp4']
            : ($hint === 'mp4' ? ['mp4', 'mpegts', 'hls'] : ['mpegts', 'hls', 'mp4']);

        foreach ($modes as $mode) {
            $phpBase = arenaGetAbsoluteProxyUrl($channelId, $enc);
            $nodeBase = "http://127.0.0.1:8787/stream?id={$channelId}&t={$enc}";

            $isLocalDev = (php_sapi_name() === 'cli-server');
            $candidates = [];
            if ($mode === 'hls') {
                $candidates = [
                    ['mode' => 'hls', 'url' => "{$nodeBase}&format=hls", 'via' => 'node'],
                ];
                if (!$isLocalDev) {
                    $candidates[] = ['mode' => 'hls', 'url' => "{$phpBase}&format=hls", 'via' => 'php'];
                }
            } elseif ($mode === 'mp4') {
                $candidates = [
                    ['mode' => 'mp4', 'url' => "{$nodeBase}&type=mp4", 'via' => 'node'],
                ];
                if (!$isLocalDev) {
                    $candidates[] = ['mode' => 'mp4', 'url' => "{$phpBase}&type=mp4", 'via' => 'php'];
                }
            } else {
                $candidates = [
                    ['mode' => 'mpegts', 'url' => "{$nodeBase}&type=mpegts", 'via' => 'node'],
                ];
                if (!$isLocalDev) {
                    $candidates[] = ['mode' => 'mpegts', 'url' => "{$phpBase}&type=mpegts", 'via' => 'php'];
                }
            }

            foreach ($candidates as $c) {
                $key = $c['mode'] . '|' . $c['url'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $strategies[] = $c;
            }
        }
    }

    return array_slice($strategies, 0, 16);
}

function arenaProbeChannelStream(string $url): array
{
    $fetch = arenaFetchUrl($url, 4096, false, false);
    if (!$fetch['ok'] || (int)($fetch['info']['http_code'] ?? 0) >= 400) {
        $fetch = arenaFetchUrl($url, 4096, false, true);
    }

    if (!$fetch['ok']) {
        return [
            'stream_type' => arenaGuessStreamMode($url),
            'final_url' => $url,
            'content_type' => '',
            'error' => null,
        ];
    }

    $code = (int)($fetch['info']['http_code'] ?? 0);
    if ($code >= 400) {
        return [
            'stream_type' => arenaGuessStreamMode($url),
            'final_url' => $fetch['final_url'] ?: $url,
            'content_type' => $fetch['content_type'] ?? '',
            'error' => null,
        ];
    }

    $validationError = arenaValidateStreamResponse($fetch);
    if ($validationError) {
        return [
            'stream_type' => arenaGuessStreamMode($url),
            'final_url' => $fetch['final_url'] ?: $url,
            'content_type' => $fetch['content_type'] ?? '',
            'error' => null,
        ];
    }

    $final = $fetch['final_url'] ?: $url;
    $type = arenaDetectStreamType($final, $fetch['body'], $fetch['content_type'] ?? '');

    return [
        'stream_type' => $type,
        'final_url' => $final,
        'content_type' => $fetch['content_type'] ?? '',
        'error' => null,
    ];
}

function arenaPipeStream(string $url, bool $lockContentType = false): void
{
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', 'off');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    set_time_limit(0);
    ignore_user_abort(true);

    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-cache');

    $headers = arenaStreamHeadersForUrl($url);
    if (!empty($_SERVER['HTTP_RANGE'])) {
        $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
    }

    $sentContentType = $lockContentType;
    $sentStatus = false;
    $inRedirect = false;

    $ch = curl_init($url);
    curl_setopt_array($ch, arenaStreamCurlOpts($url) + [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $headerLine) use (&$sentContentType, &$sentStatus, &$inRedirect, $lockContentType) {
            $line = trim($headerLine);
            if ($line === '') {
                return strlen($headerLine);
            }
            if (preg_match('/^HTTP\/[\d.]+ (\d+)/i', $line, $m)) {
                $code = (int)$m[1];
                if ($code >= 300 && $code < 400) {
                    $inRedirect = true;
                } else {
                    $inRedirect = false;
                    if (!$sentStatus && ($code === 200 || $code === 206)) {
                        http_response_code($code);
                        $sentStatus = true;
                    }
                }
                return strlen($headerLine);
            }
            if (!$inRedirect) {
                if (!$lockContentType && !$sentContentType && stripos($line, 'Content-Type:') === 0) {
                    header($line);
                    $sentContentType = true;
                } elseif (stripos($line, 'Content-Range:') === 0 || 
                          stripos($line, 'Content-Length:') === 0 || 
                          stripos($line, 'Accept-Ranges:') === 0) {
                    header($line);
                }
            }
            return strlen($headerLine);
        },
        CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) {
            echo $chunk;
            if (function_exists('flush')) {
                flush();
            }
            return strlen($chunk);
        },
        CURLOPT_TIMEOUT => 0,
    ]);

    curl_exec($ch);
    if (curl_errno($ch)) {
        http_response_code(502);
    }
    curl_close($ch);
}
