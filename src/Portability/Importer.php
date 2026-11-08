<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\ExtendedFilesystem;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use App\Service\Cms\CmsService;
use App\Service\Config\PluginService;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use ZipArchive;

readonly class Importer
{
    public const string PENDING_DIRECTORY = 'import/archive';

    /**
     * @param iterable<SectionInterface> $sections
     */
    public function __construct(
        #[AutowireIterator(SectionInterface::class)]
        private iterable $sections,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private ExtendedFilesystem $fs,
        private ImageImporter $imageImporter,
        private ArchiveReader $archiveReader,
        private SiteSettings $siteSettings,
        private DateShifter $dateShifter,
        private ClockInterface $clock,
        private PluginService $pluginService,
        private CmsService $cmsService,
        private ImageLocationService $imageLocationService,
        private ImageRepository $imageRepository,
        private ImageService $imageService,
    ) {}

    public function import(string $zipOrDirectory, bool $shiftDates = false, bool $applySite = false, ?User $cleanFor = null): ImportSummary
    {
        $locatedImageIds = $cleanFor instanceof User ? $this->locatedImageIds() : [];

        $summary = $this->unpacked($zipOrDirectory, fn(string $directory): ImportSummary => $this->importDirectory(
            $directory,
            $shiftDates,
            $applySite,
            $cleanFor,
            dryRun: false,
        ));

        $this->cmsService->invalidateAll();
        $this->cmsService->invalidateMenuCaches();
        if ($cleanFor instanceof User) {
            $this->removeDetachedImages($locatedImageIds);
        }

        return $summary;
    }

    public function preview(string $zipOrDirectory, bool $applySite = false, ?User $cleanFor = null): ImportSummary
    {
        return $this->unpacked($zipOrDirectory, fn(string $directory): ImportSummary => $this->importDirectory(
            $directory,
            false,
            $applySite,
            $cleanFor,
            dryRun: true,
        ));
    }

    /**
     * @param callable(string): ImportSummary $work
     */
    private function unpacked(string $zipOrDirectory, callable $work): ImportSummary
    {
        if ($this->fs->isDirectory($zipOrDirectory)) {
            return $work($zipOrDirectory);
        }

        $tempDir = sys_get_temp_dir() . '/meetagain-import-' . uniqid('', true);
        $this->fs->makeDirectory($tempDir);

        try {
            $this->extract($zipOrDirectory, $tempDir);

            return $work($tempDir);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    private function importDirectory(string $directory, bool $shiftDates, bool $applySite, ?User $cleanFor, bool $dryRun): ImportSummary
    {
        $data = $this->archiveReader->read($directory);
        $systemUser = $this->systemUser();

        $weeks = $shiftDates ? $this->dateShifter->weeksBetween($this->readExportedAt($data), $this->clock->now()) : 0;
        $data = $this->dateShifter->shift($data, $weeks);
        $site = $applySite && is_array($data['site'] ?? null) ? $data['site'] : null;

        $sections = $this->activeSections();
        $walk = function () use ($directory, $data, $site, $sections, $cleanFor, $dryRun, $systemUser): ImportContext {
            if ($cleanFor instanceof User) {
                $this->purge($sections, new PurgeContext($cleanFor, $systemUser));
            }

            $context = new ImportContext($this->imageImporter, $directory, $systemUser, $this->readAttributions($data), $data, $dryRun);
            foreach ($sections as $section) {
                $rows = $data[$section->getKey()] ?? [];
                $section->import(is_array($rows) ? $rows : [], $context);
                $this->em->flush();
            }

            if ($site !== null && !$dryRun) {
                $this->siteSettings->apply($site, $context);
                $this->em->flush();
            }

            return $context;
        };

        $context = $dryRun ? $this->rolledBack($walk) : $this->em->wrapInTransaction($walk);

        $claimed = array_flip(array_map(static fn(SectionInterface $section): string => $section->getKey(), $sections));
        foreach ($this->archiveReader->countRows(array_diff_key($data, $claimed)) as $kind => $rows) {
            $context->count($kind, Outcome::Skipped, $rows);
        }

        return $context->toSummary($this->archiveReader->missingPlugins($data), $weeks, $site !== null);
    }

    /**
     * @param callable(): ImportContext $walk
     */
    private function rolledBack(callable $walk): ImportContext
    {
        $managedBefore = $this->managedEntities();
        $this->em->beginTransaction();
        try {
            return $walk();
        } finally {
            $this->em->rollback();
            $this->forgetDryRun($managedBefore);
        }
    }

    /**
     * @return array<int, object> spl object id => entity
     */
    private function managedEntities(): array
    {
        $entities = [];
        foreach ($this->em->getUnitOfWork()->getIdentityMap() as $byId) {
            foreach ($byId as $entity) {
                $entities[spl_object_id($entity)] = $entity;
            }
        }

        return $entities;
    }

    /**
     * @param array<int, object> $managedBefore
     */
    private function forgetDryRun(array $managedBefore): void
    {
        if (!$this->em->isOpen()) {
            return;
        }

        foreach ($this->managedEntities() as $objectId => $entity) {
            if (!isset($managedBefore[$objectId])) {
                $this->em->detach($entity);
            }
        }

        foreach ($managedBefore as $entity) {
            $this->em->refresh($entity);
        }
    }

    /**
     * @param list<SectionInterface> $sections
     */
    private function purge(array $sections, PurgeContext $context): void
    {
        foreach (array_reverse($sections) as $section) {
            $section->purge($context);
        }
    }

    /**
     * @return list<int>
     */
    private function locatedImageIds(): array
    {
        $this->imageLocationService->discover();

        return array_keys($this->imageLocationService->countPerImageId());
    }

    /**
     * @param list<int> $imageIds
     */
    private function removeDetachedImages(array $imageIds): void
    {
        if ($imageIds === []) {
            return;
        }

        $this->imageLocationService->discover();
        $stillLocated = $this->imageLocationService->countPerImageId();
        foreach ($imageIds as $imageId) {
            if (isset($stillLocated[$imageId])) {
                continue;
            }

            $image = $this->imageRepository->find($imageId);
            if ($image instanceof Image) {
                $this->imageService->removeIfOrphaned($image);
            }
        }
    }

    private function systemUser(): User
    {
        $systemUser = $this->userRepository->findOneBy(['email' => 'import@example.com']);
        if (!$systemUser instanceof User) {
            throw new RuntimeException('System import user not found. Run app:install:seed first.');
        }

        return $systemUser;
    }

    private function extract(string $zipPath, string $tempDir): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open ZIP file');
        }

        $zip->extractTo($tempDir);
        $zip->close();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function readExportedAt(array $data): DateTimeImmutable
    {
        $exportedAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string) ($data['exported_at'] ?? ''));
        if ($exportedAt === false) {
            throw new RuntimeException('The archive carries no valid exported_at, so its dates cannot be shifted');
        }

        return $exportedAt;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<string, array{attribution: string|null, attribution_not_required: bool}>
     */
    private function readAttributions(array $data): array
    {
        $attributions = [];
        foreach (is_array($data['images'] ?? null) ? $data['images'] : [] as $path => $image) {
            if (!is_array($image)) {
                continue;
            }

            $attributions[(string) $path] = [
                'attribution' => isset($image['attribution']) ? (string) $image['attribution'] : null,
                'attribution_not_required' => (bool) ($image['attribution_not_required'] ?? false),
            ];
        }

        return $attributions;
    }

    /**
     * @return list<SectionInterface>
     */
    private function activeSections(): array
    {
        $activePlugins = $this->pluginService->getActiveList();
        $sections = array_values(array_filter(
            iterator_to_array($this->sections, false),
            static fn(SectionInterface $section): bool => !$section instanceof PluginSectionInterface
            || in_array($section->getPluginKey(), $activePlugins, true),
        ));
        usort($sections, static fn(SectionInterface $a, SectionInterface $b): int => $a->getOrder() <=> $b->getOrder());

        return $sections;
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
