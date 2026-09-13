<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\User;

readonly class Scope
{
    /**
     * @param array<int, 'admin'|'organizer'|'user'> $users
     * @param list<int> $eventIds
     * @param list<int> $cmsIds
     * @param array<string, list<int>> $itemIds
     * @param list<string> $plugins
     * @param array<int, list<DataCategory>> $grants
     * @param list<int> $topicIds
     * @param list<int> $announcementIds
     * @param array<string, list<int>> $tagIds
     * @param array<string, string> $circulationContexts context => item type
     */
    public function __construct(
        public array $users = [],
        public array $eventIds = [],
        public array $cmsIds = [],
        public array $itemIds = [],
        public array $plugins = [],
        public ?Site $site = null,
        public array $grants = [],
        public ?string $stewardEmail = null,
        public array $topicIds = [],
        public array $announcementIds = [],
        public array $tagIds = [],
        public array $circulationContexts = [],
        public bool $everyUpload = false,
    ) {}

    public function grants(?User $user, DataCategory $category): bool
    {
        return $this->grantsId($user?->getId(), $category);
    }

    public function grantsId(?int $userId, DataCategory $category): bool
    {
        if ($userId === null || !isset($this->users[$userId])) {
            return false;
        }

        return $category === DataCategory::Profile || in_array($category, $this->grants[$userId] ?? [], true);
    }

    public function carriesUpload(?int $uploaderId): bool
    {
        return $this->everyUpload || $this->grantsId($uploaderId, DataCategory::Uploads);
    }

    public function creditEmail(?User $user, DataCategory $category = DataCategory::Profile): ?string
    {
        return $this->grants($user, $category) ? $user?->getEmail() : $this->stewardEmail;
    }

    /**
     * @param array<string, list<int>> $itemIds
     */
    public function withItemIds(array $itemIds): self
    {
        return new self(
            users: $this->users,
            eventIds: $this->eventIds,
            cmsIds: $this->cmsIds,
            itemIds: $itemIds,
            plugins: $this->plugins,
            site: $this->site,
            grants: $this->grants,
            stewardEmail: $this->stewardEmail,
            topicIds: $this->topicIds,
            announcementIds: $this->announcementIds,
            tagIds: $this->tagIds,
            circulationContexts: $this->circulationContexts,
            everyUpload: $this->everyUpload,
        );
    }
}
