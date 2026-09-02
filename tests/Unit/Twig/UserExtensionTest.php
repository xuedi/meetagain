<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\UserExtension;
use PHPUnit\Framework\TestCase;

class UserExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsRegisteredTwigFunctions(): void
    {
        // Arrange
        $subject = new UserExtension();

        // Act
        $names = array_map(static fn($f): string => $f->getName(), $subject->getFunctions());

        // Assert
        static::assertContains('get_user_name', $names);
        static::assertContains('get_member_view_actions', $names);
        static::assertContains('get_member_view_sections', $names);
    }
}
