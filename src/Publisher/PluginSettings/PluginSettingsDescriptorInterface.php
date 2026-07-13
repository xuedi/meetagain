<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormInterface;

/**
 * Scope-agnostic description of one plugin's settings surface: its form, its data
 * object, its neutral defaults. A plugin defines this once; core renders it at the
 * global scope and any override scope supplied by a scope provider renders the same
 * descriptor. Persistence is delegated to a store, so a descriptor never touches the
 * database.
 *
 * Implementations are auto-discovered via #[AutoconfigureTag].
 */
#[AutoconfigureTag]
interface PluginSettingsDescriptorInterface
{
    /** Stable, lowercase-snake_case key. Routes a form submit and selects a store. */
    public function getKey(): string;

    /** Translation key for the section title. */
    public function getTitleKey(): string;

    /** FQCN of the Symfony FormType the form is built from. */
    public function getFormType(): string;

    /**
     * Extra options passed to createForm(), derived from the bound data object.
     *
     * @return array<string, mixed>
     */
    public function getFormOptions(object $data): array;

    /** A fresh data object carrying the plugin's neutral defaults. */
    public function createDefault(): object;

    /**
     * Map non-mapped form fields onto the data object before persistence. Keeps stores
     * pure: any clear/encrypt/transform logic lives here, not in the store.
     */
    public function applyForm(object $data, FormInterface $form): void;

    /** Higher runs first; only affects display order. */
    public function getPriority(): int;
}
