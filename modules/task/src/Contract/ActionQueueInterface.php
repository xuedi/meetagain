<?php declare(strict_types=1);

namespace Module\Task\Contract;

interface ActionQueueInterface
{
    /**
     * @param array<string, string|int|bool|null> $values keyed by parameter key
     */
    public function queue(string $identifier, array $values, ?string $requestedBy): void;

    /**
     * @param array<string, string|int|bool|null> $values pairs the request's values must all contain
     */
    public function latest(string $identifier, array $values = []): ?RequestedAction;

    public function forgetRequester(string $requestedBy, string $replacement): int;
}
