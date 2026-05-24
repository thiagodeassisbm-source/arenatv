<?php
/**
 * API JSON — executa teste MySQL e responde em até ~8 segundos.
 */
header('Content-Type: application/json; charset=utf-8');

$root = __DIR__;
$cacheDir = $root . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$statusFile = $cacheDir . '/mysql_status.json';
$action = $_GET['action'] ?? '';
$php = PHP_BINARY ?: 'php';
$inner = $root . '/debug_mysql_inner.php';

$args = [$php, $inner];
if ($action === 'bootstrap') {
    $args[] = 'bootstrap';
}

$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open($args, $descriptors, $pipes, $root);

if (!is_resource($proc)) {
    echo json_encode([
        'checks' => [[
            'label' => 'Processo PHP',
            'status' => 'fail',
            'detail' => 'Não foi possível iniciar o teste MySQL.',
            'fix' => 'Reinicie o iniciar.bat.',
        ]],
        'summary' => ['ok' => 0, 'warn' => 0, 'fail' => 1],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

fclose($pipes[0]);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

$deadline = time() + 8;
while (time() < $deadline) {
    $st = proc_get_status($proc);
    if (!$st['running']) {
        break;
    }
    usleep(150000);
}

$st = proc_get_status($proc);
if ($st['running']) {
    proc_terminate($proc);
    proc_close($proc);
    $out = [
        'checks' => [[
            'label' => 'MySQL',
            'status' => 'fail',
            'detail' => 'Conexão travou (limite 8s). Erro típico: handshake MySQL (2013) — serviço travado.',
            'fix' => 'Execute <strong>corrigir_mysql.bat</strong> → depois <strong>instalar_banco.bat</strong> → <strong>iniciar.bat</strong>. phpMyAdmin exige Apache ligado no XAMPP (porta 80).',
        ]],
        'summary' => ['ok' => 0, 'warn' => 0, 'fail' => 1],
        'config' => ['host' => '127.0.0.1', 'port' => 3306],
    ];
    file_put_contents($statusFile, json_encode($out, JSON_UNESCAPED_UNICODE));
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

proc_close($proc);

if (is_file($statusFile) && filesize($statusFile) > 10) {
    readfile($statusFile);
    exit;
}

echo json_encode([
    'checks' => [[
        'label' => 'MySQL',
        'status' => 'fail',
        'detail' => 'Teste terminou sem resultado.',
        'fix' => 'Reinicie o MySQL no XAMPP e clique em Testar MySQL de novo.',
    ]],
    'summary' => ['ok' => 0, 'warn' => 0, 'fail' => 1],
], JSON_UNESCAPED_UNICODE);
