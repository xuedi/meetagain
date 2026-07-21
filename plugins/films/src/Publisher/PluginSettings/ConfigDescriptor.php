<?php declare(strict_types=1);

namespace Plugin\Films\Publisher\PluginSettings;

use App\Publisher\PluginSettings\PluginSettingsDescriptorInterface;
use Plugin\Films\Form\ConfigType;
use Plugin\Films\ValueObject\Config;
use Symfony\Component\Form\FormInterface;

/**
 * Descriptor for the films taxonomy settings. Distinct key from SettingsDescriptor ('films'):
 * the taxonomy is JSON on the generic per-scope store, the API keys are a custom entity store.
 */
final class ConfigDescriptor implements PluginSettingsDescriptorInterface
{
    public function getKey(): string
    {
        return 'films_taxonomy';
    }

    public function getPluginKey(): string
    {
        return 'films';
    }

    public function isScopable(): bool
    {
        return true;
    }

    public function getTitleKey(): string
    {
        return 'films_taxonomy.page_title';
    }

    public function getFormType(): string
    {
        return ConfigType::class;
    }

    public function getFormOptions(object $data): array
    {
        return [];
    }

    public function createDefault(): object
    {
        return new Config();
    }

    public function applyForm(object $data, FormInterface $form): void
    {
        \assert($data instanceof Config);

        $data->getTaxonomy()->normalize();
    }

    public function getPriority(): int
    {
        return 0;
    }
}
