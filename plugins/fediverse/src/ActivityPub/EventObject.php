<?php declare(strict_types=1);

namespace Plugin\Fediverse\ActivityPub;

use App\Entity\Event;
use App\Entity\Location as Venue;
use App\Service\Config\LanguageService;
use App\Service\Seo\UrlOwnerService;
use DateTimeImmutable;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Entity\Publication;
use Plugin\Fediverse\Enum\Content;
use Plugin\Fediverse\Enum\Location;
use Plugin\Fediverse\Repository\PublicationRepository;
use Plugin\Fediverse\ValueObject\Config;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class EventObject
{
    public const string PUBLIC = 'https://www.w3.org/ns/activitystreams#Public';
    public const int SUMMARY_MAX = 500;
    public const int OUTBOX_SIZE = 20;
    public const array CONTEXT = [
        'https://www.w3.org/ns/activitystreams',
        [
            'mz' => 'https://mobilizon.org/ns#',
            'sc' => 'http://schema.org#',
            'ical' => 'http://www.w3.org/2002/12/cal/ical#',
            'joinMode' => ['@id' => 'mz:joinMode', '@type' => 'mz:joinModeType'],
            'externalParticipationUrl' => ['@id' => 'mz:externalParticipationUrl', '@type' => 'sc:URL'],
        ],
    ];

    private const string EVENT_ROUTE = 'app_event_details';
    private const string OBJECT_ROUTE = 'app_plugin_fediverse_event';

    public function __construct(
        private Actor $actor,
        private UrlGeneratorInterface $urlGenerator,
        private LanguageService $languageService,
        private UrlOwnerService $urlOwnerService,
        private PublicationRepository $publications,
        private TranslatorInterface $translator,
    ) {}

    public function id(Publication $publication): string
    {
        return $this->actor->accountOrigin() . $this->urlGenerator->generate(self::OBJECT_ROUTE, ['id' => $publication->getId()]);
    }

    /** @return array<string, mixed> */
    public function tombstone(Publication $publication): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $this->id($publication),
            'type' => 'Tombstone',
            'formerType' => 'Event',
            'deleted' => $publication->getDeletedAt()?->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function outbox(Account $account): array
    {
        $items = [];
        foreach ($this->publications->findLiveFor($account, self::OUTBOX_SIZE) as $publication) {
            $items[] = $this->activity('Create', $account, $publication->getDocument(), $publication->getHash(), $publication->getCreatedAt());
        }

        return [
            '@context' => self::CONTEXT,
            'id' => $this->actor->id($account) . '/outbox',
            'type' => 'OrderedCollection',
            'totalItems' => $this->publications->countLiveFor($account),
            'orderedItems' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $object
     * @return array<string, mixed>
     */
    public function activity(string $type, Account $account, array $object, string $hash, DateTimeImmutable $published): array
    {
        unset($object['@context']);
        $actorId = $this->actor->id($account);

        return [
            '@context' => self::CONTEXT,
            'id' => $object['id'] . '#' . strtolower($type) . '-' . substr($hash, 0, 12),
            'type' => $type,
            'actor' => $actorId,
            'published' => $published->format(DATE_ATOM),
            'to' => [self::PUBLIC],
            'cc' => [$actorId . '/followers'],
            'object' => $object,
        ];
    }

    public function defaultLocale(): string
    {
        return $this->languageService->getFilteredDefaultLocale();
    }

    public function pageUrl(Event $event, string $locale): string
    {
        return $this->origin($event) . $this->urlGenerator->generate(self::EVENT_ROUTE, ['_locale' => $locale, 'id' => $event->getId()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Event $event, Account $account, Config $config): array
    {
        $default = $this->defaultLocale();
        $url = $this->pageUrl($event, $default);
        $names = [];
        $summaries = [];
        $contents = [];
        foreach ($this->languageService->getFilteredEnabledCodes() as $locale) {
            $name = trim($event->getTitle($locale));
            if ($name === '') {
                continue;
            }

            $names[$locale] = $name;
            $summary = $config->getContent() === Content::Title ? null : $this->summary($event, $locale);
            $notice = $event->isCanceled() ? $this->paragraph($this->translator->trans('events.notification_event_canceled', [], null, $locale)) : '';
            if ($summary !== null || $notice !== '') {
                $summaries[$locale] = $notice . $summary;
            }
            $content = $config->getContent() === Content::Full ? $this->description($event, $locale) : $summary;
            if ($content !== null) {
                $contents[$locale] = $content;
            }
        }
        $primary = isset($names[$default]) ? $default : array_key_first($names);
        $actorId = $this->actor->id($account);

        $document = [
            'type' => 'Event',
            'name' => $primary === null ? '' : $names[$primary],
            'nameMap' => $names,
            'url' => $url,
            'attributedTo' => $actorId,
            'to' => [self::PUBLIC],
            'cc' => [$actorId . '/followers'],
            'startTime' => $event->getStart()->format(DATE_ATOM),
            'joinMode' => 'external',
            'externalParticipationUrl' => $url,
            'ical:status' => $event->isCanceled() ? 'CANCELLED' : 'CONFIRMED',
        ];
        if ($event->getStop() !== null) {
            $document['endTime'] = $event->getStop()->format(DATE_ATOM);
        }

        if ($summaries !== []) {
            $document['summary'] = $summaries[$primary] ?? reset($summaries);
            $document['summaryMap'] = $summaries;
        }

        if ($contents !== []) {
            $document['content'] = $contents[$primary] ?? reset($contents);
            $document['contentMap'] = $contents;
        }

        $place = $this->place($event->getLocation(), $config->getLocation());
        if ($place !== null) {
            $document['location'] = $place;
        }

        $image = $event->getPreviewImage()?->getHash();
        if ($config->isImage() && $image !== null) {
            $document['attachment'] = [[
                'type' => 'Document',
                'mediaType' => 'image/webp',
                'url' => sprintf('%s/images/thumbnails/%s_600x400.webp', $this->origin($event), $image),
                'name' => 'Banner',
            ]];
        }

        return $document;
    }

    private function origin(Event $event): string
    {
        return $this->urlOwnerService->getOwnerHost(self::EVENT_ROUTE, ['id' => $event->getId()]);
    }

    private function description(Event $event, string $locale): ?string
    {
        $description = trim($event->getDescription($locale));

        return $description === '' ? null : $description;
    }

    private function summary(Event $event, string $locale): ?string
    {
        $summary = trim($event->getTeaser($locale));
        if ($summary === '') {
            $summary = trim(preg_replace('/\s+/u', ' ', strip_tags($event->getDescription($locale))) ?? '');
            if (mb_strlen($summary) > self::SUMMARY_MAX) {
                $summary = rtrim(mb_substr($summary, 0, self::SUMMARY_MAX - 1)) . '…';
            }
        }

        return $summary === '' ? null : $this->paragraph($summary);
    }

    private function paragraph(string $text): string
    {
        return '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_HTML5) . '</p>';
    }

    /** @return array<string, mixed>|null */
    private function place(?Venue $venue, Location $location): ?array
    {
        $city = trim((string) $venue?->getCity());
        if (!$venue instanceof Venue || $location === Location::None || $city === '') {
            return null;
        }

        if ($location === Location::City) {
            return ['type' => 'Place', 'name' => $city, 'address' => ['type' => 'PostalAddress', 'addressLocality' => $city]];
        }

        $place = [
            'type' => 'Place',
            'name' => trim((string) $venue->getName()) ?: $city,
            'address' => array_filter(
                [
                    'type' => 'PostalAddress',
                    'streetAddress' => trim((string) $venue->getStreet()),
                    'postalCode' => trim((string) $venue->getPostcode()),
                    'addressLocality' => $city,
                ],
                static fn(string $value): bool => $value !== '',
            ),
        ];
        if ($venue->getLatitude() !== null && $venue->getLongitude() !== null) {
            $place['latitude'] = (float) $venue->getLatitude();
            $place['longitude'] = (float) $venue->getLongitude();
        }

        return $place;
    }
}
