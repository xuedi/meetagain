<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Entity\CmsBlockTypes;
use App\Repository\CmsBlockRepository;
use App\Service\CmsBlockService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CmsBlockServiceTest extends TestCase
{
    public function testCreateBlockPersistsNewBlock(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);
        $blockRepoStub->method('getMaxPriority')->willReturn(5.0);

        $emMock->expects($this->once())->method('persist')->with($this->isInstanceOf(CmsBlock::class));
        $emMock->expects($this->once())->method('flush');

        $subject = new CmsBlockService($emMock, $blockRepoStub);
        $page = new Cms();

        $block = $subject->createBlock($page, 'en', CmsBlockTypes::Headline, ['title' => 'Test']);

        $this->assertSame('en', $block->getLanguage());
        $this->assertSame(6.0, $block->getPriority());
    }

    public function testUpdateBlockModifiesExistingBlock(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);

        $block = new CmsBlock();
        $block->setType(CmsBlockTypes::Paragraph);
        $block->setJson(['title' => 'old', 'content' => 'old content']);

        $blockRepoStub->method('find')->with(42)->willReturn($block);
        $emMock->expects($this->once())->method('persist')->with($block);
        $emMock->expects($this->once())->method('flush');

        $subject = new CmsBlockService($emMock, $blockRepoStub);
        $result = $subject->updateBlock(42, CmsBlockTypes::Paragraph, ['title' => 'new', 'content' => 'new content']);

        $this->assertSame($block, $result);
    }

    public function testUpdateBlockThrowsWhenBlockNotFound(): void
    {
        $emStub = $this->createStub(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);
        $blockRepoStub->method('find')->willReturn(null);

        $subject = new CmsBlockService($emStub, $blockRepoStub);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not load block');

        $subject->updateBlock(999, CmsBlockTypes::Paragraph, ['title' => '', 'content' => '']);
    }

    public function testDeleteBlockRemovesBlock(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);

        $block = new CmsBlock();
        $blockRepoStub->method('find')->with(42)->willReturn($block);

        $emMock->expects($this->once())->method('remove')->with($block);
        $emMock->expects($this->once())->method('flush');

        $subject = new CmsBlockService($emMock, $blockRepoStub);
        $subject->deleteBlock(42);
    }

    public function testDeleteBlockThrowsWhenBlockNotFound(): void
    {
        $emStub = $this->createStub(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);
        $blockRepoStub->method('find')->willReturn(null);

        $subject = new CmsBlockService($emStub, $blockRepoStub);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not load block');

        $subject->deleteBlock(999);
    }

    public function testMoveBlockDownAdjustsPriority(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);

        $block = new CmsBlock();
        $block->setPriority(3);

        $blockRepoStub->method('find')->with(42)->willReturn($block);
        $blockRepoStub->method('findBy')->willReturn([$block]);

        $emMock->expects($this->exactly(2))->method('persist');
        $emMock->expects($this->exactly(2))->method('flush');

        $subject = new CmsBlockService($emMock, $blockRepoStub);
        $subject->moveBlockDown(1, 42, 'en');

        $this->assertEquals(1, $block->getPriority());
    }

    public function testMoveBlockUpAdjustsPriority(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);

        $block = new CmsBlock();
        $block->setPriority(3);

        $blockRepoStub->method('find')->with(42)->willReturn($block);
        $blockRepoStub->method('findBy')->willReturn([$block]);

        $emMock->expects($this->exactly(2))->method('persist');
        $emMock->expects($this->exactly(2))->method('flush');

        $subject = new CmsBlockService($emMock, $blockRepoStub);
        $subject->moveBlockUp(1, 42, 'en');

        $this->assertEquals(1, $block->getPriority());
    }
}
