<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\Image;
use App\Portability\Item\Registry;
use App\Portability\Item\UploadsInterface;
use DateTimeInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use ZipArchive;

readonly class Exporter
{
    public const string FORMAT = 'meetagain-group-export';
    public const string VERSION = '2.0';

    /**
     * @param iterable<SectionInterface> $sections
     */
    public function __construct(
        #[AutowireIterator(SectionInterface::class)]
        private iterable $sections,
        private Registry $itemRegistry,
        private ArchiveReader $archiveReader,
        private DateShifter $dateShifter,
        private ClockInterface $clock,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    public function export(Scope $scope, ?DateTimeInterface $anchor = null): string
    {
        $zipPath = sys_get_temp_dir() . '/meetagain-export-' . uniqid('', true) . '.zip';

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $images = new ZipImageWriter($zip, $this->projectDir);

        $now = $this->clock->now();
        $exportedAt = $anchor === null ? $now : $this->dateShifter->mondayOf($anchor);
        $weeks = $anchor === null ? 0 : $this->dateShifter->weeksBetween($now, $anchor);

        $plugins = $scope->plugins;
        sort($plugins);

        $site = $this->exportSite($scope->site, $images);
        $sections = $this->exportSections($scope, $images);

        $data = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => $exportedAt->format(DateTimeInterface::ATOM),
            'plugins' => $plugins,
            'site' => $site,
            ...$this->dateShifter->shift($sections, $weeks),
            'images' => $images->getAttributions(),
        ];

        $zip->addFromString(
            'export.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
        $zip->close();

        return $zipPath;
    }

    /**
     * @return array<string, int> rows per kind, counted as the import summary counts them
     */
    public function preview(Scope $scope): array
    {
        return $this->archiveReader->countRows($this->exportSections($scope, new ZipImageWriter(null, $this->projectDir)));
    }

    /**
     * @return array<string, mixed>
     */
    private function exportSections(Scope $scope, ImageWriterInterface $images): array
    {
        $scope = $this->withoutWithheldUploads($scope);

        $sections = [];
        foreach ($this->orderedSections() as $section) {
            if ($section instanceof PluginSectionInterface && !in_array($section->getPluginKey(), $scope->plugins, true)) {
                continue;
            }

            $sections[$section->getKey()] = $section->export($scope, $images);
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function exportSite(?Site $site, ImageWriterInterface $images): ?array
    {
        if (!$site instanceof Site) {
            return null;
        }

        $themeColors = $site->themeColors;
        ksort($themeColors);
        $settings = $site->settings;
        ksort($settings);
        $pluginSettings = $site->pluginSettings;
        ksort($pluginSettings);

        return [
            'slug' => $site->slug,
            'name' => $site->name,
            'description' => $site->description,
            'languages' => $site->languages,
            'theme_colors' => $themeColors,
            'settings' => $settings,
            'plugin_settings' => $pluginSettings,
            'logo_file' => $site->logo instanceof Image ? $images->addImage($site->logo) : null,
        ];
    }

    private function withoutWithheldUploads(Scope $scope): Scope
    {
        $itemIds = $scope->itemIds;
        foreach ($this->itemRegistry->all() as $contributor) {
            $itemType = $contributor->getItemType();
            $ids = $itemIds[$itemType] ?? [];
            if (!$contributor instanceof UploadsInterface || $ids === []) {
                continue;
            }

            $uploaders = $contributor->getUploaderIds($ids);
            $itemIds[$itemType] = array_values(array_filter($ids, static fn(int $itemId): bool => $scope->carriesUpload($uploaders[$itemId] ?? null)));
        }

        return $scope->withItemIds($itemIds);
    }

    /**
     * @return list<SectionInterface>
     */
    private function orderedSections(): array
    {
        $sections = iterator_to_array($this->sections, false);
        usort($sections, static fn(SectionInterface $a, SectionInterface $b): int => $a->getOrder() <=> $b->getOrder());

        return $sections;
    }
}
