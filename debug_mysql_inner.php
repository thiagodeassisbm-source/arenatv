<?php
/** Testes MySQL — deve terminar em poucos segundos. */
$root = dirname(__FILE__);
$cacheDir = $root . '/cache';
$statusFile = $cacheDir . '/mysql_status.json';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

ini_set('default_socket_timeout', '3');
set_time_limit(10);

$config = ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => '', 'name' => 'venda_canais'];
$src = @file_get_contents($root . '/config/db.php');
if ($src) {
    foreach (['host' => "/define\('DB_HOST',\s*'([^']*)'\)/", 'port' => "/define\('DB_PORT',\s*(\d+)\)/", 'user' => "/define\('DB_USER',\s*'([^']*)'\)/", 'pass' => "/define\('DB_PASS',\s*'([^']*)'\)/", 'name' => "/define\('DB_NAME',\s*'([^']*)'\)/"] as $k => $p) {
        if (preg_match($p, $src, $m)) {
            $config[$k] = $k === 'port' ? (int) $m[1] : $m[1];
        }
    }
}

$action = $argv[1] ?? '';
$checks = [];
function addc(array &$c, string $l, string $s, string $d = '', string $f = ''): void
{
    $c[] = ['label' => $l, 'status' => $s, 'detail' => $d, 'fix' => $f];
}

foreach (['127.0.0.1', 'localhost'] as $h) {
    $t = microtime(true);
    $fp = @fsockopen($h, $config['port'], $errno, $errstr, 3);
    $ms = round((microtime(true) - $t) * 1000);
    if ($fp) {
        fclose($fp);
        addc($checks, "Porta $h:{$config['port']}", 'ok', "Aberta ({$ms} ms)");
    } else {
        addc($checks, "Porta $h:{$config['port']}", 'fail', "[$errno] $errstr", 'XAMPP → Start no MySQL.');
    }
}

$mysqld = @shell_exec('tasklist /FI "IMAGENAME eq mysqld.exe" /NH 2>nul');
addc($checks, 'mysqld.exe', (str_contains((string) $mysqld, 'mysqld') ? 'ok' : 'fail'), trim($mysqld ?: 'parado'), 'Start no MySQL no XAMPP.');

$conn = null;
$workingHost = null;
if (extension_loaded('mysqli')) {
    foreach (array_unique([$config['host'], '127.0.0.1', 'localhost']) as $h) {
        $t = microtime(true);
        $mysqli = mysqli_init();
        mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        $ok = @mysqli_real_connect($mysqli, $h, $config['user'], $config['pass'], null, $config['port']);
        $ms = round((microtime(true) - $t) * 1000);
        if ($ok) {
            $conn = $mysqli;
            $workingHost = $h;
            $ver = mysqli_get_server_info($mysqli);
            addc($checks, "MySQL ($h)", 'ok', "Conectou em {$ms} ms — $ver");
            break;
        }
        addc($checks, "MySQL ($h)", 'fail', mysqli_connect_error() ?: "falhou ({$ms} ms)", 'Stop → 10s → Start no MySQL.');
    }
} else {
    addc($checks, 'mysqli', 'fail', 'Extensão ausente');
}

if ($conn) {
    $r = mysqli_query($conn, 'SHOW DATABASES');
    $dbs = [];
    while ($row = mysqli_fetch_row($r)) {
        $dbs[] = $row[0];
    }
    $exists = in_array($config['name'], $dbs, true);
    addc($checks, 'Banco ' . $config['name'], $exists ? 'ok' : 'warn', $exists ? 'existe' : 'não existe');

    if ($action === 'bootstrap' && !$exists) {
        mysqli_query($conn, 'CREATE DATABASE IF NOT EXISTS `' . $config['name'] . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        mysqli_select_db($conn, $config['name']);
        $sql = file_get_contents($root . '/database.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $st) {
            if ($st !== '') {
                mysqli_query($conn, $st);
            }
        }
        addc($checks, 'Bootstrap', 'ok', 'Banco criado e SQL importado.');
        $exists = true;
    }

    if ($exists) {
        mysqli_select_db($conn, $config['name']);
        $r = mysqli_query($conn, 'SHOW TABLES');
        $tables = [];
        while ($row = mysqli_fetch_row($r)) {
            $tables[] = $row[0];
        }
        addc($checks, 'Tabelas', 'ok', implode(', ', $tables) ?: '(vazio)');
        if (in_array('channels', $tables, true)) {
            $r = mysqli_query($conn, 'SELECT COUNT(*) FROM channels');
            $n = mysqli_fetch_row($r)[0];
            addc($checks, 'Teste index.php', 'ok', "$n canal(is) — painel deve abrir.");
        }
    }
    mysqli_close($conn);
}

$fail = count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));
$warn = count(array_filter($checks, fn ($c) => $c['status'] === 'warn'));
$ok = count($checks) - $fail - $warn;

$json = json_encode(['checks' => $checks, 'summary' => ['ok' => $ok, 'warn' => $warn, 'fail' => $fail], 'config' => $config], JSON_UNESCAPED_UNICODE);
file_put_contents($statusFile, $json);
echo $json;
