<?php declare(strict_types=1);

namespace Tests\Unit\Activity;

use App\Activity\MessageAbstract;
use App\Activity\Messages\AdminMemberStatusChanged;
use App\Activity\Messages\ReportedImage;
use App\Activity\Messages\UpdatedProfilePicture;
use App\Service\Media\ImageHtmlRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class HtmlEscapingTest extends TestCase
{
    private const string HOSTILE = '<img src=x onerror=alert(1)>';

    private const array MESSAGE_DIRECTORIES = [
        'src/Activity/Messages',
        'plugins/*/src/Activity/Messages',
        'modules/*/src/Internal/Activity',
    ];

    private const array NUMERIC_KEYS = ['attempts', 'created', 'granted', 'images', 'members', 'skipped', 'updated'];

    private const array NUMERIC_OVERRIDES = [
        AdminMemberStatusChanged::class => ['old' => 1, 'new' => 2],
        ReportedImage::class => ['reason' => 1],
        UpdatedProfilePicture::class => ['old' => 1, 'new' => 2],
    ];

    /**
     * @return iterable<string, array{class-string<MessageAbstract>}>
     */
    public static function messageProvider(): iterable
    {
        $root = dirname(__DIR__, 3);
        foreach (self::MESSAGE_DIRECTORIES as $pattern) {
            foreach (glob($root . '/' . $pattern . '/*.php') ?: [] as $file) {
                $class = self::classInFile($file);
                if ($class === null || !is_subclass_of($class, MessageAbstract::class)) {
                    continue;
                }
                if (new ReflectionClass($class)->isAbstract()) {
                    continue;
                }

                yield substr($file, strlen($root) + 1) => [$class];
            }
        }
    }

    /**
     * @param class-string<MessageAbstract> $class
     */
    #[DataProvider('messageProvider')]
    public function testHtmlRenderEscapesEveryUserControlledValue(string $class): void
    {
        // Arrange
        $message = new $class();
        $message->injectServices(
            $this->createStub(RouterInterface::class),
            $this->createStub(ImageHtmlRenderer::class),
            $this->interpolatingTranslator(),
            $this->hostileMeta($class),
            [1 => self::HOSTILE],
            [1 => self::HOSTILE],
        );

        // Act
        $html = $message->render(true);

        // Assert
        static::assertStringNotContainsString('<img', $html);
    }

    private function hostileMeta(string $class): array
    {
        $source = (string) file_get_contents((string) new ReflectionClass($class)->getFileName());
        preg_match_all("/meta\\['(\\w+)'\\]/", $source, $matches);

        $meta = [];
        foreach (array_unique($matches[1]) as $key) {
            $isNumeric = str_ends_with($key, '_id') || in_array($key, self::NUMERIC_KEYS, true);
            $meta[$key] = $isNumeric ? 1 : self::HOSTILE;
        }

        return (self::NUMERIC_OVERRIDES[$class] ?? []) + $meta;
    }

    private function interpolatingTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            use TranslatorTrait;

            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return trim($id . ' ' . implode(' ', array_map(strval(...), $parameters)));
            }
        };
    }

    private static function classInFile(string $file): ?string
    {
        $source = (string) file_get_contents($file);
        if (!preg_match('/^namespace\s+([^;]+);/m', $source, $namespace)) {
            return null;
        }
        if (!preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $source, $class)) {
            return null;
        }

        return $namespace[1] . '\\' . $class[1];
    }
}
