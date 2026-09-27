<?php declare(strict_types=1);

namespace Module\Email\Tests\Stub;

use Module\Email\Contract\SendingIdentity;
use Module\Email\Contract\SendingIdentityProviderInterface;
use Override;

final readonly class IdentityProvider implements SendingIdentityProviderInterface
{
    public const string SITE_NAME = 'Stub Site';
    public const string SITE_URL = 'https://stub.module-test.example';
    public const string GREETING = 'The stub team';

    #[Override]
    public function resolve(?object $origin, string $locale): ?SendingIdentity
    {
        if (!$origin instanceof Origin) {
            return null;
        }

        return new SendingIdentity(siteName: self::SITE_NAME, siteUrl: self::SITE_URL, greeting: self::GREETING);
    }
}
