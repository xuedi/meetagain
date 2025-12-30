<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\EmailQueueRepository;
use App\Repository\EventRepository;
use App\Repository\ImageRepository;
use App\Repository\TranslationSuggestionRepository;
use App\Repository\UserRepository;

readonly class DashboardActionService
{
    public function __construct(
        private EventRepository $eventRepo,
        private UserRepository $userRepo,
        private EmailQueueRepository $mailRepo,
        private ImageRepository $imageRepo,
        private TranslationSuggestionRepository $translationSuggestionRepo,
    ) {
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
    public function getImageStats(\DateTimeImmutable $start, \DateTimeImmutable $stop): array
    {
        return $this->imageRepo->getStorageStats($start, $stop);
    }

    /**
     * Upcoming events
     */
    public function getUpcomingEvents(int $limit = 3): array
    {
        return $this->eventRepo->getUpcomingEvents($limit);
    }

    /**
     * Past events without photos
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

    public function getPendingSuggestionsCount(): int
    {
        return $this->translationSuggestionRepo->getPendingCount();
    }
}
