<?php declare(strict_types=1);

namespace Module\Suggestion\Contract;

interface SuggestionInterface
{
    public function providerFor(string $targetType): ?TargetProviderInterface;

    /** Pre-translated error when the draft was refused; null once the suggestion is stored. */
    public function propose(string $targetType, int $proposerId, object $draft): ?string;

    /** @return list<View> */
    public function pendingFor(int $proposerId, string $targetType): array;

    public function find(int $id): ?View;

    /** Stores a pending suggestion as given, without permission checks, validation or activity. */
    public function restore(PortableSuggestion $suggestion): int;
}
