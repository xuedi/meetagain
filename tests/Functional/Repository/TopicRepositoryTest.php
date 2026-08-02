<?php declare(strict_types=1);

namespace Tests\Functional\Repository;

use App\Entity\Topic;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TopicRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TopicRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(TopicRepository::class);
    }

    public function testDescendantIdsWalkTheWholeSubtree(): void
    {
        // Arrange
        $root = $this->persistTopic('Language');
        $branch = $this->persistTopic('learn materials', $root);
        $leaf = $this->persistTopic('podcasts', $branch);
        $sibling = $this->persistTopic('looking for tandem', $root);
        $unrelated = $this->persistTopic('off topic');
        $this->em->flush();

        // Act
        $descendants = $this->repo->descendantIds($root);

        // Assert
        sort($descendants);
        $expected = [(int) $branch->getId(), (int) $leaf->getId(), (int) $sibling->getId()];
        sort($expected);
        self::assertSame($expected, $descendants);
        self::assertNotContains((int) $unrelated->getId(), $descendants);
    }

    public function testDeletingATopicCascadesToItsSubtree(): void
    {
        // Arrange
        $root = $this->persistTopic('Language');
        $branch = $this->persistTopic('learn materials', $root);
        $leafId = (int) $this->persistTopic('podcasts', $branch)->getId();
        $this->em->flush();

        // Act
        $this->em->remove($root);
        $this->em->flush();
        $this->em->clear();

        // Assert
        self::assertNull($this->repo->find($leafId));
    }

    public function testCountChildrenCountsOnlyDirectChildren(): void
    {
        // Arrange
        $root = $this->persistTopic('Language');
        $branch = $this->persistTopic('learn materials', $root);
        $this->persistTopic('looking for tandem', $root);
        $this->persistTopic('podcasts', $branch);
        $this->em->flush();

        // Act + Assert
        self::assertSame(2, $this->repo->countChildren($root));
        self::assertSame(1, $this->repo->countChildren($branch));
    }

    public function testAnEmptyScopeHidesEveryTopic(): void
    {
        // Arrange
        $this->persistTopic('Language');
        $this->em->flush();

        // Act + Assert
        self::assertSame([], $this->repo->findAllInScope([]));
    }

    public function testAScopeNarrowsTheResultToItsAllowList(): void
    {
        // Arrange
        $wanted = $this->persistTopic('Language');
        $this->persistTopic('off topic');
        $this->em->flush();

        // Act
        $topics = $this->repo->findAllInScope([(int) $wanted->getId()]);

        // Assert
        self::assertSame([$wanted], $topics);
    }

    private function persistTopic(string $title, ?Topic $parent = null): Topic
    {
        $topic = new Topic();
        $topic->setTitle($title);
        $topic->setParent($parent);
        $this->em->persist($topic);

        return $topic;
    }
}
