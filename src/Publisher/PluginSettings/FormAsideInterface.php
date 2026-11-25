<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

/**
 * Lets a descriptor render a block in the info column beside its settings form, on the global and the scope page.
 */
interface FormAsideInterface
{
    /**
     * Template rendered with the section's form view as `form` and the form element's id as `formId`; a button it
     * renders with `form="{{ formId }}"` submits the whole form.
     */
    public function getFormAsideTemplate(): string;
}
