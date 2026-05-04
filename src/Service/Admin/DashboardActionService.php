<?php declare(strict_types=1);

namespace App\Service\Admin;

use App\Filter\Admin\Dashboard\DashboardScope;
use App\Repository\CommandExecutionLogRepository;
use App\Repository\EmailQueueRepository;
use App\Repository\EventRepository;
use App\Repository\ImageRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;

readonly class DashboardActionService
{
    public function __construct(
        private EventRepository $eventRepo,
        private UserRepository $userRepo,
        private EmailQueueRepository $mailRepo,
        private ImageRepository $imageRepo,
        private MessageRepository $messageRepo,
        private CommandExecutionLogRepository $commandLogRepo,
    ) {}

    public function getNeedForApproval(): array
    {
        return $this->userRepo->findBy(['status' => 1], ['createdAt' => 'desc']);
    }

    public function getActionItems(): array
    {
        return [
            'reportedImages' => $this->imageRepo->getReportedCount(),
            'staleEmails' => $this->mailRepo->getStaleCount(60),
            'pendingEmails' => $this->mailRepo->getPendingCount(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getUserStatusBreakdown(): array
    {
        return $this->userRepo->getStatusBreakdown();
    }

    public function getActiveUsersCount(?DashboardScope $scope = null): int
    {
        return $this->userRepo->getRecentlyActiveCount(7, $scope?->userIds());
    }

    public function getImageStats(DateTimeImmutable $start, DateTimeImmutable $stop): array
    {
        return $this->imageRepo->getStorageStats($start, $stop);
    }

    public function getPastEventsWithoutPhotos(int $limit = 5, ?DashboardScope $scope = null): array
    {
        return $this->eventRepo->getPastEventsWithoutPhotos($limit, $scope?->eventIds());
    }

    public function getRecurringEventsCount(?DashboardScope $scope = null): int
    {
        return $this->eventRepo->getRecurringCount($scope?->eventIds());
    }

    public function getUnverifiedCount(): int
    {
        return $this->userRepo->getUnverifiedCount();
    }

    /**
     * @return array{total: int, unread: int}
     */
    public function getMessageStats(?DashboardScope $scope = null): array
    {
        return $this->messageRepo->getSystemStats($scope?->userIds());
    }

    public function getMembersThisWeek(int $year, int $week, ?DashboardScope $scope = null): int
    {
        $tmp = new \DateTime();
        $tmp->setISODate($year, $week);
        $start = DateTimeImmutable::createFromMutable($tmp);

        return $this->userRepo->countCreatedSince($start, $scope?->userIds());
    }

    /**
     * @return array<\App\Entity\Event>
     */
    public function getUpcomingEventsLowRsvp(int $daysAhead = 14, int $minYes = 3, ?DashboardScope $scope = null): array
    {
        return $this->eventRepo->findUpcomingWithLowRsvp($daysAhead, $minYes, $scope?->eventIds());
    }

    /**
     * @return array<string, int>
     */
    public function getEmailQueueBreakdown(): array
    {
        return $this->mailRepo->getPendingByTemplate();
    }

    /**
     * @return array{total: int, successful: int, failed: int}
     */
    public function getCommandExecutionStats(): array
    {
        $since = new DateTimeImmutable('-24 hours');

        return $this->commandLogRepo->getStats($since);
    }

    /**
     * @return array<string, \App\Entity\CommandExecutionLog>
     */
    public function getLastCommandExecutions(): array
    {
        return $this->commandLogRepo->getLastExecutionsByCommand();
    }

    /**
     * @return array{total: int, sent: int, failed: int}
     */
    public function getEmailDeliveryStats(): array
    {
        $since = new DateTimeImmutable('-24 hours');

        return $this->mailRepo->getDeliveryStats($since);
    }

    public function getEmailDeliverySuccessRate(): float
    {
        $since = new DateTimeImmutable('-24 hours');

        return $this->mailRepo->getDeliverySuccessRate($since);
    }
}
