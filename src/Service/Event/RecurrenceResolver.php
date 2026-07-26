<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Enum\EventInterval;
use App\Exception\Event\InvalidRecurrencePatternException;
use App\ValueObject\RecurrencePattern;
use DateTimeInterface;

final readonly class RecurrenceResolver
{
    // An unparseable spec resolves to null rather than throwing: one bad row must not fatal the cron.
    public function resolve(?EventInterval $rule, ?string $ruleSpec, DateTimeInterface $anchor): ?RecurrencePattern
    {
        if (EventInterval::Custom === $rule) {
            if (null === $ruleSpec || '' === $ruleSpec) {
                return null;
            }

            try {
                return RecurrencePattern::fromRfcString($ruleSpec);
            } catch (InvalidRecurrencePatternException) {
                return null;
            }
        }

        return $rule instanceof EventInterval ? RecurrencePattern::fromInterval($rule, $anchor) : null;
    }
}
