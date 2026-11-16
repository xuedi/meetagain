/**
 * Messages - composer keyboard handling, auto-grow, in-place edit and edit-window countdown.
 *
 * Each chat layout on the page ([data-chat], desktop and mobile) holds its own thread and composer form, so every
 * handler is bound per layout. Enter sends and Shift+Enter breaks the line; on touch devices Enter keeps its
 * default and only the button sends. A send goes into an outbox posted in the background one at a time, and the
 * thread and conversation list are swapped in from the page each post redirects to, so the composer keeps its focus
 * and takes the next message at once. A failed send puts every unsent text back; a 422 then falls back to a plain
 * submit to show the form errors. On desktop the visible composer takes focus on load. An edit
 * is saved through the JSON edit endpoint and swapped in from its HTML. The desktop thread scrolls in its own box;
 * the mobile thread flows with the page above a sticky composer, so scrolling to the end moves the window.
 *
 * Loaded in:  templates/profile/messages/index.html.twig (when messages present)
 * Used by:    [data-chat], [data-conversations], .messages-scroll, .message-form, .message-input, .message-edit-trigger
 * Depends on: ma-fetch.js (maFetch)
 */

document.addEventListener('DOMContentLoaded', () => {
    const scrollToEnd = () => document.querySelectorAll('.messages-scroll').forEach(el => {
        if (el.offsetParent === null) return;
        if (getComputedStyle(el).overflowY !== 'visible') {
            el.scrollTop = el.scrollHeight;
            return;
        }
        const composer = el.closest('[data-chat]')?.querySelector('.message-composer-sticky');
        window.scrollTo(0, window.scrollY + el.getBoundingClientRect().bottom - window.innerHeight + (composer?.offsetHeight ?? 0));
    });

    const armEditWindows = () => document.querySelectorAll('[data-editable-until]').forEach(bubble => {
        const ms = (parseInt(bubble.dataset.editableUntil, 10) * 1000) - Date.now();
        if (ms <= 0) return bubble.querySelector('.message-edit-trigger')?.remove();
        setTimeout(() => bubble.querySelector('.message-edit-trigger')?.remove(), Math.min(ms, 2 ** 31 - 1));
    });

    const refreshFrom = html => {
        const page = new DOMParser().parseFromString(html, 'text/html');
        ['#desktop-message-container', '#mobile-message-container', '[data-conversations="desktop"]', '[data-conversations="mobile"]'].forEach(selector => {
            const fresh = page.querySelector(selector);
            const current = document.querySelector(selector);
            if (fresh && current) current.innerHTML = fresh.innerHTML;
        });
        armEditWindows();
        scrollToEnd();
    };

    scrollToEnd();
    armEditWindows();

    const isTouch = window.matchMedia('(pointer: coarse)').matches;

    document.querySelectorAll('[data-chat]').forEach(chat => {
        const form = chat.querySelector('.message-form');
        if (!form) return;
        const scroll = chat.querySelector('.messages-scroll');
        const hidden = form.querySelector('input[name="editingMessageId"]');
        const text = form.querySelector('.message-input');
        const submit = form.querySelector('.submit-button');
        const cancel = form.querySelector('.cancel-edit');
        const errorBanner = form.querySelector('.message-edit-error');
        const errors = {
            'profile_messages.edit_no_change': form.dataset.errorNoChange,
            'profile_messages.edit_empty': form.dataset.errorEmpty,
            'profile_messages.edit_too_long': form.dataset.errorTooLong,
            'profile_messages.edit_window_expired': form.dataset.errorExpired,
            'profile_messages.edit_csrf': form.dataset.errorCsrf,
        };
        let busy = false;

        const grow = () => {
            text.style.height = 'auto';
            text.style.height = `${text.scrollHeight + text.offsetHeight - text.clientHeight}px`;
            text.style.overflowY = text.scrollHeight > text.clientHeight + 1 ? 'auto' : 'hidden';
        };
        const refreshSubmit = () => { submit.disabled = busy || text.value.trim() === ''; };
        const showError = message => {
            errorBanner.textContent = message;
            errorBanner.classList.toggle('is-hidden', !message);
        };

        const setEditMode = (bubble, content) => {
            chat.querySelectorAll('.message-bubble.is-warning').forEach(b => b.classList.remove('is-warning', 'is-light'));
            showError('');
            if (bubble) {
                bubble.classList.add('is-warning', 'is-light');
                hidden.value = bubble.dataset.messageId;
                text.value = content;
                submit.textContent = form.dataset.labelSave;
                cancel.classList.remove('is-hidden');
                text.focus();
            } else {
                hidden.value = '';
                text.value = '';
                submit.textContent = form.dataset.labelSend;
                cancel.classList.add('is-hidden');
            }
            grow();
            refreshSubmit();
        };

        const saveEdit = () => {
            const id = hidden.value;
            const url = form.dataset.editUrlTemplate.replace('__ID__', encodeURIComponent(id));
            const body = new FormData();
            body.append('content', text.value);
            body.append('_token', form.dataset.csrfToken);

            busy = true;
            refreshSubmit();
            maFetch(url, true, body).then(payload => {
                document.querySelectorAll(`[data-message-id="${id}"]`).forEach(bubble => {
                    bubble.querySelector('.message-text').innerHTML = payload.html;
                    bubble.querySelector('.message-edit-trigger')?.setAttribute('data-content', payload.content);
                    if (!bubble.querySelector('.edited-marker')) {
                        const marker = document.createElement('span');
                        marker.className = 'ml-1 edited-marker';
                        marker.textContent = form.dataset.editedMarkerText;
                        bubble.querySelector('.message-time').after(marker);
                    }
                });
                busy = false;
                setEditMode(null);
            }).catch(err => {
                busy = false;
                refreshSubmit();
                showError(errors[err?.payload?.error] || form.dataset.errorGeneric);
            });
        };

        const outbox = [];
        let sending = false;

        const returnUnsent = () => {
            const unsent = outbox.splice(0).map(draft => draft.text);
            if (text.value.trim() !== '') unsent.push(text.value);
            text.value = unsent.join('\n');
            grow();
            refreshSubmit();
        };

        const flushOutbox = () => {
            if (sending || outbox.length === 0) return;
            sending = true;
            fetch(form.action, {method: 'POST', body: outbox[0].body}).then(response => {
                if (response.status === 422) {
                    returnUnsent();
                    form.submit();
                    return;
                }
                if (!response.ok || !response.redirected) throw new Error(`send failed: ${response.status}`);
                outbox.shift();
                return response.text().then(refreshFrom);
            }).catch(() => {
                returnUnsent();
                showError(form.dataset.errorSend);
            }).finally(() => {
                sending = false;
                flushOutbox();
            });
        };

        const sendMessage = () => {
            outbox.push({body: new FormData(form), text: text.value});
            text.value = '';
            showError('');
            grow();
            refreshSubmit();
            text.focus();
            flushOutbox();
        };

        scroll?.addEventListener('click', e => {
            const trigger = e.target.closest('.message-edit-trigger');
            if (!trigger) return;
            e.preventDefault();
            setEditMode(trigger.closest('[data-message-id]'), trigger.dataset.content || '');
        });

        cancel.addEventListener('click', e => { e.preventDefault(); setEditMode(null); });

        text.addEventListener('input', () => { grow(); refreshSubmit(); });

        text.addEventListener('keydown', e => {
            if (e.key === 'Escape' && hidden.value) {
                e.preventDefault();
                setEditMode(null);
                return;
            }
            if (e.key !== 'Enter' || e.shiftKey || e.isComposing || e.keyCode === 229 || isTouch) return;
            e.preventDefault();
            if (!submit.disabled) form.requestSubmit(submit);
        });

        form.addEventListener('submit', e => {
            if (busy || text.value.trim() === '') {
                e.preventDefault();
                return;
            }
            e.preventDefault();
            if (hidden.value) {
                saveEdit();
                return;
            }
            sendMessage();
        });

        window.addEventListener('pageshow', () => { busy = false; refreshSubmit(); });

        grow();
        refreshSubmit();

        if (!isTouch && chat.offsetParent !== null) {
            text.focus();
            text.setSelectionRange(text.value.length, text.value.length);
        }
    });
});
