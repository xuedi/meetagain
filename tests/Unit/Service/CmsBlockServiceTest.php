<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\EntityActionDispatcher;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\EntityAction;
use App\Repository\CmsBlockRepository;
use App\Service\Cms\BlockHydrator;
use App\Service\Cms\CmsBlockService;
use App\Service\Cms\RichTextNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class CmsBlockServiceTest extends TestCase
{
    private function makeHydrator(): BlockHydrator
    {
        $sanitizer = new class implements HtmlSanitizerInterface {
            public function sanitize(string $input): string
            {
                return $input;
            }

            public function sanitizeFor(string $context, string $input): string
            {
                return $input;
            }
        };

        return new BlockHydrator($sanitizer, new RichTextNormalizer());
    }

    private function makePage(int $id): Cms
    {
        $page = $this->createStub(Cms::class);
        $page->method('getId')->willReturn($id);

        return $page;
    }

    public function testCreateBlockPersistsNewBlockAndDispatchesAction(): void
    {
        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);
        $dispatcherMock = $this->createMock(EntityActionDispatcher::class);

        $emMock->expects($this->once())->method('persist')->with(static::isInstanceOf(CmsBlock::class));
        $emMock->expects($this->exactly(2))->method('flush');

        $dispatcherMock->expects($this->once())->method('dispatch')->with(EntityAction::UpdateCmsBlock, 1);

        $subject = new CmsBlockService($emMock, $blockRepoStub, $this->makeHydrator(), $dispatcherMock);

        // Act
        $block = $subject->createBlock($this->makePage(1), 'en', CmsBlockType::Headline, ['title' => 'Test']);

        // Assert
        static::assertSame('en', $block->getLanguage());
    }

    public function testUpdateBlockModifiesExistingBlockAndDispatchesAction(): void
    {
        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);
        $dispatcherMock = $this->createMock(EntityActionDispatcher::class);

        $block = new CmsBlock();
        $block->setType(CmsBlockType::Text);
        $block->setJson(['title' => 'old', 'content' => 'old content']);
        $block->setPage($this->makePage(17));

        $blockRepoStub->method('find')->willReturn($block);
        $emMock->expects($this->once())->method('persist')->with($block);
        $emMock->expects($this->once())->method('flush');

        $dispatcherMock->expects($this->once())->method('dispatch')->with(EntityAction::UpdateCmsBlock, 17);

        $subject = new CmsBlockService($emMock, $blockRepoStub, $this->makeHydrator(), $dispatcherMock);

        // Act
        $result = $subject->updateBlock($block, CmsBlockType::Text, ['title' => 'new', 'content' => 'new content']);

        // Assert
        static::assertSame($block, $result);
    }

    public function testUpdateBlockThrowsWhenBlockHasNoPage(): void
    {
        // Arrange
        $emStub = $this->createStub(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);
        $dispatcherMock = $this->createMock(EntityActionDispatcher::class);
        $dispatcherMock->expects($this->never())->method('dispatch');

        $block = new CmsBlock();
        $block->setType(CmsBlockType::Text);
        $block->setJson(['title' => 'old', 'content' => 'old content']);

        $subject = new CmsBlockService($emStub, $blockRepoStub, $this->makeHydrator(), $dispatcherMock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Could not load the page owning this block');

        // Act
        $subject->updateBlock($block, CmsBlockType::Text, ['title' => '', 'content' => '']);
    }

    public function testDeleteBlockRemovesBlockAndDispatchesAction(): void
    {
        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);
        $dispatcherMock = $this->createMock(EntityActionDispatcher::class);

        $block = new CmsBlock();
        $block->setPage($this->makePage(23));
        $blockRepoStub->method('find')->willReturn($block);

        $emMock->expects($this->once())->method('remove')->with($block);
        $emMock->expects($this->once())->method('flush');

        $dispatcherMock->expects($this->once())->method('dispatch')->with(EntityAction::UpdateCmsBlock, 23);

        $subject = new CmsBlockService($emMock, $blockRepoStub, $this->makeHydrator(), $dispatcherMock);

        // Act
        $subject->deleteBlock($block);
    }

    public function testDeleteBlockThrowsWhenBlockHasNoPage(): void
    {
        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);
        $dispatcherMock = $this->createMock(EntityActionDispatcher::class);
        $emMock->expects($this->never())->method('remove');
        $dispatcherMock->expects($this->never())->method('dispatch');

        $subject = new CmsBlockService($emMock, $blockRepoStub, $this->makeHydrator(), $dispatcherMock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Could not load the page owning this block');

        // Act
        $subject->deleteBlock(new CmsBlock());
    }

    public function testMoveBlockDownAdjustsPriorityAndDispatchesAction(): void
    {
        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);
        $dispatcherMock = $this->createMock(EntityActionDispatcher::class);

        $block = new CmsBlock();
        $block->setPriority(3);
        $block->setLanguage('en');
        $block->setPage($this->makePage(1));

        $blockRepoStub->method('findBy')->willReturn([$block]);

        $emMock->expects($this->exactly(2))->method('persist');
        $emMock->expects($this->exactly(2))->method('flush');

        $dispatcherMock->expects($this->once())->method('dispatch')->with(EntityAction::UpdateCmsBlock, 1);

        $subject = new CmsBlockService($emMock, $blockRepoStub, $this->makeHydrator(), $dispatcherMock);

        // Act
        $subject->moveBlockDown($block);

        // Assert
        static::assertSame(1.0, $block->getPriority());
    }

    public function testUpdateHeroBlockHandlesImageRightCheckbox(): void
    {
        // Arrange
        $emMock = $this->createStub(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);
        $dispatcherStub = $this->createStub(EntityActionDispatcher::class);

        $block = new CmsBlock();
        $block->setType(CmsBlockType::Hero);
        $block->setPage($this->makePage(5));
        $block->setJson([
            'headline' => 'old',
            'subHeadline' => 'old',
            'text' => 'old',
            'buttonLink' => 'old',
            'buttonText' => 'old',
            'color' => 'old',
            'imageRight' => true,
        ]);

        $blockRepoStub->method('find')->willReturn($block);

        $subject = new CmsBlockService($emMock, $blockRepoStub, $this->makeHydrator(), $dispatcherStub);

        // Act + Assert
        $payload = [
            'headline' => 'new',
            'subHeadline' => 'new',
            'text' => 'new',
            'buttonLink' => 'new',
            'buttonText' => 'new',
            'color' => 'new',
        ];
        $subject->updateBlock($block, CmsBlockType::Hero, $payload);
        static::assertFalse($block->getJson()['imageRight']);

        // Act + Assert
        $payload['imageRight'] = '1';
        $subject->updateBlock($block, CmsBlockType::Hero, $payload);
        static::assertTrue($block->getJson()['imageRight']);
    }
}
