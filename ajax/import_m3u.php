<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

ini_set('max_execution_time', '0');
ini_set('memory_limit', '1024M');
ignore_user_abort(true);

function importJsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeM3uUrl(string $raw): ?string
{
    $url = trim($raw);
    if ($url === '') {
        return null;
    }
    $url = preg_replace('/^\xEF\xBB\xBF/', '', $url);
    if (!preg_match('#^https?://#i', $url)) {
        return null;
    }
    $parts = parse_url($url);
    if (empty($parts['host']) || empty($parts['scheme'])) {
        return null;
    }
    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return null;
    }
    return $url;
}

function readM3uUrlFromRequest(): ?string
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json) && !empty($json['m3u_url'])) {
            return normalizeM3uUrl((string) $json['m3u_url']);
        }
    }

    if (!empty($_POST['m3u_url']) && is_string($_POST['m3u_url'])) {
        return normalizeM3uUrl($_POST['m3u_url']);
    }

    return null;
}

function downloadM3uToFile(string $url, string $destPath): void
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL não está habilitado no PHP.');
    }

    $fp = fopen($destPath, 'wb');
    if ($fp === false) {
        throw new RuntimeException('Não foi possível criar arquivo temporário.');
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT        => 600,
        CURLOPT_USERAGENT      => 'VLC/3.0.0 LibVLC/3.0.0',
        CURLOPT_HTTPHEADER     => ['Accept: */*'],
    ]);

    $ok = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if ($ok === false || $curlError !== '') {
        @unlink($destPath);
        throw new RuntimeException('Falha ao baixar lista: ' . ($curlError ?: 'erro de rede'));
    }
    if ($httpCode >= 400) {
        @unlink($destPath);
        throw new RuntimeException('Servidor retornou HTTP ' . $httpCode);
    }
    if (!is_file($destPath) || filesize($destPath) < 20) {
        @unlink($destPath);
        throw new RuntimeException('Lista M3U vazia ou inválida.');
    }
}

function parseExtinfLine(string $line, array $sportsKeywords): array
{
    $channel = [
        'name' => '',
        'logo' => '',
        'group' => 'Sem Categoria',
        'tvg_id' => '',
        'url' => '',
        'is_sports' => 0,
    ];

    $commaPos = strrpos($line, ',');
    if ($commaPos !== false) {
        $channel['name'] = trim(substr($line, $commaPos + 1));
    }
    if (preg_match('/tvg-logo="([^"]+)"/i', $line, $m)) {
        $channel['logo'] = $m[1];
    }
    if (preg_match('/group-title="([^"]+)"/i', $line, $m)) {
        $channel['group'] = dbNormalizeGroupName($m[1]);
    }
    if (preg_match('/tvg-id="([^"]+)"/i', $line, $m)) {
        $channel['tvg_id'] = $m[1];
    }
    if ($channel['name'] === '' && preg_match('/tvg-name="([^"]+)"/i', $line, $m)) {
        $channel['name'] = $m[1];
    }
    if ($channel['name'] === '') {
        $channel['name'] = 'Canal Sem Nome';
    }

    $searchStr = mb_strtolower($channel['name'] . ' ' . $channel['group']);
    foreach ($sportsKeywords as $keyword) {
        if (str_contains($searchStr, $keyword)) {
            $channel['is_sports'] = 1;
            break;
        }
    }

    return $channel;
}

function flushChannelBatch(PDO $pdo, array &$batch, int &$totalSports): void
{
    if ($batch === []) {
        return;
    }

    $sql = 'INSERT INTO channels (name, logo, url, group_name, tvg_id, is_sports) VALUES ';
    $placeholders = [];
    $values = [];

    foreach ($batch as $chan) {
        $placeholders[] = '(?, ?, ?, ?, ?, ?)';
        $values[] = $chan['name'];
        $values[] = $chan['logo'];
        $values[] = $chan['url'];
        $values[] = $chan['group'];
        $values[] = $chan['tvg_id'];
        $values[] = $chan['is_sports'];
        if ($chan['is_sports'] == 1) {
            $totalSports++;
        }
    }

    $pdo->prepare($sql . implode(', ', $placeholders))->execute($values);
    $batch = [];
}

