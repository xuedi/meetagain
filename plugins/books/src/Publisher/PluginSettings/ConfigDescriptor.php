<?php declare(strict_types=1);

namespace Plugin\Books\Publisher\PluginSettings;

use App\Publisher\PluginSettings\DescriptorInterface;
use Plugin\Books\Form\ConfigType;
use Plugin\Books\ValueObject\Config;
use Symfony\Component\Form\FormInterface;

final class ConfigDescriptor implements DescriptorInterface
{
    public function getKey(): string
    {
        return 'books';
    }

    public function getPluginKey(): string
    {
        return 'books';
    }

    public function isScopable(): bool
    {
        return true;
    }

    public function getTitleKey(): string
    {
        return 'books_config.page_title';
    }

    public function getIntroKey(): ?string
    {
        return 'books_config.intro_circulation';
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

        if (!$data->isCirculation()) {
            $data->setTrustSystem(false);
        }
    }

    public function getPriority(): int
    {
        return 0;
    }
}
