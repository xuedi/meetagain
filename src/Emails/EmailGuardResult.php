<?php declare(strict_types=1);

namespace App\Emails;

final readonly class EmailGuardResult
{
    public function __construct(
        public EmailGuardOutcome $outcome,
        public string $ruleName,
        public string $explanation = '',
        public ?string $contextKey = null,
    ) {}

    public static function pass(string $ruleName): self
    {
        return new self(EmailGuardOutcome::Pass, $ruleName);
    }

    public static function skip(string $ruleName, string $explanation): self
    {
        return new self(EmailGuardOutcome::Skip, $ruleName, $explanation);
    }

    public static function error(string $ruleName, string $explanation, ?string $contextKey = null): self
    {
        return new self(EmailGuardOutcome::Error, $ruleName, $explanation, $contextKey);
    }
}
