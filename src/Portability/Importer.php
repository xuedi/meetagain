<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\User;
use App\ExtendedFilesystem;
use App\Repository\UserRepository;
use App\Service\Config\PluginService;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use ZipArchive;

readonly class Importer
{
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
    ) {}

    public function import(string $zipOrDirectory, bool $shiftDates = false, bool $applySite = false): ImportSummary
    {
        if ($this->fs->isDirectory($zipOrDirectory)) {
            return $this->importDirectory($zipOrDirectory, $shiftDates, $applySite);
        }

        $tempDir = sys_get_temp_dir() . '/meetagain-import-' . uniqid('', true);
        $this->fs->makeDirectory($tempDir);

        try {
            $this->extract($zipOrDirectory, $tempDir);

            return $this->importDirectory($tempDir, $shiftDates, $applySite);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    private function importDirectory(string $directory, bool $shiftDates, bool $applySite): ImportSummary
    {
        $data = $this->archiveReader->read($directory);

        $systemUser = $this->userRepository->findOneBy(['email' => 'import@example.com']);
        if (!$systemUser instanceof User) {
            throw new RuntimeException('System import user not found. Run app:install:seed first.');
        }

        $weeks = $shiftDates ? $this->dateShifter->weeksBetween($this->readExportedAt($data), $this->clock->now()) : 0;
        $data = $this->dateShifter->shift($data, $weeks);
        $site = $applySite && is_array($data['site'] ?? null) ? $data['site'] : null;

        $context = new ImportContext($this->imageImporter, $directory, $systemUser, $this->readAttributions($data));
        $sections = $this->activeSections();
        $this->em->wrapInTransaction(function () use ($data, $site, $context, $sections): void {
            foreach ($sections as $section) {
                $rows = $data[$section->getKey()] ?? [];
                $section->import(is_array($rows) ? $rows : [], $context);
                $this->em->flush();
            }

            if ($site !== null) {
                $this->siteSettings->apply($site, $context);
                $this->em->flush();
            }
        });

        $claimed = array_flip(array_map(static fn(SectionInterface $section): string => $section->getKey(), $sections));
        foreach ($this->archiveReader->countRows(array_diff_key($data, $claimed)) as $kind => $rows) {
            $context->count($kind, Outcome::Skipped, $rows);
        }

        return $context->toSummary($this->archiveReader->missingPlugins($data), $weeks, $site !== null);
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
