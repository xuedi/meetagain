<?php declare(strict_types=1);

namespace Plugin\Photos\Publisher\PluginSettings;

use App\Publisher\PluginSettings\DescriptorInterface;
use Plugin\Photos\Form\ConfigType;
use Plugin\Photos\ValueObject\Config;
use Symfony\Component\Form\FormInterface;

final class ConfigDescriptor implements DescriptorInterface
{
    public function getKey(): string
    {
        return 'photos';
    }

    public function getPluginKey(): string
    {
        return 'photos';
    }

    public function isScopable(): bool
    {
        return true;
    }

    public function getTitleKey(): string
    {
        return 'photos_config.page_title';
    }

    public function getIntroKey(): ?string
    {
        return null;
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
    }

    public function getPriority(): int
    {
        return 0;
    }
}
