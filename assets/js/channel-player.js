/**
 * Motor multi-estratégia: TS (mpegts.js), HLS (hls.js) e MP4 — proxy Node + PHP.
 */
(function (global) {
    let hls = null;
    let mpegtsPlayer = null;
    let tryTimer = null;
    let playing = false;

    function clearTryTimer() {
        if (tryTimer) {
            clearTimeout(tryTimer);
            tryTimer = null;
        }
    }

    function destroyPlayers(video) {
        clearTryTimer();
        if (hls) {
            hls.destroy();
            hls = null;
        }
        if (mpegtsPlayer) {
            try {
                mpegtsPlayer.pause();
                mpegtsPlayer.unload();
                mpegtsPlayer.detachMediaElement();
                mpegtsPlayer.destroy();
            } catch (e) { /* ignore */ }
            mpegtsPlayer = null;
        }
        if (video) {
            video.pause();
            video.removeAttribute('src');
            video.innerHTML = '';
            video.load();
        }
    }

    function playMp4(video, url, onOk, onFail) {
        video.src = url;
        video.load();
        const ok = () => {
            if (playing) return;
            playing = true;
            clearTryTimer();
            onOk();
        };
        video.addEventListener('playing', ok, { once: true });
        video.addEventListener('canplay', ok, { once: true });
        video.addEventListener('error', onFail, { once: true });
        video.play().catch(onFail);
    }

    function playHls(video, url, onOk, onFail) {
        if (!global.Hls || !Hls.isSupported()) {
            onFail();
            return;
        }
        hls = new Hls({
            enableWorker: true,
            lowLatencyMode: true,
            maxBufferLength: 18,
        });
        hls.loadSource(url);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            video.play().catch(() => {});
        });
        hls.on(Hls.Events.ERROR, (_, data) => {
            if (data.fatal) onFail();
        });
        const ok = () => {
            if (playing) return;
            playing = true;
            clearTryTimer();
            onOk();
        };
        video.addEventListener('playing', ok, { once: true });
    }

    function playMpegts(video, url, onOk, onFail) {
        if (!global.mpegts || !mpegts.getFeatureList().mseLivePlayback) {
            onFail();
            return;
        }
        mpegtsPlayer = mpegts.createPlayer({
            type: 'mpegts',
            url: url,
            isLive: true,
            hasAudio: true,
            hasVideo: true,
        }, {
            enableWorker: true,
            enableStashBuffer: true,
            stashInitialSize: 256 * 1024,
            lazyLoad: false,
            liveBufferLatencyChasing: true,
        });
        mpegtsPlayer.attachMediaElement(video);
        mpegtsPlayer.load();
        mpegtsPlayer.play();
        mpegtsPlayer.on(mpegts.Events.ERROR, onFail);
        const ok = () => {
            if (playing) return;
            playing = true;
            clearTryTimer();
            onOk();
        };
        video.addEventListener('playing', ok, { once: true });
    }

    function tryStrategy(video, strategy, onOk, onFail) {
        destroyPlayers(video);
        playing = false;
        const mode = strategy.mode;
        const url = strategy.url;

        if (mode === 'mp4') {
            playMp4(video, url, onOk, onFail);
            return;
        }
        if (mode === 'hls') {
            playHls(video, url, onOk, onFail);
            return;
        }
        playMpegts(video, url, onOk, onFail);
    }

    global.ArenaChannelPlayer = {
        start(video, strategies, callbacks) {
            const onOk = callbacks.onPlaying || function () {};
            const onFailAll = callbacks.onFailed || function () {};
            const onTrying = callbacks.onTrying || function () {};
            let index = 0;
            const timeoutMs = 6000;

            function next() {
                clearTryTimer();
                if (playing) return;
                if (index >= strategies.length) {
                    onFailAll();
                    return;
                }
                const current = strategies[index];
                index += 1;
                onTrying(current, index, strategies.length);

                tryStrategy(video, current, onOk, () => {
                    if (!playing) {
                        tryTimer = setTimeout(next, 100);
                    }
                });

                tryTimer = setTimeout(() => {
                    if (!playing) next();
                }, timeoutMs);
            }

            playing = false;
            destroyPlayers(video);
            next();
        },
        stop(video) {
            playing = false;
            destroyPlayers(video);
        },
    };
})(window);
