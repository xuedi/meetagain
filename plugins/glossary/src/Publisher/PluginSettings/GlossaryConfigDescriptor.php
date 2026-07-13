<?php declare(strict_types=1);

namespace Plugin\Glossary\Publisher\PluginSettings;

use App\Publisher\PluginSettings\PluginSettingsDescriptorInterface;
use Plugin\Glossary\Config\GlossaryConfig;
use Plugin\Glossary\Form\GlossaryConfigType;
use Symfony\Component\Form\FormInterface;

final class GlossaryConfigDescriptor implements PluginSettingsDescriptorInterface
{
    public function getKey(): string
    {
        return 'glossary';
    }

    public function getTitleKey(): string
    {
        return 'glossary_config.page_title';
    }

    public function getFormType(): string
    {
        return GlossaryConfigType::class;
    }

    public function getFormOptions(object $data): array
    {
        return [];
    }

    public function createDefault(): object
    {
        return new GlossaryConfig();
    }

    public function applyForm(object $data, FormInterface $form): void
    {
        \assert($data instanceof GlossaryConfig);

        $data->normalizeCategories();
    }

    public function getPriority(): int
    {
        return 0;
    }
}
