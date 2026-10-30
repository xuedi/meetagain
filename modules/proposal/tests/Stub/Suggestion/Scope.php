<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Stub\Suggestion;

use Module\Proposal\Contract\Suggestion\ScopeProviderInterface;
use Override;

final class Scope implements ScopeProviderInterface
{
    public ?string $current = null;

    #[Override]
    public function capture(): ?string
    {
        return $this->current;
    }

    #[Override]
    public function runIn(string $scope, callable $work): mixed
    {
        $previous = $this->current;
        $this->current = $scope;
        try {
            return $work();
        } finally {
            $this->current = $previous;
        }
    }
}
