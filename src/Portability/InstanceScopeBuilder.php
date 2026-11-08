<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\Announcement;
use App\Entity\Cms;
use App\Entity\Event;
use App\Entity\Topic;
use App\Enum\UserRole;
use App\Portability\Item\Registry;
use App\Repository\AnnouncementRepository;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Repository\ItemTagRepository;
use App\Repository\TopicRepository;
use App\Repository\UserRepository;
use App\Service\Config\PluginService;
use Module\Circulation\Contract\CirculationInterface;

readonly class InstanceScopeBuilder
{
    public function __construct(
        private UserRepository $userRepository,
        private EventRepository $eventRepository,
        private CmsRepository $cmsRepository,
        private TopicRepository $topicRepository,
        private AnnouncementRepository $announcementRepository,
        private ItemTagRepository $tagRepository,
        private CirculationInterface $circulation,
        private Registry $itemRegistry,
        private PluginService $pluginService,
        private SiteSettings $siteSettings,
    ) {}

    public function build(): Scope
    {
        $users = [];
        $grants = [];
        foreach ($this->userRepository->findBy([], ['id' => 'ASC']) as $user) {
            if ($user->getRole() === UserRole::System) {
                continue;
            }

            $userId = (int) $user->getId();
            $users[$userId] = $user->getRole() === UserRole::Admin ? 'admin' : 'user';
            $grants[$userId] = DataCategory::cases();
        }

        $itemIds = [];
        foreach ($this->itemRegistry->all() as $contributor) {
            $itemIds[$contributor->getItemType()] = $contributor->allItemIds();
        }

        $tagIds = [];
        foreach ($this->tagRepository->findBy([], ['id' => 'ASC']) as $tag) {
            $tagIds[(string) $tag->getItemType()][] = (int) $tag->getId();
        }
        ksort($tagIds);

        return new Scope(
            users: $users,
            eventIds: $this->ids($this->eventRepository->findBy([], ['id' => 'ASC'])),
            cmsIds: $this->ids($this->cmsRepository->findBy([], ['id' => 'ASC'])),
            itemIds: $itemIds,
            plugins: array_values(array_filter($this->pluginService->getActiveList(), $this->pluginService->isInstalled(...))),
            site: $this->siteSettings->current(),
            grants: $grants,
            topicIds: $this->ids($this->topicRepository->findBy([], ['id' => 'ASC'])),
            announcementIds: $this->ids($this->announcementRepository->findBy([], ['id' => 'ASC'])),
            tagIds: $tagIds,
            circulationContexts: $this->circulationContexts(),
            everyUpload: true,
        );
    }

    /**
     * @return array<string, string> context => item type, every context the ledger knows plus one per active item type
     */
    public function circulationContexts(): array
    {
        $circulationContexts = $this->circulation->ledgerContexts();
        foreach ($this->itemRegistry->all() as $contributor) {
            $circulationContexts[$this->circulation->contextFor($contributor->getItemType())] ??= $contributor->getItemType();
        }
        ksort($circulationContexts);

        return $circulationContexts;
    }

    /**
     * @param array<array-key, Event|Cms|Topic|Announcement> $entities
     * @return list<int>
     */
    private function ids(array $entities): array
    {
        return array_values(array_map(static fn(Event|Cms|Topic|Announcement $entity): int => (int) $entity->getId(), $entities));
    }
}
