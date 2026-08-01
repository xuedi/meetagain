<?php declare(strict_types=1);

namespace Tests\Functional\Item\Tag;

use App\Entity\ItemTag;
use App\Enum\ItemAction;
use App\Item\Tag\TagService;
use App\Item\Tag\TypeRegistry;
use App\Repository\ItemTagAssignmentRepository;
use App\Repository\ItemTagRepository;
use App\Service\Config\LanguageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TagServiceTest extends KernelTestCase
{
    private const string TYPE = 'tagprobe';

    private EntityManagerInterface $em;
    private ItemTagRepository $tagRepo;
    private ItemTagAssignmentRepository $assignmentRepo;
    private TagService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->tagRepo = $container->get(ItemTagRepository::class);
        $this->assignmentRepo = $container->get(ItemTagAssignmentRepository::class);

        $registry = $this->createStub(TypeRegistry::class);
        $registry->method('has')->willReturn(true);

        $this->service = new TagService(
            $this->em,
            $this->tagRepo,
            $this->assignmentRepo,
            $registry,
            $container->get(LanguageService::class),
            [],
            [],
            [],
        );
    }

    public function testTaggingWithASubTagPersistsEveryAncestorAlongsideIt(): void
    {
        // Arrange
        [$root, $branch, $leaf] = $this->branch();

        // Act
        $this->service->setTags(self::TYPE, 5001, [(int) $leaf->getId()]);

        // Assert
        $stored = $this->service->getTagIds(self::TYPE, 5001);
        sort($stored);
        $expected = [(int) $root->getId(), (int) $branch->getId(), (int) $leaf->getId()];
        sort($expected);
        static::assertSame($expected, $stored);
    }

    public function testAnUnknownTagIdIsDroppedOnWrite(): void
    {
        // Arrange
        [$root] = $this->branch();

        // Act
        $this->service->setTags(self::TYPE, 5002, [(int) $root->getId(), 987654]);

        // Assert
        static::assertSame([(int) $root->getId()], $this->service->getTagIds(self::TYPE, 5002));
    }

    public function testBadgesShowTheLeafMostTagsOnly(): void
    {
        // Arrange
        [, , $leaf] = $this->branch();
        $this->service->setTags(self::TYPE, 5003, [(int) $leaf->getId()]);

        // Act
        $labels = $this->service->getLabels(self::TYPE, 5003, 'en');

        // Assert
        static::assertSame(['Chicken'], $labels);
    }

    public function testTheVocabularyIsOrderedDepthFirst(): void
    {
        // Arrange
        $this->branch();
        $other = $this->tag('Fish');

        // Act
        $ordered = array_map(
            static fn(ItemTag $tag): string => $tag->getLabels()['en'],
            $this->service->getVocabulary(self::TYPE),
        );

        // Assert
        static::assertSame(['Meat', 'Poultry', 'Chicken', 'Fish'], $ordered);
        static::assertSame('Fish', $other->getLabels()['en']);
    }

    public function testDeletingATagTakesItsBranchAndItsAssignmentsWithIt(): void
    {
        // Arrange
        [$root, $branch, $leaf] = $this->branch();
        $this->service->setTags(self::TYPE, 5004, [(int) $leaf->getId()]);

        // Act
        $this->service->deleteTag($branch);

        // Assert
        static::assertSame([(int) $root->getId()], $this->service->getTagIds(self::TYPE, 5004));
        static::assertCount(1, $this->tagRepo->findForType(self::TYPE));
    }

    public function testSavingTheEditorRowsCreatesRenamesAndDeletes(): void
    {
        // Arrange
        [$root] = $this->branch();

        // Act
        $this->service->saveVocabulary(self::TYPE, [
            ['id' => (string) $root->getId(), 'parent' => '', 'labels' => ['en' => 'Protein']],
            ['id' => 'n1', 'parent' => (string) $root->getId(), 'labels' => ['en' => 'Tofu']],
        ]);

        // Assert
        $labels = array_map(static fn(ItemTag $tag): string => $tag->getLabels()['en'], $this->service->getVocabulary(self::TYPE));
        static::assertSame(['Protein', 'Tofu'], $labels);
        static::assertSame(2, $this->service->getVocabulary(self::TYPE)[1]->getDepth());
    }

    public function testABlankRowIsNotStored(): void
    {
        // Arrange + Act
        $this->service->saveVocabulary(self::TYPE, [
            ['id' => '', 'parent' => '', 'labels' => ['en' => '  ']],
            ['id' => '', 'parent' => '', 'labels' => ['en' => 'Kept']],
        ]);

        // Assert
        static::assertCount(1, $this->service->getVocabulary(self::TYPE));
    }

    public function testDeletingAnItemSweepsItsAssignments(): void
    {
        // Arrange
        [$root] = $this->branch();
        $this->service->setTags(self::TYPE, 5005, [(int) $root->getId()]);

        // Act
        $this->service->onItemAction(ItemAction::Deleted, self::TYPE, 5005);

        // Assert
        static::assertSame([], $this->service->getTagIds(self::TYPE, 5005));
    }

    public function testDescendantIdsReachEveryLevelBelowATag(): void
    {
        // Arrange
        [$root, $branch, $leaf] = $this->branch();

        // Act
        $descendants = $this->tagRepo->descendantIds($root);
        sort($descendants);

        // Assert
        $expected = [(int) $branch->getId(), (int) $leaf->getId()];
        sort($expected);
        static::assertSame($expected, $descendants);
    }

    /** @return array{ItemTag, ItemTag, ItemTag} */
    private function branch(): array
    {
        $root = $this->tag('Meat');
        $branch = $this->tag('Poultry', $root);
        $leaf = $this->tag('Chicken', $branch);

        return [$root, $branch, $leaf];
    }

    private function tag(string $label, ?ItemTag $parent = null): ItemTag
    {
        $tag = new ItemTag();
        $tag->setItemType(self::TYPE);
        $tag->setLabels(['en' => $label]);
        $tag->setParent($parent);
        $tag->setPosition($this->tagRepo->nextPosition(self::TYPE));
        $this->em->persist($tag);
        $this->em->flush();

        return $tag;
    }
}
