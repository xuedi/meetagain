/**
 * Karaoke Timing -- Tap along to stamp each lyric line with the playback time
 *
 * Every line has a plain time input, so the page works without JavaScript. With it, the stamp button (or
 * the space key while the page has focus) writes the player's current time into the next line's input and
 * moves on; undo steps back and restores what was there. Clicking a row makes it the next one. Rows are
 * marked while the video plays, from the times currently in the inputs, so a wrong stamp shows at once.
 *
 * Loaded in:  plugins/karaoke/templates/timing.html.twig
 * Used by:    [data-karaoke-player][data-karaoke-custom] with [data-karaoke-row], [data-karaoke-stamp],
 *             [data-karaoke-undo], [data-karaoke-timing-controls] and [data-karaoke-timing-help]
 * Depends on: karaoke_player.js (window.KaraokePlayer.attach)
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-karaoke-player][data-karaoke-custom]');
    if (!root || !window.KaraokePlayer) {
        return;
    }

    const rows = Array.from(root.querySelectorAll('[data-karaoke-row]'));
    const inputs = rows.map((row) => row.querySelector('input'));
    const offsetInput = root.querySelector('input[name="offset"]');
    const history = [];
    let next = Math.max(0, inputs.findIndex((input) => input.value.trim() === ''));
    let current = -1;

    const format = (ms) => {
        const minutes = Math.floor(ms / 60000);
        const seconds = Math.floor((ms % 60000) / 1000);
        const centis = Math.floor((ms % 1000) / 10);
        return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0') + '.' + String(centis).padStart(2, '0');
    };

    const parse = (value) => {
        const match = /^\[?(\d{1,3}):([0-5]\d)(?:[.:](\d{1,3}))?\]?$/.exec(value.trim());
        if (!match) {
            return null;
        }
        return (Number(match[1]) * 60 + Number(match[2])) * 1000 + Number((match[3] || '').padEnd(3, '0'));
    };

    const markNext = () => {
        rows.forEach((row, i) => row.classList.toggle('is-next', i === next));
        if (rows[next]) {
            rows[next].scrollIntoView({block: 'nearest', behavior: 'smooth'});
        }
    };

    const onTime = (ms) => {
        const shifted = ms - Number(offsetInput ? offsetInput.value : 0);
        let index = -1;
        inputs.forEach((input, i) => {
            const start = parse(input.value);
            if (start !== null && start <= shifted) {
                index = i;
            }
        });
        if (index === current) {
            return;
        }
        if (current >= 0) {
            rows[current].classList.remove('is-current');
        }
        current = index;
        if (current >= 0) {
            rows[current].classList.add('is-current');
        }
    };

    window.KaraokePlayer.attach(root, onTime).then((adapter) => {
        if (!adapter || rows.length === 0) {
            return;
        }

        const stamp = () => {
            if (next >= inputs.length) {
                return;
            }
            const ms = Math.max(0, adapter.currentMs() - Number(offsetInput ? offsetInput.value : 0));
            history.push({index: next, previous: inputs[next].value});
            inputs[next].value = format(ms);
            next += 1;
            markNext();
        };

        const undo = () => {
            const last = history.pop();
            if (!last) {
                return;
            }
            inputs[last.index].value = last.previous;
            next = last.index;
            markNext();
        };

        root.querySelector('[data-karaoke-timing-controls]').classList.remove('is-hidden');
        root.querySelector('[data-karaoke-timing-help]').classList.remove('is-hidden');
        root.querySelector('[data-karaoke-stamp]').addEventListener('click', stamp);
        root.querySelector('[data-karaoke-undo]').addEventListener('click', undo);

        rows.forEach((row, i) => {
            row.addEventListener('click', (event) => {
                if (event.target.tagName !== 'INPUT') {
                    next = i;
                    markNext();
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            const typing = ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON'].includes(event.target.tagName);
            if (typing || event.repeat) {
                return;
            }
            if (event.key === ' ') {
                event.preventDefault();
                stamp();
            } else if (event.key === 'Backspace') {
                event.preventDefault();
                undo();
            }
        });

        markNext();
    });
});
