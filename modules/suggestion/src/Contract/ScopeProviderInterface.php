<?php declare(strict_types=1);

namespace Module\Suggestion\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Remembers where a suggestion was made and replays that place when it is reviewed. The key is opaque to the
 * module; with no implementation, or a null key, suggestions are reviewed and created in the reviewer's own context.
 */
#[AutoconfigureTag]
interface ScopeProviderInterface
{
    public function capture(): ?string;

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function runIn(string $scope, callable $work): mixed;
}
