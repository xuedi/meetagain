<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateTranslation;
use App\Entity\Image;
use App\Entity\Language;
use App\Entity\User;
use App\Enum\ImageType;
use App\Portability\ImageImporter;
use App\Portability\ImportContext;
use App\Portability\PluginSettings;
use App\Portability\SiteSettings;
use App\Repository\ImageRepository;
use App\Repository\LanguageRepository;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Config\PluginService;
use App\Service\Email\EmailTemplateService;
use App\Service\Media\ImageLocationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class SiteSettingsTest extends TestCase
{
    /** @var list<object> */
    private array $persisted = [];

    public function testNameDescriptionAndTheKnownFeatureSwitchesAreWritten(): void
    {
        // Arrange
        $strings = [];
        $booleans = [];
        $config = $this->createStub(ConfigService::class);
        $config->method('setString')->willReturnCallback(static function (string $name, string $value) use (&$strings): void {
            $strings[$name] = $value;
        });
        $config->method('setBoolean')->willReturnCallback(static function (string $name, bool $value) use (&$booleans): void {
            $booleans[$name] = $value;
        });
        $site = [
            'name' => 'Weiqi Club',
            'description' => 'Go in Berlin',
            'settings' => ['show_town_hall' => true, 'send_upcoming_digest' => false, 'send_event_reminders' => 'yes', 'automatic_registration' => true],
        ];

        // Act
        $this->siteSettings(config: $config)->apply($site, $this->context());

        // Assert
        static::assertSame(['site_name' => 'Weiqi Club', 'seo_description_default' => 'Go in Berlin'], $strings);
        static::assertSame(['show_town_hall' => true, 'send_upcoming_digest' => false], $booleans);
    }

    public function testALongDescriptionIsCutAtAWordToFitTheConfigColumn(): void
    {
        // Arrange
        $written = null;
        $config = $this->createStub(ConfigService::class);
        $config->method('setString')->willReturnCallback(static function (string $name, string $value) use (&$written): void {
            $written = $value;
        });

        $description = str_repeat('Board games every Friday. ', 20);

        // Act
        $this->siteSettings(config: $config)->apply(['description' => $description], $this->context());

        // Assert
        $kept = rtrim(substr((string) $written, 0, -3));
        static::assertLessThanOrEqual(255, mb_strlen((string) $written));
        static::assertStringEndsWith('...', (string) $written);
        static::assertStringStartsWith($kept, $description);
        static::assertSame(' ', $description[strlen($kept)]);
    }

    public function testTheListedLanguagesAreEnabledInOrderAndTheOthersDisabled(): void
    {
        // Arrange
        $english = $this->language('en', true, 1);
        $german = $this->language('de', true, 2);
        $spanish = $this->language('es', false, 11);

        // Act
        $this->siteSettings(languages: [$english, $german, $spanish])->apply(['languages' => ['es', 'xx', 'en']], $this->context());

        // Assert
        static::assertTrue($spanish->isEnabled());
        static::assertSame(1, $spanish->getSortOrder());
        static::assertTrue($english->isEnabled());
        static::assertSame(2, $english->getSortOrder());
        static::assertFalse($german->isEnabled());
    }

    public function testAnEnabledLanguageGetsItsMissingEmailTemplateTranslations(): void
    {
        // Arrange
        $languages = [$this->language('en', true, 1), $this->language('es', false, 11)];

        // Act
        $this->siteSettings(languages: $languages)->apply(['languages' => ['en', 'es']], $this->context());

        // Assert
        $translations = array_values(array_filter($this->persisted, static fn(object $entity): bool => $entity instanceof EmailTemplateTranslation));
        static::assertSame(['en', 'es'], array_map(static fn(EmailTemplateTranslation $translation): ?string => $translation->getLanguage(), $translations));
        static::assertSame('Welcome (es)', $translations[1]->getSubject());
    }

    public function testNoKnownLanguageLeavesTheLanguagesAlone(): void
    {
        // Arrange
        $english = $this->language('en', true, 1);

        // Act
        $this->siteSettings(languages: [$english])->apply(['languages' => ['xx']], $this->context());

        // Assert
        static::assertTrue($english->isEnabled());
        static::assertSame([], $this->persisted);
    }

    public function testThemeColorsAreSavedUnderTheirConfigKeys(): void
    {
        // Arrange
        $config = $this->createMock(ConfigService::class);
        $config->expects($this->once())->method('saveColors')->with(['color_primary' => '#111111', 'color_text_grey' => '#222222']);

        // Act
        $this->siteSettings(config: $config)->apply(['theme_colors' => ['primary' => '#111111', 'text-grey' => '#222222']], $this->context());
    }

    public function testTheLogoReplacesThePreviousSiteLogo(): void
    {
        // Arrange
        $logo = new Image();
        new ReflectionProperty(Image::class, 'id')->setValue($logo, 42);
        $config = $this->createMock(ConfigService::class);
        $config->method('getSiteLogoId')->willReturn(7);
        $config->expects($this->once())->method('setSiteLogoId')->with(42);
        $locations = $this->createMock(ImageLocationService::class);
        $locations->expects($this->once())->method('removeLocation')->with(7, ImageType::SiteLogo, 0);
        $locations->expects($this->once())->method('addLocation')->with(42, ImageType::SiteLogo, 0);

        // Act
        $this->siteSettings(config: $config, locations: $locations)->apply(['logo_file' => 'images/logo.png'], $this->context($logo));
    }

    public function testAnEmptyBlockWritesNothing(): void
    {
        // Arrange
        $config = $this->createMock(ConfigService::class);
        $config->expects($this->never())->method('setString');
        $config->expects($this->never())->method('setBoolean');
        $config->expects($this->never())->method('saveColors');
        $config->expects($this->never())->method('setSiteLogoId');
        $pluginSettings = $this->createMock(PluginSettings::class);
        $pluginSettings->expects($this->never())->method('apply');

        // Act
        $this->siteSettings(config: $config, pluginSettings: $pluginSettings)->apply([], $this->context());
    }

    public function testThePluginSettingsAreHandedOnToBeWrittenAtTheGlobalScope(): void
    {
        // Arrange
        $pluginSettings = $this->createMock(PluginSettings::class);
        $pluginSettings->expects($this->once())->method('apply')->with(['books' => ['circulation' => true]]);

        // Act
        $this->siteSettings(pluginSettings: $pluginSettings)->apply(['plugin_settings' => ['books' => ['circulation' => true]]], $this->context());
    }

    public function testTheCurrentSettingsDescribeThisInstance(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getSiteName')->willReturn('Weiqi Club');
        $config->method('getSeoDescription')->willReturn('Go in Berlin');
        $config->method('getSiteLogoId')->willReturn(null);
        $config->method('getThemeColors')->willReturn(['color_primary' => '#111111', 'color_text_grey_light' => '#cccccc']);
        $config->method('isShowTownHall')->willReturn(true);
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getEnabledCodes')->willReturn(['en', 'de']);
        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturn(['books']);
        $pluginSettings = $this->createMock(PluginSettings::class);
        $pluginSettings->expects($this->once())->method('export')->with(['books'])->willReturn(['books' => ['circulation' => true]]);

        // Act
        $site = $this->siteSettings(config: $config, languageService: $languageService, pluginService: $pluginService, pluginSettings: $pluginSettings)->current();

        // Assert
        static::assertSame('weiqi-club', $site->slug);
        static::assertSame('Weiqi Club', $site->name);
        static::assertSame('Go in Berlin', $site->description);
        static::assertSame(['en', 'de'], $site->languages);
        static::assertSame(['primary' => '#111111', 'text-grey-light' => '#cccccc'], $site->themeColors);
        static::assertNull($site->logo);
        static::assertSame(
            ['show_town_hall' => true, 'send_rsvp_notifications' => false, 'send_event_reminders' => false, 'send_upcoming_digest' => false],
            $site->settings,
        );
        static::assertSame(['books' => ['circulation' => true]], $site->pluginSettings);
    }

    /**
     * @param list<Language> $languages
     */
    private function siteSettings(
        ?ConfigService $config = null,
        array $languages = [],
        ?ImageLocationService $locations = null,
        ?LanguageService $languageService = null,
        ?PluginService $pluginService = null,
        ?PluginSettings $pluginSettings = null,
    ): SiteSettings {
        $languageRepository = $this->createStub(LanguageRepository::class);
        $languageRepository->method('findAll')->willReturn($languages);

        $templateService = $this->createStub(EmailTemplateService::class);
        $templateService->method('getDefaultTemplates')->willReturnCallback(
            static fn(string $code): array => ['welcome' => ['subject' => 'Welcome (' . $code . ')', 'body' => '<p>Hi</p>', 'variables' => []]],
        );
        $templateService->method('getTemplate')->willReturn(new EmailTemplate()->setIdentifier('welcome'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        return new SiteSettings(
            $config ?? $this->createStub(ConfigService::class),
            $languageService ?? $this->createStub(LanguageService::class),
            $languageRepository,
            $this->createStub(ImageRepository::class),
            $locations ?? $this->createStub(ImageLocationService::class),
            $templateService,
            $em,
            new AsciiSlugger(),
            $pluginService ?? $this->createStub(PluginService::class),
            $pluginSettings ?? $this->createStub(PluginSettings::class),
        );
    }

    private function context(?Image $importedImage = null): ImportContext
    {
        $imageImporter = $this->createStub(ImageImporter::class);
        $imageImporter->method('import')->willReturn($importedImage);

        return new ImportContext($imageImporter, '/archive', new User());
    }

    private function language(string $code, bool $enabled, int $sortOrder): Language
    {
        return new Language()->setCode($code)->setName($code)->setEnabled($enabled)->setSortOrder($sortOrder);
    }
}
