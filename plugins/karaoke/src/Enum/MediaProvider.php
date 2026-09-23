<?php declare(strict_types=1);

namespace Plugin\Karaoke\Enum;

enum MediaProvider: string
{
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case Bilibili = 'bilibili';

    public function getLabel(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube',
            self::TikTok => 'TikTok',
            self::Bilibili => 'Bilibili',
        };
    }

    /** @return list<string> */
    public function getUrlPatterns(): array
    {
        return match ($this) {
            self::YouTube => [
                '~^https?://(?:www\.|m\.|music\.)?youtube\.com/watch\?(?:[^#]*&)?v=(?<id>[\w-]{11})(?![\w-])~i',
                '~^https?://(?:www\.)?youtu\.be/(?<id>[\w-]{11})(?![\w-])~i',
                '~^https?://(?:www\.|m\.)?youtube\.com/(?:shorts|embed|live)/(?<id>[\w-]{11})(?![\w-])~i',
                '~^https?://(?:www\.)?youtube-nocookie\.com/embed/(?<id>[\w-]{11})(?![\w-])~i',
            ],
            self::TikTok => [
                '~^https?://(?:www\.|m\.)?tiktok\.com/@[\w.-]+/video/(?<id>\d{8,25})(?!\d)~i',
                '~^https?://(?:www\.)?tiktok\.com/(?:player/v1|embed/v2|embed)/(?<id>\d{8,25})(?!\d)~i',
            ],
            self::Bilibili => [
                '~^https?://(?:www\.|m\.)?bilibili\.com/video/(?<id>BV[0-9A-Za-z]{10})(?![0-9A-Za-z])~',
                '~^https?://player\.bilibili\.com/player\.html\?(?:[^#]*&)?bvid=(?<id>BV[0-9A-Za-z]{10})(?![0-9A-Za-z])~',
            ],
        };
    }

    public function isValidId(string $id): bool
    {
        $pattern = match ($this) {
            self::YouTube => '~^[\w-]{11}$~',
            self::TikTok => '~^\d{8,25}$~',
            self::Bilibili => '~^BV[0-9A-Za-z]{10}$~',
        };

        return preg_match($pattern, $id) === 1;
    }

    public function getEmbedUrl(string $id): string
    {
        return match ($this) {
            self::YouTube => sprintf('https://www.youtube-nocookie.com/embed/%s?enablejsapi=1&rel=0', $id),
            self::TikTok => sprintf('https://www.tiktok.com/player/v1/%s?music_info=0&description=0&rel=0', $id),
            self::Bilibili => sprintf('https://player.bilibili.com/player.html?bvid=%s&autoplay=0', $id),
        };
    }

    public function getWatchUrl(string $id): string
    {
        return match ($this) {
            self::YouTube => sprintf('https://www.youtube.com/watch?v=%s', $id),
            self::TikTok => sprintf('https://www.tiktok.com/embed/v2/%s', $id),
            self::Bilibili => sprintf('https://www.bilibili.com/video/%s', $id),
        };
    }

    public function reportsTime(): bool
    {
        return $this !== self::Bilibili;
    }
}
