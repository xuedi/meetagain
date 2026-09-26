<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Comment;

use App\Comment\TargetProviderInterface;
use App\Entity\User;
use Module\Circulation\Contract\CirculationInterface;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Internal\Repository\HandoverRepository;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class HandoverTargetProvider implements TargetProviderInterface
{
    public const string TYPE = CirculationInterface::COMMENT_TARGET;

    public function __construct(
        private HandoverRepository $handovers,
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
        if ($handover === null || $handover->getStatus() !== HandoverStatus::Open) {
            return false;
        }

        $user = $this->security->getUser();

        return $user instanceof User && $handover->isParticipant($user);
    }

    #[Override]
    public function onCommentCreated(int $targetId, int $userId): void {}
}
