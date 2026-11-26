<?php declare(strict_types=1);

namespace Plugin\Wishlist\Publisher\PluginSettings;

use App\Publisher\PluginSettings\DescriptorInterface;
use Plugin\Wishlist\Form\ConfigType;
use Plugin\Wishlist\ValueObject\Config;
use Symfony\Component\Form\FormInterface;

final class ConfigDescriptor implements DescriptorInterface
{
    public function getKey(): string
    {
        return 'wishlist';
    }

    public function getPluginKey(): string
    {
        return 'wishlist';
    }

    public function isScopable(): bool
    {
        return true;
    }

    public function getTitleKey(): string
    {
        return 'wishlist_config.page_title';
    }

    public function getIntroKey(): ?string
    {
        return null;
    }

    public function getFormType(): string
    {
        return ConfigType::class;
    }

    public function getFormOptions(object $data, ?string $scopeId): array
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
