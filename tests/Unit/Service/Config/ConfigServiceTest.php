<?php declare(strict_types=1);

namespace Tests\Unit\Service\Config;

use App\ExtendedFilesystem;
use App\Repository\ConfigRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpKernel\KernelInterface;

class ConfigServiceTest extends TestCase
{
    private const string SCSS_FIXTURE = <<<'SCSS'
        $primary: #abcdef;
        $link: #123456;
        $info: #aabbcc;
        $success: #00ff00;
        $warning: #ffaa00;
        $danger: #ff0000;
        $text-grey: #888888;
        $text-grey-light: #cccccc;
        SCSS;

    public function testGetThemeColorsParsesAllRegisteredKeys(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('getFileContents')->willReturn(self::SCSS_FIXTURE);
        $service = $this->buildService($fs);

        // Act
        $colors = $service->getThemeColors();

        // Assert
        static::assertSame(
            [
                'color_primary' => '#abcdef',
                'color_link' => '#123456',
                'color_info' => '#aabbcc',
                'color_success' => '#00ff00',
                'color_warning' => '#ffaa00',
                'color_danger' => '#ff0000',
                'color_text_grey' => '#888888',
                'color_text_grey_light' => '#cccccc',
            ],
            $colors,
        );
    }

    public function testGetThemeColorsReturnsEmptyArrayWhenScssUnreadable(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('getFileContents')->willReturn(false);
        $service = $this->buildService($fs);

        // Act
        $colors = $service->getThemeColors();

        // Assert
        static::assertSame([], $colors);
    }

    public function testGetThemeColorsSkipsMissingScssVariables(): void
    {
        // Arrange - only $primary is present in this SCSS
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('getFileContents')->willReturn('$primary: #112233;');
        $service = $this->buildService($fs);

        // Act
        $colors = $service->getThemeColors();

        // Assert
        static::assertSame(['color_primary' => '#112233'], $colors);
    }

    /**
     * @param array<string, string> $colors
     */
    #[DataProvider('provideMalformedHexCases')]
    public function testSaveColorsRejectsMalformedHexValues(array $colors): void
    {
        // Arrange
        $written = null;
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('getFileContents')->willReturn(self::SCSS_FIXTURE);
        $fs->method('putFileContents')->willReturnCallback(static function (string $_path, string $data) use (&$written): bool {
            $written = $data;
            return true;
        });
        $service = $this->buildService($fs);

        // Act
        $service->saveColors($colors);

        // Assert - unchanged file written back
        static::assertSame(self::SCSS_FIXTURE, $written);
    }

    public static function provideMalformedHexCases(): iterable
    {
        yield 'missing hash prefix' => [['color_primary' => 'abcdef']];
        yield 'three-char shorthand not accepted' => [['color_primary' => '#abc']];
        yield 'non-hex characters' => [['color_primary' => '#zzzzzz']];
        yield 'too long' => [['color_primary' => '#abcdef00']];
        yield 'empty string' => [['color_primary' => '']];
    }

    public function testSaveColorsWritesSubstitutedContent(): void
    {
        // Arrange
        $written = null;
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('getFileContents')->willReturn(self::SCSS_FIXTURE);
        $fs->method('putFileContents')->willReturnCallback(static function (string $_path, string $data) use (&$written): bool {
            $written = $data;
            return true;
        });
        $service = $this->buildService($fs);

        // Act
        $service->saveColors(['color_primary' => '#ff00ff', 'color_link' => '#00ffff']);

        // Assert
        static::assertNotNull($written);
        static::assertStringContainsString('$primary: #ff00ff;', $written);
        static::assertStringContainsString('$link: #00ffff;', $written);
        // Untouched keys keep their original values
        static::assertStringContainsString('$info: #aabbcc;', $written);
    }

    public function testSaveColorsIsNoOpWhenScssUnreadable(): void
    {
        // Arrange
        $putCalls = 0;
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('getFileContents')->willReturn(false);
        $fs->method('putFileContents')->willReturnCallback(static function () use (&$putCalls): bool {
            $putCalls++;
            return true;
        });
        $service = $this->buildService($fs);

        // Act
        $service->saveColors(['color_primary' => '#ff00ff']);

        // Assert - no write attempted when read failed
        static::assertSame(0, $putCalls);
    }

    private function buildService(ExtendedFilesystem $fs): ConfigService
    {
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/app');

        return new ConfigService(
            repo: $this->createStub(ConfigRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            cache: new ArrayAdapter(),
            kernel: $kernel,
            appState: $this->createStub(AppStateService::class),
            fs: $fs,
        );
    }
}
