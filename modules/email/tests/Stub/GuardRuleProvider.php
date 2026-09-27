<?php declare(strict_types=1);

namespace Module\Email\Tests\Stub;

use Module\Email\Contract\GuardRuleInterface;
use Module\Email\Contract\GuardRuleProviderInterface;
use Override;

final class GuardRuleProvider implements GuardRuleProviderInterface
{
    /** @var array<string, list<GuardRuleInterface>> */
    public array $rules = [];

    #[Override]
    public function getRulesFor(string $emailIdentifier): array
    {
        return $this->rules[$emailIdentifier] ?? [];
    }
}