function importM3uFromFile(PDO $pdo, string $filePath): array
{
    dbClearChannels($pdo);

    $sportsKeywords = [];

    $seenNameGroup = [];
    $seenUrl = [];
    $batch = [];
    $rawCount = 0;
    $uniqueCount = 0;
    $totalSports = 0;
    $current = null;

    $fh = fopen($filePath, 'rb');
    if ($fh === false) {
        throw new RuntimeException('Não foi possível ler o arquivo M3U.');
    }

    while (($line = fgets($fh)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (str_starts_with($line, '#EXTM3U') || str_starts_with($line, '#EXT-X-')) {
            continue;
        }
        if (str_starts_with($line, '#EXTINF:')) {
            $current = parseExtinfLine($line, $sportsKeywords);
            continue;
        }
        if ($current !== null && preg_match('#^https?://#i', $line)) {
            $rawCount++;
            $current['url'] = $line;
            $nameKey = dbChannelDedupeKey($current['name'], $current['url'], $current['group']);
            $urlKey = dbChannelUrlKey($current['url']);

            if (!isset($seenNameGroup[$nameKey]) && ($urlKey === null || !isset($seenUrl[$urlKey]))) {
                $seenNameGroup[$nameKey] = true;
                if ($urlKey !== null) {
                    $seenUrl[$urlKey] = true;
                }
                $batch[] = $current;
                $uniqueCount++;
                if (count($batch) >= 400) {
                    flushChannelBatch($pdo, $batch, $totalSports);
                }
            }
            $current = null;
        }
    }
    fclose($fh);

    flushChannelBatch($pdo, $batch, $totalSports);

    return [
        'raw_lines' => $rawCount,
        'unique_saved' => $uniqueCount,
        'sports_channels' => $totalSports,
    ];
}

register_shutdown_function(function () {
  $err = error_get_last();
  if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
      if (!headers_sent()) {
          header('Content-Type: application/json; charset=utf-8');
          http_response_code(500);
      }
      echo json_encode([
          'success' => false,
          'error' => 'Erro fatal na importação: ' . $err['message'],
      ], JSON_UNESCAPED_UNICODE);
  }
});

try {
    $storageDir = dirname(__DIR__) . '/storage';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

    $tempFile = $storageDir . '/import_m3u_temp.m3u';
    $sourceType = '';

    if (isset($_FILES['m3u_file']) && $_FILES['m3u_file']['error'] === UPLOAD_ERR_OK) {
        if (!move_uploaded_file($_FILES['m3u_file']['tmp_name'], $tempFile)) {
            importJsonResponse(['success' => false, 'error' => 'Falha ao processar arquivo enviado.'], 400);
        }
        $sourceType = 'file';
    } else {
        $url = readM3uUrlFromRequest();
        if ($url === null) {
            importJsonResponse(['success' => false, 'error' => 'URL M3U inválida.'], 400);
        }

        dbUpsertSetting($pdo, 'm3u_url', $url);
        downloadM3uToFile($url, $tempFile);
        $sourceType = 'url';
    }

    $importStats = importM3uFromFile($pdo, $tempFile);
    @unlink($tempFile);

    importJsonResponse([
        'success' => true,
        'message' => 'Lista M3U importada com sucesso!',
        'stats' => [
            'total_channels' => $importStats['unique_saved'],
            'sports_channels' => $importStats['sports_channels'],
            'source' => $sourceType,
            'raw_lines' => $importStats['raw_lines'],
            'skipped_on_import' => $importStats['raw_lines'] - $importStats['unique_saved'],
            'removed_duplicates' => max(0, $importStats['raw_lines'] - $importStats['unique_saved']),
        ],
    ]);
} catch (Throwable $e) {
    if (isset($tempFile) && is_file($tempFile)) {
        @unlink($tempFile);
    }
    importJsonResponse([
        'success' => false,
        'error' => $e->getMessage(),
    ], 500);
}
