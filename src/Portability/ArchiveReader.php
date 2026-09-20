<?php declare(strict_types=1);

namespace App\Portability;

use App\ExtendedFilesystem;
use App\Service\Config\PluginService;
use RuntimeException;
use ZipArchive;

readonly class ArchiveReader
{
    private const array HEADER_KEYS = ['format', 'version', 'exported_at', 'plugins', 'site', 'source_group', 'images'];

    public function __construct(
        private ExtendedFilesystem $fs,
        private PluginService $pluginService,
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function read(string $zipOrDirectory): array
    {
        $data = json_decode($this->readJson($zipOrDirectory), true);
        if (!is_array($data) || ($data['format'] ?? '') !== Exporter::FORMAT) {
            throw new RuntimeException('Invalid export format');
        }

        return $data;
    }

    /**
     * @return array{format: string, version: string, exported_at: string, slug: string, name: string, description: string, plugins: list<string>, missing_plugins: list<string>, counts: array<string, int>}
     */
    public function describe(string $zipOrDirectory): array
    {
        $data = $this->read($zipOrDirectory);
        $site = is_array($data['site'] ?? null) ? $data['site'] : (is_array($data['source_group'] ?? null) ? $data['source_group'] : []);

        return [
            'format' => (string) $data['format'],
            'version' => (string) ($data['version'] ?? ''),
            'exported_at' => (string) ($data['exported_at'] ?? ''),
            'slug' => (string) ($site['slug'] ?? ''),
            'name' => (string) ($site['name'] ?? ''),
            'description' => (string) ($site['description'] ?? ''),
            'plugins' => $this->plugins($data),
            'missing_plugins' => $this->missingPlugins($data),
            'counts' => $this->countRows($data),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<string>
     */
    public function missingPlugins(array $data): array
    {
        return array_values(array_diff($this->plugins($data), $this->pluginService->getActiveList()));
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<string, int>
     */
    public function countRows(array $data): array
    {
        $counts = [];
        foreach ($data as $key => $rows) {
            if (in_array($key, self::HEADER_KEYS, true) || !is_array($rows) || $rows === []) {
                continue;
            }

            if (array_is_list($rows)) {
                $counts[(string) $key] = count($rows);
                continue;
            }

            foreach ($rows as $kind => $group) {
                if (!is_array($group) || $group === []) {
                    continue;
                }

                if (array_is_list($group)) {
                    $counts[sprintf('%s_%s', $key, $kind)] = count($group);
                    continue;
                }

                if (is_array($group['rows'] ?? null)) {
                    $counts[(string) $kind] = count($group['rows']);
                }
            }
        }

        return $counts;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<string>
     */
    private function plugins(array $data): array
    {
        $plugins = is_array($data['plugins'] ?? null) ? $data['plugins'] : [];

        return array_values(array_filter($plugins, is_string(...)));
    }

    private function readJson(string $zipOrDirectory): string
    {
        if ($this->fs->isDirectory($zipOrDirectory)) {
            $jsonPath = $zipOrDirectory . '/export.json';
            if (!$this->fs->fileExists($jsonPath)) {
                throw new RuntimeException('Invalid export: export.json not found');
            }

            return (string) $this->fs->getFileContents($jsonPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipOrDirectory) !== true) {
            throw new RuntimeException('Could not open ZIP file');
        }

        $json = $zip->getFromName('export.json');
        $zip->close();
        if ($json === false) {
            throw new RuntimeException('Invalid export: export.json not found in ZIP');
        }

        return $json;
    }
}
