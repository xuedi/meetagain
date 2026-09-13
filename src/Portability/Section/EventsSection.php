<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\Event;
use App\Entity\EventSeries;
use App\Entity\EventTranslation;
use App\Entity\Host;
use App\Entity\Image;
use App\Entity\Location;
use App\Entity\User;
use App\Enum\EventInterval;
use App\Enum\EventStatus;
use App\Enum\EventType;
use App\Enum\ImageType;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\Repository\LocationRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class EventsSection implements SectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocationRepository $locationRepository,
        private UserRepository $userRepository,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'events';
    }

    #[Override]
    public function getOrder(): int
    {
        return 40;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        if ($scope->eventIds === []) {
            return [];
        }

        $rows = [];
        foreach ($this->em->getRepository(Event::class)->findBy(['id' => $scope->eventIds], ['id' => 'ASC']) as $event) {
            $titles = [];
            $descriptions = [];
            $teasers = [];
            foreach ($event->getTranslation() as $translation) {
                $language = $translation->getLanguage();
                $titles[$language] = $translation->getTitle();
                $descriptions[$language] = $translation->getDescription();
                if ($translation->getTeaser() !== null) {
                    $teasers[$language] = $translation->getTeaser();
                }
            }
            ksort($titles);
            ksort($descriptions);
            ksort($teasers);

            $hostRefs = array_values(array_map(static fn(Host $host): int => (int) $host->getId(), $event->getHost()->toArray()));
            sort($hostRefs);

            $rows[] = [
                'ref' => (int) $event->getId(),
                'initial' => $event->isInitial() ?? true,
                'titles' => $titles,
                'descriptions' => $descriptions,
                'teasers' => $teasers,
                'start' => $event->getStart()->format(DateTimeInterface::ATOM),
                'stop' => $event->getStop()?->format(DateTimeInterface::ATOM),
                'status' => $event->getStatus()->value,
                'type' => $event->getType()?->name,
                'series_ref' => $event->getSeries()?->getId(),
                'featured' => $event->isFeatured() ?? false,
                'location_ref' => $event->getLocation()?->getId(),
                'host_refs' => $hostRefs,
                'external_rsvp' => $event->getExternalRsvp(),
                'creator_email' => $scope->creditEmail($event->getUser()),
                'image_file' => $event->getPreviewImage() instanceof Image ? $images->addImage($event->getPreviewImage()) : null,
            ];
        }

        return $rows;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $event = new Event();
            $event->setInitial((bool) ($row['initial'] ?? true));
            $event->setExternalRsvp((int) ($row['external_rsvp'] ?? 0));
            $event->setFeatured((bool) ($row['featured'] ?? false));
            $event->setCanceled(false);
            $event->setCreatedAt(new DateTimeImmutable());
            $event->setStart(new DateTime((string) ($row['start'] ?? '')));
            $event->setLocation($this->resolveLocation($row, $context));
            $event->setStatus(EventStatus::tryFrom((string) ($row['status'] ?? '')) ?? EventStatus::Published);

            if (isset($row['stop']) && $row['stop'] !== '') {
                $event->setStop(new DateTime((string) $row['stop']));
            }

            if (isset($row['type']) && $row['type'] !== '') {
                $event->setType(array_find(EventType::cases(), static fn(EventType $case): bool => $case->name === $row['type']));
            }

            $series = $context->resolveRef(EventSeries::class, $row['series_ref'] ?? null);
            if ($series instanceof EventSeries) {
                $event->setSeries($series);
            } elseif (isset($row['recurring_rule']) && $row['recurring_rule'] !== '') {
                // pre-1.1 exports carried the rule on the event - synthesize a series for it
                $event->setSeries($this->createLegacySeries($row));
            }

            $creatorEmail = (string) ($row['creator_email'] ?? '');
            $event->setUser(
                $context->resolveRef(User::class, $creatorEmail)
                ?? $this->userRepository->findOneBy(['email' => $creatorEmail])
                ?? $context->getSystemUser(),
            );

            $this->addTranslations($event, $row);

            foreach (is_array($row['host_refs'] ?? null) ? $row['host_refs'] : [] as $hostRef) {
                $host = $context->resolveRef(Host::class, $hostRef);
                if ($host instanceof Host) {
                    $event->addHost($host);
                }
            }

            $image = $context->importImage($row['image_file'] ?? null, ImageType::EventTeaser);
            if ($image instanceof Image) {
                $event->setPreviewImage($image);
            }

            $this->em->persist($event);
            if (isset($row['ref'])) {
                $context->mapRef(Event::class, (int) $row['ref'], $event);
            }
            $context->count($this->getKey(), Outcome::Created);
        }
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function addTranslations(Event $event, array $row): void
    {
        $titles = is_array($row['titles'] ?? null) ? $row['titles'] : [];
        $descriptions = is_array($row['descriptions'] ?? null) ? $row['descriptions'] : [];
        $teasers = is_array($row['teasers'] ?? null) ? $row['teasers'] : [];

        foreach ($titles as $language => $title) {
            $translation = new EventTranslation();
            $translation->setLanguage((string) $language);
            $translation->setTitle((string) $title);
            $translation->setDescription((string) ($descriptions[$language] ?? ''));
            $translation->setTeaser(isset($teasers[$language]) ? (string) $teasers[$language] : null);
            $event->addTranslation($translation);
            $this->em->persist($translation);
        }
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function resolveLocation(array $row, ImportContext $context): Location
    {
        return $context->resolveRef(Location::class, $row['location_ref'] ?? null)
            ?? $this->locationRepository->findOneBy([])
            ?? $this->createFallbackLocation($context->getSystemUser());
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function createLegacySeries(array $row): EventSeries
    {
        $titles = is_array($row['titles'] ?? null) ? $row['titles'] : [];
        $name = (string) ($titles['en'] ?? (reset($titles) ?: 'Imported series'));

        $series = new EventSeries();
        $series->setName($name !== '' ? $name : 'Imported series');
        $series->setRule(array_find(EventInterval::cases(), static fn(EventInterval $case): bool => $case->name === $row['recurring_rule']));
        $series->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($series);

        return $series;
    }

    private function createFallbackLocation(User $user): Location
    {
        $location = new Location();
        $location->setName('Unknown');
        $location->setDescription('');
        $location->setStreet('');
        $location->setCity('');
        $location->setPostcode('');
        $location->setUser($user);
        $location->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($location);

        return $location;
    }
}
