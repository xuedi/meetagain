<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Functional;

use App\DataFixtures\EventFixture;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\ItemTag;
use App\Item\Tag\TagService;
use App\Repository\EventRepository;
use App\Service\Event\EventScope;
use App\Service\Item\AssociationService;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Event\AssociationWriter;
use Plugin\Photos\Event\DateTagService;
use Plugin\Photos\Repository\PhotoRepository;
use Plugin\Photos\Service\PhotoService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventAssociationTest extends KernelTestCase
{
    private AssociationWriter $writer;
    private AssociationService $associations;
    private DateTagService $dateTagService;
    private TagService $tagService;
    private EventRepository $eventRepository;
    private PhotoRepository $photoRepository;
    private EventScope $eventScope;
    private int $firstEvent;
    private int $secondEvent;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->writer = $container->get(AssociationWriter::class);
        $this->associations = $container->get(AssociationService::class);
        $this->dateTagService = $container->get(DateTagService::class);
        $this->tagService = $container->get(TagService::class);
        $this->eventRepository = $container->get(EventRepository::class);
        $this->photoRepository = $container->get(PhotoRepository::class);
        $this->eventScope = $container->get(EventScope::class);
        $this->firstEvent = $this->eventIdByTitle(EventFixture::BERLIN_TOURNAMENT);
        $this->secondEvent = $this->eventIdByTitle(EventFixture::WEEKLY_GO_STUDY);
    }

    private function eventIdByTitle(string $title): int
    {
        $translation = self::getContainer()->get('doctrine')->getManager()
            ->getRepository(EventTranslation::class)->findOneBy(['title' => $title]);
        if ($translation === null || !$translation->getEvent() instanceof Event) {
            self::fail('Required fixture event missing: ' . $title);
        }

        return (int) $translation->getEvent()->getId();
    }

    public function testMovingAPhotoDetachesTheOldEventAndDropsItsDateRow(): void
    {
        // Arrange
        $photo = $this->photoOf($this->secondEvent);
        $this->inScope(fn(): null => $this->writer->setEvent($photo, $this->event($this->firstEvent)));
        $first = $this->inScope(fn(): ?ItemTag => $this->dateTagService->findDateTag($this->event($this->firstEvent)));
        static::assertNotNull($first);

        // Act
        $this->inScope(fn(): null => $this->writer->setEvent($photo, $this->event($this->secondEvent)));

        // Assert
        static::assertSame([$this->secondEvent], $this->eventIds($photo));
        static::assertNull($this->inScope(fn(): ?ItemTag => $this->dateTagService->findDateTag($this->event($this->firstEvent))));
        static::assertNotNull($this->inScope(fn(): ?ItemTag => $this->dateTagService->findDateTag($this->event($this->secondEvent))));
    }

    public function testClearingTheEventLeavesThePhotoItselfIntact(): void
    {
        // Arrange
        $photo = $this->photoOf($this->secondEvent);
        $ownTags = $this->tagService->getTagIds(PhotoService::ITEM_TYPE, (int) $photo->getId());
        $this->inScope(fn(): null => $this->writer->setEvent($photo, $this->event($this->firstEvent)));

        // Act
        $this->inScope(fn(): null => $this->writer->setEvent($photo, null));

        // Assert
        static::assertSame([], $this->eventIds($photo));
        static::assertInstanceOf(Photo::class, $this->photoRepository->find((int) $photo->getId()));
        static::assertSame($ownTags, $this->tagService->getTagIds(PhotoService::ITEM_TYPE, (int) $photo->getId()));
        static::assertNull($this->inScope(fn(): ?ItemTag => $this->dateTagService->findDateTag($this->event($this->firstEvent))));
    }

    public function testSavingTheSameEventAgainChangesNothing(): void
    {
        // Arrange
        $photo = $this->photoOf($this->secondEvent);
        $this->inScope(fn(): null => $this->writer->setEvent($photo, $this->event($this->firstEvent)));
        $tag = $this->inScope(fn(): ?ItemTag => $this->dateTagService->findDateTag($this->event($this->firstEvent)));

        // Act
        $this->inScope(fn(): null => $this->writer->setEvent($photo, $this->event($this->firstEvent)));

        // Assert
        static::assertSame([$this->firstEvent], $this->eventIds($photo));
        static::assertSame($tag?->getId(), $this->inScope(fn(): ?ItemTag => $this->dateTagService->findDateTag($this->event($this->firstEvent)))?->getId());
    }

    public function testTheDateRowSurvivesWhileAnotherPhotoStillCarriesIt(): void
    {
        // Arrange
        $photos = $this->photosOf($this->secondEvent, 2);
        foreach ($photos as $photo) {
            $this->inScope(fn(): null => $this->writer->setEvent($photo, $this->event($this->firstEvent)));
        }

        // Act
        $this->inScope(fn(): null => $this->writer->setEvent($photos[0], null));

        // Assert
        $tag = $this->inScope(fn(): ?ItemTag => $this->dateTagService->findDateTag($this->event($this->firstEvent)));
        static::assertNotNull($tag);
        static::assertContains((int) $tag->getId(), $this->tagService->getTagIds(PhotoService::ITEM_TYPE, (int) $photos[1]->getId()));
    }

    /**
     * @template T
     * @param  callable():T $work
     * @return T
     */
    private function inScope(callable $work): mixed
    {
        return $this->eventScope->runForEvent($this->secondEvent, $work);
    }

    /** @return list<int> */
    private function eventIds(Photo $photo): array
    {
        return $this->associations->eventIdsForItem(PhotoService::ITEM_TYPE, (int) $photo->getId());
    }

    private function event(int $id): Event
    {
        $event = $this->eventRepository->find($id);
        if (!$event instanceof Event) {
            self::fail('Required fixture event missing: ' . $id);
        }

        return $event;
    }

    private function photoOf(int $eventId): Photo
    {
        return $this->photosOf($eventId, 1)[0];
    }

    /** @return list<Photo> */
    private function photosOf(int $eventId, int $count): array
    {
        $photos = [];
        foreach ($this->associations->listForEvent($eventId) as $association) {
            if ($association->getItemType() !== PhotoService::ITEM_TYPE) {
                continue;
            }

            $photo = $this->photoRepository->find((int) $association->getItemId());
            if ($photo instanceof Photo) {
                $photos[] = $photo;
            }

            if (count($photos) === $count) {
                return $photos;
            }
        }

        self::fail('Fixture event ' . $eventId . ' carries fewer than ' . $count . ' photos');
    }
}
