<?php declare(strict_types=1);

namespace App\Emails;

use App\Service\Config\SiteNameResolver;
use App\Service\Http\RequestHostResolver;
use App\Service\Media\SiteLogoResolver;
use Module\Email\Contract\SendingIdentity;
use Module\Email\Contract\SendingIdentityProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: -1000)]
readonly class RequestSendingIdentityProvider implements SendingIdentityProviderInterface
{
    public function __construct(
        private SiteNameResolver $siteNameResolver,
        private RequestHostResolver $hostResolver,
        private SiteLogoResolver $logoResolver,
        private FooterLinkResolver $footerLinks,
    ) {}

    public function resolve(?object $origin, string $locale): SendingIdentity
    {
        $siteName = $this->siteNameResolver->resolve();
        $siteUrl = rtrim($this->hostResolver->getSchemeAndHost(), '/');

        return new SendingIdentity(
            siteName: $siteName,
            siteUrl: $siteUrl,
            logoUrl: $this->logoResolver->endpointUrl($siteUrl),
            greeting: $siteName,
            links: $this->footerLinks->resolve($this->hostResolver->getSchemeAndHost(), $locale),
        );
    }
}
