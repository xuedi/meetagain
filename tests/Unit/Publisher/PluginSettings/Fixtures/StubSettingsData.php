<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\PluginSettings\Fixtures;

use App\Publisher\PluginSettings\PluginSettingsData;

/**
 * A minimal DTO proving the generic store works independently of any real plugin.
 */
final class StubSettingsData implements PluginSettingsData
{
    public function __construct(
        public string $label = 'default',
        public int $count = 0,
    ) {}

    public function toArray(): array
    {
        return ['label' => $this->label, 'count' => $this->count];
    }

    public static function fromArray(array $raw): static
    {
        return new self((string) ($raw['label'] ?? 'default'), (int) ($raw['count'] ?? 0));
    }
}
