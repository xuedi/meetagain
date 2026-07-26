<?php declare(strict_types=1);

namespace App\Publisher\SiteLogo;

use App\Entity\Image;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface SiteLogoProviderInterface
{
    public function resolveSiteLogo(): ?Image;

    public function resolveFallbackSiteLogo(): ?Image;
}
