<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Item;

use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryCategorizableTypeProvider;
use Plugin\Glossary\Item\GlossaryListCellProvider;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Plugin\Glossary\ValueObject\Config;
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
            ->with('@Glossary/item/list_cell.html.twig', ['entry' => $entry, 'config' => $config])
            ->willReturn('<td>你好</td>');

        $provider = new GlossaryListCellProvider($this->serviceReturning($entry), $this->configReturning($config), $twig);

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

        $provider = new GlossaryListCellProvider($this->serviceReturning(null), $this->configReturning(new Config()), $twig);

        // Act
        $cell = $provider->renderListCell(404);

        // Assert
        self::assertNull($cell);
    }

    public function testRegistersUnderTheGlossaryItemType(): void
    {
        // Arrange
        $provider = new GlossaryListCellProvider(
            $this->serviceReturning(null),
            $this->configReturning(new Config()),
            $this->createStub(Environment::class),
        );

        // Act & Assert
        self::assertSame('glossary', $provider->getPluginKey());
        self::assertSame(GlossaryCategorizableTypeProvider::ITEM_TYPE, $provider->getKey());
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
