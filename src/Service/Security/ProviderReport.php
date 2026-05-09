<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Enum\SecurityRecommendation;

final readonly class ProviderReport
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public string $providerKey,
        public int $threatLevel,
        public string $summary,
        public SecurityRecommendation $recommendation,
        public array $details = [],
    ) {}

    /**
     * @return array{providerKey: string, threatLevel: int, summary: string, recommendation: string, details: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'providerKey' => $this->providerKey,
            'threatLevel' => $this->threatLevel,
            'summary' => $this->summary,
            'recommendation' => $this->recommendation->value,
            'details' => $this->details,
        ];
    }
}
