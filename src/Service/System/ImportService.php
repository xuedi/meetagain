<?php declare(strict_types=1);

namespace App\Service\System;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Entity\CmsLinkName;
use App\Entity\CmsMenuLocation;
use App\Entity\CmsTitle;
use App\Entity\Event;
use App\Entity\EventSeries;
use App\Entity\EventTranslation;
use App\Entity\Image;
use App\Entity\Location;
use App\Entity\User;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\EventInterval;
use App\Enum\EventStatus;
use App\Enum\EventType;
use App\Enum\ImageType;
use App\Enum\MenuLocation;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\ExtendedFilesystem;
use App\Item\Portability\ItemImportContext;
use App\Item\Portability\ItemPortabilityRegistry;
use App\Item\Portability\ItemTaxonomyPortability;
use App\Repository\LocationRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use ZipArchive;

readonly class ImportService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private LocationRepository $locationRepository,
        private ExtendedFilesystem $fs,
        private PortableImageImporter $imageImporter,
        private ItemPortabilityRegistry $itemRegistry,
        private ItemTaxonomyPortability $taxonomyPortability,
    ) {}

    public function import(string $zipPath): ImportSummary
    {
        $tempDir = sys_get_temp_dir() . '/meetagain-import-' . uniqid('', true);
        $this->fs->makeDirectory($tempDir);

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Could not open ZIP file');
            }
            $zip->extractTo($tempDir);
            $zip->close();

            $jsonPath = $tempDir . '/export.json';
            if (!$this->fs->fileExists($jsonPath)) {
                throw new \RuntimeException('Invalid export: export.json not found in ZIP');
            }

            $data = json_decode((string) $this->fs->getFileContents($jsonPath), true);
            if (!is_array($data) || ($data['format'] ?? '') !== 'meetagain-group-export') {
                throw new \RuntimeException('Invalid export format');
            }

            $systemUser = $this->userRepository->findOneBy(['email' => 'import@example.com']);
            if ($systemUser === null) {
                throw new \RuntimeException('System import user not found. Run fixtures first.');
            }

            $counts = [
                'usersCreated' => 0,
                'usersSkipped' => 0,
                'locationsCreated' => 0,
                'eventsCreated' => 0,
                'cmsPagesCreated' => 0,
                'cmsPagesSkipped' => 0,
                'itemSectionsSkipped' => 0,
                'taxonomyAssignmentsDropped' => 0,
            ];

            $locationRefMap = $this->importLocations($data['locations'] ?? [], $systemUser, $counts);
            $userEmailMap = $this->importUsers($data['users'] ?? [], $tempDir, $systemUser, $counts);
            $seriesRefMap = $this->importSeries($data['series'] ?? []);
            $this->importEvents($data['events'] ?? [], $locationRefMap, $seriesRefMap, $userEmailMap, $systemUser, $tempDir, $counts);
            $this->importCmsPages($data['cms_pages'] ?? [], $tempDir, $systemUser, $counts);

            $this->em->flush();

            $itemsByType = $this->importItems($data['items'] ?? [], $tempDir, $systemUser, $counts);

            return new ImportSummary(
                usersCreated: $counts['usersCreated'],
                usersSkipped: $counts['usersSkipped'],
                locationsCreated: $counts['locationsCreated'],
                eventsCreated: $counts['eventsCreated'],
                cmsPagesCreated: $counts['cmsPagesCreated'],
                cmsPagesSkipped: $counts['cmsPagesSkipped'],
                itemsByType: $itemsByType,
                itemSectionsSkipped: $counts['itemSectionsSkipped'],
                taxonomyAssignmentsDropped: $counts['taxonomyAssignmentsDropped'],
            );
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * @param array<array<string, mixed>> $locationsData
     * @param array<string, int> $counts
     * @return array<int, Location> ref => Location
     */
    private function importLocations(array $locationsData, User $systemUser, array &$counts): array
    {
        $refMap = [];

        foreach ($locationsData as $locData) {
            $title = (string) ($locData['title'] ?? '');
            $existing = $this->locationRepository->findOneBy(['name' => $title]);

            if ($existing !== null) {
                $refMap[(int) $locData['ref']] = $existing;
                continue;
            }

            $location = new Location();
            $location->setName($title !== '' ? $title : 'Unknown');
            $location->setDescription('');
            $location->setStreet('');
            $location->setCity((string) ($locData['city'] ?? ''));
            $location->setPostcode('');
            $location->setUser($systemUser);
            $location->setCreatedAt(new DateTimeImmutable());

            $lat = $locData['latitude'] ?? null;
            $lon = $locData['longitude'] ?? null;
            $location->setLatitude($lat !== null ? (string) $lat : null);
            $location->setLongitude($lon !== null ? (string) $lon : null);

            $this->em->persist($location);
            $refMap[(int) $locData['ref']] = $location;
            ++$counts['locationsCreated'];
        }

        return $refMap;
    }

    /**
     * @param array<array<string, mixed>> $usersData
     * @param array<string, int> $counts
     * @return array<string, User> email => User
     */
    private function importUsers(array $usersData, string $tempDir, User $systemUser, array &$counts): array
    {
        $emailMap = [];

        foreach ($usersData as $userData) {
            $email = (string) ($userData['email'] ?? '');
            if ($email === '') {
                continue;
            }

            $existing = $this->userRepository->findOneBy(['email' => $email]);
            if ($existing !== null) {
                $emailMap[$email] = $existing;
                ++$counts['usersSkipped'];
                continue;
            }

            $role = match ($userData['role'] ?? 'user') {
                'admin' => UserRole::Admin,
                default => UserRole::User,
            };

            $user = new User();
            $user->setEmail($email);
            $user->setName($userData['name'] ?? '');
            $user->setLocale($userData['locale'] ?? 'en');
            $user->setRole($role);
            $user->setPassword(''); // locked — must use forgot password to activate
            $user->setBio($userData['bio'] ?? null);
            $user->setPublic((bool) ($userData['public'] ?? true));
            $user->setStatus(UserStatus::Active);
            $user->setVerified(true);
            $user->setRestricted(false);
            $user->setTagging(false);
            $user->setOsmConsent(false);
            $user->setNotification(false);
            $user->setCreatedAt(new DateTimeImmutable());
            $user->setLastLogin(new DateTime());

            if (isset($userData['image_file']) && $userData['image_file'] !== '') {
                $imagePath = $tempDir . '/' . $userData['image_file'];
                $image = $this->importImage($imagePath, ImageType::ProfilePicture, $systemUser);
                if ($image !== null) {
                    $user->setImage($image);
                }
            }

            $this->em->persist($user);
            $emailMap[$email] = $user;
            ++$counts['usersCreated'];
        }

        return $emailMap;
    }

    /**
     * @param array<array<string, mixed>> $seriesData
     * @return array<int, EventSeries> ref => EventSeries
     */
    private function importSeries(array $seriesData): array
    {
        $refMap = [];

        foreach ($seriesData as $data) {
            $name = (string) ($data['name'] ?? '');
            $rule = isset($data['rule']) && $data['rule'] !== '' ? $this->findEventIntervalByName((string) $data['rule']) : null;

            $series = new EventSeries();
            $series->setName($name !== '' ? $name : 'Imported series');
            $series->setRule($rule);
            $series->setCreatedAt(new DateTimeImmutable());

            $this->em->persist($series);
            $refMap[(int) $data['ref']] = $series;
        }

        return $refMap;
    }

    /**
     * @param array<array<string, mixed>> $eventsData
     * @param array<int, Location> $locationRefMap
     * @param array<int, EventSeries> $seriesRefMap
     * @param array<string, User> $userEmailMap
     * @param array<string, int> $counts
     */
    private function importEvents(
        array $eventsData,
        array $locationRefMap,
        array $seriesRefMap,
        array $userEmailMap,
        User $systemUser,
        string $tempDir,
        array &$counts,
    ): void {
        foreach ($eventsData as $eventData) {
            $locationRef = $eventData['location_ref'] ?? null;
            $location = $locationRef !== null ? $locationRefMap[(int) $locationRef] ?? null : null;

            if ($location === null) {
                $location = $this->locationRepository->findOneBy([]) ?? $this->createFallbackLocation($systemUser);
            }

            $event = new Event();
            $event->setInitial(true);
            $event->setFeatured((bool) ($eventData['featured'] ?? false));
            $event->setCanceled(false);
            $event->setCreatedAt(new DateTimeImmutable());
            $event->setStart(new DateTime((string) $eventData['start']));
            $event->setLocation($location);

            if (isset($eventData['stop']) && $eventData['stop'] !== '') {
                $event->setStop(new DateTime((string) $eventData['stop']));
            }

            $status = EventStatus::tryFrom((string) ($eventData['status'] ?? '')) ?? EventStatus::Published;
            $event->setStatus($status);

            if (isset($eventData['type']) && $eventData['type'] !== '') {
                $event->setType($this->findEventTypeByName((string) $eventData['type']));
            }

            $seriesRef = $eventData['series_ref'] ?? null;
            if ($seriesRef !== null && isset($seriesRefMap[(int) $seriesRef])) {
                $event->setSeries($seriesRefMap[(int) $seriesRef]);
            } elseif (isset($eventData['recurring_rule']) && $eventData['recurring_rule'] !== '') {
                // pre-1.1 exports carried the rule on the event - synthesize a series for it
                $event->setSeries($this->createLegacySeries($eventData));
            }

            $creatorEmail = (string) ($eventData['creator_email'] ?? '');
            $creator = $userEmailMap[$creatorEmail] ?? $this->userRepository->findOneBy(['email' => $creatorEmail]) ?? $systemUser;
            $event->setUser($creator);

            foreach ($eventData['titles'] ?? [] as $lang => $title) {
                $translation = new EventTranslation();
                $translation->setLanguage((string) $lang);
                $translation->setTitle((string) $title);
                $translation->setDescription((string) ($eventData['descriptions'][$lang] ?? ''));
                $translation->setTeaser($eventData['teasers'][$lang] ?? null);
                $event->addTranslation($translation);
                $this->em->persist($translation);
            }

            if (isset($eventData['image_file']) && $eventData['image_file'] !== '') {
                $imagePath = $tempDir . '/' . $eventData['image_file'];
                $image = $this->importImage($imagePath, ImageType::EventTeaser, $systemUser);
                if ($image !== null) {
                    $event->setPreviewImage($image);
                }
            }

            $this->em->persist($event);
            ++$counts['eventsCreated'];
        }
    }

    /**
     * @param array<array<string, mixed>> $cmsPagesData
     * @param array<string, int> $counts
     */
    private function importCmsPages(array $cmsPagesData, string $tempDir, User $systemUser, array &$counts): void
    {
        foreach ($cmsPagesData as $pageData) {
            $slug = (string) ($pageData['slug'] ?? '');

            if ($slug !== '' && $this->em->getRepository(Cms::class)->findOneBy(['slug' => $slug]) !== null) {
                ++$counts['cmsPagesSkipped'];
                continue;
            }

            $cms = new Cms();
            $cms->setSlug($slug !== '' ? $slug : null);
            $cms->setPublished((bool) ($pageData['published'] ?? false));
            $cms->setLocked(false);
            $cms->setCreatedAt(new DateTimeImmutable());
            $cms->setCreatedBy($systemUser);

            foreach ($pageData['titles'] ?? [] as $lang => $title) {
                $cmsTitle = new CmsTitle();
                $cmsTitle->setLanguage((string) $lang);
                $cmsTitle->setTitle((string) $title);
                $cms->addTitle($cmsTitle);
            }

            foreach ($pageData['link_names'] ?? [] as $lang => $name) {
                $cmsLinkName = new CmsLinkName();
                $cmsLinkName->setLanguage((string) $lang);
                $cmsLinkName->setName((string) $name);
                $cms->addLinkName($cmsLinkName);
            }

            foreach ($pageData['menu_locations'] ?? [] as $locationValue) {
                $menuLocation = MenuLocation::tryFrom((int) $locationValue);
                if ($menuLocation !== null) {
                    $cmsMenuLocation = new CmsMenuLocation();
                    $cmsMenuLocation->setLocation($menuLocation);
                    $cms->addMenuLocation($cmsMenuLocation);
                }
            }

            $this->em->persist($cms);

            foreach ($pageData['blocks'] ?? [] as $blockData) {
                $blockType = CmsBlockType::tryFrom((int) ($blockData['type'] ?? 0));
                if ($blockType === null) {
                    continue;
                }

                $block = new CmsBlock();
                $block->setLanguage((string) ($blockData['language'] ?? 'en'));
                $block->setType($blockType);
                $block->setPriority((float) ($blockData['priority'] ?? 1.0));
                $block->setJson(is_array($blockData['json'] ?? null) ? $blockData['json'] : []);
                $block->setPage($cms);

                if (isset($blockData['image_file']) && $blockData['image_file'] !== '') {
                    $imagePath = $tempDir . '/' . $blockData['image_file'];
                    $image = $this->importImage($imagePath, ImageType::CmsBlock, $systemUser);
                    if ($image !== null) {
                        $block->setImage($image);
                    }
                }

                $this->em->persist($block);
            }

            ++$counts['cmsPagesCreated'];
        }
    }

    /**
     * Item sections run after the core flush: a contributor needs the generated ids to build its
     * ref map, and the taxonomy re-keying needs that map in turn.
     *
     * @param array<string, mixed> $itemsData
     * @param array<string, int> $counts
     * @return array<string, array{created: int, matched: int}>
     */
    private function importItems(array $itemsData, string $tempDir, User $systemUser, array &$counts): array
    {
        $context = new ItemImportContext($this->imageImporter, $tempDir, $systemUser);
        $byType = [];

        foreach ($itemsData as $itemType => $block) {
            $contributor = $this->itemRegistry->contributorFor($itemType);
            if ($contributor === null || !is_array($block)) {
                ++$counts['itemSectionsSkipped'];
                continue;
            }

            $rows = array_values(array_filter(
                is_array($block['rows'] ?? null) ? $block['rows'] : [],
                is_array(...),
            ));
            $result = $contributor->importItems($rows, $context);

            $byType[$itemType] = ['created' => $result->created, 'matched' => $result->matched];
            $counts['taxonomyAssignmentsDropped'] += $this->taxonomyPortability->import(
                $itemType,
                $block,
                $result->refToItemId,
            );
        }

        return $byType;
    }

    private function importImage(string $imagePath, ImageType $type, User $uploader): ?Image
    {
        return $this->imageImporter->import($imagePath, $type, $uploader);
    }

    /**
     * @param array<string, mixed> $eventData
     */
    private function createLegacySeries(array $eventData): EventSeries
    {
        $titles = $eventData['titles'] ?? [];
        $name = (string) ($titles['en'] ?? (reset($titles) ?: 'Imported series'));

        $series = new EventSeries();
        $series->setName($name !== '' ? $name : 'Imported series');
        $series->setRule($this->findEventIntervalByName((string) $eventData['recurring_rule']));
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

    private function findEventTypeByName(string $name): ?EventType
    {
        foreach (EventType::cases() as $case) {
            if ($case->name !== $name) {
                continue;
            }

            return $case;
        }

        return null;
    }

    private function findEventIntervalByName(string $name): ?EventInterval
    {
        foreach (EventInterval::cases() as $case) {
            if ($case->name !== $name) {
                continue;
            }

            return $case;
        }

        return null;
    }

    private function removeDirectory(string $dir): void
    {
        if (!$this->fs->isDirectory($dir)) {
            return;
        }

        foreach ($this->fs->scanDirectory($dir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if ($this->fs->isDirectory($path)) {
                $this->removeDirectory($path);
                continue;
            }
            $this->fs->deleteFile($path);
        }

        $this->fs->removeDirectory($dir);
    }
}
