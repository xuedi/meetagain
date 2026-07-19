<?php declare(strict_types=1);

namespace Plugin\Voting\ValueObject;

use App\Publisher\PluginSettings\PluginSettingsData;
use Plugin\Voting\Enum\ChoiceMode;

/**
 * Effective voting behaviour: how many days a new poll runs by default and whether voters
 * may approve several candidates or exactly one. The neutral default is a 7-day poll with
 * multiple (approval) voting.
 */
final class Config implements PluginSettingsData
{
    private int $defaultDurationDays = 7;
    private ChoiceMode $choiceMode = ChoiceMode::Multiple;

    public function getDefaultDurationDays(): int
    {
        return $this->defaultDurationDays;
    }

    public function setDefaultDurationDays(int $defaultDurationDays): static
    {
        $this->defaultDurationDays = max(1, $defaultDurationDays);

        return $this;
    }

    public function getChoiceMode(): ChoiceMode
    {
        return $this->choiceMode;
    }

    public function setChoiceMode(ChoiceMode $choiceMode): static
    {
        $this->choiceMode = $choiceMode;

        return $this;
    }

    public function isSingleChoice(): bool
    {
        return $this->choiceMode === ChoiceMode::Single;
    }

    public function toArray(): array
    {
        return [
            'defaultDurationDays' => $this->defaultDurationDays,
            'choiceMode' => $this->choiceMode->value,
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $config->defaultDurationDays = max(1, (int) ($raw['defaultDurationDays'] ?? 7));
        $config->choiceMode = ChoiceMode::tryFrom((string) ($raw['choiceMode'] ?? '')) ?? ChoiceMode::Multiple;

        return $config;
    }
}
