<?php declare(strict_types=1);

namespace App\Publisher\WellKnown;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Supplies one discovery document. Implementations sharing a suffix are tried in priority order and
 * the first non-null result wins; an unclaimed suffix must stay a 404.
 */
#[AutoconfigureTag]
interface WellKnownProviderInterface
{
    /**
     * Path below `/.well-known/` without a leading slash, e.g. `security.txt` or `mcp/server-card.json`.
     * Root-level documents use their bare filename, e.g. `llms.txt`.
     */
    public function getSuffix(): string;

    /**
     * Higher priority runs first. Core uses 0, plugins overriding core use 100.
     */
    public function getPriority(): int;

    public function provide(Request $request): ?WellKnownDocument;
}
