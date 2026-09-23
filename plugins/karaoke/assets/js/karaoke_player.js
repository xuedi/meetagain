/**
 * Karaoke Player -- Consent gate, provider adapters and the current-line marker
 *
 * The gate form loads the video once by posting back to the song page; this module mounts the iframe in
 * place instead, and stores the "always load" choice through the core consent endpoint first when that
 * box is ticked. Each provider that reports its playback position gets an adapter with the same shape
 * (onTime, seekTo): YouTube through its IFrame Player API, loaded only after consent, TikTok through the
 * messages its embed player posts. The adapter drives the marker on the lyric lines, click-to-seek and the
 * line loop. Other providers play without a marker. Without JavaScript the gate and the lyrics still work.
 *
 * Loaded in:  plugins/karaoke/templates/detail.html.twig, plugins/karaoke/templates/timing.html.twig
 * Used by:    [data-karaoke-player] with [data-karaoke-stage], [data-karaoke-gate], [data-karaoke-lyrics],
 *             [data-karaoke-controls] (plugins/karaoke/templates/_player.html.twig)
 */
window.KaraokePlayer = (() => {
    const TIKTOK_ORIGIN = 'https://www.tiktok.com';
    const POLL_MS = 200;

    const loadYouTubeApi = () => {
        if (window.YT && window.YT.Player) {
            return Promise.resolve(window.YT);
        }
        if (!loadYouTubeApi.pending) {
            loadYouTubeApi.pending = new Promise((resolve) => {
                const previous = window.onYouTubeIframeAPIReady;
                window.onYouTubeIframeAPIReady = () => {
                    if (typeof previous === 'function') {
                        previous();
                    }
                    resolve(window.YT);
                };
                const script = document.createElement('script');
                script.src = 'https://www.youtube.com/iframe_api';
                document.head.appendChild(script);
            });
        }
        return loadYouTubeApi.pending;
    };

    const youTubeAdapter = (frame, onTime) => {
        let player = null;
        let timer = null;
        const stop = () => {
            clearInterval(timer);
            timer = null;
        };
        const poll = () => {
            if (player && typeof player.getCurrentTime === 'function') {
                onTime(Math.round(player.getCurrentTime() * 1000));
            }
        };
        loadYouTubeApi().then((YT) => {
            player = new YT.Player(frame, {
                events: {
                    onStateChange: (event) => {
                        poll();
                        if (event.data === YT.PlayerState.PLAYING) {
                            timer = timer || setInterval(poll, POLL_MS);
                        } else {
                            stop();
                        }
                    },
                },
            });
        });
        return {
            seekTo: (ms) => {
                if (player && typeof player.seekTo === 'function') {
                    player.seekTo(ms / 1000, true);
                    onTime(ms);
                }
            },
            currentMs: () => (player && typeof player.getCurrentTime === 'function' ? Math.round(player.getCurrentTime() * 1000) : 0),
        };
    };

    const tikTokAdapter = (frame, onTime) => {
        let lastMs = 0;
        window.addEventListener('message', (event) => {
            if (event.origin !== TIKTOK_ORIGIN || event.source !== frame.contentWindow) {
                return;
            }
            let data = event.data;
            if (typeof data === 'string') {
                try {
                    data = JSON.parse(data);
                } catch {
                    return;
                }
            }
            if (data && data['x-tiktok-player'] && data.type === 'onCurrentTime' && data.value) {
                lastMs = Math.round(Number(data.value.currentTime) * 1000);
                onTime(lastMs);
            }
        });
        return {
            seekTo: (ms) => {
                frame.contentWindow.postMessage({'x-tiktok-player': true, type: 'seekTo', value: ms / 1000}, TIKTOK_ORIGIN);
                lastMs = ms;
                onTime(ms);
            },
            currentMs: () => lastMs,
        };
    };

    const ADAPTERS = {youtube: youTubeAdapter, tiktok: tikTokAdapter};

    const mountFrame = (stage) => {
        const frame = document.createElement('iframe');
        frame.className = 'has-ratio';
        frame.src = stage.dataset.embedUrl;
        frame.title = document.title;
        frame.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture';
        frame.allowFullscreen = true;
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.dataset.karaokeFrame = '';
        stage.replaceChildren(frame);
        return frame;
    };

    const whenFrameReady = (stage, callback) => {
        const existing = stage.querySelector('[data-karaoke-frame]');
        if (existing) {
            callback(existing);
            return;
        }
        const gate = stage.querySelector('[data-karaoke-gate]');
        if (!gate) {
            return;
        }
        const always = gate.querySelector('[data-karaoke-always]');
        if (always) {
            always.classList.remove('is-hidden');
        }
        gate.addEventListener('submit', (event) => {
            event.preventDefault();
            const button = gate.querySelector('[data-consent-url]');
            const remember = gate.querySelector('input[name="always"]');
            const mount = () => callback(mountFrame(stage));
            if (!remember || !remember.checked || !button) {
                mount();
                return;
            }
            const body = new FormData();
            body.append('_token', gate.querySelector('input[name="_token"]').value);
            maFetch(button.dataset.consentUrl, true, body).then(mount, mount);
        });
    };

    const attach = (root, onTime) => {
        const stage = root.querySelector('[data-karaoke-stage]');
        if (!stage) {
            return Promise.resolve(null);
        }
        return new Promise((resolve) => {
            whenFrameReady(stage, (frame) => {
                const factory = ADAPTERS[stage.dataset.provider];
                resolve(factory ? factory(frame, onTime) : null);
            });
        });
    };

    const readFlag = (key) => {
        try {
            return window.localStorage.getItem('karaoke.' + key) === '1';
        } catch {
            return false;
        }
    };

    const writeFlag = (key, value) => {
        try {
            window.localStorage.setItem('karaoke.' + key, value ? '1' : '0');
        } catch {}
    };

    const initLyrics = (root) => {
        const list = root.querySelector('[data-karaoke-lyrics]');
        const controls = root.querySelector('[data-karaoke-controls]');
        const offsetMs = Number(root.dataset.offsetMs || 0);
        const lines = list ? Array.from(list.querySelectorAll('[data-start-ms]')) : [];
        const starts = lines.map((line) => Number(line.dataset.startMs) + offsetMs);
        let current = -1;
        let loop = false;
        let adapter = null;

        const indexAt = (ms) => {
            let index = -1;
            for (let i = 0; i < starts.length && starts[i] <= ms; i++) {
                index = i;
            }
            return index;
        };

        const scrollIntoList = (line) => {
            const top = line.offsetTop - list.clientHeight / 3;
            list.scrollTo({top: Math.max(0, top), behavior: 'smooth'});
        };

        const onTime = (ms) => {
            if (loop && current >= 0 && current + 1 < starts.length && ms >= starts[current + 1] && adapter) {
                adapter.seekTo(starts[current]);
                return;
            }
            const index = indexAt(ms);
            if (index === current) {
                return;
            }
            if (current >= 0) {
                lines[current].classList.remove('is-current');
                lines[current].removeAttribute('aria-current');
            }
            current = index;
            if (current >= 0) {
                lines[current].classList.add('is-current');
                lines[current].setAttribute('aria-current', 'true');
                scrollIntoList(lines[current]);
            }
        };

        if (controls) {
            controls.classList.remove('is-hidden');
            controls.querySelectorAll('[data-karaoke-toggle]').forEach((button) => {
                const key = button.dataset.karaokeToggle;
                const apply = (on) => {
                    button.setAttribute('aria-pressed', on ? 'true' : 'false');
                    button.classList.toggle('is-link', on);
                    if (key === 'loop') {
                        loop = on;
                    } else if (list) {
                        list.classList.toggle(key, on);
                    }
                };
                if (key !== 'loop') {
                    apply(readFlag(key));
                }
                button.addEventListener('click', () => {
                    const on = button.getAttribute('aria-pressed') !== 'true';
                    apply(on);
                    if (key !== 'loop') {
                        writeFlag(key, on);
                    }
                });
            });
        }

        return {
            onTime,
            connect: (connected) => {
                adapter = connected;
                if (!adapter || !list || lines.length === 0) {
                    return;
                }
                list.classList.add('is-seekable');
                lines.forEach((line, i) => {
                    line.tabIndex = 0;
                    line.setAttribute('role', 'button');
                    const seek = () => adapter.seekTo(starts[i]);
                    line.addEventListener('click', seek);
                    line.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            seek();
                        }
                    });
                });
            },
        };
    };

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.querySelector('[data-karaoke-player]');
        if (!root || root.hasAttribute('data-karaoke-custom')) {
            return;
        }
        const lyrics = initLyrics(root);
        attach(root, lyrics.onTime).then(lyrics.connect);
    });

    return {attach};
})();
