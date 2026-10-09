<?php declare(strict_types=1);

namespace Module\Suggestion\Contract;

final readonly class PortableSuggestion
{
    /**
     * @param array<string, scalar|null> $payload
     */
    public function __construct(
        public string $targetType,
        public int $proposerId,
        public array $payload,
        public ?string $scope = null,
    ) {}
}
