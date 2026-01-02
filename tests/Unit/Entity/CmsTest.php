<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use PHPUnit\Framework\TestCase;

class CmsTest extends TestCase
{
    public function testGetLanguagesReturnsUniqueLanguages(): void
    {
        $cms = new Cms();

        $block1 = new CmsBlock();
        $block1->setLanguage('en');
        $cms->addBlock($block1);

        $block2 = new CmsBlock();
        $block2->setLanguage('de');
        $cms->addBlock($block2);

        $block3 = new CmsBlock();
        $block3->setLanguage('en');
        $cms->addBlock($block3);

        $languages = $cms->getLanguages();

        $this->assertCount(2, $languages);
        $this->assertContains('en', $languages);
        $this->assertContains('de', $languages);
    }

    public function testGetLanguagesReturnsEmptyArrayWhenNoBlocks(): void
    {
        $cms = new Cms();
        $this->assertSame([], $cms->getLanguages());
    }
}
