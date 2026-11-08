<?php declare(strict_types=1);

namespace App\Portability;

use DateTimeImmutable;
use League\Flysystem\FilesystemOperator;
use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Override;
use RuntimeException;

readonly class ExportAction implements ActionInterface
{
    public const string IDENTIFIER = 'instance-export';
    public const string ARCHIVE = 'export/instance.zip';

    public function __construct(
        private Exporter $exporter,
        private InstanceScopeBuilder $scopeBuilder,
        private FilesystemOperator $privateStorage,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    #[Override]
    public function getSection(): ActionSection
    {
        return ActionSection::Backup;
    }

    #[Override]
    public function getLabel(): string
    {
        return 'admin_system_import.export_label';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'admin_system_import.export_description';
    }

    #[Override]
    public function getParameters(): array
    {
        return [];
    }

    #[Override]
    public function getFacts(): array
    {
        $archive = $this->archive();
        if ($archive === null) {
            return [];
        }

        return [
            'admin_system_import.export_fact_written' => $archive['writtenAt']->format('Y-m-d H:i'),
            'admin_system_import.export_fact_size' => $this->megabytes($archive['size']),
        ];
    }

    #[Override]
    public function run(array $values, ?string $cursor = null): Result
    {
        $zipPath = $this->exporter->export($this->scopeBuilder->build());
        try {
            $stream = fopen($zipPath, 'rb');
            if ($stream === false) {
                throw new RuntimeException(sprintf('Cannot read the written archive %s.', $zipPath));
            }
            try {
                $this->privateStorage->writeStream(self::ARCHIVE, $stream);
            } finally {
                fclose($stream);
            }
            $size = (int) filesize($zipPath);
        } finally {
            unlink($zipPath);
        }

        return new Result(Status::ok, sprintf('Archive written, %s.', $this->megabytes($size)));
    }

    /**
     * @return array{writtenAt: DateTimeImmutable, size: int}|null
     */
    public function archive(): ?array
    {
        if (!$this->privateStorage->fileExists(self::ARCHIVE)) {
            return null;
        }

        return [
            'writtenAt' => new DateTimeImmutable()->setTimestamp($this->privateStorage->lastModified(self::ARCHIVE)),
            'size' => $this->privateStorage->fileSize(self::ARCHIVE),
        ];
    }

    private function megabytes(int $bytes): string
    {
        return sprintf('%.1f MB', $bytes / 1_048_576);
    }
}
