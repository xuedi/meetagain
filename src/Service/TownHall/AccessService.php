<?php declare(strict_types=1);

namespace App\Service\TownHall;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class AccessService
{
    /**
     * @param iterable<AccessCheckerInterface> $checkers
     */
    public function __construct(
        #[AutowireIterator(AccessCheckerInterface::class)]
        private iterable $checkers,
    ) {}

    public function canAccess(?User $user): bool
    {
        foreach ($this->checkers as $checker) {
            if (!$checker->canAccess($user)) {
                return false;
            }
        }

        return true;
    }
}
