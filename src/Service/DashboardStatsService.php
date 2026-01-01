<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\ActivityRepository;
use App\Repository\EmailQueueRepository;
use App\Repository\EventRepository;
use App\Repository\NotFoundLogRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;

readonly class DashboardStatsService
{
    public function __construct(
        private EventRepository $eventRepo,
        private UserRepository $userRepo,
        private EmailQueueRepository $mailRepo,
        private NotFoundLogRepository $notFoundRepo,
        private ActivityRepository $activityRepo,
    ) {
    }

    public function getTimeControl(int $year, int $week): array
    {
        $dates = $this->calculateDates($year, $week);

        return [
            'week' => $week,
            'year' => $year,
            'weekNext' => $week + 1,
            'weekPrevious' => $week - 1,
            'weekDetails' => sprintf(
                '%s - %s',
                $dates['start']->format('Y-m-d'),
                $dates['stop']->format('Y-m-d'),
            ),
        ];
    }

    public function getDetails(int $year, int $week): array
    {
        $dates = $this->calculateDates($year, $week);

        return [
            '404pages' => [
                'count' => $this->notFoundRepo->count(),
                'week' => $this->notFoundRepo->matching($this->timeCrit($dates))->count(),
            ],
            'members' => [
                'count' => $this->userRepo->count(),
                'week' => $this->userRepo->matching($this->timeCrit($dates))->count(),
            ],
            'activity' => [
                'count' => $this->activityRepo->count(),
                'week' => $this->activityRepo->matching($this->timeCrit($dates))->count(),
            ],
            'events' => [
                'count' => $this->eventRepo->count(),
                'week' => $this->eventRepo->matching($this->timeCrit($dates, 'start'))->count(),
            ],
            'emails' => [
                'count' => $this->mailRepo->count(),
                'week' => $this->mailRepo->matching($this->timeCrit($dates))->count(),
            ],
        ];
    }

    public function getPagesNotFound(int $year, int $week): array
    {
        $dates = $this->calculateDates($year, $week);

        return [
            'list' => $this->notFoundRepo->getWeekSummary($dates['start'], $dates['stop']),
        ];
    }

    /**
     * Get RSVP statistics for the week.
     *
     * @return array{yes: int, no: int, total: int}
     */
    public function getRsvpStats(int $year, int $week): array
    {
        $dates = $this->calculateDates($year, $week);

        return $this->activityRepo->getRsvpStats($dates['start'], $dates['stop']);
    }

    /**
     * Get login activity trend for the week.
     *
     * @return array<string, int>
     */
    public function getLoginTrend(int $year, int $week): array
    {
        $dates = $this->calculateDates($year, $week);

        return $this->activityRepo->getLoginTrend($dates['start'], $dates['stop']);
    }

    /**
     * Get social network statistics.
     */
    public function getSocialNetworkStats(int $year, int $week): array
    {
        $dates = $this->calculateDates($year, $week);

        return $this->userRepo->getSocialNetworkStats($dates['start']);
    }

    public function calculateDates(?int $year, ?int $week): array
    {
        $now = new DateTime();
        $year ??= (int) $now->format('Y');
        $week ??= (int) $now->format('W');

        $tmpDate = new DateTime();
        $tmpDate->setISODate($year, $week);

        return [
            'start' => DateTimeImmutable::createFromMutable($tmpDate),
            'stop' => DateTimeImmutable::createFromMutable($tmpDate->modify('+6 days')),
        ];
    }

    private function timeCrit(array $dates, ?string $column = 'createdAt'): Criteria
    {
        $start = $dates['start'];
        $stop = $dates['stop'];
        if ($column === 'start') {
            $start = new DateTime();
            $start->setTimestamp($dates['start']->getTimestamp());
            $stop = new DateTime();
            $stop->setTimestamp($dates['stop']->getTimestamp());
        }

        $criteria = new Criteria();
        $criteria->where(Criteria::expr()->gte($column, $start));
        $criteria->andWhere(Criteria::expr()->lte($column, $stop));

        return $criteria;
    }
}
