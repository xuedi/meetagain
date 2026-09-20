<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\SecurityMeasureLog;
use App\Enum\SecurityMeasure;
use App\Enum\SecurityMeasureOutcome;
use App\Repository\SecurityMeasureLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

readonly class MeasureLogger
{
    public function __construct(
        private SecurityMeasureLogRepository $repo,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {}

    public function recordPass(SecurityMeasure $measure, ?string $context = null): void
    {
        $now = $this->clock->now();
        $day = $now->setTime(0, 0);

        if ($this->repo->incrementPassCounter($measure, $day, $context, $now) > 0) {
            return;
        }

        $counter = new SecurityMeasureLog();
        $counter->setDay($day)->setCreatedAt($now)->setMeasure($measure)->setOutcome(SecurityMeasureOutcome::Passed)->setCount(1)->setContext($context);

        $this->em->persist($counter);
        $this->em->flush();
    }

    /**
     * @param array<string, scalar|null>|null $detail
     */
    public function recordBlock(SecurityMeasure $measure, ?string $context = null, ?Request $request = null, ?array $detail = null): void
    {
        $now = $this->clock->now();

        $log = new SecurityMeasureLog();
        $log
            ->setDay($now->setTime(0, 0))
            ->setCreatedAt($now)
            ->setMeasure($measure)
            ->setOutcome(SecurityMeasureOutcome::Blocked)
            ->setCount(1)
            ->setContext($context)
            ->setIp($request?->getClientIp())
            ->setUserAgent($request?->headers->get('User-Agent'))
            ->setDetail($detail);

        $this->em->persist($log);
        $this->em->flush();
    }

    public function purgeOlderThan(int $retentionDays): int
    {
        return $this->repo->deleteOlderThan(
            $this->clock
                ->now()
                ->setTime(0, 0)
                ->modify(sprintf('-%d days', max(1, $retentionDays))),
        );
    }
}
