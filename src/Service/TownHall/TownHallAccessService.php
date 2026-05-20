<?php declare(strict_types=1);

namespace App\Service\TownHall;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class TownHallAccessService
{
    /**
     * @param iterable<TownHallAccessCheckerInterface> $checkers
     */
    public function __construct(
        #[AutowireIterator(TownHallAccessCheckerInterface::class)]
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
