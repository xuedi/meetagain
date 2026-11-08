<?php declare(strict_types=1);

namespace App\Filter\Portability;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Decides whether this instance offers a clean import. Implementations compose with AND: one refusal hides it.
 */
#[AutoconfigureTag]
interface CleanImportFilterInterface
{
    public function allowsCleanImport(): bool;
}
