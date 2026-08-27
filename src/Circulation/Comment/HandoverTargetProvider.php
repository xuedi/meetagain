<?php declare(strict_types=1);

namespace App\Circulation\Comment;

use App\Comment\TargetProviderInterface;
use App\Entity\Comment;
use App\Entity\User;
use App\Enum\CirculationHandoverStatus;
use App\Repository\CirculationHandoverRepository;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class HandoverTargetProvider implements TargetProviderInterface
{
    public const string TYPE = 'circulation_handover';

    public function __construct(
        private CirculationHandoverRepository $handovers,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function getTypeKey(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function getReturnUrl(int $targetId): ?string
    {
        if ($this->handovers->find($targetId) === null) {
            return null;
        }

        return $this->urlGenerator->generate('app_circulation_handover', ['id' => $targetId]);
    }

    #[Override]
    public function canComment(int $targetId): bool
    {
        $handover = $this->handovers->find($targetId);
        if ($handover === null || $handover->getStatus() !== CirculationHandoverStatus::Open) {
            return false;
        }

        $user = $this->security->getUser();

        return $user instanceof User && $handover->isParticipant($user);
    }

    #[Override]
    public function onCommentCreated(Comment $comment): void {}
}
