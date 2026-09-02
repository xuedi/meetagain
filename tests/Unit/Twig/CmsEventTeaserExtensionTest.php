<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\CmsEventTeaserExtension;
use PHPUnit\Framework\TestCase;

class CmsEventTeaserExtensionTest extends TestCase
{
    public function testRegistersCmsUpcomingEventsTwigFunction(): void
    {
        // Arrange
        $subject = new CmsEventTeaserExtension();

        // Act
        $names = array_map(static fn($f) => $f->getName(), $subject->getFunctions());

        // Assert
        static::assertContains('cms_upcoming_events', $names);
    }
}
