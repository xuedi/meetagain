<?php declare(strict_types=1);

namespace App\Service\Config;

use App\Entity\Config;
use App\Enum\ConfigType;
use App\ExtendedFilesystem;
use App\Repository\ConfigRepository;
use App\Service\AppStateService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class ConfigService
{
    private const string CACHE_KEY_THEME_COLORS = 'theme_colors';
    private const string CACHE_KEY_PREFIX = 'config_';

    public function __construct(
        private ConfigRepository $repo,
        private EntityManagerInterface $em,
        #[Autowire(service: 'cache.config')]
        private CacheInterface $cache,
        private KernelInterface $kernel,
        private AppStateService $appState,
        private ExtendedFilesystem $fs,
    ) {}

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

    public function getWebsiteImageId(): ?int
    {
        $id = $this->getInt('website_image_id', 0);

        return $id > 0 ? $id : null;
    }

    public function setWebsiteImageId(?int $id): void
    {
        $this->setInt('website_image_id', $id ?? 0);
    }

    public function getMailerAddress(): Address
    {
        return new Address($this->getString('email_sender_mail', 'sender@email.com'), $this->getString('email_sender_name', 'email sender'));
    }

    public function isShowFrontpage(): bool
    {
        return $this->getBoolean('show_frontpage', true);
    }

    public function isShowTownHall(): bool
    {
        return $this->getBoolean('show_town_hall', false);
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
            $item->expiresAfter(null);

            return $this->parseConfigScss();
        });
    }

    public function saveColors(array $colors): void
    {
        $path = $this->kernel->getProjectDir() . '/assets/styles/_config.scss';
        $content = $this->fs->getFileContents($path);
        if ($content === false) {
            return;
        }

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

            $content = preg_replace('/(\$' . preg_quote($scssVar, '/') . '\s*:\s*)#[0-9a-fA-F]{3,6}(\s*;)/', '${1}' . $value . '${2}', $content);
        }

        if (is_string($content)) {
            $this->fs->putFileContents($path, $content);
        }
        $this->cache->delete(self::CACHE_KEY_THEME_COLORS);
    }

    private function parseConfigScss(): array
    {
        $path = $this->kernel->getProjectDir() . '/assets/styles/_config.scss';
        $content = $this->fs->getFileContents($path);
        if ($content === false) {
            return [];
        }

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
            $item->expiresAfter(null);

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
