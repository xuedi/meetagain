<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Enum\ImageType;
use App\Entity\Language;
use App\Service\Media\ImageService;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class LanguageFixture extends AbstractFixture implements FixtureGroupInterface, DependentFixtureInterface
{
    // Keep constants for backward compatibility in other fixtures
    public const string ENGLISH = 'en';
    public const string GERMAN = 'de';
    public const string CHINESE = 'cn';

    /**
     * Curated list of standard languages. Only the 3 original languages are enabled by default.
     * All others are seeded as disabled — admins enable what their instance needs.
     * Flag SVGs are named after the language code (e.g. en.svg, de.svg, fr.svg).
     */
    private const array LANGUAGES = [
        // Enabled by default
        ['code' => 'en', 'name' => 'English',    'sortOrder' => 1,  'enabled' => true,  'image' => 'en.jpg'],
        ['code' => 'de', 'name' => 'German',     'sortOrder' => 2,  'enabled' => true,  'image' => 'de.jpg'],
        ['code' => 'cn', 'name' => 'Chinese',    'sortOrder' => 3,  'enabled' => true,  'image' => 'cn.jpg'],

        // Disabled by default
        ['code' => 'fr', 'name' => 'French',       'sortOrder' => 10],
        ['code' => 'es', 'name' => 'Spanish',      'sortOrder' => 11],
        ['code' => 'it', 'name' => 'Italian',      'sortOrder' => 12],
        ['code' => 'pt', 'name' => 'Portuguese',   'sortOrder' => 13],
        ['code' => 'nl', 'name' => 'Dutch',        'sortOrder' => 14],
        ['code' => 'pl', 'name' => 'Polish',       'sortOrder' => 15],
        ['code' => 'ru', 'name' => 'Russian',      'sortOrder' => 16],
        ['code' => 'ja', 'name' => 'Japanese',     'sortOrder' => 17],
        ['code' => 'ko', 'name' => 'Korean',       'sortOrder' => 18],
        ['code' => 'ar', 'name' => 'Arabic',       'sortOrder' => 19],
        ['code' => 'tr', 'name' => 'Turkish',      'sortOrder' => 20],
        ['code' => 'sv', 'name' => 'Swedish',      'sortOrder' => 21],
        ['code' => 'no', 'name' => 'Norwegian',    'sortOrder' => 22],
        ['code' => 'da', 'name' => 'Danish',       'sortOrder' => 23],
        ['code' => 'fi', 'name' => 'Finnish',      'sortOrder' => 24],
        ['code' => 'uk', 'name' => 'Ukrainian',    'sortOrder' => 25],
        ['code' => 'cs', 'name' => 'Czech',        'sortOrder' => 26],
        ['code' => 'hu', 'name' => 'Hungarian',    'sortOrder' => 27],
        ['code' => 'ro', 'name' => 'Romanian',     'sortOrder' => 28],
        ['code' => 'el', 'name' => 'Greek',        'sortOrder' => 29],
        ['code' => 'bg', 'name' => 'Bulgarian',    'sortOrder' => 30],
        ['code' => 'hr', 'name' => 'Croatian',     'sortOrder' => 31],
        ['code' => 'sk', 'name' => 'Slovak',       'sortOrder' => 32],
        ['code' => 'hi', 'name' => 'Hindi',        'sortOrder' => 33],
        ['code' => 'vi', 'name' => 'Vietnamese',   'sortOrder' => 34],
        ['code' => 'id', 'name' => 'Indonesian',   'sortOrder' => 35],
        ['code' => 'th', 'name' => 'Thai',         'sortOrder' => 36],
        ['code' => 'ms', 'name' => 'Malay',        'sortOrder' => 37],
        ['code' => 'he', 'name' => 'Hebrew',       'sortOrder' => 38],
        ['code' => 'fa', 'name' => 'Persian',      'sortOrder' => 39],
        ['code' => 'sr', 'name' => 'Serbian',      'sortOrder' => 40],
        ['code' => 'sl', 'name' => 'Slovenian',    'sortOrder' => 41],
        ['code' => 'lt', 'name' => 'Lithuanian',   'sortOrder' => 42],
        ['code' => 'lv', 'name' => 'Latvian',      'sortOrder' => 43],
        ['code' => 'et', 'name' => 'Estonian',     'sortOrder' => 44],
        ['code' => 'is', 'name' => 'Icelandic',    'sortOrder' => 45],
        ['code' => 'af', 'name' => 'Afrikaans',    'sortOrder' => 46],
        ['code' => 'sw', 'name' => 'Swahili',      'sortOrder' => 47],
        ['code' => 'bn', 'name' => 'Bengali',      'sortOrder' => 48],
        ['code' => 'ur', 'name' => 'Urdu',         'sortOrder' => 49],
        ['code' => 'ta', 'name' => 'Tamil',        'sortOrder' => 50],
        ['code' => 'my', 'name' => 'Burmese',      'sortOrder' => 51],
        ['code' => 'km', 'name' => 'Khmer',        'sortOrder' => 52],
        ['code' => 'ka', 'name' => 'Georgian',     'sortOrder' => 53],
        ['code' => 'hy', 'name' => 'Armenian',     'sortOrder' => 54],
        ['code' => 'az', 'name' => 'Azerbaijani',  'sortOrder' => 55],
        ['code' => 'be', 'name' => 'Belarusian',   'sortOrder' => 56],
        ['code' => 'mk', 'name' => 'Macedonian',   'sortOrder' => 57],
        ['code' => 'sq', 'name' => 'Albanian',     'sortOrder' => 58],
        ['code' => 'mn', 'name' => 'Mongolian',    'sortOrder' => 59],
        ['code' => 'si', 'name' => 'Sinhala',      'sortOrder' => 60],
        ['code' => 'lo', 'name' => 'Lao',          'sortOrder' => 61],
        ['code' => 'ga', 'name' => 'Irish',        'sortOrder' => 62],
        ['code' => 'cy', 'name' => 'Welsh',        'sortOrder' => 63],
        ['code' => 'mt', 'name' => 'Maltese',      'sortOrder' => 64],
        ['code' => 'ca', 'name' => 'Catalan',      'sortOrder' => 65],
        ['code' => 'eu', 'name' => 'Basque',       'sortOrder' => 66],
        ['code' => 'gl', 'name' => 'Galician',     'sortOrder' => 67],
    ];

    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $this->start();
        $importUser = $this->getRefUser(SystemUserFixture::IMPORT);

        foreach (self::LANGUAGES as $data) {
            $language = new Language();
            $language->setCode($data['code']);
            $language->setName($data['name']);
            $language->setEnabled($data['enabled'] ?? false);
            $language->setSortOrder($data['sortOrder']);

            if (isset($data['image'])) {
                $imageFile = __DIR__ . '/Language/' . $data['image'];
                if (file_exists($imageFile)) {
                    $uploadedImage = new UploadedFile($imageFile, $data['image'], null, null, true);
                    $image = $this->imageService->upload($uploadedImage, $importUser, ImageType::LanguageTile);
                    $this->imageService->createThumbnails($image, ImageType::LanguageTile);
                    $language->setTileImage($image);
                }
            }

            $manager->persist($language);
        }

        $manager->flush();
        $this->stop();
    }

    public function getDependencies(): array
    {
        return [
            SystemUserFixture::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['install'];
    }
}
