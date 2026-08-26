<?php declare(strict_types=1);

namespace App\Circulation\Trust;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use Module\Trust\Contract\AccessProviderInterface;
use Override;

final readonly class AccessProvider implements AccessProviderInterface
{
    public function __construct(
        private ContextIndex $index,
        private UserRepository $users,
    ) {}

    #[Override]
    public function canView(string $context, int $userId): ?bool
    {
        if ($this->index->itemTypeFor($context) === null) {
            return null;
        }

        return $this->users->find($userId) !== null;
    }

    #[Override]
    public function canAdminister(string $context, int $userId): ?bool
    {
        if ($this->index->itemTypeFor($context) === null) {
            return null;
        }

        $user = $this->users->find($userId);
        if ($user === null) {
            return false;
        }

        return $user->getRole() === UserRole::Admin;
    }
}
