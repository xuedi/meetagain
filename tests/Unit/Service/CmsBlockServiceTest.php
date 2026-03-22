<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Enum\CmsBlock\CmsBlockType;
use App\Repository\CmsBlockRepository;
use App\Service\Cms\BlockHydrator;
use App\Service\Cms\CmsBlockService;
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

        $emMock->expects($this->once())->method('persist')->with(static::isInstanceOf(CmsBlock::class));
        $emMock->expects($this->once())->method('flush');

        $subject = new CmsBlockService($emMock, $blockRepoStub, new BlockHydrator());
        $page = new Cms();

        $block = $subject->createBlock($page, 'en', CmsBlockType::Headline, ['title' => 'Test']);

        static::assertSame('en', $block->getLanguage());
        static::assertSame(6.0, $block->getPriority());
    }

    public function testUpdateBlockModifiesExistingBlock(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);

        $block = new CmsBlock();
        $block->setType(CmsBlockType::Paragraph);
        $block->setJson(['title' => 'old', 'content' => 'old content']);

        $blockRepoStub->method('find')->willReturn($block);
        $emMock->expects($this->once())->method('persist')->with($block);
        $emMock->expects($this->once())->method('flush');

        $subject = new CmsBlockService($emMock, $blockRepoStub, new BlockHydrator());
        $result = $subject->updateBlock(42, CmsBlockType::Paragraph, ['title' => 'new', 'content' => 'new content']);

        static::assertSame($block, $result);
    }

    public function testUpdateBlockThrowsWhenBlockNotFound(): void
    {
        $emStub = $this->createStub(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);
        $blockRepoStub->method('find')->willReturn(null);

        $subject = new CmsBlockService($emStub, $blockRepoStub);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not load block');

        $subject->updateBlock(999, CmsBlockType::Paragraph, ['title' => '', 'content' => '']);
    }

    public function testDeleteBlockRemovesBlock(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);

        $block = new CmsBlock();
        $blockRepoStub->method('find')->willReturn($block);

        $emMock->expects($this->once())->method('remove')->with($block);
        $emMock->expects($this->once())->method('flush');

        $subject = new CmsBlockService($emMock, $blockRepoStub, new BlockHydrator());
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

        $blockRepoStub->method('find')->willReturn($block);
        $blockRepoStub->method('findBy')->willReturn([$block]);

        $emMock->expects($this->exactly(2))->method('persist');
        $emMock->expects($this->exactly(2))->method('flush');

        $subject = new CmsBlockService($emMock, $blockRepoStub, new BlockHydrator());
        $subject->moveBlockDown(1, 42, 'en');

        static::assertSame(1.0, $block->getPriority());
    }

    public function testUpdateHeroBlockHandlesImageRightCheckbox(): void
    {
        $emMock = $this->createStub(EntityManagerInterface::class);
        $blockRepoStub = $this->createStub(CmsBlockRepository::class);

        $block = new CmsBlock();
        $block->setType(CmsBlockType::Hero);
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

        $subject = new CmsBlockService($emMock, $blockRepoStub, new BlockHydrator());

        // Test when imageRight is missing from payload (unchecked)
        $payload = [
            'headline' => 'new',
            'subHeadline' => 'new',
            'text' => 'new',
            'buttonLink' => 'new',
            'buttonText' => 'new',
            'color' => 'new',
        ];
        $subject->updateBlock(42, CmsBlockType::Hero, $payload);
        static::assertFalse($block->getJson()['imageRight']);

        // Test when imageRight is in payload (checked)
        $payload['imageRight'] = '1';
        $subject->updateBlock(42, CmsBlockType::Hero, $payload);
        static::assertTrue($block->getJson()['imageRight']);
    }
}
