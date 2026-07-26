/**
 * Recurrence Builder — custom RFC-5545 rules on the admin event form
 *
 * Drives three things:
 *   1. Mode switch — "Custom" in the Recurring dropdown hides Start/Stop and reveals the
 *      rule sentence; any preset reverses it.
 *   2. Modal — the selects feed the preview endpoint, which returns candidate start dates
 *      each carrying the rule string that picking it would produce.
 *   3. Apply — writes the chosen spec, date and times into the form's hidden inputs.
 *
 * Computes nothing. Dates, rule strings and which controls apply all come from the preview
 * endpoint; this file reads inputs, sends them, and renders the answer.
 *
 * Loaded in:  templates/admin/event/edit.html.twig, new.html.twig
 * Used by:    templates/admin/event/_recurrence_modal.html.twig, _recurrence_summary.html.twig
 * Depends on: nothing (ma-fetch.js is frontend-only; plain fetch is enough for one GET)
 */

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('recurrence-modal');
    if (!modal) return;

    const config = modal.dataset;
    const ruleSelect = document.getElementById(config.ruleSelect);
    const specInput = document.getElementById(config.specInput);
    const startInput = document.getElementById(config.startInput);
    const stopInput = document.getElementById(config.stopInput);
    const summaryText = document.getElementById('recurrence-summary-text');
    const summaryWrapper = document.getElementById('recurrence-summary-wrapper');
    const scheduleWrappers = document.querySelectorAll('[data-recurrence-schedule]');

    const ordinal = document.getElementById('recurrence-ordinal');
    const weekday = document.getElementById('recurrence-weekday');
    const day = document.getElementById('recurrence-day');
    const period = document.getElementById('recurrence-period');
    const startSelect = document.getElementById('recurrence-start');
    const startTime = document.getElementById('recurrence-start-time');
    const endTime = document.getElementById('recurrence-end-time');
    const preview = document.getElementById('recurrence-preview');
    const hint = document.getElementById('recurrence-hint');
    const multiHint = document.getElementById('recurrence-multi-hint');
    const ordinalWrap = document.getElementById('recurrence-ordinal-wrap');
    const weekdayWrap = document.getElementById('recurrence-weekday-wrap');
    const weekdayControls = document.getElementById('recurrence-weekday-controls');
    const dayControls = document.getElementById('recurrence-day-controls');

    const WEEKDAY_LIST_SIZE = 7;
    // Flatpickr renders these as text with "Y-m-d H:i"; a datetime-local build uses "T".
    const DATETIME_PATTERN = /^(\d{4}-\d{2}-\d{2})([T ])(\d{2}:\d{2})/;
    let candidates = [];
    let requestToken = 0;

    function splitDateTime(input) {
        const match = DATETIME_PATTERN.exec(input.value || '');
        if (match) {
            return { date: match[1], separator: match[2], time: match[3] };
        }

        return { date: '', separator: input.type === 'datetime-local' ? 'T' : ' ', time: '' };
    }

    // Writing .value alone leaves the flatpickr widget showing the old date.
    function writeDateTime(input, value) {
        input.value = value;
        for (let node = input; node; node = node.parentElement) {
            if (node._flatpickr) {
                node._flatpickr.setDate(value, false);
                return;
            }
        }
    }

    const selectedMode = () => modal.querySelector('input[name="recurrence-mode"]:checked').value;
    const isCustom = () => ruleSelect.value === config.customValue;

    function selectedValues(select) {
        return Array.from(select.selectedOptions).map(function (option) {
            return option.value;
        });
    }

    function applySelection(select, values) {
        Array.from(select.options).forEach(function (option) {
            option.selected = values.indexOf(option.value) !== -1;
        });
    }

    // The server decides which controls the current selection leaves applicable, and corrects the
    // selection itself when the two disagree. Nothing here re-derives those rules.
    function applyState(payload) {
        const controls = payload.controls;
        const selection = payload.selection;

        modal.querySelectorAll('input[name="recurrence-mode"]').forEach(function (radio) {
            radio.checked = radio.value === selection.mode;
        });

        weekday.multiple = controls.weekdayMultiple;
        weekday.size = controls.weekdayMultiple ? WEEKDAY_LIST_SIZE : 1;
        weekdayWrap.classList.toggle('is-multiple', controls.weekdayMultiple);

        applySelection(ordinal, selection.ordinal.map(String));
        applySelection(weekday, selection.weekday);
        applySelection(day, selection.day.map(String));
        period.value = selection.period;

        Array.from(period.options).forEach(function (option) {
            option.hidden = controls.periods.indexOf(option.value) === -1;
        });

        weekdayControls.classList.toggle('is-hidden', !controls.weekday);
        ordinalWrap.classList.toggle('is-hidden', !controls.ordinal);
        dayControls.classList.toggle('is-hidden', !controls.day);
        multiHint.classList.toggle('is-hidden', !controls.multiHint);
        hint.classList.toggle('is-hidden', !controls.shortMonthHint);
    }

    function refreshCandidates() {
        const params = new URLSearchParams({ mode: selectedMode(), period: period.value });
        selectedValues(day).forEach(function (value) {
            params.append('day[]', value);
        });
        selectedValues(weekday).forEach(function (value) {
            params.append('weekday[]', value);
        });
        selectedValues(ordinal).forEach(function (value) {
            params.append('ordinal[]', value);
        });

        // Guards against a slow earlier response landing after a newer one.
        const token = ++requestToken;

        return fetch(config.previewUrl + '?' + params.toString())
            .then(function (response) {
                if (!response.ok) throw new Error('Preview request failed');
                return response.json();
            })
            .then(function (payload) {
                if (token !== requestToken) return;

                applyState(payload);
                candidates = payload.candidates || [];
                startSelect.innerHTML = '';
                candidates.forEach(function (candidate, index) {
                    const option = document.createElement('option');
                    option.value = String(index);
                    option.textContent = candidate.label;
                    startSelect.appendChild(option);
                });
                renderPreview();
            })
            .catch(function (err) {
                console.error('Recurrence preview failed:', err);
            });
    }

    function currentCandidate() {
        return candidates[Number(startSelect.value)] || null;
    }

    function renderPreview() {
        const candidate = currentCandidate();
        preview.textContent = candidate ? candidate.summary : '';
    }

    function openModal() {
        startTime.value = splitDateTime(startInput).time;
        endTime.value = splitDateTime(stopInput).time;

        refreshCandidates().then(function () {
            modal.classList.add('is-active');
        });
    }

    function closeModal() {
        modal.classList.remove('is-active');
    }

    // Re-picking the same option fires no change event, so an unconfigured Custom must reset
    // or the builder cannot be reopened. A configured rule stays put - dismiss means no change.
    function dismissModal() {
        closeModal();
        if (specInput.value) {
            return;
        }

        ruleSelect.value = '';
        ruleSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function apply() {
        const candidate = currentCandidate();
        if (!candidate) return;

        const start = splitDateTime(startInput);
        const stop = splitDateTime(stopInput);

        specInput.value = candidate.spec;
        writeDateTime(startInput, candidate.date + start.separator + (startTime.value || start.time || '00:00'));
        if (endTime.value || stop.time) {
            writeDateTime(stopInput, candidate.date + stop.separator + (endTime.value || stop.time));
        }
        summaryText.textContent = candidate.summary;
        closeModal();
    }

    function syncMode() {
        const custom = isCustom();
        scheduleWrappers.forEach(function (wrapper) {
            wrapper.classList.toggle('is-hidden', custom);
        });
        if (summaryWrapper) {
            summaryWrapper.classList.toggle('is-hidden', !custom);
        }
        if (custom && !specInput.value) {
            openModal();
        }
    }

    [ordinal, weekday, day, period].forEach(function (control) {
        control.addEventListener('change', refreshCandidates);
    });
    modal.querySelectorAll('input[name="recurrence-mode"]').forEach(function (radio) {
        radio.addEventListener('change', refreshCandidates);
    });
    startSelect.addEventListener('change', renderPreview);
    modal.querySelectorAll('[data-recurrence-close]').forEach(function (trigger) {
        trigger.addEventListener('click', dismissModal);
    });
    document.getElementById('recurrence-apply').addEventListener('click', apply);

    const editLink = document.getElementById('recurrence-summary-edit');
    if (editLink) {
        editLink.addEventListener('click', function (event) {
            event.preventDefault();
            openModal();
        });
    }

    ruleSelect.addEventListener('change', syncMode);
    syncMode();
});
