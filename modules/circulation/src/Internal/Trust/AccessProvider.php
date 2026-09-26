<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Trust;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Module\Trust\Contract\AccessProviderInterface;
use Override;

final readonly class AccessProvider implements AccessProviderInterface
{
    public function __construct(
        private ContextIndex $index,
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function canView(string $context, int $userId): ?bool
    {
        if ($this->index->itemTypeFor($context) === null) {
            return null;
        }

        return $this->em->find(User::class, $userId) !== null;
    }

    #[Override]
    public function canAdminister(string $context, int $userId): ?bool
    {
        return $this->index->itemTypeFor($context) === null ? null : false;
    }
}
