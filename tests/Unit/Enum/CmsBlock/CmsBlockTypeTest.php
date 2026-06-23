<?php declare(strict_types=1);

namespace Tests\Unit\Enum\CmsBlock;

use App\Enum\CmsBlock\CmsBlockType;
use PHPUnit\Framework\TestCase;

class CmsBlockTypeTest extends TestCase
{
    public function testTextBlockHasRichText(): void
    {
        // Arrange / Act / Assert
        static::assertTrue(CmsBlockType::Text->hasRichText());
    }

    public function testHeadlineBlockHasNoRichText(): void
    {
        // Arrange / Act / Assert
        static::assertFalse(CmsBlockType::Headline->hasRichText());
    }

    public function testHeroBlockHasNoRichText(): void
    {
        // Arrange / Act / Assert
        static::assertFalse(CmsBlockType::Hero->hasRichText());
    }
}
