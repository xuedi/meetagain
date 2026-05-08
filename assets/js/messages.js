/**
 * Messages - auto-scroll, in-place edit, and edit-window countdown.
 *
 * Loaded in:  templates/profile/messages/index.html.twig (when messages present)
 * Depends on: ma-fetch.js (maFetch)
 */

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.messages-scroll').forEach(el => { el.scrollTop = el.scrollHeight; });

    // Hide each pencil icon the moment its message crosses the editable boundary.
    document.querySelectorAll('[data-editable-until]').forEach(bubble => {
        const ms = (parseInt(bubble.dataset.editableUntil, 10) * 1000) - Date.now();
        if (ms <= 0) return bubble.querySelector('.message-edit-trigger')?.remove();
        setTimeout(() => bubble.querySelector('.message-edit-trigger')?.remove(), Math.min(ms, 2 ** 31 - 1));
    });

    const form = document.getElementById('message-form');
    if (!form) return;
    const hidden = form.querySelector('input[name="editingMessageId"]');
    const text = form.querySelector('input[type="text"]');
    const submit = form.querySelector('.submit-button');
    const cancel = form.querySelector('.cancel-edit');

    const setEditMode = (bubble, content) => {
        document.querySelectorAll('.box.is-warning').forEach(b => b.classList.remove('is-warning', 'is-light'));
        document.getElementById('message-edit-error')?.remove();
        if (bubble) {
            (bubble.querySelector('.box') || bubble).classList.add('is-warning', 'is-light');
            hidden.value = bubble.dataset.messageId;
            text.value = content;
            text.focus();
            submit.textContent = form.dataset.labelSave;
            cancel.classList.remove('is-hidden');
        } else {
            hidden.value = '';
            text.value = '';
            submit.textContent = form.dataset.labelSend;
            cancel.classList.add('is-hidden');
        }
    };

    document.querySelectorAll('.messages-scroll').forEach(scroll => {
        scroll.addEventListener('click', e => {
            const trigger = e.target.closest('.message-edit-trigger');
            if (!trigger) return;
            e.preventDefault();
            setEditMode(trigger.closest('[data-message-id]'), trigger.dataset.content || '');
        });
    });

    cancel.addEventListener('click', e => { e.preventDefault(); setEditMode(null); });

    form.addEventListener('submit', e => {
        if (!hidden.value) return;
        e.preventDefault();
        const id = hidden.value;
        const url = form.dataset.editUrlTemplate.replace('__ID__', encodeURIComponent(id));
        const body = new FormData();
        body.append('content', text.value);
        body.append('_token', form.dataset.csrfToken);

        maFetch(url, true, body).then(payload => {
            document.querySelectorAll(`[data-message-id="${id}"]`).forEach(bubble => {
                bubble.querySelector('.breakText').textContent = payload.content;
                if (!bubble.querySelector('.edited-marker')) {
                    const marker = document.createElement('small');
                    marker.className = 'has-text-grey ml-1 edited-marker';
                    marker.textContent = form.dataset.editedMarkerText;
                    bubble.querySelector('small').after(marker);
                }
            });
            setEditMode(null);
        }).catch(err => {
            const msg = err?.payload?.error === 'profile_messages.edit_no_change'
                ? form.dataset.errorNoChange : form.dataset.errorGeneric;
            let banner = document.getElementById('message-edit-error');
            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'message-edit-error';
                banner.className = 'notification is-danger is-light';
                form.parentNode.insertBefore(banner, form);
            }
            banner.textContent = msg;
        });
    });
});
