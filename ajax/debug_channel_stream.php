<?php
/**
 * Diagnóstico de stream por canal — retorna JSON detalhado.
 * GET: id=123  ou  url=...  ou  name=RECORD (busca parcial)
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/stream_helper.php';

set_time_limit(120);

function dbgHttpGet(string $url, int $timeoutSec = 12, bool $useRange = false, int $rangeBytes = 4096): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'Extensão curl não instalada no PHP'];
    }
    $ch = curl_init($url);
    $opts = arenaStreamCurlOpts($url);
    $opts[CURLOPT_URL] = $url;
    $opts[CURLOPT_RETURNTRANSFER] = true;
    $opts[CURLOPT_HEADER] = true;
    $opts[CURLOPT_TIMEOUT] = $timeoutSec;
    $opts[CURLOPT_CONNECTTIMEOUT] = 8;
    if ($useRange) {
        $opts[CURLOPT_RANGE] = '0-' . ($rangeBytes - 1);
    }
    curl_setopt_array($ch, $opts);
    $t0 = microtime(true);
    $raw = curl_exec($ch);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $err = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => $err ?: 'curl falhou', 'ms' => $ms, 'info' => $info];
    }
    $headerSize = (int) ($info['header_size'] ?? 0);
    $body = substr($raw, $headerSize);
    return [
        'ok' => true,
        'ms' => $ms,
        'http_code' => (int) ($info['http_code'] ?? 0),
        'content_type' => $info['content_type'] ?? '',
        'final_url' => $info['url'] ?? $url,
        'redirect_count' => (int) ($info['redirect_count'] ?? 0),
        'body_len' => strlen($body),
        'body_hex' => bin2hex(substr($body, 0, 16)),
        'body_preview' => substr(preg_replace('/[^\x20-\x7E\r\n]/', '.', $body), 0, 200),
        'is_m3u8' => str_starts_with(ltrim($body), '#EXTM3U'),
        'is_mp4' => strlen($body) >= 8 && substr($body, 4, 4) === 'ftyp',
        'is_ts' => strlen($body) > 0 && ord($body[0]) === 0x47,
        'is_html' => str_contains(strtolower($info['content_type'] ?? ''), 'text/html') || str_starts_with(ltrim($body), '<'),
    ];
}

function dbgPingNode(): array
{
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $raw = @file_get_contents('http://127.0.0.1:8787/ping', false, $ctx);
    if ($raw === false) {
        return ['ok' => false, 'detail' => 'Proxy Node (8787) offline — rode iniciar.bat com Node.js instalado'];
    }
    return ['ok' => true, 'detail' => 'Proxy Node ativo', 'response' => $raw];
}

function dbgTestLocalProxy(int $channelId, string $targetUrl, string $type): array
{
    $enc = arenaEncodeStreamTarget($targetUrl);
    $path = "/ajax/stream_proxy.php?id={$channelId}&t={$enc}&type={$type}";
    $full = 'http://127.0.0.1:8000' . $path;
    return array_merge(['proxy_url' => $path, 'full_url' => $full], dbgHttpGet($full, 8, true, 2048));
}

function dbgTestNodeProxy(int $channelId, string $targetUrl, string $type): array
{
    $enc = arenaEncodeStreamTarget($targetUrl);
    $full = "http://127.0.0.1:8787/stream?id={$channelId}&t={$enc}&type={$type}";
    return array_merge(['proxy_url' => $full], dbgHttpGet($full, 8, true, 2048));
}

function dbgRecommendations(array $report): array
{
    $tips = [];
    $upstream = $report['upstream_tests'][0] ?? null;

    if (!extension_loaded('curl')) {
        $tips[] = ['level' => 'error', 'msg' => 'Habilite extension=curl no php.ini'];
    }

    if (!($report['node_proxy']['ok'] ?? false)) {
        $tips[] = ['level' => 'warn', 'msg' => 'Instale Node.js e reinicie iniciar.bat para proxy mais rápido (porta 8787)'];
    }

    if ($upstream) {
        $code = $upstream['http_code'] ?? 0;
        if ($code === 404) {
            $tips[] = ['level' => 'error', 'msg' => 'Servidor IPTV retornou HTTP 404 — link expirado ou canal offline. Reimporte a lista M3U com URL nova do provedor.'];
        } elseif ($code === 403 || $code === 401) {
            $tips[] = ['level' => 'error', 'msg' => 'Acesso negado (HTTP ' . $code . ') — conta IPTV pode estar bloqueada ou expirada.'];
        } elseif ($code >= 500) {
            $tips[] = ['level' => 'error', 'msg' => 'Servidor IPTV com erro HTTP ' . $code . ' — tente mais tarde.'];
        } elseif ($code === 0) {
            $tips[] = ['level' => 'error', 'msg' => 'Sem resposta do servidor IPTV — DNS, firewall ou servidor fora do ar.'];
        } elseif ($upstream['is_html'] ?? false) {
            $tips[] = ['level' => 'error', 'msg' => 'Servidor devolveu HTML em vez de vídeo (Cloudflare/bloqueio/página de erro).'];
        } elseif ($code === 200 && ($upstream['body_len'] ?? 0) === 0 && str_contains($upstream['content_type'] ?? '', 'octet-stream')) {
            $tips[] = ['level' => 'ok', 'msg' => 'Stream ao vivo TS (corpo vazio no teste curto é normal) — player deve usar mpegts.js + proxy.'];
        } elseif ($upstream['is_ts'] ?? false) {
            $tips[] = ['level' => 'ok', 'msg' => 'Stream MPEG-TS detectado (byte 0x47) — use mpegts.js via proxy.'];
        } elseif ($upstream['is_mp4'] ?? false) {
            $tips[] = ['level' => 'ok', 'msg' => 'Stream MP4 detectado — player nativo deve funcionar.'];
        } elseif ($upstream['is_m3u8'] ?? false) {
            $tips[] = ['level' => 'ok', 'msg' => 'Playlist HLS detectada — use hls.js via proxy.'];
        }
    }

    $phpOk = false;
    $nodeOk = false;
    foreach ($report['proxy_tests'] as $pt) {
        if (($pt['via'] ?? '') === 'php' && ($pt['http_code'] ?? 0) >= 200 && ($pt['http_code'] ?? 0) < 400) {
            $phpOk = true;
        }
        if (($pt['via'] ?? '') === 'node' && ($pt['http_code'] ?? 0) >= 200 && ($pt['http_code'] ?? 0) < 400) {
            $nodeOk = true;
        }
    }
    if (!$phpOk && !$nodeOk && $upstream && ($upstream['http_code'] ?? 0) >= 200 && ($upstream['http_code'] ?? 0) < 400) {
        $tips[] = ['level' => 'warn', 'msg' => 'Upstream responde OK mas proxy local falhou — verifique se iniciar.bat está aberto e porta 8000 livre.'];
    }
    if ($phpOk || $nodeOk) {
        $tips[] = ['level' => 'ok', 'msg' => 'Proxy local funcionando — se o player falhar, limpe cache (Ctrl+F5) e teste de novo.'];
    }

    if (empty($tips)) {
        $tips[] = ['level' => 'warn', 'msg' => 'Não foi possível determinar causa — envie este JSON ao suporte.'];
    }
    return $tips;
}

try {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $urlParam = isset($_GET['url']) ? trim($_GET['url']) : '';
    $nameParam = isset($_GET['name']) ? trim($_GET['name']) : '';

    $channel = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id, name, url, group_name FROM channels WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($urlParam !== '') {
        $channel = ['id' => 0, 'name' => '(URL manual)', 'url' => $urlParam, 'group_name' => ''];
    } elseif ($nameParam !== '') {
        $stmt = $pdo->prepare('SELECT id, name, url, group_name FROM channels WHERE name LIKE ? LIMIT 1');
        $stmt->execute(['%' . $nameParam . '%']);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$channel || empty($channel['url'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Canal não encontrado. Use ?id=, ?name= ou ?url=',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $url = trim($channel['url']);
    $channelId = (int) ($channel['id'] ?? 0);
    $variants = arenaUrlVariants($url);
    $strategies = $channelId > 0 ? arenaBuildPlayStrategies($channelId, $url) : [];
    $probe = arenaProbeChannelStream($url);

    $upstreamTests = [];
    $labels = [
        ['label' => 'GET completo (sem Range)', 'range' => false],
        ['label' => 'GET com Range 0-4095', 'range' => true],
    ];
    foreach ($variants as $vi => $variant) {
        foreach ($labels as $lab) {
            $upstreamTests[] = array_merge(
                ['variant_index' => $vi, 'variant_url' => $variant, 'test' => $lab['label']],
                dbgHttpGet($variant, 15, $lab['range'], 4096)
            );
            if (count($upstreamTests) >= 8) {
                break 2;
            }
        }
    }

    $proxyTests = [];
    if ($channelId > 0) {
        $proxyTests[] = array_merge(['via' => 'php', 'mode' => 'mpegts'], dbgTestLocalProxy($channelId, $url, 'mpegts'));
        $proxyTests[] = array_merge(['via' => 'php', 'mode' => 'mp4'], dbgTestLocalProxy($channelId, $url, 'mp4'));
        $proxyTests[] = array_merge(['via' => 'php', 'mode' => 'hls'], dbgHttpGet(
            'http://127.0.0.1:8000/ajax/stream_proxy.php?id=' . $channelId . '&t=' . arenaEncodeStreamTarget($url) . '&format=hls',
            8,
            true,
            2048
        ));
        $nodePing = dbgPingNode();
        if ($nodePing['ok']) {
            $proxyTests[] = array_merge(['via' => 'node', 'mode' => 'mpegts'], dbgTestNodeProxy($channelId, $url, 'mpegts'));
            $proxyTests[] = array_merge(['via' => 'node', 'mode' => 'hls'], dbgHttpGet(
                'http://127.0.0.1:8787/stream?id=' . $channelId . '&t=' . arenaEncodeStreamTarget($url) . '&format=hls',
                8,
                true,
                2048
            ));
        }
    }

    $report = [
        'success' => true,
        'tested_at' => date('c'),
        'environment' => [
            'php_version' => PHP_VERSION,
            'curl' => extension_loaded('curl'),
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'server' => $_SERVER['HTTP_HOST'] ?? 'cli',
        ],
        'node_proxy' => dbgPingNode(),
        'channel' => [
            'id' => $channelId,
            'name' => $channel['name'],
            'group_name' => $channel['group_name'] ?? '',
            'url' => $url,
        ],
        'probe' => $probe,
        'url_variants' => $variants,
        'play_strategies_count' => count($strategies),
        'play_strategies' => array_slice($strategies, 0, 8),
        'upstream_tests' => $upstreamTests,
        'proxy_tests' => $proxyTests,
    ];
    $report['recommendations'] = dbgRecommendations($report);
    $report['summary'] = [
        'can_play' => false,
        'main_issue' => null,
    ];

    foreach ($report['recommendations'] as $rec) {
        if ($rec['level'] === 'error') {
            $report['summary']['main_issue'] = $rec['msg'];
            break;
        }
    }
    foreach ($upstreamTests as $t) {
        if (($t['http_code'] ?? 0) >= 200 && ($t['http_code'] ?? 0) < 400 && !($t['is_html'] ?? false)) {
            $report['summary']['can_play'] = true;
            break;
        }
    }
    if ($report['summary']['main_issue'] && str_contains($report['summary']['main_issue'], '404')) {
        $report['summary']['can_play'] = false;
    }

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
