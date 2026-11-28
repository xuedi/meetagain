<?php declare(strict_types=1);

namespace App\Contribution;

use App\Entity\User;
use App\Item\Tag\TypeRegistry;
use Override;
use Symfony\Bundle\SecurityBundle\Security;

readonly class TagSection implements TargetProviderInterface
{
    public const string TYPE = 'tag';

    public function __construct(
        private TypeRegistry $types,
        private Security $security,
    ) {}

    #[Override]
    public function getType(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function getPluginKey(): string
    {
        return '';
    }

    #[Override]
    public function mayTouch(User $user, int|string $id): bool
    {
        return !$this->security->isGranted('ROLE_STEWARD') && $this->types->has((string) $id);
    }
}
