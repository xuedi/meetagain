<?php declare(strict_types=1);

namespace Plugin\Dishes\Publisher\PluginSettings;

use App\Publisher\PluginSettings\PluginSettingsDescriptorInterface;
use Plugin\Dishes\Config;
use Plugin\Dishes\Form\ConfigType;
use Symfony\Component\Form\FormInterface;

final class ConfigDescriptor implements PluginSettingsDescriptorInterface
{
    public function getKey(): string
    {
        return 'dishes';
    }

    public function getTitleKey(): string
    {
        return 'dishes_config.page_title';
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

    public function applyForm(object $data, FormInterface $form): void {}

    public function getPriority(): int
    {
        return 0;
    }
}
