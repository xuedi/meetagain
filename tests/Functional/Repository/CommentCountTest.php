<?php declare(strict_types=1);

namespace Tests\Functional\Repository;

use App\Entity\Comment;
use App\Repository\CommentRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CommentCountTest extends KernelTestCase
{
    private const string TARGET_TYPE = 'countprobe';

    private EntityManagerInterface $em;
    private CommentRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(CommentRepository::class);
    }

    public function testCountsAreGroupedPerTargetOfOneType(): void
    {
        // Arrange
        $this->persistComment(9101);
        $this->persistComment(9101);
        $this->persistComment(9102);
        $this->persistComment(9103, 'othertype');
        $this->em->flush();

        // Act
        $counts = $this->repo->countPerTargetForType(self::TARGET_TYPE);

        // Assert
        self::assertSame(2, $counts[9101]);
        self::assertSame(1, $counts[9102]);
        self::assertArrayNotHasKey(9103, $counts);
    }

    public function testCountForTargetIgnoresOtherTargetsAndTypes(): void
    {
        // Arrange
        $this->persistComment(9101);
        $this->persistComment(9101);
        $this->persistComment(9102);
        $this->persistComment(9101, 'othertype');
        $this->em->flush();

        // Act + Assert
        self::assertSame(2, $this->repo->countForTarget(self::TARGET_TYPE, 9101));
        self::assertSame(0, $this->repo->countForTarget(self::TARGET_TYPE, 9999));
    }

    private function persistComment(int $targetId, string $targetType = self::TARGET_TYPE): void
    {
        $comment = new Comment();
        $comment->setTargetType($targetType);
        $comment->setTargetId($targetId);
        $comment->setContent('probe');
        $comment->setCreatedAt(new DateTimeImmutable());
        $this->em->persist($comment);
    }
}
