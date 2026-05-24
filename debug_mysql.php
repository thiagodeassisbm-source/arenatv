<?php
/**
 * API JSON — testes MySQL (chamado pelo debug.php via AJAX, timeout curto).
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('default_socket_timeout', '3');
set_time_limit(15);

$projectRoot = dirname(__FILE__);
$config = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'user' => 'root',
    'pass' => '',
    'name' => 'venda_canais',
];
$dbConfigFile = $projectRoot . '/config/db.php';
if (is_readable($dbConfigFile)) {
    $src = file_get_contents($dbConfigFile);
    foreach ([
        'host' => "/define\('DB_HOST',\s*'([^']*)'\)/",
        'port' => "/define\('DB_PORT',\s*(\d+)\)/",
        'user' => "/define\('DB_USER',\s*'([^']*)'\)/",
        'pass' => "/define\('DB_PASS',\s*'([^']*)'\)/",
        'name' => "/define\('DB_NAME',\s*'([^']*)'\)/",
    ] as $key => $pattern) {
        if (preg_match($pattern, $src, $m)) {
            $config[$key] = $key === 'port' ? (int) $m[1] : $m[1];
        }
    }
}

$action = $_GET['action'] ?? '';
$checks = [];

function add(array &$checks, string $label, string $status, string $detail = '', string $fix = ''): void
{
    $checks[] = compact('label', 'status', 'detail', 'fix');
}

function portTest(string $host, int $port): array
{
    $t = microtime(true);
    $fp = @fsockopen($host, $port, $errno, $errstr, 3);
    $ms = round((microtime(true) - $t) * 1000);
    if ($fp) {
        fclose($fp);
        return ['ok' => true, 'ms' => $ms, 'error' => ''];
    }
    return ['ok' => false, 'ms' => $ms, 'error' => "[$errno] $errstr"];
}

function pdoTest(string $host, int $port, string $user, string $pass, ?string $db): array
{
    $t = microtime(true);
    try {
        $dsn = $db
            ? "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4"
            : "mysql:host=$host;port=$port;charset=utf8mb4";
        $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
            $opts[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 3;
        }
        $pdo = new PDO($dsn, $user, $pass, $opts);
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        return [
            'ok' => true,
            'ms' => round((microtime(true) - $t) * 1000),
            'version' => $ver,
            'pdo' => $pdo,
            'error' => '',
            'code' => 0,
        ];
    } catch (PDOException $e) {
        return [
            'ok' => false,
            'ms' => round((microtime(true) - $t) * 1000),
            'version' => null,
            'pdo' => null,
            'error' => $e->getMessage(),
            'code' => (int) ($e->errorInfo[1] ?? 0),
        ];
    }
}

foreach (['127.0.0.1', 'localhost'] as $h) {
    $p = portTest($h, $config['port']);
    if ($p['ok']) {
        add($checks, "Porta TCP $h:{$config['port']}", 'ok', "Aberta ({$p['ms']} ms)");
    } else {
        add($checks, "Porta TCP $h:{$config['port']}", 'fail', $p['error'], 'Inicie o MySQL no XAMPP (botão Start).');
    }
}

$mysqld = @shell_exec('tasklist /FI "IMAGENAME eq mysqld.exe" /NH 2>nul');
add(
    $checks,
    'Processo mysqld.exe',
    ($mysqld && str_contains($mysqld, 'mysqld')) ? 'ok' : 'fail',
    trim($mysqld ?: 'não encontrado'),
    'No XAMPP: Start no MySQL.'
);

$workingHost = null;
$pdoServer = null;
foreach (array_unique([$config['host'], '127.0.0.1', 'localhost']) as $h) {
    $r = pdoTest($h, $config['port'], $config['user'], $config['pass'], null);
    if ($r['ok']) {
        $workingHost = $h;
        $pdoServer = $r['pdo'];
        add($checks, "PDO MySQL ($h)", 'ok', "Conectou em {$r['ms']} ms — {$r['version']}");
        break;
    }
    $fix = match ($r['code']) {
        2002, 2003 => 'MySQL parado ou travado. Stop → aguarde 10s → Start no XAMPP.',
        1045 => 'Senha errada em config/db.php.',
        default => 'Se a porta abre mas PDO falha/trava: reinicie o MySQL.',
    };
    add($checks, "PDO MySQL ($h)", 'fail', "[{$r['code']}] {$r['error']} ({$r['ms']} ms)", $fix);
}

if ($pdoServer) {
    $dbs = $pdoServer->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
    $exists = in_array($config['name'], $dbs, true);
    add($checks, 'Banco ' . $config['name'], $exists ? 'ok' : 'warn', $exists ? 'existe' : 'não existe');

    if ($action === 'bootstrap' && !$exists) {
        try {
            $pdoServer->exec(
                'CREATE DATABASE IF NOT EXISTS `' . $config['name'] . '` '
                . 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            $pdoServer->exec('USE `' . $config['name'] . '`');
            $sql = file_get_contents($projectRoot . '/database.sql');
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $st) {
                if ($st !== '') {
                    $pdoServer->exec($st);
                }
            }
            add($checks, 'Bootstrap', 'ok', 'Banco criado e SQL importado.');
            $exists = true;
        } catch (Throwable $e) {
            add($checks, 'Bootstrap', 'fail', $e->getMessage());
        }
    }

    if ($exists && $workingHost) {
        $db = pdoTest($workingHost, $config['port'], $config['user'], $config['pass'], $config['name']);
        if ($db['ok']) {
            $tables = $db['pdo']->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            add($checks, 'Tabelas', 'ok', implode(', ', $tables) ?: '(vazio)');
            foreach (['channels', 'games', 'settings'] as $tbl) {
                if (in_array($tbl, $tables, true)) {
                    $n = (int) $db['pdo']->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
                    add($checks, "Registros: $tbl", 'ok', (string) $n);
                }
            }
            try {
                $n = (int) $db['pdo']->query('SELECT COUNT(*) FROM channels')->fetchColumn();
                add($checks, 'Teste index.php', 'ok', "Query OK — $n canais. <a href='index.php'>Abrir painel</a>");
            } catch (Throwable $e) {
                add($checks, 'Teste index.php', 'fail', $e->getMessage());
            }
        }
    }
}

$fail = count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));
$warn = count(array_filter($checks, fn ($c) => $c['status'] === 'warn'));
$ok = count($checks) - $fail - $warn;

echo json_encode([
    'checks' => $checks,
    'summary' => ['ok' => $ok, 'warn' => $warn, 'fail' => $fail],
    'config' => $config,
], JSON_UNESCAPED_UNICODE);
