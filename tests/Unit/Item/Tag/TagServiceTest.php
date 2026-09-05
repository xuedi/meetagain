<?php declare(strict_types=1);

namespace App\Tests\Unit\Item\Tag;

use App\Entity\ItemTag;
use App\Entity\ItemTagAssignment;
use App\Item\Tag\TagService;
use App\Item\Tag\TypeRegistry;
use App\Repository\ItemTagAssignmentRepository;
use App\Repository\ItemTagRepository;
use App\Service\Config\LanguageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class TagServiceTest extends TestCase
{
    private const string TYPE = 'photo';

    private ?EntityManagerInterface $em = null;

    private ?ItemTagAssignmentRepository $assignmentRepo = null;

    public function testAManagedRowStaysOutOfEverythingTheEditorOffers(): void
    {
        // Arrange
        $free = $this->tag(1, 'Landscape');
        $managed = $this->tag(2, 'Events', managed: true);
        $service = $this->service([$free, $managed]);

        // Act
        $vocabulary = $service->getVocabulary(self::TYPE);

        // Assert
        static::assertSame([$free, $managed], $vocabulary);
        static::assertSame([$free], $service->getManagedVocabulary(self::TYPE));
        static::assertSame([1 => 'Landscape'], $service->getAssignableChoices(self::TYPE, 'en'));
        static::assertSame([1 => 'Landscape', 2 => 'Events'], $service->getChoices(self::TYPE, 'en'));
    }

    public function testSavingTagsNeitherDropsNorAcceptsAManagedAssignment(): void
    {
        // Arrange
        $free = $this->tag(1, 'Landscape');
        $managed = $this->tag(2, 'Events', managed: true);
        $freeAssignment = $this->assignment($free);
        $assignmentRepo = $this->createStub(ItemTagAssignmentRepository::class);
        $assignmentRepo->method('findFor')->willReturn([$freeAssignment, $this->assignment($managed)]);
        $this->assignmentRepo = $assignmentRepo;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::once())->method('remove')->with($freeAssignment);
        $em->expects(static::never())->method('persist');
        $this->em = $em;

        // Act
        $this->service([$free, $managed])->setTags(self::TYPE, 7, [2]);
    }

    public function testAVocabularySaveThatOmitsAManagedRowLeavesItStanding(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::never())->method('remove');
        $this->em = $em;
        $assignmentRepo = $this->createMock(ItemTagAssignmentRepository::class);
        $assignmentRepo->expects(static::never())->method('deleteForTags');
        $this->assignmentRepo = $assignmentRepo;

        // Act
        $this->service([$this->tag(2, 'Events', managed: true)])->saveVocabulary(self::TYPE, []);
    }

    private function tag(int $id, string $label, bool $managed = false): ItemTag
    {
        $tag = new ItemTag();
        $tag->setItemType(self::TYPE);
        $tag->setLabels(['en' => $label]);
        $tag->setManaged($managed);
        new ReflectionProperty(ItemTag::class, 'id')->setValue($tag, $id);

        return $tag;
    }

    private function assignment(ItemTag $tag): ItemTagAssignment
    {
        return new ItemTagAssignment()->setItemType(self::TYPE)->setItemId(7)->setTag($tag);
    }

    /** @param list<ItemTag> $tags */
    private function service(array $tags): TagService
    {
        $tagRepo = $this->createStub(ItemTagRepository::class);
        $tagRepo->method('findForType')->willReturn($tags);
        $tagRepo->method('descendantIds')->willReturn([]);
        $tagRepo->method('findBy')->willReturn([]);

        $registry = $this->createStub(TypeRegistry::class);
        $registry->method('has')->willReturn(true);

        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getFilteredDefaultLocale')->willReturn('en');

        $em = $this->em ?? $this->createStub(EntityManagerInterface::class);
        $assignmentRepo = $this->assignmentRepo ?? $this->createStub(ItemTagAssignmentRepository::class);

        return new TagService($em, $tagRepo, $assignmentRepo, $registry, $languageService, [], [], [], []);
    }
}
