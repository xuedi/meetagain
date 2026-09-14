<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Entity\CmsLinkName;
use App\Entity\CmsMenuLocation;
use App\Entity\CmsTitle;
use App\Entity\Image;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\MenuLocation;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\CmsPagesSection;

final class CmsPagesSectionTest extends SectionTestCase
{
    public function testAPageSurvivesTheRoundTrip(): void
    {
        // Arrange
        $exported = new CmsPagesSection($this->entityManager([$this->sourcePage()]))->export(new Scope(cmsIds: [1]), $this->images());
        $blockImage = new Image();
        $context = $this->context($blockImage);

        // Act
        new CmsPagesSection($this->entityManager())->import($exported, $context);

        // Assert
        $page = $this->onlyPersisted(Cms::class);
        static::assertSame('about', $page->getSlug());
        static::assertTrue($page->isPublished());
        static::assertSame('About us', $page->getTitles()->first()->getTitle());
        static::assertSame('About', $page->getLinkNames()->first()->getName());
        static::assertSame(MenuLocation::cases()[0], $page->getMenuLocations()->first()->getLocation());

        $block = $this->onlyPersisted(CmsBlock::class);
        static::assertSame('en', $block->getLanguage());
        static::assertSame(CmsBlockType::cases()[0], $block->getType());
        static::assertSame(2.0, $block->getPriority());
        static::assertSame(['title' => 'Welcome'], $block->getJson());
        static::assertSame($page, $block->getPage());
        static::assertSame($blockImage, $block->getImage());
        static::assertSame($page, $context->resolveRef(Cms::class, 1));
        static::assertSame(1, $context->toSummary()->get('cms_pages', Outcome::Created));
    }

    public function testAPageWhoseSlugExistsIsSkippedAndItsRefPointsAtTheExistingPage(): void
    {
        // Arrange
        $context = $this->context();
        $existing = new Cms();

        // Act
        new CmsPagesSection($this->entityManager([], $existing))->import([['ref' => 3, 'slug' => 'about', 'blocks' => [['type' => 1]]]], $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame($existing, $context->resolveRef(Cms::class, 3));
        static::assertSame(1, $context->toSummary()->get('cms_pages', Outcome::Skipped));
    }

    private function sourcePage(): Cms
    {
        $title = new CmsTitle();
        $title->setLanguage('en');
        $title->setTitle('About us');

        $linkName = new CmsLinkName();
        $linkName->setLanguage('en');
        $linkName->setName('About');

        $menuLocation = new CmsMenuLocation();
        $menuLocation->setLocation(MenuLocation::cases()[0]);

        $block = $this->withId(new CmsBlock(), 4);
        $block->setLanguage('en');
        $block->setType(CmsBlockType::cases()[0]);
        $block->setPriority(2.0);
        $block->setJson(['title' => 'Welcome']);
        $block->setImage(new Image());

        $page = $this->withId(new Cms(), 1);
        $page->setSlug('about');
        $page->setPublished(true);
        $page->addTitle($title);
        $page->addLinkName($linkName);
        $page->addMenuLocation($menuLocation);
        $page->addBlock($block);

        return $page;
    }
}
