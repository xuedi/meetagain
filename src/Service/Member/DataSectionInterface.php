<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DataSectionInterface
{
    /**
     * The member's own data this provider holds, keyed by the name of the JSON file it lands in. Never a password
     * hash, a token secret or another member's personal data beyond a display name.
     *
     * @return array<string, array<mixed>>
     */
    public function export(User $user): array;
}
