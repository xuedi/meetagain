<?php declare(strict_types=1);

namespace App\Portability;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One top-level block of an archive, in both directions. The export reads only what the scope
 * names. Imports run in ascending order, so a section can resolve refs an earlier one mapped.
 */
#[AutoconfigureTag]
interface SectionInterface
{
    public function getKey(): string;

    public function getOrder(): int;

    /**
     * @return array<array-key, mixed>
     */
    public function export(Scope $scope, ImageWriterInterface $images): array;

    /**
     * @param array<array-key, mixed> $rows
     */
    public function import(array $rows, ImportContext $context): void;
}
