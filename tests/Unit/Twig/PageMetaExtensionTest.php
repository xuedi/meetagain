<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\PageMetaExtension;
use PHPUnit\Framework\TestCase;

class PageMetaExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsExpectedFunctions(): void
    {
        // Arrange
        $subject = new PageMetaExtension();

        // Act
        $functionNames = array_map(static fn($f) => $f->getName(), $subject->getFunctions());

        // Assert
        static::assertCount(5, $functionNames);
        static::assertContains('get_canonical_url', $functionNames);
        static::assertContains('get_site_name', $functionNames);
        static::assertContains('get_meta_description', $functionNames);
        static::assertContains('get_organization_schema', $functionNames);
        static::assertContains('page_noindex', $functionNames);
    }
}
