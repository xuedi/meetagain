<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Entity\Event;
use App\Entity\Host;
use App\Publisher\OrganizationSchema\OrganizationSchemaProviderInterface;
use App\Service\Config\ConfigService;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class EventSchemaService
{
    private const string DEFAULT_IMAGE_PATH = '/images/locations/default.jpg';
    private const string OFFER_CURRENCY = 'EUR';

    /**
     * @param iterable<OrganizationSchemaProviderInterface> $organizationProviders
     */
    public function __construct(
        private ConfigService $configService,
        #[AutowireIterator(OrganizationSchemaProviderInterface::class)]
        private iterable $organizationProviders,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildSchema(Event $event, string $canonicalUrl, string $locale): array
    {
        $organizer = $this->resolveOrganizer($event);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->getTitle($locale),
            'startDate' => $event->getStart()->format('c'),
            'url' => $canonicalUrl,
            'eventStatus' => $event->isCanceled() ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'description' => $this->resolveDescription($event, $locale),
            'image' => $this->resolveImage($event, $organizer),
            'organizer' => $organizer,
            'performer' => $organizer,
            'offers' => $this->buildOffer($event, $canonicalUrl),
        ];

        if ($event->getStop() !== null) {
            $schema['endDate'] = $event->getStop()->format('c');
        }

        $location = $event->getLocation();
        if ($location !== null) {
            $locationSchema = [
                '@type' => 'Place',
                'name' => $location->getName() ?? '',
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $location->getStreet() ?? '',
                    'postalCode' => $location->getPostcode() ?? '',
                    'addressLocality' => $location->getCity() ?? '',
                ],
            ];

            if ($location->getLatitude() !== null && $location->getLongitude() !== null) {
                $locationSchema['geo'] = [
                    '@type' => 'GeoCoordinates',
                    'latitude' => (float) $location->getLatitude(),
                    'longitude' => (float) $location->getLongitude(),
                ];
            }

            $schema['location'] = $locationSchema;
        }

        return $schema;
    }

    /**
     * @return array<string, string>
     */
    private function buildOffer(Event $event, string $canonicalUrl): array
    {
        $rsvpOpen = !$event->isCanceled() && $event->getStart() > $this->clock->now();
        $validFrom = $event->getCreatedAt() ?? $event->getStart();

        return [
            '@type' => 'Offer',
            'url' => $canonicalUrl,
            'price' => '0',
            'priceCurrency' => self::OFFER_CURRENCY,
            'availability' => $rsvpOpen ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            'validFrom' => $validFrom->format('c'),
        ];
    }

    private function resolveDescription(Event $event, string $locale): string
    {
        $teaser = $event->getTeaser($locale);
        if ($teaser !== '') {
            return substr($teaser, 0, 500);
        }

        $description = strip_tags($event->getDescription($locale));
        if ($description !== '') {
            return substr($description, 0, 500);
        }

        return $event->getTitle($locale);
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $organizer
     *
     * @return array<int, string>
     */
    private function resolveImage(Event $event, array $organizer): array
    {
        $host = rtrim($this->configService->getHost(), '/');

        $previewImage = $event->getPreviewImage();
        if ($previewImage !== null && $previewImage->getHash() !== null) {
            return [
                sprintf('%s/images/thumbnails/%s_600x400.webp', $host, $previewImage->getHash()),
            ];
        }

        $organizerLogo = $this->extractOrganizerLogo($organizer);
        if ($organizerLogo !== null) {
            return [$organizerLogo];
        }

        return [$host . self::DEFAULT_IMAGE_PATH];
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $organizer
     */
    private function extractOrganizerLogo(array $organizer): ?string
    {
        $candidates = array_is_list($organizer) ? $organizer : [$organizer];
        foreach ($candidates as $candidate) {
            if (isset($candidate['logo']) && is_string($candidate['logo']) && $candidate['logo'] !== '') {
                return $candidate['logo'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    private function resolveOrganizer(Event $event): array
    {
        $hosts = $event->getHost();
        if ($hosts->count() > 0) {
            $built = [];
            foreach ($hosts as $host) {
                if (!$host instanceof Host) {
                    continue;
                }

                $built[] = $this->buildOrganizationFromHost($host);
            }
            if ($built !== []) {
                return count($built) === 1 ? $built[0] : $built;
            }
        }

        return $this->resolvePlatformOrganizer();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrganizationFromHost(Host $host): array
    {
        $platformHost = rtrim($this->configService->getHost(), '/');

        return [
            '@type' => 'Organization',
            'name' => $host->getName() ?? '',
            'url' => $platformHost,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePlatformOrganizer(): array
    {
        foreach ($this->organizationProviders as $provider) {
            $org = $provider->getOrganizationSchema();
            if ($org !== null) {
                return $org;
            }
        }

        $host = rtrim($this->configService->getHost(), '/');

        return [
            '@type' => 'Organization',
            '@id' => $host . '/#organization',
            'name' => 'MeetAgain',
            'url' => $host,
        ];
    }
}
