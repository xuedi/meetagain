<?php declare(strict_types=1);

namespace App\Service\TownHall;

use App\Entity\User;
use App\Service\Config\ConfigService;

readonly class CoreTownHallAccessChecker implements TownHallAccessCheckerInterface
{
    public function __construct(
        private ConfigService $configService,
    ) {}

    public function canAccess(?User $user): bool
    {
        if (!$this->configService->isShowTownHall()) {
            return false;
        }

        return $user !== null;
    }
}
