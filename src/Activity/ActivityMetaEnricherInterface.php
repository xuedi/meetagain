<?php declare(strict_types=1);

namespace App\Activity;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ActivityMetaEnricherInterface
{
    /**
     * Enrich activity meta with additional context before the activity is persisted.
     * Return only the keys to add. Original caller keys always take precedence.
     * Must not throw — enrichment is best-effort.
     *
     * Key naming convention: use '_<plugin_key>_' prefix to avoid collisions.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function enrich(string $type, User $user, array $meta): array;
}
