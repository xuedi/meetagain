<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormInterface;

/**
 * Contributes one boxed section to the core admin page at /admin/plugin/settings.
 *
 * Implementations are auto-discovered via #[AutoconfigureTag]. Each provider owns one
 * Symfony form, its data object, and its persistence. The page renders the providers
 * in priority order and dispatches a form submission back to the matching provider by key.
 *
 * loadData() and getFormOptions() are called in the same request to build the form, so
 * implementations that derive options from the loaded data (typical pattern) must memoise
 * loadData() and reuse the cached result in getFormOptions().
 */
#[AutoconfigureTag]
interface PluginSettingsProviderInterface
{
    /** Stable, lowercase-snake_case key. Used to route the form submit to the right provider. */
    public function getKey(): string;

    /** Translation key for the section title. */
    public function getTitleKey(): string;

    /** FQCN of the Symfony FormType this provider's form is built from. */
    public function getFormType(): string;

    /** Hydrate and return the data object the form is bound to. */
    public function loadData(): object;

    /**
     * Optional extra options passed to createForm(). Return [] for none.
     *
     * @return array<string, mixed>
     */
    public function getFormOptions(): array;

    /** Persist the bound data. Called after the form is valid. */
    public function save(object $data, FormInterface $form): void;

    /** Higher runs first; only affects display order on the page. */
    public function getPriority(): int;
}
