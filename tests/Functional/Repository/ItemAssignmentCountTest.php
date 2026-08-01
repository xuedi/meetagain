<?php declare(strict_types=1);

namespace Tests\Functional\Repository;

use App\Entity\ItemTag;
use App\Entity\ItemTagAssignment;
use App\Repository\ItemTagAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ItemAssignmentCountTest extends KernelTestCase
{
    private const string ITEM_TYPE = 'countprobe';

    private EntityManagerInterface $em;
    private ItemTagAssignmentRepository $tagRepo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->tagRepo = $container->get(ItemTagAssignmentRepository::class);
    }

    public function testTagCountsGroupOnlyTheGivenItems(): void
    {
        // Arrange
        $first = $this->persistTag('First');
        $second = $this->persistTag('Second');
        $this->persistAssignment(9101, $first);
        $this->persistAssignment(9101, $second);
        $this->persistAssignment(9102, $first);
        $this->persistAssignment(9103, $second);
        $this->em->flush();

        // Act
        $counts = $this->tagRepo->countsByTag(self::ITEM_TYPE, [9101, 9102]);

        // Assert
        static::assertSame([(int) $first->getId() => 2, (int) $second->getId() => 1], $counts);
    }

    public function testTagCountsOfAnEmptyIdSetAreEmpty(): void
    {
        // Arrange + Act
        $counts = $this->tagRepo->countsByTag(self::ITEM_TYPE, []);

        // Assert
        static::assertSame([], $counts);
    }

    private function persistTag(string $label): ItemTag
    {
        $tag = new ItemTag();
        $tag->setItemType(self::ITEM_TYPE);
        $tag->setLabels(['en' => $label]);
        $this->em->persist($tag);
        $this->em->flush();

        return $tag;
    }

    private function persistAssignment(int $itemId, ItemTag $tag): void
    {
        $assignment = new ItemTagAssignment();
        $assignment->setItemType(self::ITEM_TYPE);
        $assignment->setItemId($itemId);
        $assignment->setTag($tag);
        $this->em->persist($assignment);
    }
}
