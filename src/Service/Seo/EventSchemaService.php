<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Entity\Event;
use App\Publisher\OrganizationSchema\OrganizationSchemaProviderInterface;
use App\Service\Config\ConfigService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Builds a schema.org/Event JSON-LD array for use in templates.
 * Covers all Google Event rich result required and recommended fields.
 */
final readonly class EventSchemaService
{
    /**
     * @param iterable<OrganizationSchemaProviderInterface> $organizationProviders
     */
    public function __construct(
        private ConfigService $configService,
        #[AutowireIterator(OrganizationSchemaProviderInterface::class)]
        private iterable $organizationProviders,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildSchema(Event $event, string $canonicalUrl, string $locale): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->getTitle($locale),
            'startDate' => $event->getStart()->format('c'),
            'url' => $canonicalUrl,
            'eventStatus' => $event->isCanceled()
                ? 'https://schema.org/EventCancelled'
                : 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        ];

        if ($event->getStop() !== null) {
            $schema['endDate'] = $event->getStop()->format('c');
        }

        $description = $event->getTeaser($locale) ?: strip_tags($event->getDescription($locale));
        if ($description !== '') {
            $schema['description'] = substr($description, 0, 500);
        }

        if ($event->getPreviewImage() !== null) {
            $image = $event->getPreviewImage();
            $host = rtrim($this->configService->getHost(), '/');
            $schema['image'] = [
                sprintf('%s/images/thumbnails/%s_600x400.webp', $host, $image->getHash()),
            ];
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

        $schema['organizer'] = $this->resolveOrganizer();

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOrganizer(): array
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
