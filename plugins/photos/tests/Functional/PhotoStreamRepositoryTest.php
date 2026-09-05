<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Functional;

use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Repository\PhotoRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PhotoStreamRepositoryTest extends KernelTestCase
{
    private PhotoRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repo = self::getContainer()->get(PhotoRepository::class);
    }

    public function testAnEmptyAllowListBlocksEveryStreamQuery(): void
    {
        // Act + Assert
        static::assertSame([], $this->repo->findByCreator($this->busiestAuthor(), []));
        static::assertSame([], $this->repo->countByCreator([]));
    }

    public function testANullAllowListDoesNotRestrictTheStream(): void
    {
        // Arrange
        $author = $this->busiestAuthor();

        // Act
        $unrestricted = $this->repo->findByCreator($author, null);

        // Assert
        static::assertNotSame([], $unrestricted);
        foreach ($unrestricted as $photo) {
            static::assertSame($author, $photo->getCreatedBy());
        }
    }

    public function testTheStreamIsNarrowedToTheAllowedIds(): void
    {
        // Arrange
        $author = $this->busiestAuthor();
        $keep = (int) $this->repo->findByCreator($author, null)[0]->getId();

        // Act
        $narrowed = $this->repo->findByCreator($author, [$keep]);

        // Assert
        static::assertCount(1, $narrowed);
        static::assertSame($keep, (int) $narrowed[0]->getId());
    }

    public function testTheLimitedStreamCarriesTheSameNewestPhotosAsTheFullOne(): void
    {
        // Arrange
        $author = $this->busiestAuthor();
        $full = $this->repo->findByCreator($author, null);

        // Act
        $limited = $this->repo->findByCreator($author, null, 2);

        // Assert
        static::assertCount(2, $limited);
        static::assertSame(
            [(int) $full[0]->getId(), (int) $full[1]->getId()],
            array_map(static fn(Photo $photo): int => (int) $photo->getId(), $limited),
        );
    }

    public function testTheGroupedCountReturnsOneRowPerAuthorMatchingTheirStream(): void
    {
        // Act
        $counts = $this->repo->countByCreator(null);

        // Assert
        static::assertSame(array_unique(array_keys($counts)), array_keys($counts));
        foreach ($counts as $userId => $total) {
            static::assertCount($total, $this->repo->findByCreator($userId, null));
        }
    }

    public function testTheGroupedCountIsOrderedByTheBiggestStreamFirst(): void
    {
        // Act
        $totals = array_values($this->repo->countByCreator(null));

        // Assert
        $sorted = $totals;
        rsort($sorted);
        static::assertSame($sorted, $totals);
    }

    private function busiestAuthor(): int
    {
        $counts = $this->repo->countByCreator(null);
        if ($counts === []) {
            self::fail('The fixtures hold no photo with an uploader');
        }

        return array_key_first($counts);
    }
}
