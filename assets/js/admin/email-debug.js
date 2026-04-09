/**
 * Admin Email Debug — Dynamic Context Field Generator
 *
 * Reads the selected email type's mock context data (stored as JSON in the
 * option's data-context attribute) and dynamically builds a Bulma horizontal
 * form field for each context variable. Re-runs whenever the email type
 * select changes.
 *
 * Loaded in:  templates/admin/email/debugging/index.html.twig only
 * Used by:    #emailType select, #contextFields container
 * Depends on: none
 */

document.addEventListener('DOMContentLoaded', function () {
    const emailTypeSelect = document.getElementById('emailType');
    const contextFieldsContainer = document.getElementById('contextFields');

    if (!emailTypeSelect || !contextFieldsContainer) return;

    function updateContextFields() {
        const selectedOption = emailTypeSelect.options[emailTypeSelect.selectedIndex];
        const context = JSON.parse(selectedOption.dataset.context || '{}');

        contextFieldsContainer.innerHTML = '';

        for (const [key, value] of Object.entries(context)) {
            const field = document.createElement('div');
            field.className = 'field is-horizontal';

            const fieldLabel = document.createElement('div');
            fieldLabel.className = 'field-label is-normal';
            fieldLabel.innerHTML = '<label class="label">' + key + '</label>';

            const fieldBody = document.createElement('div');
            fieldBody.className = 'field-body';

            const fieldDiv = document.createElement('div');
            fieldDiv.className = 'field';

            const control = document.createElement('div');
            control.className = 'control';

            const input = document.createElement('input');
            input.className = 'input';
            input.type = 'text';
            input.name = 'context[' + key + ']';
            input.value = value;

            control.appendChild(input);
            fieldDiv.appendChild(control);
            fieldBody.appendChild(fieldDiv);
            field.appendChild(fieldLabel);
            field.appendChild(fieldBody);
            contextFieldsContainer.appendChild(field);
        }
    }

    emailTypeSelect.addEventListener('change', updateContextFields);
    updateContextFields();
});
