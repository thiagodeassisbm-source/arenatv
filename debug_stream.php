<?php
/**
 * Debug de streams IPTV — descobre por que canais não abrem.
 * http://127.0.0.1:8000/debug_stream.php
 */
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/config/db.php';

$sampleChannels = [];
try {
    $sampleChannels = $pdo->query(
        "SELECT id, name, substr(url, 1, 90) AS url_short FROM channels ORDER BY RANDOM() LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $sampleChannels = $pdo->query(
        "SELECT id, name, substr(url, 1, 90) AS url_short FROM channels LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Stream | Arena Stream</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #07040e;
            --card: rgba(22, 14, 43, 0.85);
            --purple: #7c3aed;
            --purple-light: #a78bfa;
            --red: #ef4444;
            --green: #10b981;
            --yellow: #fbbf24;
            --text: #f3f4f6;
            --muted: #9ca3af;
            --border: rgba(124, 58, 237, 0.25);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', system-ui, sans-serif;
            background: radial-gradient(circle at top, #1b0c30, var(--bg));
            color: var(--text);
            min-height: 100vh;
            padding: 24px;
        }
        .wrap { max-width: 1100px; margin: 0 auto; }
        h1 { font-size: 26px; margin-bottom: 6px; }
        .sub { color: var(--muted); margin-bottom: 24px; font-size: 14px; }
        a { color: var(--purple-light); }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h2 { font-size: 16px; margin-bottom: 14px; color: var(--purple-light); }
        label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 6px; }
        input, select {
            width: 100%;
            background: rgba(0,0,0,0.35);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            color: var(--text);
            font-size: 14px;
            margin-bottom: 12px;
        }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 700px) { .row { grid-template-columns: 1fr; } }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--purple), #9333ea);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 700;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:disabled { opacity: 0.5; cursor: wait; }
        .samples { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .chip {
            background: rgba(124,58,237,0.15);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 12px;
            cursor: pointer;
            color: var(--purple-light);
        }
        .chip:hover { background: rgba(124,58,237,0.3); }
        #loading { display: none; color: var(--purple-light); margin: 16px 0; }
        .summary {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            line-height: 1.5;
        }
        .summary.ok { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.35); }
        .summary.fail { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.35); }
        .rec { padding: 10px 12px; border-radius: 8px; margin-bottom: 8px; font-size: 13px; }
        .rec.error { background: rgba(239,68,68,0.15); color: #fca5a5; }
        .rec.warn { background: rgba(251,191,36,0.12); color: #fde68a; }
        .rec.ok { background: rgba(16,185,129,0.12); color: #6ee7b7; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.06); vertical-align: top; }
        th { color: var(--muted); font-weight: 600; }
        .code-ok { color: var(--green); font-weight: 700; }
        .code-bad { color: var(--red); font-weight: 700; }
        .code-warn { color: var(--yellow); font-weight: 700; }
        pre {
            background: #0a0615;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            overflow: auto;
            font-size: 11px;
            max-height: 400px;
            color: #c4b5fd;
        }
        .top-links { margin-bottom: 20px; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top-links"><a href="index.php">← Painel</a> · <a href="debug.php">Debug geral</a></div>
    <h1>Debug de Streams IPTV</h1>
    <p class="sub">Testa conexão com o servidor IPTV, proxy PHP/Node e detecta o formato (TS / HLS / MP4).</p>

    <div class="card">
        <h2>Testar canal</h2>
        <div class="row">
            <div>
                <label>ID do canal</label>
                <input type="number" id="inp-id" placeholder="Ex: 1523">
            </div>
            <div>
                <label>Ou buscar por nome</label>
                <input type="text" id="inp-name" placeholder="Ex: RECORD ALAGOAS">
            </div>
        </div>
        <label>Ou colar URL do stream</label>
        <input type="text" id="inp-url" placeholder="http://servidor/.../ts">
        <button class="btn" id="btn-run" onclick="runDebug()">Executar diagnóstico</button>
        <p id="loading">Analisando stream… pode levar até 30 segundos.</p>
        <?php if ($sampleChannels): ?>
        <p style="font-size:12px;color:var(--muted);margin-top:14px">Canais aleatórios:</p>
        <div class="samples">
            <?php foreach ($sampleChannels as $sc): ?>
            <span class="chip" data-id="<?= (int)$sc['id'] ?>" data-name="<?= htmlspecialchars($sc['name'], ENT_QUOTES) ?>">
                #<?= (int)$sc['id'] ?> <?= htmlspecialchars($sc['name']) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div id="results" style="display:none">
        <div class="card">
            <h2>Resultado</h2>
            <div id="summary-box"></div>
            <div id="rec-box"></div>
            <h2 style="margin-top:16px">Testes upstream (servidor IPTV)</h2>
            <div style="overflow-x:auto"><table id="tbl-upstream"></table></div>
            <h2 style="margin-top:16px">Testes proxy local (PHP :8000 / Node :8787)</h2>
            <div style="overflow-x:auto"><table id="tbl-proxy"></table></div>
            <h2 style="margin-top:16px">JSON completo</h2>
            <pre id="json-out"></pre>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
        document.getElementById('inp-id').value = chip.dataset.id || '';
        document.getElementById('inp-name').value = chip.dataset.name || '';
        runDebug();
    });
});

