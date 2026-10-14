<?php declare(strict_types=1);

namespace App\Publisher\Footer;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes markup to a full-width bar under the footer columns. Implementations are unioned; `null` contributes nothing.
 */
#[AutoconfigureTag]
interface BarProviderInterface
{
    public function getFooterBarHtml(): ?string;
}
