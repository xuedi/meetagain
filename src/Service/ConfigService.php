<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Config;
use App\Entity\ConfigType;
use App\Entity\ImageType;
use App\Repository\ConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class ConfigService
{
    private const string CACHE_KEY_THEME_COLORS = 'theme_colors';

    public function __construct(
        private ConfigRepository $repo,
        private EntityManagerInterface $em,
        private CacheInterface $cache,
    ) {
    }

    public function getThumbnailSizes(ImageType $type): array
    {
        return match ($type) {
            ImageType::ProfilePicture => [[400, 400], [80, 80], [50, 50]],
            ImageType::EventTeaser => [[1024, 768], [600, 400], [210, 140]], // included EventUpload
            ImageType::EventUpload => [[1024, 768], [210, 140]],
            ImageType::CmsBlock => [[432, 432], [80, 80]],
            ImageType::PluginDishPreview => [[1024, 768], [400, 400], [100, 100], [50, 50]], // is also part of gallery
            ImageType::PluginDishGallery => [[1024, 768], [400, 400]],
            ImageType::LanguageTile => [[600, 400], [300, 200]],
        };
    }

    public function getThumbnailSizeList(): array
    {
        return [
            '1024x768' => 0, // gallery image bit
            '600x400' => 0, // event preview image
            '432x432' => 0, // cmsBlock image
            '400x400' => 0, // profile big
            '210x140' => 0, // gallery image preview
            '80x80' => 0, // ?
            '50x50' => 0, // ?
        ];
    }

    public function isValidThumbnailSize(ImageType $type, int $checkWidth, int $checkHeight): bool
    {
        foreach ($this->getThumbnailSizes($type) as [$width, $height]) {
            if ($checkWidth == $width && $checkHeight == $height) {
                return true;
            }
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

    public function getSystemUserId(): int
    {
        return $this->getInt('system_user_id', 1);
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
            throw new \RuntimeException(sprintf("Config '%s' not found", $name));
        }

        $value = $setting->getValue() !== 'true';
        $setting->setValue($value ? 'true' : 'false');

        $this->em->persist($setting);
        $this->em->flush();

        return $value;
    }

    public function saveForm(array $formData): void
    {
        $this->setString('website_url', $formData['url']);
        $this->setString('website_host', $formData['host']);
        $this->setString('email_sender_name', $formData['senderName']);
        $this->setString('email_sender_mail', $formData['senderEmail']);
        $this->setInt('system_user_id', $formData['systemUser']);
        $this->setString('date_format', $formData['dateFormat']);
    }

    public function getThemeColorDefaults(): array
    {
        return [
            'color_primary' => '#00d1b2',
            'color_link' => '#485fc7',
            'color_info' => '#3e8ed0',
            'color_success' => '#48c78e',
            'color_warning' => '#ffe08a',
            'color_danger' => '#f14668',
            'color_text_grey' => '#767676',
            'color_text_grey_light' => '#959595',
        ];
    }

    public function getThemeColors(): array
    {
        return $this->cache->get(self::CACHE_KEY_THEME_COLORS, function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            $defaults = $this->getThemeColorDefaults();
            $colorNames = array_keys($defaults);

            $configs = $this->repo->findBy(['name' => $colorNames]);
            $dbColors = [];
            foreach ($configs as $config) {
                $dbColors[$config->getName()] = $config->getValue();
            }

            return array_merge($defaults, $dbColors);
        });
    }

    public function getColor(string $name, string $default): string
    {
        return $this->getString('color_' . $name, $default);
    }

    public function saveColors(array $colors): void
    {
        foreach ($colors as $name => $value) {
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value)) {
                $this->setString($name, $value);
            }
        }
        $this->cache->delete(self::CACHE_KEY_THEME_COLORS);
    }

    private function getBoolean(string $name, bool $default = false): bool
    {
        $setting = $this->repo->findOneBy(['name' => $name]);
        if ($setting === null) {
            return $default;
        }

        return $setting->getValue() === 'true';
    }

    private function getString(string $name, string $default): string
    {
        $setting = $this->repo->findOneBy(['name' => $name]);
        if ($setting === null) {
            return $default;
        }

        return $setting->getValue();
    }

    private function setString(string $name, string $value): void
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
    }

    private function getInt(string $name, int $default): int
    {
        $setting = $this->repo->findOneBy(['name' => $name]);
        if ($setting === null) {
            return $default;
        }

        return (int) $setting->getValue();
    }

    private function setInt(string $name, int $value): void
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
    }
}
