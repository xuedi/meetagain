<?php

declare(strict_types=1);

namespace App\Service\Config;

use App\Entity\Config;
use App\Enum\ConfigType;
use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Filter\Image\ImageThumbnailSizeFilterInterface;
use App\Repository\ConfigRepository;
use App\Service\AppStateService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class ConfigService
{
    private const string CACHE_KEY_THEME_COLORS = 'theme_colors';
    private const string CACHE_KEY_PREFIX = 'config_';
    private const int CACHE_TTL = 60 * 60 * 24;

    /**
     * @param iterable<ImageThumbnailSizeFilterInterface> $thumbnailSizeFilters
     */
    public function __construct(
        private ConfigRepository $repo,
        private EntityManagerInterface $em,
        private CacheInterface $cache,
        private KernelInterface $kernel,
        private AppStateService $appState,
        #[AutowireIterator(ImageThumbnailSizeFilterInterface::class)]
        private iterable $thumbnailSizeFilters = [],
    ) {}

    /**
     * Returns thumbnail sizes for a given ImageType.
     *
     * Note: sizes may appear in multiple ImageType entries — e.g. EventTeaser and EventUpload
     * share the same size set. This is intentional: each ImageType also represents a location,
     * and the same physical size can be valid in more than one location context.
     */
    public function getThumbnailSizes(ImageType $type): array
    {
        foreach ($this->thumbnailSizeFilters as $filter) {
            $sizes = $filter->getThumbnailSizes($type);
            if ($sizes !== null) {
                return $sizes;
            }
        }

        return match ($type) {
            ImageType::ProfilePicture => [[400, 400], [100, 100], [80, 80], [50, 50]],
            ImageType::EventTeaser => [[1024, 768], [600, 400], [210, 140], [100, 100], [50, 50]], // included EventUpload
            ImageType::EventUpload, ImageType::CmsGallery => [[1024, 768], [210, 140], [100, 100], [50, 50]],
            ImageType::CmsCardImage => [[600, 400], [300, 200], [100, 100], [50, 50]],
            ImageType::CmsBlock => [[432, 432], [100, 100], [80, 80], [50, 50]],
            ImageType::PluginDish => [[1024, 768], [600, 400], [400, 400], [100, 100], [50, 50]],
            ImageType::LanguageTile => [[600, 400], [300, 200], [100, 100], [50, 50]],
            ImageType::PluginBookclubCover => [[400, 500], [200, 250], [100, 100], [50, 50]],
            ImageType::SiteLogo => [[400, 400], [100, 100]],
            default => throw new RuntimeException(sprintf(
                'No thumbnail sizes registered for image type "%s". Plugin-owned types must be supplied via ImageThumbnailSizeFilterInterface.',
                $type->name,
            )),
        };
    }

    public function getFitMode(ImageType $type): ImageFitMode
    {
        foreach ($this->thumbnailSizeFilters as $filter) {
            $mode = $filter->getFitMode($type);
            if ($mode !== null) {
                return $mode;
            }
        }

        return match ($type) {
            ImageType::SiteLogo => ImageFitMode::Fit,
            default => ImageFitMode::Crop,
        };
    }

    public function getThumbnailSizeList(): array
    {
        return [
            '1024x768' => 0, // gallery image bit
            '600x400' => 0, // event preview image
            '432x432' => 0, // cmsBlock image
            '400x400' => 0, // profile big
            '300x200' => 0, // cms card image
            '210x140' => 0, // gallery image preview
            '100x100' => 0, // report preview
            '80x80' => 0, // ?
            '50x50' => 0, // ?
        ];
    }

    public function isValidThumbnailSize(ImageType $type, int $checkWidth, int $checkHeight): bool
    {
        foreach ($this->getThumbnailSizes($type) as [$width, $height]) {
            if (!($checkWidth === $width && $checkHeight === $height)) {
                continue;
            }

            return true;
        }

        return false;
    }

    public function getHost(): string
    {
        return $this->getString('website_host', 'https://localhost');
    }

    public function getUrl(): string
    {
        return $this->getString('website_url', 'localhost');
    }

    public function getSeoDescription(string $context): string
    {
        return match ($context) {
            'events' => $this->getString('seo_description_events', ''),
            'members' => $this->getString('seo_description_members', ''),
            default => $this->getString('seo_description_default', ''),
        };
    }

    public function getSystemUserId(): int
    {
        return $this->getInt('system_user_id', 1);
    }

    public function getSiteLogoId(): ?int
    {
        $id = $this->getInt('site_logo_id', 0);

        return $id > 0 ? $id : null;
    }

    public function setSiteLogoId(?int $id): void
    {
        $this->setInt('site_logo_id', $id ?? 0);
    }

    public function getMailerAddress(): Address
    {
        return new Address(
            $this->getString('email_sender_mail', 'sender@email.com'),
            $this->getString('email_sender_name', 'email sender'),
        );
    }

    public function isShowFrontpage(): bool
    {
        return $this->getBoolean('show_frontpage', false);
    }

    public function isAutomaticRegistration(): bool
    {
        return $this->getBoolean('automatic_registration', false);
    }

    public function isSendRsvpNotifications(): bool
    {
        return $this->getBoolean('send_rsvp_notifications', true);
    }

    public function isSendAdminNotification(): bool
    {
        return $this->getBoolean('send_admin_notification', true);
    }

    public function isEmailDeliverySyncEnabled(): bool
    {
        return $this->getBoolean('email_delivery_sync_enabled', false);
    }

    public function isEventRemindersEnabled(): bool
    {
        return $this->getBoolean('send_event_reminders', true);
    }

    public function isUpcomingDigestEnabled(): bool
    {
        return $this->getBoolean('send_upcoming_digest', true);
    }

    public function getDateFormat(): string
    {
        return $this->getString('date_format', 'Y-m-d H:i');
    }

    public function getDateFormatFlatpickr(): string
    {
        // Convert PHP date format to flatpickr format (A -> K for AM/PM)
        return str_replace('A', 'K', $this->getDateFormat());
    }

    public function getBooleanConfigs(): array
    {
        return $this->repo->findBy(['type' => ConfigType::Boolean]);
    }

    public function toggleBoolean(string $name): bool
    {
        $setting = $this->repo->findOneBy(['name' => $name]);
        if ($setting === null) {
            throw new RuntimeException(sprintf("Config '%s' not found", $name));
        }

        $value = $setting->getValue() !== 'true';
        $setting->setValue($value ? 'true' : 'false');

        $this->em->persist($setting);
        $this->em->flush();
        $this->cache->delete(self::CACHE_KEY_PREFIX . $name);

        return $value;
    }

    public function getFooterColumnTitle(string $column): string
    {
        return $this->appState->get('footer_' . $column . '_title') ?? '';
    }

    public function saveForm(array $formData): void
    {
        $this->setString('website_url', $formData['url']);
        $this->setString('website_host', $formData['host']);
        $this->setString('email_sender_name', $formData['senderName']);
        $this->setString('email_sender_mail', $formData['senderEmail']);
        $this->setInt('system_user_id', $formData['systemUser']);
        $this->setString('date_format', $formData['dateFormat']);
        $this->appState->set('footer_col1_title', $formData['footerCol1Title'] ?? '');
        $this->appState->set('footer_col2_title', $formData['footerCol2Title'] ?? '');
        $this->appState->set('footer_col3_title', $formData['footerCol3Title'] ?? '');
        $this->appState->set('footer_col4_title', $formData['footerCol4Title'] ?? '');
    }

    public function saveSeoForm(array $formData): void
    {
        $this->setString('seo_description_default', $formData['seoDescriptionDefault'] ?? '');
        $this->setString('seo_description_events', $formData['seoDescriptionEvents'] ?? '');
        $this->setString('seo_description_members', $formData['seoDescriptionMembers'] ?? '');
    }

    public function getThemeColors(): array
    {
        return $this->cache->get(self::CACHE_KEY_THEME_COLORS, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->parseConfigScss();
        });
    }

    public function saveColors(array $colors): void
    {
        $path = $this->kernel->getProjectDir() . '/assets/styles/_config.scss';
        $content = file_get_contents($path);

        $scssToKey = [
            'primary' => 'color_primary',
            'link' => 'color_link',
            'info' => 'color_info',
            'success' => 'color_success',
            'warning' => 'color_warning',
            'danger' => 'color_danger',
            'text-grey' => 'color_text_grey',
            'text-grey-light' => 'color_text_grey_light',
        ];

        foreach ($scssToKey as $scssVar => $key) {
            if (!isset($colors[$key])) {
                continue;
            }

            $value = (string) $colors[$key];
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                continue;
            }

            $content = preg_replace(
                '/(\$' . preg_quote($scssVar, '/') . '\s*:\s*)#[0-9a-fA-F]{3,6}(\s*;)/',
                '${1}' . $value . '${2}',
                $content,
            );
        }

        file_put_contents($path, $content);
        $this->cache->delete(self::CACHE_KEY_THEME_COLORS);
    }

    private function parseConfigScss(): array
    {
        $path = $this->kernel->getProjectDir() . '/assets/styles/_config.scss';
        $content = file_get_contents($path);

        $scssToKey = [
            'primary' => 'color_primary',
            'link' => 'color_link',
            'info' => 'color_info',
            'success' => 'color_success',
            'warning' => 'color_warning',
            'danger' => 'color_danger',
            'text-grey' => 'color_text_grey',
            'text-grey-light' => 'color_text_grey_light',
        ];

        $map = [];
        foreach ($scssToKey as $scssVar => $key) {
            $m = [];
            if (!preg_match('/\$' . preg_quote($scssVar, '/') . '\s*:\s*(#[0-9a-fA-F]{3,6})\s*;/', $content, $m)) {
                continue;
            }

            $map[$key] = $m[1];
        }

        return $map;
    }

    private function getCachedValue(string $name): ?string
    {
        return $this->cache->get(self::CACHE_KEY_PREFIX . $name, function (ItemInterface $item) use ($name): ?string {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->repo->findOneBy(['name' => $name])?->getValue();
        });
    }

    private function getBoolean(string $name, bool $default = false): bool
    {
        return ($this->getCachedValue($name) ?? ($default ? 'true' : 'false')) === 'true';
    }

    public function getString(string $name, string $default): string
    {
        return $this->getCachedValue($name) ?? $default;
    }

    public function setString(string $name, string $value): void
    {
        $setting = $this->repo->findOneBy(['name' => $name]);
        if ($setting === null) {
            $setting = new Config();
            $setting->setName($name);
            $setting->setType(ConfigType::String);
        }
        $setting->setValue($value);

        $this->em->persist($setting);
        $this->em->flush();
        $this->cache->delete(self::CACHE_KEY_PREFIX . $name);
    }

    public function getInt(string $name, int $default): int
    {
        $value = $this->getCachedValue($name);

        return $value === null ? $default : (int) $value;
    }

    public function setInt(string $name, int $value): void
    {
        $setting = $this->repo->findOneBy(['name' => $name]);
        if ($setting === null) {
            $setting = new Config();
            $setting->setName($name);
            $setting->setType(ConfigType::Integer);
        }
        $setting->setValue((string) $value);

        $this->em->persist($setting);
        $this->em->flush();
        $this->cache->delete(self::CACHE_KEY_PREFIX . $name);
    }
}
