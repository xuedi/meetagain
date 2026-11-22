<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Service\Config\PluginService;
use App\Twig\PluginRuntime;
use App\Twig\ProfileConfigRuntime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class ProfileConfigRuntimeTest extends TestCase
{
    private const array SLUGS = ['/en/profile/config', '/en/profile/config/delete', '/en/profile/access-tokens'];

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function paths(): iterable
    {
        yield 'the entry itself' => ['/en/profile/config', '/en/profile/config'];
        yield 'a deeper entry wins over its parent' => ['/en/profile/config/delete', '/en/profile/config/delete'];
        yield 'a sub-page keeps its entry' => ['/en/profile/access-tokens/12', '/en/profile/access-tokens'];
        yield 'a shared prefix is no match' => ['/en/profile/access-tokens-old', null];
        yield 'an unrelated page' => ['/en/events', null];
    }

    #[DataProvider('paths')]
    public function testTheLongestMatchingEntryIsActive(string $path, ?string $expected): void
    {
        // Arrange
        $subject = new ProfileConfigRuntime(
            new PluginRuntime([], $this->createStub(PluginService::class), $this->createStub(Environment::class), []),
            $this->createStub(UrlGeneratorInterface::class),
            new RequestStack(),
        );

        // Act
        $active = $subject->activeSlug(self::SLUGS, $path);

        // Assert
        static::assertSame($expected, $active);
    }
}
