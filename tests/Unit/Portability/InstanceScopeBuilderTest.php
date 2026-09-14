<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Circulation\ContextResolver;
use App\Circulation\DefaultContextProvider;
use App\Entity\Announcement;
use App\Entity\Cms;
use App\Entity\Event;
use App\Entity\ItemTag;
use App\Entity\Topic;
use App\Entity\User;
use App\Enum\UserRole;
use App\Portability\DataCategory;
use App\Portability\InstanceScopeBuilder;
use App\Portability\Item\ContributorInterface;
use App\Portability\Item\Registry;
use App\Portability\Site;
use App\Portability\SiteSettings;
use App\Repository\AnnouncementRepository;
use App\Repository\CirculationLedgerEntryRepository;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Repository\ItemTagRepository;
use App\Repository\TopicRepository;
use App\Repository\UserRepository;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class InstanceScopeBuilderTest extends TestCase
{
    public function testTheScopeCoversEveryMemberAndEverythingThisInstanceHolds(): void
    {
        // Arrange
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findBy')->willReturn([
            $this->user(1, UserRole::System),
            $this->user(2, UserRole::Admin),
            $this->user(3, UserRole::User),
        ]);
        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('findBy')->willReturn([$this->withId(new Event(), 5), $this->withId(new Event(), 6)]);
        $cmsRepository = $this->createStub(CmsRepository::class);
        $cmsRepository->method('findBy')->willReturn([$this->withId(new Cms(), 9)]);
        $topicRepository = $this->createStub(TopicRepository::class);
        $topicRepository->method('findBy')->willReturn([$this->withId(new Topic(), 21)]);
        $announcementRepository = $this->createStub(AnnouncementRepository::class);
        $announcementRepository->method('findBy')->willReturn([$this->withId(new Announcement(), 31)]);
        $tagRepository = $this->createStub(ItemTagRepository::class);
        $tagRepository->method('findBy')->willReturn([$this->tag(41, 'film'), $this->tag(42, 'book'), $this->tag(43, 'film')]);
        $ledgerRepository = $this->createStub(CirculationLedgerEntryRepository::class);
        $ledgerRepository->method('findContextItemTypes')->willReturn(['book' => 'book']);

        $books = $this->createStub(ContributorInterface::class);
        $books->method('getItemType')->willReturn('book');
        $books->method('allItemIds')->willReturn([4, 8]);
        $films = $this->createStub(ContributorInterface::class);
        $films->method('getItemType')->willReturn('film');
        $films->method('allItemIds')->willReturn([7]);
        $registry = $this->createStub(Registry::class);
        $registry->method('all')->willReturn([$books, $films]);

        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturn(['books', 'core_navigation']);
        $pluginService->method('isInstalled')->willReturnCallback(static fn(string $pluginKey): bool => $pluginKey === 'books');

        $site = new Site('weiqi-club', 'Weiqi Club', '');
        $siteSettings = $this->createStub(SiteSettings::class);
        $siteSettings->method('current')->willReturn($site);

        $builder = new InstanceScopeBuilder(
            $userRepository,
            $eventRepository,
            $cmsRepository,
            $topicRepository,
            $announcementRepository,
            $tagRepository,
            $ledgerRepository,
            new ContextResolver([new DefaultContextProvider()]),
            $registry,
            $pluginService,
            $siteSettings,
        );

        // Act
        $scope = $builder->build();

        // Assert
        static::assertSame([2 => 'admin', 3 => 'user'], $scope->users);
        static::assertSame([2 => DataCategory::cases(), 3 => DataCategory::cases()], $scope->grants);
        static::assertNull($scope->stewardEmail);
        static::assertSame([5, 6], $scope->eventIds);
        static::assertSame([9], $scope->cmsIds);
        static::assertSame(['book' => [4, 8], 'film' => [7]], $scope->itemIds);
        static::assertSame(['books'], $scope->plugins);
        static::assertSame($site, $scope->site);
        static::assertSame([21], $scope->topicIds);
        static::assertSame([31], $scope->announcementIds);
        static::assertSame(['book' => [42], 'film' => [41, 43]], $scope->tagIds);
        static::assertSame(['book' => 'book', 'film' => 'film'], $scope->circulationContexts);
        static::assertTrue($scope->everyUpload);
    }

    private function user(int $id, UserRole $role): User
    {
        $user = $this->withId(new User(), $id);
        $user->setRole($role);

        return $user;
    }

    private function tag(int $id, string $itemType): ItemTag
    {
        $tag = $this->withId(new ItemTag(), $id);
        $tag->setItemType($itemType);

        return $tag;
    }

    /**
     * @template T of object
     * @param T $entity
     * @return T
     */
    private function withId(object $entity, int $id): object
    {
        new ReflectionProperty($entity::class, 'id')->setValue($entity, $id);

        return $entity;
    }
}
