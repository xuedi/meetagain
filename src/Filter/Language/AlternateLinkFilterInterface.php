<?php declare(strict_types=1);

namespace App\Filter\Language;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Allows plugins to restrict which locale codes appear as hreflang alternate
 * links in the page <head>. Does NOT affect the language switcher dropdown.
 *
 * Return null to signal no opinion (all locales pass through).
 * Return an array to restrict alternate links to those locale codes only.
 */
#[AutoconfigureTag]
interface AlternateLinkFilterInterface
{
    /**
     * @return string[]|null Allowed locale codes, or null for no restriction
     */
    public function getAllowedAlternateLocaleCodes(Request $request): ?array;
}
