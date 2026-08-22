<?php declare(strict_types=1);

namespace App\Emails;

final readonly class SendingIdentity
{
    /** @param list<array{label: string, url: string}> $links */
    public function __construct(
        public string $siteName,
        public string $siteUrl,
        public ?string $logoUrl = null,
        public ?int $logoHeight = null,
        public ?int $logoImageId = null,
        public string $greeting = '',
        public array $links = [],
        public ?string $attribution = null,
    ) {}
}
