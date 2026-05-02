<?php declare(strict_types=1);

namespace App\Emails;

use App\Service\Email\BlocklistCheckerInterface;
use DateTimeImmutable;
use InvalidArgumentException;

abstract readonly class EmailAbstract implements EmailInterface
{
    public function __construct(
        protected BlocklistCheckerInterface $blocklist,
    ) {}

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return null;
    }

    public function getGuardRules(): array
    {
        return [];
    }

    public function guardCheck(array $context): bool
    {
        $rules = $this->getGuardRules();
        if ($rules === []) {
            return true;
        }

        foreach ($rules as $rule) {
            $result = $rule->evaluate($context);
            if ($result->outcome === EmailGuardOutcome::Error) {
                throw new InvalidArgumentException(sprintf(
                    "Guard rule '%s' for email '%s' returned Error: %s",
                    $result->ruleName,
                    $this->getIdentifier(),
                    $result->explanation,
                ));
            }
            if ($result->outcome === EmailGuardOutcome::Skip) {
                return false;
            }
        }

        return true;
    }

}
