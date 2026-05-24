<?php
$root = __DIR__;
$cacheDir = $root . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$statusFile = $cacheDir . '/mysql_status.json';
$lockFile = $cacheDir . '/mysql_running.lock';
$action = $argv[1] ?? '';

$php = PHP_BINARY ?: 'php';
$inner = $root . '/debug_mysql_inner.php';
$args = [$php, $inner];
if ($action !== '') {
    $args[] = $action;
}

$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open($args, $descriptors, $pipes, $root);

if (is_resource($proc)) {
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $deadline = time() + 8;
    while (time() < $deadline) {
        $st = proc_get_status($proc);
        if (!$st['running']) {
            break;
        }
        usleep(200000);
    }
    $st = proc_get_status($proc);
    if ($st['running']) {
        proc_terminate($proc);
        proc_close($proc);
        $fallback = [
            'checks' => [[
                'label' => 'MySQL',
                'status' => 'fail',
                'detail' => 'Conexão travou (limite 8s). O serviço MySQL está corrompido ou sobrecarregado.',
                'fix' => 'XAMPP: Stop no MySQL → espere 15 segundos → Start. Feche HeidiSQL, Laragon, etc.',
            ]],
            'summary' => ['ok' => 0, 'warn' => 0, 'fail' => 1],
            'config' => ['host' => '127.0.0.1', 'port' => 3306],
        ];
        file_put_contents($statusFile, json_encode($fallback, JSON_UNESCAPED_UNICODE));
    } else {
        proc_close($proc);
    }
}

if (!is_file($statusFile) || filesize($statusFile) < 10) {
    $fallback = [
        'checks' => [[
            'label' => 'MySQL',
            'status' => 'fail',
            'detail' => 'Não foi possível testar o MySQL.',
            'fix' => 'Inicie o MySQL no XAMPP e tente novamente.',
        ]],
        'summary' => ['ok' => 0, 'warn' => 0, 'fail' => 1],
        'config' => ['host' => '127.0.0.1', 'port' => 3306],
    ];
    file_put_contents($statusFile, json_encode($fallback, JSON_UNESCAPED_UNICODE));
}

@unlink($lockFile);
