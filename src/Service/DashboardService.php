<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\NotFoundLog;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\EmailQueueRepository;
use App\Repository\EventRepository;
use App\Repository\NotFoundLogRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DashboardService
{
    private int $week = 0;
    private int $weekNext = 0;
    private int $weekPrevious = 0;
    private int $year = 0;

    private DateTimeImmutable $weekStartDate;
    private DateTimeImmutable $weekStopDate;

    public function __construct(
        private readonly EventRepository $eventRepo,
        private readonly UserRepository $userRepo,
        private readonly EmailQueueRepository $mailRepo,
        private readonly NotFoundLogRepository $notFoundRepo,
        private readonly ActivityRepository $activityRepo,
    ) {
        // nothing to do, just chilling :-)
    }

    public function getTimeControl(): array
    {
        return ['week' => $this->week, 'year' => $this->year, 'weekNext' => $this->weekNext, 'weekPrevious' => $this->weekPrevious, 'weekDetails' => sprintf(
            "%s - %s",
            $this->weekStartDate->format('Y-m-d'),
            $this->weekStopDate->format('Y-m-d'),
        )];
    }

    public function getDetails(): array
    {
        return [
            '404pages' => [
                'count' => $this->notFoundRepo->count(),
                'week' => $this->notFoundRepo->matching($this->timeCrit())->count(),
            ],
            'members' => [
                'count' => $this->userRepo->count(),
                'week' => $this->userRepo->matching($this->timeCrit())->count(),
            ],
            'activity' => [
                'count' => $this->activityRepo->count(),
                'week' => $this->activityRepo->matching($this->timeCrit())->count(),
            ],
            'events' => [
                'count' => $this->eventRepo->count(),
                'week' => $this->eventRepo->matching($this->timeCrit('start'))->count(),
            ],
            'emails' => [
                'count' => $this->mailRepo->count(),
                'week' => $this->mailRepo->matching($this->timeCrit())->count(),
            ],
        ];
    }

    public function getPagesNotFound(): array
    {
        return [
            'list' => $this->notFoundRepo->getWeekSummary($this->weekStartDate, $this->weekStopDate)
        ];
    }

    public function setTime(?int $year, ?int $week): void
    {
        $this->year = $year ?? (int)new DateTime()->format('Y');
        $this->week = $week ?? (int)new DateTime()->format('W');

        $tmpDate = new DateTime();
        $tmpDate->setISODate($this->year, $this->week);

        $this->weekStartDate = DateTimeImmutable::createFromMutable($tmpDate);
        $this->weekStopDate = DateTimeImmutable::createFromMutable($tmpDate->modify('+6 days'));

        $this->weekNext = $this->week + 1; // TODO: over/underflow someday
        $this->weekPrevious = $this->week - 1;
    }

    private function timeCrit(?string $column = 'createdAt'): Criteria
    {
        $start = $this->weekStartDate;
        $stop = $this->weekStopDate;
        if ($column === 'start') {
            $start = new DateTime()->setTimestamp($start->getTimestamp());
            $stop = new DateTime()->setTimestamp($stop->getTimestamp());
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
}
