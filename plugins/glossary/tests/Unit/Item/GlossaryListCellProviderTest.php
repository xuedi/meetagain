<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Item;

use App\Review\ChangeProposalService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use App\Item\Tag\TagService;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Item\GlossaryListCellProvider;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Plugin\Glossary\ValueObject\Config;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Environment;

class GlossaryListCellProviderTest extends TestCase
{
    public function testRendersTheCellTemplateWithEntryAndConfig(): void
    {
        // Arrange
        $entry = (new Glossary())->setPhrase('你好');
        $config = new Config();

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('@Glossary/item/list_cell.html.twig', ['entry' => $entry, 'config' => $config, 'hasTags' => false, 'viewMode' => null])
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
        $provider = $this->makeProvider(
            $this->serviceReturning(null),
            $this->configReturning(new Config()),
            $this->createStub(Environment::class),
        );

        // Act & Assert
        self::assertSame('glossary', $provider->getPluginKey());
        self::assertSame(GlossaryTaggableTypeProvider::ITEM_TYPE, $provider->getKey());
    }

    public function testExposesTheIndexAndDetailRoutes(): void
    {
        // Arrange
        $provider = $this->makeProvider(
            $this->serviceReturning(null),
            $this->configReturning(new Config()),
            $this->createStub(Environment::class),
        );

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

    private function makeProvider(
        GlossaryService $service,
        ConfigService $configService,
        Environment $twig,
    ): GlossaryListCellProvider {
        return new GlossaryListCellProvider(
            $service,
            $configService,
            $this->createStub(TagService::class),
            $twig,
            $this->createStub(ChangeProposalService::class),
            $this->createStub(Security::class),
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

        return $service;
    }

    private function configReturning(Config $config): ConfigService
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfig')->willReturn($config);

        return $configService;
    }
}
