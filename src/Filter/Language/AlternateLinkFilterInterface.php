<?php declare(strict_types=1);

namespace App\Filter\Language;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Narrows the locale codes emitted as hreflang alternates. Does not affect the language switcher.
 */
#[AutoconfigureTag]
interface AlternateLinkFilterInterface
{
    /**
     * @return string[]|null Allowed locale codes, or null for no restriction
     */
    public function getAllowedAlternateLocaleCodes(Request $request): ?array;
}
