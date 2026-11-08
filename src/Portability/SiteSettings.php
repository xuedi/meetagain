<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\Image;
use App\Entity\Language;
use App\Enum\ImageType;
use App\Repository\ImageRepository;
use App\Repository\LanguageRepository;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Config\PluginService;
use App\Service\Media\ImageLocationService;
use App\Theme\Colors;
use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Contract\TemplatesInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\TruncateMode;

use function Symfony\Component\String\u;

readonly class SiteSettings
{
    private const array FLAGS = ['show_town_hall', 'show_event_geo', 'send_rsvp_notifications', 'send_event_reminders', 'send_upcoming_digest'];
    private const int CONFIG_VALUE_LENGTH = 255;

    public function __construct(
        private ConfigService $configService,
        private LanguageService $languageService,
        private LanguageRepository $languageRepository,
        private ImageRepository $imageRepository,
        private ImageLocationService $imageLocationService,
        private TemplatesInterface $templates,
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        private PluginService $pluginService,
        private PluginSettings $pluginSettings,
        private Colors $colors,
    ) {}

    public function current(): Site
    {
        $name = $this->configService->getSiteName();
        $logoId = $this->configService->getSiteLogoId();

        return new Site(
            slug: strtolower((string) $this->slugger->slug($name)),
            name: $name,
            description: $this->configService->getSeoDescription('default'),
            languages: $this->languageService->getEnabledCodes(),
            themeColors: $this->themeColors(null),
            logo: $logoId === null ? null : $this->imageRepository->find($logoId),
            settings: [
                'show_town_hall' => $this->configService->isShowTownHall(),
                'show_event_geo' => $this->configService->isShowEventGeo(),
                'send_rsvp_notifications' => $this->configService->isSendRsvpNotifications(),
                'send_event_reminders' => $this->configService->isEventRemindersEnabled(),
                'send_upcoming_digest' => $this->configService->isUpcomingDigestEnabled(),
            ],
            pluginSettings: $this->pluginSettings->export(array_values($this->pluginService->getActiveList())),
        );
    }

    /**
     * @return array<string, string>
     */
    public function themeColors(?string $scopeId): array
    {
        $themeColors = [];
        foreach ($this->colors->forScope($scopeId) as $key => $color) {
            $themeColors[str_replace('_', '-', substr((string) $key, strlen('color_')))] = (string) $color;
        }

        return $themeColors;
    }

    /**
     * @param array<array-key, mixed> $site
     * @return list<array{field: string, current: string|bool, archive: string|bool}> only the fields apply() would change
     */
    public function diff(array $site): array
    {
        $current = $this->current();
        $rows = [];

        $name = (string) ($site['name'] ?? '');
        if ($name !== '' && $name !== $current->name) {
            $rows[] = ['field' => 'name', 'current' => $current->name, 'archive' => $name];
        }

        $description = $this->truncatedDescription((string) ($site['description'] ?? ''));
        if ($description !== '' && $description !== $current->description) {
            $rows[] = ['field' => 'description', 'current' => $current->description, 'archive' => $description];
        }

        $languages = $this->wantedLanguages(is_array($site['languages'] ?? null) ? $site['languages'] : []);
        if ($languages !== [] && $languages !== $current->languages) {
            $rows[] = ['field' => 'languages', 'current' => implode(', ', $current->languages), 'archive' => implode(', ', $languages)];
        }

        foreach (is_array($site['theme_colors'] ?? null) ? $site['theme_colors'] : [] as $key => $color) {
            $currentColor = $current->themeColors[(string) $key] ?? '';
            if ((string) $color !== $currentColor) {
                $rows[] = ['field' => 'theme_color', 'current' => $key . ' ' . $currentColor, 'archive' => $key . ' ' . $color];
            }
        }

        $logoFile = $site['logo_file'] ?? null;
        $logoHash = is_string($logoFile) ? pathinfo($logoFile, PATHINFO_FILENAME) : '';
        if ($logoHash !== '' && $logoHash !== $current->logo?->getHash()) {
            $rows[] = ['field' => 'logo', 'current' => $current->logo instanceof Image, 'archive' => true];
        }

        $settings = is_array($site['settings'] ?? null) ? $site['settings'] : [];
        foreach (self::FLAGS as $flag) {
            $wanted = $settings[$flag] ?? null;
            if (is_bool($wanted) && $wanted !== ($current->settings[$flag] ?? null)) {
                $rows[] = ['field' => $flag, 'current' => (bool) ($current->settings[$flag] ?? false), 'archive' => $wanted];
            }
        }

        return $rows;
    }

    /**
     * @param array<array-key, mixed> $site
     */
    public function apply(array $site, ImportContext $context): void
    {
        $name = (string) ($site['name'] ?? '');
        if ($name !== '') {
            $this->configService->setString('site_name', $name);
        }

        $description = $this->truncatedDescription((string) ($site['description'] ?? ''));
        if ($description !== '') {
            $this->configService->setString('seo_description_default', $description);
        }

        $settings = is_array($site['settings'] ?? null) ? $site['settings'] : [];
        foreach (self::FLAGS as $flag) {
            if (!is_bool($settings[$flag] ?? null)) {
                continue;
            }

            $this->configService->setBoolean($flag, $settings[$flag]);
        }

        $this->applyLanguages(is_array($site['languages'] ?? null) ? $site['languages'] : []);
        $this->applyThemeColors(is_array($site['theme_colors'] ?? null) ? $site['theme_colors'] : []);
        $this->applyLogo($site['logo_file'] ?? null, $context);

        if (is_array($site['plugin_settings'] ?? null)) {
            $this->pluginSettings->apply($site['plugin_settings']);
        }
    }

    /**
     * @param array<array-key, mixed> $codes
     */
    private function applyLanguages(array $codes): void
    {
        $languages = $this->languageRepository->findAll();
        $wanted = $this->wantedLanguages($codes);
        if ($wanted === []) {
            return;
        }

        foreach ($languages as $language) {
            $position = array_search($language->getCode(), $wanted, true);
            $language->setEnabled($position !== false);
            if ($position !== false) {
                $language->setSortOrder($position + 1);
            }
        }

        foreach ($wanted as $code) {
            $this->templates->seedLanguage($code);
        }

        $this->em->flush();
        $this->languageService->invalidateCache();
    }

    /**
     * @param array<array-key, mixed> $codes
     * @return list<string>
     */
    private function wantedLanguages(array $codes): array
    {
        $known = array_map(static fn(Language $language): string => $language->getCode(), $this->languageRepository->findAll());

        return array_values(array_intersect(array_map(strval(...), $codes), $known));
    }

    private function truncatedDescription(string $description): string
    {
        return (string) u($description)->truncate(self::CONFIG_VALUE_LENGTH, '...', TruncateMode::WordBefore);
    }

    /**
     * @param array<array-key, mixed> $themeColors
     */
    private function applyThemeColors(array $themeColors): void
    {
        $colors = [];
        foreach ($themeColors as $key => $color) {
            $colors['color_' . str_replace('-', '_', (string) $key)] = (string) $color;
        }

        if ($colors !== []) {
            $this->colors->save($colors, null);
        }
    }

    private function applyLogo(mixed $logoFile, ImportContext $context): void
    {
        $logo = $context->importImage($logoFile, ImageType::SiteLogo);
        if (!$logo instanceof Image) {
            return;
        }

        $this->em->flush();
        $previousId = $this->configService->getSiteLogoId();
        $this->configService->setSiteLogoId($logo->getId());
        if ($previousId !== null) {
            $this->imageLocationService->removeLocation($previousId, ImageType::SiteLogo, 0);
        }

        $this->imageLocationService->addLocation((int) $logo->getId(), ImageType::SiteLogo, 0);
    }
}
