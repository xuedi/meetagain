<?php declare(strict_types=1);

namespace Tests\Unit\Service\Config;

use App\Entity\Language;
use App\Service\Config\LanguageTileService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class LanguageTileServiceTest extends TestCase
{
    private const array CATALOGUE = [
        'language_tile.greeting_de' => 'Willkommen',
        'language_tile.intro_de' => 'Eine Community-Plattform',
        'language_tile.cta_continue_de' => 'Auf Deutsch fortfahren',
        'language_tile.tile_alt_de' => 'Auf Deutsch fortfahren (%language%)',
    ];

    /**
     * @param array{greeting: ?string, intro: ?string, cta: ?string, alt: ?string} $stored
     * @param array{greeting: string, intro: string, cta: string, alt: string}     $expected
     */
    #[DataProvider('provideTileTextCases')]
    public function testGetTextResolvesEverySlot(string $code, string $name, array $stored, array $expected): void
    {
        // Arrange
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn(string $id, array $parameters = []): string => strtr(self::CATALOGUE[$id] ?? $id, $parameters),
        );
        $language = (new Language())
            ->setCode($code)
            ->setName($name)
            ->setTileGreeting($stored['greeting'])
            ->setTileIntro($stored['intro'])
            ->setTileCta($stored['cta'])
            ->setTileImageAlt($stored['alt']);

        // Act
        $text = (new LanguageTileService($translator))->getText($language);

        // Assert
        static::assertSame($expected, $text);
    }

    public static function provideTileTextCases(): iterable
    {
        yield 'stored text wins over the catalogue' => [
            'de',
            'German',
            ['greeting' => 'Servus', 'intro' => 'Kurz und knapp', 'cta' => 'Weiter', 'alt' => 'Kachelbild'],
            ['greeting' => 'Servus', 'intro' => 'Kurz und knapp', 'cta' => 'Weiter', 'alt' => 'Kachelbild'],
        ];

        yield 'blank stored text falls back to the catalogue' => [
            'de',
            'German',
            ['greeting' => null, 'intro' => '   ', 'cta' => '', 'alt' => null],
            [
                'greeting' => 'Willkommen',
                'intro' => 'Eine Community-Plattform',
                'cta' => 'Auf Deutsch fortfahren',
                'alt' => 'Auf Deutsch fortfahren (German)',
            ],
        ];

        yield 'language with no catalogue keys falls back to its name' => [
            'it',
            'Italian',
            ['greeting' => null, 'intro' => null, 'cta' => null, 'alt' => null],
            ['greeting' => 'Italian', 'intro' => '', 'cta' => 'Italian', 'alt' => 'Italian'],
        ];

        yield 'stored alt text substitutes the language placeholder' => [
            'it',
            'Italian',
            ['greeting' => 'Benvenuto', 'intro' => null, 'cta' => 'Continua', 'alt' => 'Continua (%language%)'],
            ['greeting' => 'Benvenuto', 'intro' => '', 'cta' => 'Continua', 'alt' => 'Continua (Italian)'],
        ];
    }
}
