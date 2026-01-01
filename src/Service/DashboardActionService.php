<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\CommandExecutionLogRepository;
use App\Repository\EmailQueueRepository;
use App\Repository\EventRepository;
use App\Repository\ImageRepository;
use App\Repository\MessageRepository;
use App\Repository\TranslationSuggestionRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;

readonly class DashboardActionService
{
    public function __construct(
        private EventRepository $eventRepo,
        private UserRepository $userRepo,
        private EmailQueueRepository $mailRepo,
        private ImageRepository $imageRepo,
        private TranslationSuggestionRepository $translationSuggestionRepo,
        private MessageRepository $messageRepo,
        private CommandExecutionLogRepository $commandLogRepo,
    ) {
    }

    public function getNeedForApproval(): array
    {
        return $this->userRepo->findBy(['status' => 1], ['createdAt' => 'desc']);
    }

    /**
     * Items requiring admin attention.
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
     * User status breakdown.
     *
     * @return array<string, int>
     */
    public function getUserStatusBreakdown(): array
    {
        return $this->userRepo->getStatusBreakdown();
    }

    /**
     * Users active in last 7 days.
     */
    public function getActiveUsersCount(): int
    {
        return $this->userRepo->getRecentlyActiveCount(7);
    }

    /**
     * Image storage statistics.
     */
    public function getImageStats(DateTimeImmutable $start, DateTimeImmutable $stop): array
    {
        return $this->imageRepo->getStorageStats($start, $stop);
    }

    /**
     * Upcoming events.
     */
    public function getUpcomingEvents(int $limit = 3): array
    {
        return $this->eventRepo->getUpcomingEvents($limit);
    }

    /**
     * Past events without photos.
     */
    public function getPastEventsWithoutPhotos(int $limit = 5): array
    {
        return $this->eventRepo->getPastEventsWithoutPhotos($limit);
    }

    /**
     * Recurring events count.
     */
    public function getRecurringEventsCount(): int
    {
        return $this->eventRepo->getRecurringCount();
    }

    public function getPendingSuggestionsCount(): int
    {
        return $this->translationSuggestionRepo->getPendingCount();
    }

    /**
     * Count users stuck in EmailVerified status.
     */
    public function getUnverifiedCount(): int
    {
        return $this->userRepo->getUnverifiedCount();
    }

    /**
     * Get system-wide message statistics.
     *
     * @return array{total: int, unread: int}
     */
    public function getMessageStats(): array
    {
        return $this->messageRepo->getSystemStats();
    }

    /**
     * Get pending emails by template type.
     *
     * @return array<string, int>
     */
    public function getEmailQueueBreakdown(): array
    {
        return $this->mailRepo->getPendingByTemplate();
    }

    /**
     * Get command execution statistics for the last 24 hours.
     *
     * @return array{total: int, successful: int, failed: int}
     */
    public function getCommandExecutionStats(): array
    {
        $since = new DateTimeImmutable('-24 hours');

        return $this->commandLogRepo->getStats($since);
    }

    /**
     * Get last execution for each command.
     *
     * @return array<string, \App\Entity\CommandExecutionLog>
     */
    public function getLastCommandExecutions(): array
    {
        return $this->commandLogRepo->getLastExecutionsByCommand();
    }

    /**
     * Get email delivery statistics for the last 24 hours.
     *
     * @return array{total: int, sent: int, failed: int}
     */
    public function getEmailDeliveryStats(): array
    {
        $since = new DateTimeImmutable('-24 hours');

        return $this->mailRepo->getDeliveryStats($since);
    }

    /**
     * Get email delivery success rate for the last 24 hours.
     */
    public function getEmailDeliverySuccessRate(): float
    {
        $since = new DateTimeImmutable('-24 hours');

        return $this->mailRepo->getDeliverySuccessRate($since);
    }
}
