<?php declare(strict_types=1);

namespace App\Service;

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

    public function getActiveUsersCount(): int
    {
        return $this->userRepo->getRecentlyActiveCount(7);
    }

    public function getImageStats(DateTimeImmutable $start, DateTimeImmutable $stop): array
    {
        return $this->imageRepo->getStorageStats($start, $stop);
    }

    public function getUpcomingEvents(int $limit = 3): array
    {
        return $this->eventRepo->getUpcomingEvents($limit);
    }

    public function getPastEventsWithoutPhotos(int $limit = 5): array
    {
        return $this->eventRepo->getPastEventsWithoutPhotos($limit);
    }

    public function getRecurringEventsCount(): int
    {
        return $this->eventRepo->getRecurringCount();
    }

    public function getUnverifiedCount(): int
    {
        return $this->userRepo->getUnverifiedCount();
    }

    /**
     * @return array{total: int, unread: int}
     */
    public function getMessageStats(): array
    {
        return $this->messageRepo->getSystemStats();
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
