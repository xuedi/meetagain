<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateTranslation;
use App\Entity\Image;
use App\Entity\Language;
use App\Enum\ImageType;
use App\Repository\ImageRepository;
use App\Repository\LanguageRepository;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Config\PluginService;
use App\Service\Email\EmailTemplateService;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\TruncateMode;

use function Symfony\Component\String\u;

readonly class SiteSettings
{
    private const array FLAGS = ['show_town_hall', 'send_rsvp_notifications', 'send_event_reminders', 'send_upcoming_digest'];
    private const int CONFIG_VALUE_LENGTH = 255;

    public function __construct(
        private ConfigService $configService,
        private LanguageService $languageService,
        private LanguageRepository $languageRepository,
        private ImageRepository $imageRepository,
        private ImageLocationService $imageLocationService,
        private EmailTemplateService $templateService,
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        private PluginService $pluginService,
        private PluginSettings $pluginSettings,
    ) {}

    public function current(): Site
    {
        $name = $this->configService->getSiteName();
        $logoId = $this->configService->getSiteLogoId();

        $themeColors = [];
        foreach ($this->configService->getThemeColors() as $key => $color) {
            $themeColors[str_replace('_', '-', substr((string) $key, strlen('color_')))] = (string) $color;
        }

        return new Site(
            slug: strtolower((string) $this->slugger->slug($name)),
            name: $name,
            description: $this->configService->getSeoDescription('default'),
            languages: $this->languageService->getEnabledCodes(),
            themeColors: $themeColors,
            logo: $logoId === null ? null : $this->imageRepository->find($logoId),
            settings: [
                'show_town_hall' => $this->configService->isShowTownHall(),
                'send_rsvp_notifications' => $this->configService->isSendRsvpNotifications(),
                'send_event_reminders' => $this->configService->isEventRemindersEnabled(),
                'send_upcoming_digest' => $this->configService->isUpcomingDigestEnabled(),
            ],
            pluginSettings: $this->pluginSettings->export(array_values($this->pluginService->getActiveList())),
        );
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

        $description = (string) ($site['description'] ?? '');
        if ($description !== '') {
            $this->configService->setString(
                'seo_description_default',
                (string) u($description)->truncate(self::CONFIG_VALUE_LENGTH, '...', TruncateMode::WordBefore),
            );
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
        $known = array_map(static fn(Language $language): string => $language->getCode(), $languages);
        $wanted = array_values(array_intersect(array_map(strval(...), $codes), $known));
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
            $this->addTemplateTranslations($code);
        }

        $this->em->flush();
        $this->languageService->invalidateCache();
    }

    private function addTemplateTranslations(string $code): void
    {
        foreach ($this->templateService->getDefaultTemplates($code) as $identifier => $data) {
            $template = $this->templateService->getTemplate($identifier);
            if (!$template instanceof EmailTemplate || $template->findTranslation($code) instanceof EmailTemplateTranslation) {
                continue;
            }

            $translation = new EmailTemplateTranslation();
            $translation->setEmailTemplate($template);
            $translation->setLanguage($code);
            $translation->setSubject($data['subject']);
            $translation->setBody($data['body']);
            $translation->setUpdatedAt(new DateTimeImmutable());

            $this->em->persist($translation);
        }
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
            $this->configService->saveColors($colors);
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
