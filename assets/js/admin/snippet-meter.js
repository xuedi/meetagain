/**
 * Snippet Meter -- Live Length of the Search Snippet a Teaser Becomes
 *
 * The event teaser is the page's meta and link-preview description, followed by a
 * " - meeting at <date>" suffix the field never shows. The meter counts both and colours the
 * total against the range search engines show uncut: green inside it, yellow when short,
 * red when long enough to be truncated.
 *
 * Loaded in:  templates/admin/event/edit.html.twig
 * Used by:    textarea[data-snippet-meter] with data-snippet-meter-empty, data-snippet-reserved,
 *             data-snippet-min, data-snippet-max
 */

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('textarea[data-snippet-meter]').forEach(function (field) {
        const reserved = parseInt(field.dataset.snippetReserved, 10) || 0;
        const min = parseInt(field.dataset.snippetMin, 10);
        const max = parseInt(field.dataset.snippetMax, 10);
        const meter = document.createElement('p');
        meter.className = 'help';
        (field.closest('.control') || field).after(meter);

        function render() {
            const length = [...field.value.trim()].length;
            meter.classList.remove('has-text-grey', 'has-text-success', 'has-text-warning-dark', 'has-text-danger');
            if (length === 0) {
                meter.textContent = field.dataset.snippetMeterEmpty;
                meter.classList.add('has-text-grey');
                return;
            }

            const total = length + reserved;
            meter.textContent = field.dataset.snippetMeter.replace('%count%', total).replace('%max%', max);
            if (total > max) {
                meter.classList.add('has-text-danger');
            } else if (total < min) {
                meter.classList.add('has-text-warning-dark');
            } else {
                meter.classList.add('has-text-success');
            }
        }

        field.addEventListener('input', render);
        render();
    });
});
