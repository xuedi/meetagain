<?php

declare(strict_types=1);

namespace App\Twig;

use App\Authorization\Action\ActionAuthorizationService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ActionAuthorizationExtension extends AbstractExtension
{
    public function __construct(
        private readonly ActionAuthorizationService $authService,
        private readonly Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('action_authorized', [$this, 'isActionAuthorized']),
        ];
    }

    public function isActionAuthorized(string $action, int $eventId): bool
    {
        $user = $this->security->getUser();
        return $this->authService->isActionAllowed($action, $eventId, $user);
    }
}
