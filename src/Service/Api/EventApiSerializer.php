<?php declare(strict_types=1);

namespace App\Service\Api;

use App\Entity\Event;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Serialises an `Event` into the shape returned by the public JSON API.
 *
 * Locale selection is the caller's responsibility; pass the resolved locale in.
 * `$baseUrl` is the absolute scheme+host from the current request (e.g. tenant
 * domain) so the emitted `url` and `previewImageUrl` point at the requesting host.
 */
final readonly class EventApiSerializer
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSummary(Event $event, string $locale, string $baseUrl): array
    {
        return [
            'id' => $event->getId(),
            'title' => $event->getTitle($locale),
            'start' => $event->getStart()->format('c'),
            'end' => $event->getStop()?->format('c'),
            'location' => $event->getLocation()?->getName() ?? '',
            'url' => $this->buildEventUrl($event, $locale, $baseUrl),
            'previewImageUrl' => $this->buildImageUrl($event, $baseUrl),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetail(Event $event, string $locale, string $baseUrl): array
    {
        return [
            ...$this->toSummary($event, $locale, $baseUrl),
            'description' => strip_tags($event->getDescription($locale)),
        ];
    }

    private function buildEventUrl(Event $event, string $locale, string $baseUrl): string
    {
        $path = $this->urlGenerator->generate(
            'app_event_details',
            ['_locale' => $locale, 'id' => $event->getId()],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        return rtrim($baseUrl, '/') . $path;
    }

    private function buildImageUrl(Event $event, string $baseUrl): ?string
    {
        $image = $event->getPreviewImage();
        if ($image === null) {
            return null;
        }

        return sprintf('%s/images/thumbnails/%s_600x400.webp', rtrim($baseUrl, '/'), $image->getHash());
    }
}
