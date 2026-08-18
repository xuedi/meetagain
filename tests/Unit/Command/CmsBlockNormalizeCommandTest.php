<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\CmsBlockNormalizeCommand;
use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\EntityActionDispatcher;
use App\Enum\CmsBlock\CmsBlockType;
use App\Repository\CmsBlockRepository;
use App\Service\Cms\RichTextNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Tester\CommandTester;

class CmsBlockNormalizeCommandTest extends TestCase
{
    private const string FLATTENED = '<p>MeetAgain UG</p><p>Urbanstrasse 96</p><p></p><p>Second</p>';
    private const string NORMALIZED = '<p>MeetAgain UG<br>Urbanstrasse 96</p><p>Second</p>';

    public function testCommandHasCorrectName(): void
    {
        // Act
        $command = $this->buildCommand([]);

        // Assert
        static::assertSame('app:cms:block-normalize', $command->getName());
    }

    public function testFlattenedBlockIsRewrittenAndItsPageInvalidated(): void
    {
        // Arrange
        $block = $this->makeBlock(self::FLATTENED);
        $dispatched = [];
        $flushed = 0;
        $tester = new CommandTester($this->buildCommand([$block], $dispatched, $flushed));

        // Act
        $tester->execute([]);

        // Assert
        static::assertSame(self::NORMALIZED, $block->getJson()['content']);
        static::assertSame([7], $dispatched, 'the block page is invalidated so cached HTML is dropped');
        static::assertSame(1, $flushed);
        static::assertStringContainsString('Normalized 1 of 1 blocks', $tester->getDisplay());
    }

    public function testAlreadyNormalizedBlockIsLeftUntouched(): void
    {
        // Arrange
        $block = $this->makeBlock(self::NORMALIZED);
        $dispatched = [];
        $flushed = 0;
        $tester = new CommandTester($this->buildCommand([$block], $dispatched, $flushed));

        // Act
        $tester->execute([]);

        // Assert
        static::assertSame(self::NORMALIZED, $block->getJson()['content']);
        static::assertSame([], $dispatched);
        static::assertSame(0, $flushed, 'a no-op run must not write');
        static::assertStringContainsString('all already normalized', $tester->getDisplay());
    }

    public function testAdjacentSingleLineParagraphsAreLeftAloneOnceTheMarkersAreGone(): void
    {
        // Arrange
        $block = $this->makeBlock('<p>A real paragraph.</p><p>A second real paragraph.</p>');
        $dispatched = [];
        $flushed = 0;
        $tester = new CommandTester($this->buildCommand([$block], $dispatched, $flushed));

        // Act
        $tester->execute([]);

        // Assert
        static::assertSame(
            '<p>A real paragraph.</p><p>A second real paragraph.</p>',
            $block->getJson()['content'],
            'a re-run must not merge paragraphs an earlier run already separated',
        );
        static::assertSame(0, $flushed);
    }

    public function testRepairIsStableWhenTheCommandRunsTwice(): void
    {
        // Arrange
        $block = $this->makeBlock(self::FLATTENED);
        $dispatched = [];
        $flushed = 0;
        $tester = new CommandTester($this->buildCommand([$block], $dispatched, $flushed));

        // Act
        $tester->execute([]);
        $afterFirstRun = $block->getJson()['content'];
        $tester->execute([]);

        // Assert
        static::assertSame(self::NORMALIZED, $afterFirstRun);
        static::assertSame($afterFirstRun, $block->getJson()['content']);
        static::assertSame(1, $flushed, 'the second run finds nothing to write');
    }

    public function testDryRunReportsWithoutWriting(): void
    {
        // Arrange
        $block = $this->makeBlock(self::FLATTENED);
        $dispatched = [];
        $flushed = 0;
        $tester = new CommandTester($this->buildCommand([$block], $dispatched, $flushed));

        // Act
        $tester->execute(['--dry-run' => true]);

        // Assert
        static::assertSame(0, $flushed, 'dry run never flushes');
        static::assertSame([], $dispatched);
        static::assertStringContainsString('1 of 1 blocks would change', $tester->getDisplay());
    }

    private function makeBlock(string $content): CmsBlock
    {
        $page = new Cms();
        $reflection = new ReflectionProperty(Cms::class, 'id');
        $reflection->setValue($page, 7);

        $block = new CmsBlock();
        $block->setPage($page);
        $block->setLanguage('en');
        $block->setType(CmsBlockType::Text);
        $block->setJson(['title' => 'T', 'content' => $content, 'imageRight' => false]);

        return $block;
    }

    /**
     * @param array<CmsBlock> $blocks
     * @param array<int> $dispatched
     */
    private function buildCommand(array $blocks, array &$dispatched = [], int &$flushed = 0): CmsBlockNormalizeCommand
    {
        $repository = $this->createStub(CmsBlockRepository::class);
        $repository->method('findAll')->willReturn($blocks);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willReturnCallback(static function () use (&$flushed): void {
            $flushed++;
        });

        $dispatcher = $this->createStub(EntityActionDispatcher::class);
        $dispatcher->method('dispatch')->willReturnCallback(
            static function ($action, int $entityId) use (&$dispatched): void {
                $dispatched[] = $entityId;
            },
        );

        return new CmsBlockNormalizeCommand($repository, new RichTextNormalizer(), $em, $dispatcher);
    }
}
