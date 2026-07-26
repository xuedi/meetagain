<?php declare(strict_types=1);

namespace App\Activity;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface MetaEnricherInterface
{
    /**
     * Returns only the keys to add, prefixed '_<owner_key>_'. Caller keys win; must not throw.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function enrich(string $type, User $user, array $meta): array;
}
