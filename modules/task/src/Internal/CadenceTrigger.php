<?php declare(strict_types=1);

namespace Module\Task\Internal;

use DateInterval;
use DateTimeImmutable;
use Module\Task\Contract\Cadence;
use Override;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

readonly class CadenceTrigger implements TriggerInterface
{
    private CronExpressionTrigger $cron;

    public function __construct(
        private string $identifier,
        private Cadence $cadence,
        string $timezone,
    ) {
        $this->cron = CronExpressionTrigger::fromSpec($cadence->cron, $identifier, $timezone);
    }

    #[Override]
    public function __toString(): string
    {
        if ($this->cadence->jitterSeconds === 0) {
            return (string) $this->cron;
        }

        return sprintf('%s with 0-%d second jitter', $this->cron, $this->cadence->jitterSeconds);
    }

    #[Override]
    public function getNextRunDate(DateTimeImmutable $run): ?DateTimeImmutable
    {
        $slot = $this->cron->getNextRunDate($run->sub(new DateInterval(sprintf('PT%dS', $this->cadence->jitterSeconds + 60))));
        while ($slot !== null) {
            $time = $slot->add(new DateInterval(sprintf('PT%dS', $this->offset($slot))));
            if ($time > $run) {
                return $time;
            }
            $slot = $this->cron->getNextRunDate($slot);
        }

        return null;
    }

    private function offset(DateTimeImmutable $slot): int
    {
        if ($this->cadence->jitterSeconds === 0) {
            return 0;
        }

        $hash = hash('xxh3', $this->identifier . '@' . $slot->getTimestamp());

        return (int) hexdec(substr($hash, 0, 8)) % ($this->cadence->jitterSeconds + 1);
    }
}
