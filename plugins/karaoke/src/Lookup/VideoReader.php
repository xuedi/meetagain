<?php declare(strict_types=1);

namespace Plugin\Karaoke\Lookup;

use DateInterval;
use Plugin\Karaoke\Enum\MediaProvider;
use Plugin\Karaoke\Service\ConfigService;
use Plugin\Karaoke\ValueObject\MediaLink;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

readonly class VideoReader
{
    private const int CACHE_SECONDS = 86_400;

    public function __construct(
        private JsonClient $client,
        private ConfigService $configService,
        #[Autowire(service: 'cache.karaoke_lookup')]
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    public function read(MediaLink $link, ?string $pastedUrl = null): ?Video
    {
        try {
            $raw = $this->cache->get('karaoke_video_' . $link->provider->value . '_' . sha1($link->id), function (ItemInterface $item) use (
                $link,
                $pastedUrl,
            ): array {
                $item->expiresAfter(self::CACHE_SECONDS);

                return match ($link->provider) {
                    MediaProvider::YouTube => $this->youtube($link->id),
                    MediaProvider::TikTok => $this->oembed('https://www.tiktok.com/oembed', $this->tiktokUrl($link->id, $pastedUrl)),
                    MediaProvider::Bilibili => $this->bilibili($link->id),
                };
            });
        } catch (Throwable $exception) {
            $this->logger->warning('Karaoke: reading the video metadata failed: ' . $exception->getMessage(), [
                'provider' => $link->provider->value,
                'id' => $link->id,
            ]);

            return null;
        }

        $title = (string) ($raw['title'] ?? '');
        if ($title === '') {
            return null;
        }

        return new Video(
            title: $title,
            author: isset($raw['author']) && $raw['author'] !== '' ? (string) $raw['author'] : null,
            durationSeconds: isset($raw['duration']) ? (int) $raw['duration'] : null,
        );
    }

    /** @return array{title: string, author: ?string, duration: ?int} */
    private function youtube(string $id): array
    {
        $video = $this->oembed('https://www.youtube.com/oembed', MediaProvider::YouTube->getWatchUrl($id));

        $apiKey = $this->configService->getYoutubeApiKey();
        if ($apiKey === null) {
            return $video;
        }

        $details = $this->client->get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'contentDetails',
            'id' => $id,
            'key' => $apiKey,
        ]);
        $isoDuration = $details['items'][0]['contentDetails']['duration'] ?? null;
        if (is_string($isoDuration)) {
            $video['duration'] = $this->isoSeconds($isoDuration);
        }

        return $video;
    }

    /** @return array{title: string, author: ?string, duration: ?int} */
    private function oembed(string $endpoint, string $url): array
    {
        $data = $this->client->get($endpoint, ['url' => $url, 'format' => 'json']) ?? [];

        return [
            'title' => (string) ($data['title'] ?? ''),
            'author' => isset($data['author_name']) ? (string) $data['author_name'] : null,
            'duration' => null,
        ];
    }

    /** @return array{title: string, author: ?string, duration: ?int} */
    private function bilibili(string $bvid): array
    {
        $data = $this->client->get('https://api.bilibili.com/x/web-interface/view', ['bvid' => $bvid]) ?? [];
        $video = is_array($data['data'] ?? null) ? $data['data'] : [];

        return [
            'title' => (string) ($video['title'] ?? ''),
            'author' => isset($video['owner']['name']) ? (string) $video['owner']['name'] : null,
            'duration' => isset($video['duration']) ? (int) $video['duration'] : null,
        ];
    }

    private function tiktokUrl(string $id, ?string $pastedUrl): string
    {
        $isFullVideoUrl = $pastedUrl !== null && preg_match('~^https?://(?:www\.|m\.)?tiktok\.com/@[\w.-]+/video/\d+~i', $pastedUrl) === 1;

        return $isFullVideoUrl ? (string) $pastedUrl : sprintf('https://www.tiktok.com/@/video/%s', $id);
    }

    private function isoSeconds(string $isoDuration): ?int
    {
        try {
            $interval = new DateInterval($isoDuration);
        } catch (Throwable) {
            return null;
        }

        return ($interval->d * 86_400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }
}
