<?php declare(strict_types=1);

namespace Module\Email\Contract;

final readonly class GuardResult
{
    public function __construct(
        public GuardOutcome $outcome,
        public string $ruleName,
        public string $explanation = '',
        public ?string $contextKey = null,
    ) {}

    public static function pass(string $ruleName): self
    {
        return new self(GuardOutcome::Pass, $ruleName);
    }

    public static function skip(string $ruleName, string $explanation): self
    {
        return new self(GuardOutcome::Skip, $ruleName, $explanation);
    }

    public static function error(string $ruleName, string $explanation, ?string $contextKey = null): self
    {
        return new self(GuardOutcome::Error, $ruleName, $explanation, $contextKey);
    }
}
