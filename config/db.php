<?php
// Garantir fuso horário padrão do Brasil para todas as páginas e APIs
date_default_timezone_set('America/Sao_Paulo');

/**
 * Conexão com banco de dados.
 * Local: SQLite (automático, sem XAMPP).
 * Produção (Hostinger): MySQL — veja config/local.php.example
 */

if (is_file(__DIR__ . '/local.php')) {
    require __DIR__ . '/local.php';
}
if (!defined('DB_USE_SQLITE')) {
    define('DB_USE_SQLITE', true);
}

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'venda_canais');
define('DB_CHARSET', 'utf8mb4');
define('DB_SQLITE_PATH', dirname(__DIR__) . '/storage/venda_canais.sqlite');

function dbPdoOptions(): array
{
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
        $opts[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 3;
    }
    return $opts;
}

function dbIsSqlite(): bool
{
    return defined('DB_DRIVER') && DB_DRIVER === 'sqlite';
}

function dbUpsertSetting(PDO $pdo, string $key, string $value): void
{
    if (dbIsSqlite()) {
        $sql = 'INSERT INTO settings (meta_key, meta_value) VALUES (?, ?)
                ON CONFLICT(meta_key) DO UPDATE SET meta_value = excluded.meta_value';
    } else {
        $sql = "INSERT INTO settings (meta_key, meta_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)";
    }
    $pdo->prepare($sql)->execute([$key, $value]);
}

function dbClearChannels(PDO $pdo): void
{
    if (dbIsSqlite()) {
        $pdo->exec('DELETE FROM channels');
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'channels'");
        return;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE TABLE channels');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

function dbNormalizeGroupName(?string $group): string
{
    $group = trim((string) $group);
    return $group !== '' ? $group : 'Sem Categoria';
}

function dbChannelDedupeKey(string $name, string $url, ?string $group): string
{
    $n = mb_strtolower(trim($name));
    $g = mb_strtolower(dbNormalizeGroupName($group));
    $u = strtolower(trim($url));

    // Um canal por nome dentro da mesma categoria (evita 15x "A Mulher Que..." em Doramas)
    return 'ng:' . $g . '|' . $n;
}

function dbChannelUrlKey(string $url): ?string
{
    $url = trim($url);
    return $url !== '' ? 'url:' . strtolower($url) : null;
}

/** Remove duplicados: mesma URL ou mesmo nome+categoria. Retorna estatísticas. */
function dbDedupeChannels(PDO $pdo): array
{
    $rows = $pdo->query('SELECT id, name, url, group_name FROM channels ORDER BY id ASC')->fetchAll();
    $seenNameGroup = [];
    $seenUrl = [];
    $deleteIds = [];

    foreach ($rows as $row) {
        $nameKey = dbChannelDedupeKey((string) $row['name'], (string) $row['url'], $row['group_name']);
        $urlKey = dbChannelUrlKey((string) $row['url']);

        if (isset($seenNameGroup[$nameKey]) || ($urlKey !== null && isset($seenUrl[$urlKey]))) {
            $deleteIds[] = (int) $row['id'];
            continue;
        }
        $seenNameGroup[$nameKey] = (int) $row['id'];
        if ($urlKey !== null) {
            $seenUrl[$urlKey] = (int) $row['id'];
        }
    }

    $removed = count($deleteIds);
    if ($removed > 0) {
        $chunks = array_chunk($deleteIds, 500);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $pdo->prepare("DELETE FROM channels WHERE id IN ($placeholders)")->execute($chunk);
        }
    }

    return [
        'before' => count($rows),
        'after' => count($rows) - $removed,
        'removed' => $removed,
    ];
}

/** Agrupa lista de canais para importação (mantém o primeiro de cada chave). */
function dbUniqueChannelsForImport(array $channels): array
{
    $seenNameGroup = [];
    $seenUrl = [];
    $unique = [];

    foreach ($channels as $chan) {
        $name = trim((string) ($chan['name'] ?? ''));
        $url = trim((string) ($chan['url'] ?? ''));
        $group = dbNormalizeGroupName($chan['group'] ?? $chan['group_name'] ?? null);

        if ($name === '' && $url === '') {
            continue;
        }

        $name = $name !== '' ? $name : 'Canal Sem Nome';
        $nameKey = dbChannelDedupeKey($name, $url, $group);
        $urlKey = dbChannelUrlKey($url);

        if (isset($seenNameGroup[$nameKey]) || ($urlKey !== null && isset($seenUrl[$urlKey]))) {
            continue;
        }
        $seenNameGroup[$nameKey] = true;
        if ($urlKey !== null) {
            $seenUrl[$urlKey] = true;
        }

        $chan['name'] = $name !== '' ? $name : 'Canal Sem Nome';
        $chan['group'] = $group;
        $chan['group_name'] = $group;
        $unique[] = $chan;
    }

    return $unique;
}

function dbBootstrapSqlite(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        meta_key TEXT NOT NULL UNIQUE,
        meta_value TEXT,
        updated_at TEXT DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS channels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        logo TEXT,
        url TEXT NOT NULL,
        group_name TEXT,
        tvg_id TEXT,
        is_sports INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now'))
    )");

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_channels_sports ON channels(is_sports)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_channels_name ON channels(name)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS games (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        channel_id INTEGER,
        game_date TEXT,
        price REAL DEFAULT 0,
        status TEXT DEFAULT 'ativo',
        external_link TEXT,
        transmission_start TEXT,
        transmission_end TEXT,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE SET NULL
    )");

    try {
        $pdo->exec("ALTER TABLE games ADD COLUMN transmission_start TEXT");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE games ADD COLUMN transmission_end TEXT");
    } catch (PDOException $e) {}

    $defaults = [
        ['site_name', 'Arena Stream - Venda de Jogos'],
        ['currency', 'BRL'],
        ['m3u_url', ''],
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings (meta_key, meta_value) VALUES (?, ?)');
    foreach ($defaults as [$k, $v]) {
        $stmt->execute([$k, $v]);
    }
}

function dbConnectSqlite(): PDO
{
    if (!extension_loaded('pdo_sqlite')) {
        throw new PDOException('Extensão pdo_sqlite não está habilitada no PHP.');
    }

    $dir = dirname(DB_SQLITE_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, dbPdoOptions());
    dbBootstrapSqlite($pdo);
    return $pdo;
}

function dbConnectMysql(): PDO
{
    $targets = [
        ['type' => 'tcp', 'host' => DB_HOST, 'port' => DB_PORT],
        ['type' => 'tcp', 'host' => '127.0.0.1', 'port' => DB_PORT],
    ];
    $socket = 'C:/xampp/mysql/mysql.sock';
    if (file_exists($socket)) {
        $targets[] = ['type' => 'socket', 'socket' => $socket];
    }

    $lastError = null;
    foreach ($targets as $target) {
        try {
            if (($target['type'] ?? '') === 'socket') {
                $dsn = 'mysql:unix_socket=' . $target['socket'] . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $target['host'],
                    $target['port'],
                    DB_NAME,
                    DB_CHARSET
                );
            }
            return new PDO($dsn, DB_USER, DB_PASS, dbPdoOptions());
        } catch (PDOException $e) {
            $lastError = $e;
            $code = (int) ($e->errorInfo[1] ?? 0);
            if ($code === 1049) {
                $serverDsn = ($target['type'] ?? '') === 'socket'
                    ? 'mysql:unix_socket=' . $target['socket'] . ';charset=' . DB_CHARSET
                    : sprintf('mysql:host=%s;port=%d;charset=%s', $target['host'], $target['port'], DB_CHARSET);
                $s = new PDO($serverDsn, DB_USER, DB_PASS, dbPdoOptions());
                $s->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $sql = file_get_contents(dirname(__DIR__) . '/database.sql');
                $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                $s->exec('USE `' . DB_NAME . '`');
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $st) {
                    if ($st !== '') {
                        $s->exec($st);
                    }
                }
                return new PDO($dsn, DB_USER, DB_PASS, dbPdoOptions());
            }
            if (in_array($code, [2002, 2003, 2013], true)) {
                continue;
            }
            throw $e;
        }
    }
    throw $lastError ?? new PDOException('MySQL indisponível.');
}

