<?php declare(strict_types=1);

namespace Module\Email\Tests\Stub;

use Closure;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final class GuardRule implements GuardRuleInterface
{
    public int $calls = 0;

    /** @param Closure(array<string, mixed>): GuardResult $decide */
    private function __construct(
        private readonly string $name,
        private readonly Closure $decide,
    ) {}

    public static function returning(GuardResult $result): self
    {
        return new self($result->ruleName, static fn(array $context): GuardResult => $result);
    }

    /** @param Closure(array<string, mixed>): GuardResult $decide */
    public static function deciding(string $name, Closure $decide): self
    {
        return new self($name, $decide);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCost(): GuardCost
    {
        return GuardCost::Free;
    }

    public function evaluate(array $context): GuardResult
    {
        ++$this->calls;

        return ($this->decide)($context);
    }
}