async function runDebug() {
    const btn = document.getElementById('btn-run');
    const loading = document.getElementById('loading');
    const results = document.getElementById('results');
    const id = document.getElementById('inp-id').value.trim();
    const name = document.getElementById('inp-name').value.trim();
    const url = document.getElementById('inp-url').value.trim();

    if (!id && !name && !url) {
        alert('Informe ID, nome ou URL.');
        return;
    }

    let qs = '';
    if (id) qs = 'id=' + encodeURIComponent(id);
    else if (url) qs = 'url=' + encodeURIComponent(url);
    else qs = 'name=' + encodeURIComponent(name);

    btn.disabled = true;
    loading.style.display = 'block';
    results.style.display = 'none';

    try {
        const res = await fetch('ajax/debug_channel_stream.php?' + qs);
        const data = await res.json();
        renderResults(data);
        results.style.display = 'block';
    } catch (e) {
        alert('Erro: ' + e.message);
    } finally {
        btn.disabled = false;
        loading.style.display = 'none';
    }
}

function codeClass(code) {
    if (code >= 200 && code < 400) return 'code-ok';
    if (code >= 400) return 'code-bad';
    return 'code-warn';
}

function renderResults(data) {
    const summary = document.getElementById('summary-box');
    const recBox = document.getElementById('rec-box');
    document.getElementById('json-out').textContent = JSON.stringify(data, null, 2);

    if (!data.success) {
        summary.className = 'summary fail';
        summary.innerHTML = '<strong>Erro:</strong> ' + (data.error || 'desconhecido');
        return;
    }

    const ch = data.channel || {};
    const can = data.summary && data.summary.can_play;
    summary.className = 'summary ' + (can ? 'ok' : 'fail');
    summary.innerHTML =
        '<strong>' + (ch.name || 'Canal') + '</strong> (ID ' + ch.id + ')<br>' +
        'URL: <code style="font-size:11px;word-break:break-all">' + (ch.url || '') + '</code><br><br>' +
        '<strong>Probe:</strong> tipo ' + (data.probe && data.probe.stream_type || '?') +
        ' · Node proxy: ' + (data.node_proxy && data.node_proxy.ok ? 'ativo' : 'offline') + '<br>' +
        '<strong>Conclusão:</strong> ' + (can
            ? 'Servidor IPTV responde — player deve conseguir via proxy'
            : (data.summary && data.summary.main_issue || 'Servidor IPTV não entregou stream válido'));

    recBox.innerHTML = (data.recommendations || []).map(function (r) {
        return '<div class="rec ' + r.level + '">' + r.msg + '</div>';
    }).join('');

    const upHead = '<tr><th>Teste</th><th>HTTP</th><th>Content-Type</th><th>ms</th><th>Formato</th><th>Preview</th></tr>';
    const upBody = (data.upstream_tests || []).map(function (t) {
        var fmt = [];
        if (t.is_ts) fmt.push('TS');
        if (t.is_mp4) fmt.push('MP4');
        if (t.is_m3u8) fmt.push('HLS');
        if (t.is_html) fmt.push('HTML');
        if (!fmt.length) fmt.push('-');
        return '<tr><td>' + (t.test || '') + '<br><small style="color:#9ca3af">' + (t.variant_url || '').slice(-50) + '</small></td>' +
            '<td class="' + codeClass(t.http_code) + '">' + (t.http_code != null ? t.http_code : (t.error || '?')) + '</td>' +
            '<td>' + (t.content_type || '-') + '</td><td>' + (t.ms || '-') + '</td><td>' + fmt.join(', ') + '</td>' +
            '<td><small>' + (t.body_preview || t.error || '').slice(0, 80) + '</small></td></tr>';
    }).join('');
    document.getElementById('tbl-upstream').innerHTML = upHead + upBody;

    const pxHead = '<tr><th>Via</th><th>Modo</th><th>HTTP</th><th>Content-Type</th><th>Detalhe</th></tr>';
    const pxBody = (data.proxy_tests || []).map(function (t) {
        return '<tr><td>' + (t.via || '-') + '</td><td>' + (t.mode || '-') + '</td>' +
            '<td class="' + codeClass(t.http_code) + '">' + (t.http_code != null ? t.http_code : (t.error || '?')) + '</td>' +
            '<td>' + (t.content_type || '-') + '</td><td><small>' +
            (t.body_preview || t.error || (t.is_m3u8 ? 'playlist HLS' : '')).slice(0, 60) + '</small></td></tr>';
    }).join('') || '<tr><td colspan="5">Informe ID do canal para testar proxy</td></tr>';
    document.getElementById('tbl-proxy').innerHTML = pxHead + pxBody;
}
</script>
</body>
</html>
