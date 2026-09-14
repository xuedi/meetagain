<?php declare(strict_types=1);

namespace App\Portability;

use App\Circulation\ContextResolver;
use App\Entity\Announcement;
use App\Entity\Cms;
use App\Entity\Event;
use App\Entity\Topic;
use App\Enum\UserRole;
use App\Portability\Item\Registry;
use App\Repository\AnnouncementRepository;
use App\Repository\CirculationLedgerEntryRepository;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Repository\ItemTagRepository;
use App\Repository\TopicRepository;
use App\Repository\UserRepository;
use App\Service\Config\PluginService;

readonly class InstanceScopeBuilder
{
    public function __construct(
        private UserRepository $userRepository,
        private EventRepository $eventRepository,
        private CmsRepository $cmsRepository,
        private TopicRepository $topicRepository,
        private AnnouncementRepository $announcementRepository,
        private ItemTagRepository $tagRepository,
        private CirculationLedgerEntryRepository $ledgerRepository,
        private ContextResolver $contextResolver,
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
        $circulationContexts = $this->ledgerRepository->findContextItemTypes();
        foreach ($this->itemRegistry->all() as $contributor) {
            $itemType = $contributor->getItemType();
            $itemIds[$itemType] = $contributor->allItemIds();
            $circulationContexts[$this->contextResolver->resolve($itemType)] ??= $itemType;
        }
        ksort($circulationContexts);

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
            circulationContexts: $circulationContexts,
            everyUpload: true,
        );
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
