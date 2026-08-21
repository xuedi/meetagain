<?php declare(strict_types=1);

namespace App\Publisher\HeadHtml;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes markup to the document head. Implementations are unioned; `null` contributes nothing.
 */
#[AutoconfigureTag]
interface HeadHtmlProviderInterface
{
    public function getHeadHtml(): ?string;
}
