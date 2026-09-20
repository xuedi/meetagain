<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Service;

use App\EntityActionDispatcher;
use App\Enum\ItemAction;
use App\Item\ActionDispatcher;
use App\Item\AdminFilterService;
use App\Item\FilterService;
use App\Item\Tag\TagService;
use App\Review\ChangeProposalService;
use App\Service\Config\LanguageService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Repository\GlossaryRepository;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Plugin\Glossary\ValueObject\Config;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class GlossaryServiceTest extends TestCase
{
    public function testCreateStampsTheOwnerTheMomentAndTheDefaultTermLanguage(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $service = $this->makeService($em, $this->createStub(GlossaryRepository::class), config: new Config()->setTermLanguage('zh'));
        $glossary = new Glossary()
            ->setPhrase('你好')
            ->submitDefinitions(['en' => 'Hello', 'de' => '']);

        // Act
        $service->create($glossary, userId: 9);

        // Assert
        self::assertSame(9, $glossary->getCreatedBy());
        self::assertNotNull($glossary->getCreatedAt());
        self::assertSame('zh', $glossary->getTermLanguage());
        self::assertSame(['en' => 'Hello'], $glossary->getDefinitionMap());
    }

    public function testEveryReadPathGoesThroughTheItemFilterAlone(): void
    {
        // Arrange
        $repo = $this->createMock(GlossaryRepository::class);
        $repo->expects(self::once())->method('findOneAllowed')->with(4, null)->willReturn(null);
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $entry = $service->get(4);

        // Assert
        self::assertNull($entry);
    }

    public function testDeleteRemovesEntryAndItsPendingProposals(): void
    {
        // Arrange
        $item = new Glossary();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($item);
        $em->expects(self::once())->method('flush');
        $proposals = $this->createMock(ChangeProposalService::class);
        $proposals->expects(self::once())->method('removeForTarget')->with(GlossaryTaggableTypeProvider::ITEM_TYPE, 1);
        $service = $this->makeService($em, $this->repoReturning($item), changeProposalService: $proposals);

        // Act
        $service->delete(1);
    }

    public function testApplyChangeWritesScalarField(): void
    {
        // Arrange
        $item = new Glossary()->setPhrase('old');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($item);
        $em->expects(self::once())->method('flush');
        $service = $this->makeService($em, $this->repoReturning($item));

        // Act
        $service->applyChange(1, GlossaryChangeTarget::FIELD_PHRASE, 'brandnew');

        // Assert
        self::assertSame('brandnew', $item->getPhrase());
    }

    public function testApplyChangeEmptySecondaryClearsTheField(): void
    {
        // Arrange
        $item = new Glossary()->setSecondary('lǎo');
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->repoReturning($item));

        // Act
        $service->applyChange(1, GlossaryChangeTarget::FIELD_SECONDARY, '');

        // Assert
        self::assertNull($item->getSecondary());
    }

    public function testApplyChangeWritesOneDefinitionAndLeavesTheOthers(): void
    {
        // Arrange
        $item = new Glossary()
            ->setDefinition('en', 'Hello')
            ->setDefinition('de', 'Hallo');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $service = $this->makeService($em, $this->repoReturning($item));

        // Act
        $service->applyChange(1, GlossaryChangeTarget::DEFINITION_PREFIX . 'de', 'Guten Tag');

        // Assert
        self::assertSame(['en' => 'Hello', 'de' => 'Guten Tag'], $item->getDefinitionMap());
    }

    public function testApplyChangeWithAnEmptyDefinitionRemovesThatLanguage(): void
    {
        // Arrange
        $item = new Glossary()
            ->setDefinition('en', 'Hello')
            ->setDefinition('de', 'Hallo');
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->repoReturning($item));

        // Act
        $service->applyChange(1, GlossaryChangeTarget::DEFINITION_PREFIX . 'de', null);

        // Assert
        self::assertSame(['en' => 'Hello'], $item->getDefinitionMap());
    }

    #[DataProvider('definitionFieldCases')]
    public function testDefinitionLanguageIsReadFromTheFieldName(string $field, ?string $expected): void
    {
        // Arrange
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->createStub(GlossaryRepository::class));

        // Act
        $language = $service->definitionLanguageOf($field);

        // Assert
        self::assertSame($expected, $language);
    }

    public static function definitionFieldCases(): iterable
    {
        yield 'a two-letter code is a language' => ['definition_en', 'en'];
        yield 'another code works the same' => ['definition_zh', 'zh'];
        yield 'a bare prefix is no language' => ['definition_', null];
        yield 'a three-letter code is refused' => ['definition_eng', null];
        yield 'upper case is refused' => ['definition_EN', null];
        yield 'a scalar field is no definition' => ['phrase', null];
    }

    public function testApplyChangeRoutesEveryProposedTagThroughTheTagService(): void
    {
        // Arrange
        $tagService = $this->createMock(TagService::class);
        $tagService->expects(self::once())->method('setTags')->with(GlossaryTaggableTypeProvider::ITEM_TYPE, 1, [3, 7]);
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->repoReturning(new Glossary()), tagService: $tagService);

        // Act
        $service->applyChange(1, GlossaryChangeTarget::FIELD_TAG, '3,7');
    }

    public function testTagIdsRoundTripThroughTheProposalValue(): void
    {
        // Arrange
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->repoReturning(new Glossary()));

        // Act & Assert
        self::assertSame('3,7', $service->encodeTagIds([7, 3]));
        self::assertNull($service->encodeTagIds([]));
        self::assertSame([3, 7], $service->decodeTagIds('3,7'));
        self::assertSame([], $service->decodeTagIds(null));
        self::assertSame([], $service->decodeTagIds(''));
    }

    public function testApplyChangeRejectsUnknownField(): void
    {
        // Arrange
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->repoReturning(new Glossary()));

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $service->applyChange(1, 'unknown', 'value');
    }

    public function testApplyChangeThrowsWhenEntryIsGone(): void
    {
        // Arrange
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->repoReturning(null));

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $service->applyChange(1, GlossaryChangeTarget::FIELD_PHRASE, 'value');
    }

    public function testUpdateCopiesTheDraftOntoTheManagedEntry(): void
    {
        // Arrange
        $managed = new Glossary()
            ->setPhrase('old')
            ->setDefinition('en', 'old')
            ->setDefinition('de', 'alt');
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->repoReturning($managed));
        $draft = new Glossary()
            ->setPhrase('new')
            ->setSecondary('xīn')
            ->submitDefinitions(['en' => 'new', 'de' => '']);

        // Act
        $service->update($draft, 1, []);

        // Assert
        self::assertSame('new', $managed->getPhrase());
        self::assertSame('xīn', $managed->getSecondary());
        self::assertSame(['en' => 'new'], $managed->getDefinitionMap());
    }

    public function testADraftCopiesTheFieldsWithoutSharingDefinitionRows(): void
    {
        // Arrange
        $entry = new Glossary()
            ->setPhrase('你好')
            ->setSecondary('nǐ hǎo')
            ->setTermLanguage('zh')
            ->setDefinition('en', 'Hello');
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->createStub(GlossaryRepository::class));

        // Act
        $draft = $service->draftOf($entry);

        // Assert
        self::assertSame('你好', $draft->getPhrase());
        self::assertSame('nǐ hǎo', $draft->getSecondary());
        self::assertSame('zh', $draft->getTermLanguage());
        self::assertSame(['en' => 'Hello'], $draft->getSubmittedDefinitions());
        self::assertCount(0, $draft->getDefinitions());
    }

    public function testTheDuplicateRuleIgnoresCaseAndSpacing(): void
    {
        // Arrange
        $repo = $this->createStub(GlossaryRepository::class);
        $repo->method('findAllowed')->willReturn([new Glossary()->setPhrase('La sobremesa')]);
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $repo);

        // Act & Assert
        self::assertTrue($service->isDuplicatePhrase(' la sobremesa '));
        self::assertTrue($service->isDuplicatePhrase('LA   Sobremesa'));
        self::assertFalse($service->isDuplicatePhrase('la sobremesas'));
    }

    public function testDefinitionFallsBackToTheSourceLocaleWhenTheRequestedOneIsMissing(): void
    {
        // Arrange
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setLocale('fr');
        $requestStack->push($request);
        $service = $this->makeService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(GlossaryRepository::class),
            requestStack: $requestStack,
        );
        $entry = new Glossary()
            ->setDefinition('de', 'Hallo')
            ->setDefinition('en', 'Hello');

        // Act
        $definition = $service->definitionFor($entry);

        // Assert
        self::assertSame('Hello', $definition);
    }

    public function testImportFlushesOncePerChunkAndAnnouncesEveryEntry(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('flush');
        $dispatcher = $this->createMock(ActionDispatcher::class);
        $dispatcher->expects(self::exactly(201))->method('dispatch')->with(ItemAction::Created, GlossaryTaggableTypeProvider::ITEM_TYPE, self::anything());
        $service = $this->makeService($em, $this->createStub(GlossaryRepository::class), itemActionDispatcher: $dispatcher);
        $rows = [];
        for ($i = 0; $i < 201; ++$i) {
            $rows[] = [
                new Glossary()
                    ->setPhrase('word ' . $i)
                    ->setDefinition('en', 'meaning'),
                [],
            ];
        }

        // Act
        $service->import($rows, userId: 3);

        // Assert
        self::assertSame(3, $rows[200][0]->getCreatedBy());
    }

    public function testListNarrowsThroughTheCoreItemFilterChain(): void
    {
        // Arrange
        $filter = $this->createMock(FilterService::class);
        $filter->expects(self::once())->method('getAllowedItemIds')->with(GlossaryTaggableTypeProvider::ITEM_TYPE)->willReturn([4, 7]);

        $entry = new Glossary()->setPhrase('你好');
        $repo = $this->createMock(GlossaryRepository::class);
        $repo->expects(self::once())->method('findAllowed')->with([4, 7])->willReturn([$entry]);

        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $repo, $filter);

        // Act
        $list = $service->getList();

        // Assert
        self::assertSame([$entry], $list);
    }

    public function testCreateAnnouncesTheNewItemToTheItemActionChain(): void
    {
        // Arrange
        $dispatcher = $this->createMock(ActionDispatcher::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(ItemAction::Created, GlossaryTaggableTypeProvider::ITEM_TYPE, 0);

        $service = $this->makeService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(GlossaryRepository::class),
            itemActionDispatcher: $dispatcher,
        );

        // Act
        $service->create(new Glossary()->setPhrase('你好'), userId: 9);
    }

    private function makeService(
        EntityManagerInterface $em,
        GlossaryRepository $repo,
        ?FilterService $filter = null,
        ?AdminFilterService $adminFilter = null,
        ?ActionDispatcher $itemActionDispatcher = null,
        ?TagService $tagService = null,
        ?ChangeProposalService $changeProposalService = null,
        ?Config $config = null,
        ?RequestStack $requestStack = null,
    ): GlossaryService {
        if ($filter === null) {
            $filter = $this->createStub(FilterService::class);
            $filter->method('getAllowedItemIds')->willReturn(null);
        }

        if ($adminFilter === null) {
            $adminFilter = $this->createStub(AdminFilterService::class);
            $adminFilter->method('getAllowedItemIds')->willReturn(null);
        }

        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfig')->willReturn($config ?? new Config());

        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getFilteredDefaultLocale')->willReturn('en');

        return new GlossaryService(
            $em,
            $repo,
            $filter,
            $adminFilter,
            $this->createStub(EntityActionDispatcher::class),
            $tagService ?? $this->createStub(TagService::class),
            $itemActionDispatcher ?? $this->createStub(ActionDispatcher::class),
            $changeProposalService ?? $this->createStub(ChangeProposalService::class),
            $configService,
            $languageService,
            $requestStack ?? new RequestStack(),
        );
    }

    private function repoReturning(?Glossary $item): GlossaryRepository
    {
        $repo = $this->createStub(GlossaryRepository::class);
        $repo->method('findOneAllowed')->willReturn($item);

        return $repo;
    }
}
