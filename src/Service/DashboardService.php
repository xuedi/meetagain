<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\ActivityRepository;
use App\Repository\EmailQueueRepository;
use App\Repository\EventRepository;
use App\Repository\ImageRepository;
use App\Repository\NotFoundLogRepository;
use App\Repository\TranslationSuggestionRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;

readonly class DashboardService
{
    public function __construct(
        private EventRepository $eventRepo,
        private UserRepository $userRepo,
        private EmailQueueRepository $mailRepo,
        private NotFoundLogRepository $notFoundRepo,
        private ActivityRepository $activityRepo,
        private ImageRepository $imageRepo,
        private TranslationSuggestionRepository $translationSuggestionRepo,
    ) {
    }

    public function getTimeControl(int $year, int $week): array
    {
        $dates = $this->calculateDates($year, $week);
        return [
            'week' => $week,
            'year' => $year,
            'weekNext' => $week + 1, // TODO: over/underflow someday
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

    public function calculateDates(null|int $year, null|int $week): array
    {
        $year ??= (int) (new DateTime())->format('Y');
        $week ??= (int) (new DateTime())->format('W');

        $tmpDate = new DateTime();
        $tmpDate->setISODate($year, $week);

        return [
            'start' => DateTimeImmutable::createFromMutable($tmpDate),
            'stop' => DateTimeImmutable::createFromMutable($tmpDate->modify('+6 days')),
        ];
    }

    private function timeCrit(array $dates, null|string $column = 'createdAt'): Criteria
    {
        $start = $dates['start'];
        $stop = $dates['stop'];
        if ($column === 'start') {
            $start = (new DateTime())->setTimestamp($start->getTimestamp());
            $stop = (new DateTime())->setTimestamp($stop->getTimestamp());
        }

        $criteria = new Criteria();
        $criteria->where(Criteria::expr()?->gte($column, $start));
        $criteria->andWhere(Criteria::expr()?->lte($column, $stop));
        return $criteria;
    }

    public function getNeedForApproval(): array
    {
        return $this->userRepo->findBy(['status' => 1], ['createdAt' => 'desc']);
    }

    /**
     * Items requiring admin attention
     */
    public function getActionItems(): array
    {
        return [
            'reportedImages' => $this->imageRepo->getReportedCount(),
            'pendingTranslations' => $this->translationSuggestionRepo->getPendingCount(),
            'staleEmails' => $this->mailRepo->getStaleCount(60),
            'pendingEmails' => $this->mailRepo->getPendingCount(),
        ];
    }

    /**
     * User status breakdown
     * @return array<string, int>
     */
    public function getUserStatusBreakdown(): array
    {
        return $this->userRepo->getStatusBreakdown();
    }

    /**
     * Users active in last 7 days
     */
    public function getActiveUsersCount(): int
    {
        return $this->userRepo->getRecentlyActiveCount(7);
    }

    /**
     * Image storage statistics
     */
    public function getImageStats(int $year, int $week): array
    {
        $dates = $this->calculateDates($year, $week);
        return $this->imageRepo->getStorageStats($dates['start'], $dates['stop']);
    }

    /**
     * Upcoming events
     * @return array
     */
    public function getUpcomingEvents(int $limit = 3): array
    {
        return $this->eventRepo->getUpcomingEvents($limit);
    }

    /**
     * Past events without photos
     * @return array
     */
    public function getPastEventsWithoutPhotos(int $limit = 5): array
    {
        return $this->eventRepo->getPastEventsWithoutPhotos($limit);
    }

    /**
     * Recurring events count
     */
    public function getRecurringEventsCount(): int
    {
        return $this->eventRepo->getRecurringCount();
    }
}
