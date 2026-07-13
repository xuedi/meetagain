<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\PluginSettings\Fixtures;

use App\Publisher\PluginSettings\PluginSettingsDescriptorInterface;
use Symfony\Component\Form\FormInterface;

final class StubDescriptor implements PluginSettingsDescriptorInterface
{
    public function __construct(
        private readonly string $key = 'stub',
        private readonly int $priority = 0,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitleKey(): string
    {
        return 'stub.title';
    }

    public function getFormType(): string
    {
        return 'StubFormType';
    }

    public function getFormOptions(object $data): array
    {
        return [];
    }

    public function createDefault(): object
    {
        return new StubSettingsData();
    }

    public function applyForm(object $data, FormInterface $form): void {}

    public function getPriority(): int
    {
        return $this->priority;
    }
}
