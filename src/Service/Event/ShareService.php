<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Entity\Event;
use App\Entity\Location;
use App\Service\Seo\UrlOwnerService;
use App\ValueObject\EventShareSheet;
use App\ValueObject\ShareLink;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ShareService
{
    private const int TEASER_LIMIT = 400;
    private const int QR_SVG_SIZE = 300;
    private const int QR_PNG_SIZE = 600;

    public function __construct(
        private UrlOwnerService $urlOwnerService,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function buildSheet(Event $event, string $locale): EventShareSheet
    {
        $title = $event->getTitle($locale);
        $teaser = $this->plainText($event->getTeaser($locale));
        $shareUrl = $this->buildShareUrl($event, $locale);

        return new EventShareSheet(
            title: $title,
            teaser: $teaser,
            shareUrl: $shareUrl,
            shareTargets: $this->buildShareTargets($title, $teaser, $shareUrl),
            mapLinks: $this->buildMapLinks($event->getLocation()),
            qrSvg: $this->buildQrSvg($shareUrl),
            qrPngUrl: $this->urlGenerator->generate('app_event_share_qr', ['id' => $event->getId()]),
        );
    }

    public function buildQrPng(Event $event, string $locale): string
    {
        return new Builder(
            writer: new PngWriter(),
            data: $this->buildShareUrl($event, $locale),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: self::QR_PNG_SIZE,
            margin: 16,
        )->build()->getString();
    }

    public function buildShareUrl(Event $event, string $locale): string
    {
        $id = $event->getId();
        $path = $this->urlGenerator->generate('app_event_details', ['_locale' => $locale, 'id' => $id]);

        return $this->urlOwnerService->getOwnerHost('app_event_details', ['id' => $id]) . $path;
    }

    /**
     * @return list<ShareLink>
     */
    private function buildShareTargets(string $title, string $teaser, string $shareUrl): array
    {
        $encodedUrl = rawurlencode($shareUrl);
        $encodedTitle = rawurlencode($title);
        $mailBody = rawurlencode($teaser === '' ? $shareUrl : $teaser . "\n\n" . $shareUrl);

        return [
            new ShareLink(
                key: 'whatsapp',
                label: 'events.share_target_whatsapp',
                url: 'https://wa.me/?text=' . rawurlencode($title . ' ' . $shareUrl),
            ),
            new ShareLink(
                key: 'telegram',
                label: 'events.share_target_telegram',
                url: 'https://t.me/share/url?url=' . $encodedUrl . '&text=' . $encodedTitle,
            ),
            new ShareLink(
                key: 'facebook',
                label: 'events.share_target_facebook',
                url: 'https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl,
            ),
            new ShareLink(
                key: 'x',
                label: 'events.share_target_x',
                url: 'https://twitter.com/intent/tweet?url=' . $encodedUrl . '&text=' . $encodedTitle,
            ),
            new ShareLink(
                key: 'linkedin',
                label: 'events.share_target_linkedin',
                url: 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encodedUrl,
            ),
            new ShareLink(
                key: 'email',
                label: 'events.share_target_email',
                url: 'mailto:?subject=' . $encodedTitle . '&body=' . $mailBody,
                icon: 'fa-regular fa-envelope',
            ),
        ];
    }

    /**
     * @return list<ShareLink>
     */
    private function buildMapLinks(?Location $location): array
    {
        if (!$location instanceof Location) {
            return [];
        }

        $address = $this->buildAddress($location);
        $latitude = $this->coordinate($location->getLatitude());
        $longitude = $this->coordinate($location->getLongitude());
        $hasCoordinates = $latitude !== null && $longitude !== null;

        if (!$hasCoordinates && $address === '') {
            return [];
        }

        $query = rawurlencode($address);
        $destination = $hasCoordinates ? rawurlencode($latitude . ',' . $longitude) : $query;

        return [
            new ShareLink(
                key: 'osm',
                label: 'events.share_map_osm',
                url: $hasCoordinates
                    ? sprintf('https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=17/%s/%s', $latitude, $longitude, $latitude, $longitude)
                    : 'https://www.openstreetmap.org/search?query=' . $query,
                icon: 'fa-solid fa-map',
            ),
            new ShareLink(
                key: 'directions',
                label: 'events.share_map_directions',
                url: 'https://www.google.com/maps/dir/?api=1&destination=' . $destination,
                icon: 'fa-solid fa-directions',
            ),
        ];
    }

    private function buildQrSvg(string $shareUrl): string
    {
        return new Builder(
            writer: new SvgWriter(),
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            data: $shareUrl,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: self::QR_SVG_SIZE,
            margin: 8,
        )->build()->getString();
    }

    private function buildAddress(Location $location): string
    {
        $town = trim(($location->getPostcode() ?? '') . ' ' . ($location->getCity() ?? ''));
        $parts = array_filter(
            [$location->getName(), $location->getStreet(), $town],
            static fn(?string $part): bool => $part !== null && trim($part) !== '',
        );

        return implode(', ', $parts);
    }

    private function coordinate(?string $value): ?string
    {
        if ($value === null || trim($value) === '' || !is_numeric($value)) {
            return null;
        }

        return trim($value);
    }

    private function plainText(string $value): string
    {
        $decoded = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = trim(preg_replace('/\s+/u', ' ', $decoded) ?? $decoded);

        if (mb_strlen($collapsed) > self::TEASER_LIMIT) {
            return mb_substr($collapsed, 0, self::TEASER_LIMIT) . '...';
        }

        return $collapsed;
    }
}
