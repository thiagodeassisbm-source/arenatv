/**
 * Proxy de stream Arena Stream (Node.js) — melhor para TS/HLS ao vivo que o PHP embutido.
 * Porta: 8787
 */
const http = require('http');
const https = require('https');
const { URL } = require('url');

const PORT = 8787;
const UA = 'VLC/3.0.20 LibVLC/3.0.20';

function decodeTarget(encoded) {
    if (!encoded) return null;
    try {
        let clean = decodeURIComponent(encoded).replace(/ /g, '+');
        while (clean.length % 4 !== 0) {
            clean += '=';
        }
        const raw = Buffer.from(clean, 'base64').toString('utf8');
        if (/^https?:\/\//i.test(raw)) return raw;
    } catch (e) {
        console.error("[Proxy Node] Erro ao decodificar URL:", e.message);
    }
    return null;
}

function headersFor(targetUrl) {
    const p = new URL(targetUrl);
    const origin = `${p.protocol}//${p.host}`;
    return {
        'User-Agent': UA,
        Accept: '*/*',
        Connection: 'keep-alive',
        Referer: `${origin}/`,
        Origin: origin,
    };
}

function pipeStream(targetUrl, req, res, forceType, redirectsLeft = 6, originalUrl = null) {
    const baseTarget = originalUrl || targetUrl;
    const p = new URL(targetUrl);
    const lib = p.protocol === 'https:' ? https : http;
    const opts = {
        hostname: p.hostname,
        port: p.port || (p.protocol === 'https:' ? 443 : 80),
        path: p.pathname + p.search,
        method: 'GET',
        headers: headersFor(baseTarget),
    };
    if (req.headers.range) opts.headers.Range = req.headers.range;

    const upstream = lib.request(opts, (up) => {
        const code = up.statusCode || 502;
        if ([301, 302, 303, 307, 308].includes(code) && redirectsLeft > 0 && up.headers.location) {
            const next = new URL(up.headers.location, targetUrl).href;
            up.resume();
            return pipeStream(next, req, res, forceType, redirectsLeft - 1, baseTarget);
        }
        if (code >= 400) {
            res.writeHead(code, { 'Access-Control-Allow-Origin': '*', 'Content-Type': 'text/plain' });
            res.end(`Upstream HTTP ${code}`);
            return;
        }
        if (code !== 200 && code !== 206) {
            res.writeHead(code, { 'Access-Control-Allow-Origin': '*', 'Content-Type': 'text/plain' });
            up.resume();
            up.on('end', () => res.end());
            return;
        }
        const outHeaders = {
            'Access-Control-Allow-Origin': '*',
            'Cache-Control': 'no-cache',
        };
        if (forceType === 'mpegts') outHeaders['Content-Type'] = 'video/mp2t';
        else if (up.headers['content-type']) outHeaders['Content-Type'] = up.headers['content-type'];
        
        if (up.headers['content-range']) outHeaders['Content-Range'] = up.headers['content-range'];
        if (up.headers['content-length']) outHeaders['Content-Length'] = up.headers['content-length'];
        if (up.headers['accept-ranges']) outHeaders['Accept-Ranges'] = up.headers['accept-ranges'];

        res.writeHead(code, outHeaders);
        up.pipe(res);
    });
    upstream.on('error', () => {
        res.writeHead(502, { 'Access-Control-Allow-Origin': '*' });
        res.end('Proxy error');
    });
    upstream.end();
}

function fetchPlaylist(targetUrl) {
    return new Promise((resolve, reject) => {
        const p = new URL(targetUrl);
        const lib = p.protocol === 'https:' ? https : http;
        lib.get(
            {
                hostname: p.hostname,
                port: p.port || (p.protocol === 'https:' ? 443 : 80),
                path: p.pathname + p.search,
                headers: headersFor(targetUrl),
            },
            (res) => {
                // Se não for uma playlist de texto (for um vídeo direto), não tenta acumular buffer infinito
                const contentType = res.headers['content-type'] || '';
                if (!contentType.includes('mpegurl') && !contentType.includes('m3u') && !contentType.includes('text')) {
                    resolve({ status: res.statusCode, isVideoDirect: true, finalUrl: targetUrl, responseStream: res });
                    return;
                }

                let data = '';
                res.on('data', (c) => {
                    data += c;
                    // Aumentado o teto seguro para playlists HLS legítimas extensas
                    if (data.length > 5000000) res.destroy(); 
                });
                res.on('end', () => resolve({ status: res.statusCode, body: data, finalUrl: targetUrl, isVideoDirect: false }));
            }
        ).on('error', reject);
    });
}

function rewriteM3u8(body, baseUrl, channelId, tParam) {
    const base = baseUrl;
    const lines = body.split(/\r?\n/);
    return lines
        .map((line) => {
            const trim = line.trim();
            if (!trim || trim[0] === '#') return line;
            try {
                const abs = new URL(trim, base).href;
                const enc = encodeURIComponent(Buffer.from(abs).toString('base64'));
                
                // Correção de Detecção Robusta: Identifica se o link é um segmento ou fluxo de vídeo contínuo
                const isVideoSegment = /\.(ts|mp4|m4s|aac|mp3|m4a)(\?|$)/i.test(trim) || 
                                       trim.includes('/ts') || 
                                       trim.includes('/live/') || 
                                       trim.includes('/play/');

                if (isVideoSegment) {
                    if (channelId) {
                        return `http://127.0.0.1:${PORT}/stream?id=${channelId}&t=${enc}&type=mpegts`;
                    }
                    return `http://127.0.0.1:${PORT}/stream?t=${enc}&type=mpegts`;
                }
                
                if (channelId) {
                    return `http://127.0.0.1:${PORT}/stream?id=${channelId}&t=${enc}&format=hls`;
                }
                return `http://127.0.0.1:${PORT}/stream?t=${enc}&format=hls`;
            } catch (e) {
                return line;
            }
        })
        .join('\n');
}

const server = http.createServer(async (req, res) => {
    if (req.method === 'OPTIONS') {
        res.writeHead(204, {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, OPTIONS',
            'Access-Control-Allow-Headers': 'Range, Content-Type',
        });
        return res.end();
    }

    const u = new URL(req.url, `http://127.0.0.1:${PORT}`);
    if (u.pathname !== '/stream' && u.pathname !== '/ping') {
        res.writeHead(404);
        return res.end('Not found');
    }
    if (u.pathname === '/ping') {
        res.writeHead(200, { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' });
        return res.end('{"ok":true}');
    }

    const target = decodeTarget(u.searchParams.get('t'));
    if (!target) {
        res.writeHead(400, { 'Access-Control-Allow-Origin': '*' });
        return res.end('URL invalida');
    }

    const format = u.searchParams.get('format') || '';
    const pipeType = u.searchParams.get('type') || '';
    const channelId = u.searchParams.get('id') || '';

    if (format === 'hls') {
        try {
            const pl = await fetchPlaylist(target);
            if (pl.isVideoDirect) {
                // Se o fetchPlaylist descobriu que era um link de vídeo direto disfarçado, faz o pipe direto
                return pipeStream(target, req, res, pipeType === 'mpegts' ? 'mpegts' : null);
            }
            if (pl.status >= 400) {
                res.writeHead(pl.status, { 'Access-Control-Allow-Origin': '*' });
                return res.end(`Playlist HTTP ${pl.status}`);
            }
            if (!pl.body.trimStart().startsWith('#EXTM3U')) {
                return pipeStream(target, req, res, pipeType === 'mpegts' ? 'mpegts' : null);
            }
            res.writeHead(200, {
                'Content-Type': 'application/vnd.apple.mpegurl',
                'Access-Control-Allow-Origin': '*',
                'Cache-Control': 'no-cache',
            });
            res.end(rewriteM3u8(pl.body, pl.finalUrl, channelId, u.searchParams.get('t')));
        } catch (e) {
            res.writeHead(502, { 'Access-Control-Allow-Origin': '*' });
            res.end('HLS error');
        }
        return;
    }

    pipeStream(target, req, res, pipeType === 'mpegts' ? 'mpegts' : null);
});

server.listen(PORT, '127.0.0.1', () => {
    console.log(`[Arena Stream] Proxy Node ativo: http://127.0.0.1:${PORT}/ping`);
});
