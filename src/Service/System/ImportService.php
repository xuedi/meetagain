<?php declare(strict_types=1);

namespace App\Service\System;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Enum\CmsBlock\CmsBlockType;
use App\Entity\CmsLinkName;
use App\Entity\CmsMenuLocation;
use App\Entity\CmsTitle;
use App\Entity\Event;
use App\Enum\EventInterval;
use App\Enum\EventStatus;
use App\Entity\EventTranslation;
use App\Enum\EventType;
use App\Entity\Image;
use App\Enum\ImageType;
use App\Entity\Location;
use App\Enum\MenuLocation;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\LocationRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use ZipArchive;

readonly class ImportService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private LocationRepository $locationRepository,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    public function import(string $zipPath): ImportSummary
    {
        $tempDir = sys_get_temp_dir() . '/meetagain-import-' . uniqid('', true);
        mkdir($tempDir, 0o755, true);

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Could not open ZIP file');
            }
            $zip->extractTo($tempDir);
            $zip->close();

            $jsonPath = $tempDir . '/export.json';
            if (!file_exists($jsonPath)) {
                throw new \RuntimeException('Invalid export: export.json not found in ZIP');
            }

            $data = json_decode((string) file_get_contents($jsonPath), true);
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
            ];

            $locationRefMap = $this->importLocations($data['locations'] ?? [], $systemUser, $counts);
            $userEmailMap = $this->importUsers($data['users'] ?? [], $tempDir, $systemUser, $counts);
            $this->importEvents($data['events'] ?? [], $locationRefMap, $userEmailMap, $systemUser, $tempDir, $counts);
            $this->importCmsPages($data['cms_pages'] ?? [], $tempDir, $systemUser, $counts);

            $this->em->flush();

            return new ImportSummary(
                usersCreated: $counts['usersCreated'],
                usersSkipped: $counts['usersSkipped'],
                locationsCreated: $counts['locationsCreated'],
                eventsCreated: $counts['eventsCreated'],
                cmsPagesCreated: $counts['cmsPagesCreated'],
                cmsPagesSkipped: $counts['cmsPagesSkipped'],
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
                'organizer' => UserRole::Organizer,
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
     * @param array<array<string, mixed>> $eventsData
     * @param array<int, Location> $locationRefMap
     * @param array<string, User> $userEmailMap
     * @param array<string, int> $counts
     */
    private function importEvents(
        array $eventsData,
        array $locationRefMap,
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

            if (isset($eventData['recurring_rule']) && $eventData['recurring_rule'] !== '') {
                $event->setRecurringRule($this->findEventIntervalByName((string) $eventData['recurring_rule']));
            }

            $creatorEmail = (string) ($eventData['creator_email'] ?? '');
            $creator =
                $userEmailMap[$creatorEmail] ?? $this->userRepository->findOneBy(['email' => $creatorEmail])
                    ?? $systemUser;
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

    private function importImage(string $imagePath, ImageType $type, User $uploader): ?Image
    {
        if (!file_exists($imagePath)) {
            return null;
        }

        $content = (string) file_get_contents($imagePath);
        $hash = sha1($content);

        $existing = $this->em->getRepository(Image::class)->findOneBy(['hash' => $hash]);
        if ($existing !== null) {
            return $existing;
        }

        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        if ($extension === '') {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) ($finfo->buffer($content) ?: 'application/octet-stream');

        $targetDir = $this->projectDir . '/data/images/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0o755, true);
        }

        file_put_contents($targetDir . $hash . '.' . $extension, $content);

        $image = new Image();
        $image->setHash($hash);
        $image->setExtension($extension);
        $image->setMimeType($mimeType);
        $image->setSize(strlen($content));
        $image->setType($type);
        $image->setUploader($uploader);
        $image->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($image);

        return $image;
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
        if (!is_dir($dir)) {
            return;
        }

        $files = (array) scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
