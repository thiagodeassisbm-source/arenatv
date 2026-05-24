<?php
/**
 * Diagnóstico Arena Stream — carrega rápido; MySQL testado via AJAX.
 * http://127.0.0.1:8000/debug.php
 */
header('Content-Type: text/html; charset=utf-8');
$projectRoot = __DIR__;

$config = ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'name' => 'venda_canais'];
$dbConfigFile = $projectRoot . '/config/db.php';
if (is_readable($dbConfigFile)) {
    $src = file_get_contents($dbConfigFile);
    if (preg_match("/define\('DB_HOST',\s*'([^']*)'\)/", $src, $m)) {
        $config['host'] = $m[1];
    }
    if (preg_match("/define\('DB_PORT',\s*(\d+)\)/", $src, $m)) {
        $config['port'] = (int) $m[1];
    }
    if (preg_match("/define\('DB_USER',\s*'([^']*)'\)/", $src, $m)) {
        $config['user'] = $m[1];
    }
    if (preg_match("/define\('DB_NAME',\s*'([^']*)'\)/", $src, $m)) {
        $config['name'] = $m[1];
    }
}

$checks = [];
function addCheck(array &$c, string $g, string $label, string $status, string $detail = '', string $fix = ''): void
{
    $c[$g][] = compact('label', 'status', 'detail', 'fix');
}

