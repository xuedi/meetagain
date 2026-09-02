<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PageMetaExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_canonical_url', [PageMetaRuntime::class, 'getCanonicalUrl']),
            new TwigFunction('get_site_name', [PageMetaRuntime::class, 'getSiteName']),
            new TwigFunction('get_meta_description', [PageMetaRuntime::class, 'getMetaDescription']),
            new TwigFunction('get_organization_schema', [PageMetaRuntime::class, 'getOrganizationSchema'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('page_noindex', [PageMetaRuntime::class, 'isNoindex']),
        ];
    }
}
