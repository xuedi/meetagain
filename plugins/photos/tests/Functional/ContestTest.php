<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Functional;

use App\Publisher\PluginSettings\GenericStore;
use App\Repository\EventItemAssociationRepository;
use App\Service\Event\EventScope;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Repository\PhotoRepository;
use App\Item\FilterService;
use Plugin\Photos\Service\ContestService;
use Plugin\Photos\Service\PhotoService;
use Plugin\Photos\ValueObject\Config;
use Plugin\Voting\Entity\Poll;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ContestTest extends KernelTestCase
{
    private ContestService $contest;
    private PhotoRepository $photoRepo;
    private EntityManagerInterface $em;
    private EventScope $eventScope;
    private int $scopeEventId;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $config = new Config()->setContest(true)->setContestSubmissionsPerMember(2);
        $container->get(GenericStore::class)->save('photos', $config, null);

        $this->contest = $container->get(ContestService::class);
        $this->photoRepo = $container->get(PhotoRepository::class);
        $this->em = $container->get(EntityManagerInterface::class);
        $this->eventScope = $container->get(EventScope::class);
        $this->scopeEventId = $container->get(EventItemAssociationRepository::class)->findEventIdsByType(PhotoService::ITEM_TYPE)[0];
    }

    /**
     * @template T
     * @param  callable():T $work
     * @return T
     */
    private function inScope(callable $work): mixed
    {
        return $this->eventScope->runForEvent($this->scopeEventId, $work);
    }

    public function testTheCapRefusesOneSubmissionTooMany(): void
    {
        // Arrange
        $photos = $this->photosOfOneMember(3);

        // Act
        $this->contest->submit($photos[0]);
        $this->contest->submit($photos[1]);

        // Assert
        static::assertSame(0, $this->inScope(fn(): int => $this->contest->remainingFor((int) $photos[0]->getCreatedBy())));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('photos_contest.flash_cap_reached');
        $this->inScope(fn(): null => $this->contest->submit($photos[2]));
    }

    public function testWithdrawingFreesTheAllowanceAgain(): void
    {
        // Arrange
        $photos = $this->photosOfOneMember(2);
        $this->contest->submit($photos[0]);
        $this->contest->submit($photos[1]);

        // Act
        $this->contest->withdraw($photos[1]);

        // Assert
        static::assertSame(1, $this->inScope(fn(): int => $this->contest->remainingFor((int) $photos[0]->getCreatedBy())));
    }

    public function testOpeningAContestEmptiesTheQueueAndIsRefusedWhileOneRuns(): void
    {
        // Arrange
        $photos = $this->photosOfOneMember(2);
        $this->contest->submit($photos[0]);
        $this->contest->submit($photos[1]);
        $queued = $this->inScope(fn(): array => $this->contest->getQueuedIds());
        static::assertCount(2, $queued);

        // Act
        $poll = $this->inScope(fn(): Poll => $this->contest->start(1));

        // Assert
        static::assertInstanceOf(Poll::class, $poll);
        static::assertNull($poll->getEvent(), 'a contest carries no event');
        static::assertSame($queued, $poll->getOptionItemIds(), 'the entrants are on the ballot');
        static::assertSame([], $this->inScope(fn(): array => $this->contest->getQueuedIds()), 'the queue is emptied');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('photos_contest.flash_already_open');
        $this->inScope(fn(): Poll => $this->contest->start(1));
    }

    public function testStartingWithAnEmptyQueueIsRefusedBeforeTheBallotIsBuilt(): void
    {
        // Arrange
        static::assertSame([], $this->inScope(fn(): array => $this->contest->getQueuedIds()));

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('photos_contest.flash_no_entries');
        $this->inScope(fn(): Poll => $this->contest->start(1));
    }

    public function testDeletingASubmittedPhotoLeavesNothingBehind(): void
    {
        // Arrange
        $photos = $this->photosOfOneMember(1);
        $this->contest->submit($photos[0]);
        $photoId = (int) $photos[0]->getId();

        // Act
        self::getContainer()->get(PhotoService::class)->delete($photos[0]);

        // Assert
        static::assertNotContains($photoId, $this->inScope(fn(): array => $this->contest->getQueuedIds()));
        static::assertNull($this->photoRepo->find($photoId));
    }

    /** @return list<Photo> */
    private function photosOfOneMember(int $count): array
    {
        $allowed = $this->inScope(fn(): ?array => self::getContainer()->get(FilterService::class)->getAllowedItemIds(PhotoService::ITEM_TYPE));
        foreach ($this->photoRepo->countByCreator($allowed) as $userId => $total) {
            if ($total < $count) {
                continue;
            }

            $photos = array_slice($this->photoRepo->findByCreator($userId, $allowed), 0, $count);
            $this->em->flush();

            return array_values($photos);
        }

        static::fail('no member owns ' . $count . ' photos');
    }
}
