<?php declare(strict_types=1);

namespace Tests\Functional\Repository;

use App\Entity\ItemCategoryAssignment;
use App\Entity\ItemTagAssignment;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ItemAssignmentCountTest extends KernelTestCase
{
    private const string ITEM_TYPE = 'countprobe';

    private EntityManagerInterface $em;
    private ItemCategoryAssignmentRepository $categoryRepo;
    private ItemTagAssignmentRepository $tagRepo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->categoryRepo = $container->get(ItemCategoryAssignmentRepository::class);
        $this->tagRepo = $container->get(ItemTagAssignmentRepository::class);
    }

    public function testCategoryCountsGroupOnlyTheGivenItems(): void
    {
        // Arrange
        $this->persistCategory(9001, 1);
        $this->persistCategory(9002, 1);
        $this->persistCategory(9003, 2);
        $this->em->flush();

        // Act
        $counts = $this->categoryRepo->countsByCategory(self::ITEM_TYPE, [9001, 9003]);

        // Assert
        static::assertSame([1 => 1, 2 => 1], $counts);
    }

    public function testCategoryCountsOfAnEmptyIdSetAreEmpty(): void
    {
        // Arrange + Act
        $counts = $this->categoryRepo->countsByCategory(self::ITEM_TYPE, []);

        // Assert
        static::assertSame([], $counts);
    }

    public function testTagCountsGroupOnlyTheGivenItems(): void
    {
        // Arrange
        $this->persistTag(9101, 5);
        $this->persistTag(9101, 6);
        $this->persistTag(9102, 5);
        $this->persistTag(9103, 6);
        $this->em->flush();

        // Act
        $counts = $this->tagRepo->countsByTag(self::ITEM_TYPE, [9101, 9102]);

        // Assert
        static::assertSame([5 => 2, 6 => 1], $counts);
    }

    public function testTagCountsOfAnEmptyIdSetAreEmpty(): void
    {
        // Arrange + Act
        $counts = $this->tagRepo->countsByTag(self::ITEM_TYPE, []);

        // Assert
        static::assertSame([], $counts);
    }

    private function persistCategory(int $itemId, int $categoryId): void
    {
        $assignment = new ItemCategoryAssignment();
        $assignment->setItemType(self::ITEM_TYPE);
        $assignment->setItemId($itemId);
        $assignment->setCategoryId($categoryId);
        $this->em->persist($assignment);
    }

    private function persistTag(int $itemId, int $tagId): void
    {
        $assignment = new ItemTagAssignment();
        $assignment->setItemType(self::ITEM_TYPE);
        $assignment->setItemId($itemId);
        $assignment->setTagId($tagId);
        $this->em->persist($assignment);
    }
}
