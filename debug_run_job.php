<?php
/** Dispara teste MySQL em processo separado (não trava o servidor). */
header('Content-Type: application/json; charset=utf-8');

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$action = $_GET['action'] ?? '';
$lockFile = $cacheDir . '/mysql_running.lock';
@file_put_contents($lockFile, (string) time());

$phpBin = PHP_BINARY ?: 'php';
$job = __DIR__ . '/debug_mysql_job.php';
$arg = $action !== '' ? ' ' . escapeshellarg($action) : '';

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    pclose(popen('start /B "" "' . $phpBin . '" "' . $job . '"' . $arg, 'r'));
} else {
    exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($job) . $arg . ' > /dev/null 2>&1 &');
}

// job grava cache/mysql_status.json

echo json_encode(['started' => true]);
