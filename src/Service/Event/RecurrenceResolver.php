<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Enum\EventInterval;
use App\Exception\Event\InvalidRecurrencePatternException;
use App\ValueObject\RecurrencePattern;
use App\ValueObject\ResolvedRecurrence;
use DateTimeInterface;

final readonly class RecurrenceResolver
{
    private const string DAILY_RULE = 'FREQ=DAILY';
    private const string DAILY_LOOKAHEAD = '+2 weeks';

    // An unparseable spec resolves to null rather than throwing: one bad row must not fatal the cron.
    public function pattern(?EventInterval $rule, ?string $ruleSpec, DateTimeInterface $anchor): ?RecurrencePattern
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

    public function resolve(?EventInterval $rule, ?string $ruleSpec, DateTimeInterface $anchor): ?ResolvedRecurrence
    {
        if (EventInterval::Daily === $rule) {
            return new ResolvedRecurrence(self::DAILY_RULE, self::DAILY_LOOKAHEAD);
        }

        $pattern = $this->pattern($rule, $ruleSpec, $anchor);

        return $pattern instanceof RecurrencePattern
            ? new ResolvedRecurrence($pattern->toRfcString(), $pattern->period->lookaheadModifier())
            : null;
    }
}
