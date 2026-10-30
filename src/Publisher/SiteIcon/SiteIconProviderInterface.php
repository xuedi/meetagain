<?php declare(strict_types=1);

namespace App\Publisher\SiteIcon;

use App\Entity\Image;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface SiteIconProviderInterface
{
    public function resolveSiteIcon(): ?Image;
}
