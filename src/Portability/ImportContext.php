<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use RuntimeException;

class ImportContext
{
    /** @var array<class-string, array<array-key, object>> */
    private array $refs = [];

    /** @var array<string, array<int, int>> */
    private array $items = [];

    /** @var array<string, array<string, int>> */
    private array $counts = [];

    /**
     * @param array<string, array{attribution?: string|null, attribution_not_required?: bool}> $imageAttributions
     */
    public function __construct(
        private readonly ImageImporter $imageImporter,
        private readonly string $extractedArchiveDir,
        private readonly User $systemUser,
        private readonly array $imageAttributions = [],
    ) {}

    public function importImage(mixed $archiveRelativePath, ImageType $type, ?User $uploader = null): ?Image
    {
        if (!is_string($archiveRelativePath) || $archiveRelativePath === '') {
            return null;
        }

        $imagePath = $this->resolveArchivePath($archiveRelativePath);

        $attribution = $this->imageAttributions[$archiveRelativePath] ?? [];

        $image = $this->imageImporter->import(
            $imagePath,
            $type,
            $uploader ?? $this->systemUser,
            $attribution['attribution'] ?? null,
            $attribution['attribution_not_required'] ?? false,
        );

        $isUncredited = $image?->getUploader() === $this->systemUser;
        if ($uploader !== null && $isUncredited) {
            $image?->setUploader($uploader);
        }

        return $image;
    }

    public function getSystemUser(): User
    {
        return $this->systemUser;
    }

    /**
     * @param class-string $class
     */
    public function mapRef(string $class, int|string $ref, object $entity): void
    {
        $this->refs[$class][$ref] = $entity;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    public function resolveRef(string $class, mixed $ref): ?object
    {
        if (!is_int($ref) && !is_string($ref)) {
            return null;
        }

        $entity = $this->refs[$class][$ref] ?? null;

        return $entity instanceof $class ? $entity : null;
    }

    /**
     * @param array<int, int> $refToItemId
     */
    public function mapItems(string $itemType, array $refToItemId): void
    {
        $this->items[$itemType] = $refToItemId;
    }

    public function knowsItemType(string $itemType): bool
    {
        return isset($this->items[$itemType]);
    }

    public function resolveItem(string $itemType, mixed $ref): ?int
    {
        if (!is_int($ref) && !is_string($ref)) {
            return null;
        }

        return $this->items[$itemType][(int) $ref] ?? null;
    }

    public function count(string $kind, Outcome $outcome, int $rows = 1): void
    {
        if ($rows === 0) {
            return;
        }

        $this->counts[$kind][$outcome->value] = ($this->counts[$kind][$outcome->value] ?? 0) + $rows;
    }

    /**
     * @param list<string> $missingPlugins
     */
    public function toSummary(array $missingPlugins = [], int $weeksShifted = 0, bool $siteApplied = false): ImportSummary
    {
        return new ImportSummary($this->counts, $missingPlugins, $weeksShifted, $siteApplied);
    }

    private function resolveArchivePath(string $relativePath): string
    {
        if (str_contains($relativePath, "\0") || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new RuntimeException('The archive names a file outside itself: ' . $relativePath);
        }

        $path = $this->extractedArchiveDir . '/' . $relativePath;

        $root = realpath($this->extractedArchiveDir);
        $resolved = $root === false ? false : realpath($path);
        if ($resolved !== false && !str_starts_with($resolved, $root . '/')) {
            throw new RuntimeException('The archive links to a file outside itself: ' . $relativePath);
        }

        return $path;
    }
}
