<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Item;

use App\Entity\ItemTag;
use App\Item\Tag\TagService;
use App\Review\ChangeProposalService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryListCellProvider;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Plugin\Glossary\ValueObject\Config;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class GlossaryListCellProviderTest extends TestCase
{
    public function testRendersTheCellTemplateWithEntryAndConfig(): void
    {
        // Arrange
        $entry = new Glossary()->setPhrase('你好');
        $config = new Config();

        $twig = $this->createMock(Environment::class);
        $twig
            ->expects(self::once())
            ->method('render')
            ->with('@Glossary/item/list_cell.html.twig', [
                'entry' => $entry,
                'definition' => 'Hello',
                'config' => $config,
                'hasTags' => false,
                'viewMode' => null,
                'tagLabels' => [],
                'hasPendingProposal' => false,
            ])
            ->willReturn('<td>你好</td>');

        $provider = $this->makeProvider($this->serviceReturning($entry), $this->configReturning($config), $twig);

        // Act
        $cell = $provider->renderListCell(7);

        // Assert
        self::assertSame('<td>你好</td>', $cell);
    }

    public function testReturnsNullForAMissingEntry(): void
    {
        // Arrange
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');

        $provider = $this->makeProvider($this->serviceReturning(null), $this->configReturning(new Config()), $twig);

        // Act
        $cell = $provider->renderListCell(404);

        // Assert
        self::assertNull($cell);
    }

    public function testRegistersUnderTheGlossaryItemType(): void
    {
        // Arrange
        $provider = $this->makeProvider($this->serviceReturning(null), $this->configReturning(new Config()), $this->createStub(Environment::class));

        // Act & Assert
        self::assertSame('glossary', $provider->getPluginKey());
        self::assertSame(GlossaryTaggableTypeProvider::ITEM_TYPE, $provider->getKey());
    }

    public function testExposesTheIndexAndDetailRoutes(): void
    {
        // Arrange
        $provider = $this->makeProvider($this->serviceReturning(null), $this->configReturning(new Config()), $this->createStub(Environment::class));

        // Act & Assert
        self::assertSame('app_plugin_glossary', $provider->getListRoute());
        self::assertSame('app_plugin_glossary_show', $provider->getDetailRoute());
    }

    public function testReportsTheCreationDateOfRequestedEntriesOnly(): void
    {
        // Arrange
        $wanted = $this->makeEntry(7, '2026-03-04');
        $other = $this->makeEntry(8, '2026-03-05');

        $service = $this->createStub(GlossaryService::class);
        $service->method('getList')->willReturn([$wanted, $other]);

        $provider = $this->makeProvider($service, $this->configReturning(new Config()), $this->createStub(Environment::class));

        // Act
        $stamps = $provider->getLastmodByItemId([7]);

        // Assert
        self::assertSame([7], array_keys($stamps));
        self::assertSame('2026-03-04', $stamps[7]->format('Y-m-d'));
    }

    public function testLooksUpTagsAndPendingProposalsOnceForTheWholeList(): void
    {
        // Arrange
        $service = $this->createStub(GlossaryService::class);
        $service->method('getList')->willReturn([$this->makeEntry(7, '2026-03-04'), $this->makeEntry(8, '2026-03-05')]);
        $service->method('definitionFor')->willReturn('Hello');

        $tagService = $this->createMock(TagService::class);
        $tagService->method('getVocabulary')->willReturn([new ItemTag()]);
        $tagService
            ->expects(self::once())
            ->method('getLabelsForItems')
            ->with(GlossaryTaggableTypeProvider::ITEM_TYPE, [7, 8], 'de')
            ->willReturn([8 => ['Verb']]);

        $proposals = $this->createMock(ChangeProposalService::class);
        $proposals->expects(self::once())->method('pendingTargetIds')->willReturn([8]);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $rendered = [];
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $template, array $context) use (&$rendered): string {
            $rendered[$context['entry']->getId()] = [$context['tagLabels'], $context['hasPendingProposal']];

            return '';
        });

        $provider = $this->makeProvider($service, $this->configReturning(new Config()), $twig, $tagService, $proposals, $security);

        // Act
        $itemIds = $provider->getItemIds();
        foreach ($itemIds as $itemId) {
            $provider->renderListCell($itemId);
        }

        // Assert
        self::assertSame([8, 7], $itemIds);
        self::assertSame([8 => [['Verb'], true], 7 => [[], false]], $rendered);
    }

    private function makeProvider(
        GlossaryService $service,
        ConfigService $configService,
        Environment $twig,
        ?TagService $tagService = null,
        ?ChangeProposalService $proposals = null,
        ?Security $security = null,
    ): GlossaryListCellProvider {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setLocale('de');
        $requestStack->push($request);

        return new GlossaryListCellProvider(
            $service,
            $configService,
            $tagService ?? $this->createStub(TagService::class),
            $twig,
            $proposals ?? $this->createStub(ChangeProposalService::class),
            $security ?? $this->createStub(Security::class),
            $requestStack,
        );
    }

    private function makeEntry(int $id, string $createdAt): Glossary
    {
        $entry = new Glossary()->setCreatedAt(new DateTimeImmutable($createdAt));
        new ReflectionProperty(Glossary::class, 'id')->setValue($entry, $id);

        return $entry;
    }

    private function serviceReturning(?Glossary $entry): GlossaryService
    {
        $service = $this->createStub(GlossaryService::class);
        $service->method('get')->willReturn($entry);
        $service->method('definitionFor')->willReturn('Hello');

        return $service;
    }

    private function configReturning(Config $config): ConfigService
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfig')->willReturn($config);

        return $configService;
    }
}
