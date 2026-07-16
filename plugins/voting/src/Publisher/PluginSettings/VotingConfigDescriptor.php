<?php declare(strict_types=1);

namespace Plugin\Voting\Publisher\PluginSettings;

use App\Publisher\PluginSettings\PluginSettingsDescriptorInterface;
use Plugin\Voting\Config\VotingConfig;
use Plugin\Voting\Form\VotingConfigType;
use Symfony\Component\Form\FormInterface;

final class VotingConfigDescriptor implements PluginSettingsDescriptorInterface
{
    public function getKey(): string
    {
        return 'voting';
    }

    public function getTitleKey(): string
    {
        return 'voting_config.page_title';
    }

    public function getFormType(): string
    {
        return VotingConfigType::class;
    }

    public function getFormOptions(object $data): array
    {
        return [];
    }

    public function createDefault(): object
    {
        return new VotingConfig();
    }

    public function applyForm(object $data, FormInterface $form): void {}

    public function getPriority(): int
    {
        return 0;
    }
}
