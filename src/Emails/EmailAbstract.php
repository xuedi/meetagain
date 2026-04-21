<?php declare(strict_types=1);

namespace App\Emails;

use DateTimeImmutable;
use InvalidArgumentException;

abstract readonly class EmailAbstract implements EmailInterface
{
    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return null;
    }

    protected function ensureHasKey(array $context, string $key): void
    {
        if (!array_key_exists($key, $context) || $context[$key] === null) {
            throw new InvalidArgumentException(sprintf(
                "Missing '%s' in context for email '%s'",
                $key,
                $this->getIdentifier(),
            ));
        }
    }

    protected function ensureInstanceOf(array $context, string $key, string $fqcn): void
    {
        $this->ensureHasKey($context, $key);
        if (!$context[$key] instanceof $fqcn) {
            throw new InvalidArgumentException(sprintf(
                "Context key '%s' for email '%s' must be instance of %s, got %s",
                $key,
                $this->getIdentifier(),
                $fqcn,
                get_debug_type($context[$key]),
            ));
        }
    }
}
