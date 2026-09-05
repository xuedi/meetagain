<?php declare(strict_types=1);

namespace Plugin\Boardgames\Comment;

use App\Comment\TargetProviderInterface;
use App\Entity\Comment;
use Override;
use Plugin\Boardgames\Service\GameService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class GameTargetProvider implements TargetProviderInterface
{
    public function __construct(
        private GameService $gameService,
        private AuthorizationCheckerInterface $authorizationChecker,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function getTypeKey(): string
    {
        return GameService::ITEM_TYPE;
    }

    #[Override]
    public function getReturnUrl(int $targetId): ?string
    {
        if ($this->gameService->get($targetId) === null) {
            return null;
        }

        return $this->urlGenerator->generate('app_plugin_boardgames_game_show', ['id' => $targetId]);
    }

    #[Override]
    public function canComment(int $targetId): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_USER') && $this->gameService->get($targetId) !== null;
    }

    #[Override]
    public function onCommentCreated(Comment $comment): void {}
}
