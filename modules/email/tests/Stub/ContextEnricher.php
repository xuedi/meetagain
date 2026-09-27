<?php declare(strict_types=1);

namespace Module\Email\Tests\Stub;

use Module\Email\Contract\ContextEnricherInterface;
use Override;

final readonly class ContextEnricher implements ContextEnricherInterface
{
    public const string KEY = 'stubEnriched';

    #[Override]
    public function enrich(array $context, string $locale): array
    {
        return [...$context, self::KEY => $locale];
    }
}