function dbConnect(): PDO
{
    if (DB_USE_SQLITE) {
        define('DB_DRIVER', 'sqlite');
        return dbConnectSqlite();
    }
    define('DB_DRIVER', 'mysql');
    return dbConnectMysql();
}

function dbConnectionHint(Throwable $e): string
{
    $msg = $e->getMessage();
    if (DB_USE_SQLITE) {
        return 'Falha no banco SQLite em <code>storage/venda_canais.sqlite</code>. Verifique se a pasta <code>storage</code> tem permissão de escrita.';
    }
    if (str_contains($msg, '2013') || str_contains($msg, 'handshake')) {
        return 'MySQL travado no XAMPP. Solução rápida: em <code>config/local.php</code> deixe <code>DB_USE_SQLITE</code> como <code>true</code> (já é o padrão).';
    }
    return 'MySQL não conectou. Use SQLite local (<code>config/local.php</code>) ou corrija o XAMPP.';
}

try {
    $pdo = dbConnect();
    if (!dbIsSqlite()) {
        try {
            $pdo->exec("ALTER TABLE games ADD COLUMN transmission_start DATETIME NULL");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE games ADD COLUMN transmission_end DATETIME NULL");
        } catch (PDOException $e) {}
    }
} catch (Throwable $e) {
    header('HTTP/1.1 500 Internal Server Error');
    $hint = dbConnectionHint($e);
    $driverLabel = DB_USE_SQLITE ? 'SQLite' : 'MySQL';
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erro de Banco | Arena Stream</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
        <style>
            *{box-sizing:border-box;margin:0;padding:0;font-family:'Outfit',sans-serif}
            body{background:#080511;color:#f3f4f6;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
            .card{background:rgba(22,17,41,.85);border:1px solid rgba(124,58,237,.3);border-radius:20px;padding:32px;max-width:560px;width:100%}
            h1{font-size:1.5rem;margin-bottom:12px}
            p{color:#9ca3af;margin-bottom:16px;line-height:1.6}
            .hint{background:rgba(124,58,237,.12);padding:14px;border-radius:10px;font-size:14px;margin-bottom:16px}
            .err{font-family:monospace;font-size:12px;color:#f87171;background:#0b0813;padding:12px;border-radius:8px;margin-bottom:16px}
            a.btn{display:inline-block;background:linear-gradient(135deg,#7c3aed,#ef4444);color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none;font-weight:600}
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Erro de banco (<?= htmlspecialchars($driverLabel) ?>)</h1>
            <p>Não foi possível iniciar o armazenamento de dados.</p>
            <div class="hint"><?= $hint ?></div>
            <div class="err"><?= htmlspecialchars($msg) ?></div>
            <a href="debug.php" class="btn">Abrir diagnóstico</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
