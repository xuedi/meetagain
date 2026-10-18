<?php declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

use App\DependencyInjection\ActivePluginMembers;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;
use stdClass;

class ActivePluginMembersTest extends TestCase
{
    public function testAMemberOfASwitchedOffPluginIsLeftOutAndCoreMembersStay(): void
    {
        // Arrange
        $core = new stdClass();
        $books = new stdClass();
        $films = new stdClass();
        $plugins = $this->createStub(PluginService::class);
        $plugins->method('getGloballyActiveList')->willReturn(['books', PluginService::NAVIGATION_KEY]);
        $services = new ActivePluginMembers(
            ['core' => $core, 'books' => $books, 'films' => $films],
            ['books' => 'books', 'films' => 'films'],
            static fn(): PluginService => $plugins,
        );

        // Act
        $kept = iterator_to_array($services);

        // Assert
        static::assertSame(['core' => $core, 'books' => $books], $kept);
        static::assertCount(2, $services);
    }
}