$g = 'PHP e servidor';
addCheck($checks, $g, 'Versão PHP', 'ok', PHP_VERSION . ' — ' . PHP_SAPI);
addCheck($checks, $g, 'PHP executável', 'ok', PHP_BINARY ?: '?');
foreach (['pdo', 'pdo_mysql', 'json'] as $ext) {
    addCheck($checks, $g, "Extensão $ext", extension_loaded($ext) ? 'ok' : 'fail', extension_loaded($ext) ? 'OK' : 'Falta no php.ini');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
addCheck($checks, $g, 'URL atual', 'ok', ($host ?: 'CLI') . ($_SERVER['REQUEST_URI'] ?? ''));
if (str_contains($host, '8000')) {
    addCheck($checks, $g, 'Servidor web', 'ok', 'PHP embutido (iniciar.bat) — correto');
} else {
    addCheck($checks, $g, 'Servidor web', 'warn', $host ?: 'desconhecido', 'Use iniciar.bat e http://127.0.0.1:8000');
}

$g = 'Arquivos';
foreach ([
    'config/db.php' => $dbConfigFile,
    'database.sql' => $projectRoot . '/database.sql',
    'index.php' => $projectRoot . '/index.php',
    'iniciar.bat' => $projectRoot . '/iniciar.bat',
] as $label => $path) {
    addCheck($checks, $g, $label, is_file($path) ? 'ok' : 'fail', is_file($path) ? $path : 'AUSENTE');
}

$useSqlite = is_file($projectRoot . '/config/local.php')
    && str_contains((string) file_get_contents($projectRoot . '/config/local.php'), 'DB_USE_SQLITE')
    && str_contains((string) file_get_contents($projectRoot . '/config/local.php'), 'true');

$g = 'Banco de dados';
if ($useSqlite || !is_file($projectRoot . '/config/local.php')) {
    $useSqlite = true;
    $sqlitePath = $projectRoot . '/storage/venda_canais.sqlite';
    addCheck($checks, $g, 'Modo', 'ok', 'SQLite local — XAMPP/MySQL NÃO necessário');
    if (is_file($sqlitePath)) {
        addCheck($checks, $g, 'Arquivo SQLite', 'ok', $sqlitePath . ' (' . number_format(filesize($sqlitePath)) . ' bytes)');
    } else {
        addCheck($checks, $g, 'Arquivo SQLite', 'warn', 'Será criado ao abrir o painel', 'Abra http://127.0.0.1:8000/');
    }
    if (extension_loaded('pdo_sqlite')) {
        addCheck($checks, $g, 'pdo_sqlite', 'ok', 'habilitado');
    } else {
        addCheck($checks, $g, 'pdo_sqlite', 'fail', 'desabilitado — habilite no php.ini');
    }
    try {
        require_once $projectRoot . '/config/db.php';
        $n = (int) $pdo->query('SELECT COUNT(*) FROM channels')->fetchColumn();
        addCheck($checks, $g, 'Conexão + query', 'ok', "Painel pronto. Canais: $n");
    } catch (Throwable $e) {
        addCheck($checks, $g, 'Conexão', 'fail', $e->getMessage());
    }
} else {
    addCheck($checks, $g, 'Modo', 'warn', 'MySQL (produção)');
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug | Arena Stream</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Outfit',sans-serif;background:#080511;color:#e5e7eb;padding:24px}
        .wrap{max-width:900px;margin:0 auto}
        h1{font-size:1.6rem;font-weight:800;margin-bottom:6px}
        .sub{color:#9ca3af;font-size:14px;margin-bottom:20px}
        .alert{background:rgba(239,68,68,.12);border:1px solid #ef4444;border-radius:12px;padding:16px;margin-bottom:20px;font-size:14px}
        .alert strong{color:#fca5a5}
        .alert.ok{background:rgba(16,185,129,.1);border-color:#10b981}
        section{background:rgba(22,17,41,.6);border:1px solid rgba(124,58,237,.15);border-radius:14px;margin-bottom:14px;overflow:hidden}
        section h3{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#a78bfa;padding:12px 16px;background:rgba(124,58,237,.08)}
        table{width:100%;border-collapse:collapse;font-size:14px}
        td{padding:10px 16px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:top}
        td:first-child{width:30%;font-weight:600}
        .badge{font-size:10px;font-weight:700;padding:2px 7px;border-radius:5px;margin-right:6px;font-family:'JetBrains Mono',monospace}
        .badge.ok{background:#10b98133;color:#34d399}
        .badge.warn{background:#f59e0b33;color:#fbbf24}
        .badge.fail{background:#ef444433;color:#f87171}
        .detail{font-family:'JetBrains Mono',monospace;font-size:11px;color:#9ca3af;word-break:break-all}
        .fix{font-size:12px;color:#c4b5fd;margin-top:4px}
        .fix a{color:#a78bfa}
        .loading{padding:24px;text-align:center;color:#9ca3af}
        .spinner{display:inline-block;width:24px;height:24px;border:3px solid rgba(124,58,237,.3);border-top-color:#7c3aed;border-radius:50%;animation:spin .8s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        .actions{margin-top:20px;display:flex;gap:10px;flex-wrap:wrap}
        .btn{display:inline-block;padding:11px 18px;border-radius:10px;font-weight:600;text-decoration:none;font-size:14px;border:none;cursor:pointer}
        .btn-p{background:linear-gradient(135deg,#7c3aed,#ef4444);color:#fff}
        .btn-s{background:rgba(124,58,237,.2);color:#e5e7eb;border:1px solid rgba(124,58,237,.4)}
        .stats{display:flex;gap:12px;margin-bottom:16px}
        .stat{background:rgba(22,17,41,.8);border:1px solid rgba(124,58,237,.2);border-radius:10px;padding:12px 16px;min-width:80px}
        .stat b{font-size:1.4rem;display:block}
        .stat span{font-size:11px;color:#9ca3af}
        .stat.ok b{color:#10b981}.stat.fail b{color:#ef4444}.stat.warn b{color:#f59e0b}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Diagnóstico Arena Stream</h1>
    <p class="sub"><?= date('d/m/Y H:i:s') ?> — host <?= h($config['host']) ?>:<?= (int) $config['port'] ?> / db <?= h($config['name']) ?></p>

    <div class="alert ok" id="topAlert">
        <strong>Modo SQLite:</strong> não precisa de XAMPP. Execute <code>iniciar.bat</code> e acesse
        <a href="/" style="color:#6ee7b7">http://127.0.0.1:8000</a>. Deixe a janela do servidor aberta.
    </div>

    <div class="stats" id="stats" style="display:none">
        <div class="stat ok"><b id="sOk">0</b><span>OK</span></div>
        <div class="stat warn"><b id="sWarn">0</b><span>Avisos</span></div>
        <div class="stat fail"><b id="sFail">0</b><span>Falhas</span></div>
    </div>

    <?php foreach ($checks as $groupName => $items): ?>
    <section>
        <h3><?= h($groupName) ?></h3>
        <table>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><span class="badge <?= h($item['status']) ?>"><?= strtoupper($item['status']) ?></span><?= h($item['label']) ?></td>
                <td>
                    <?php if ($item['detail']): ?><div class="detail"><?= h($item['detail']) ?></div><?php endif; ?>
                    <?php if ($item['fix']): ?><div class="fix"><?= $item['fix'] ?></div><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </section>
    <?php endforeach; ?>

    <div class="actions">
        <a href="debug_stream.php" class="btn btn-p">Debug streams IPTV</a>
        <a href="ping.php" class="btn btn-s">Testar PHP</a>
        <a href="index.php" class="btn btn-p">Abrir painel</a>
    </div>
</div>
</body>
</html>
